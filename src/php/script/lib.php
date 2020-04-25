<?php
Db::connect();

const ESC = "\x1b";
const GS = "\x1d";
const NUL = "\x00";

function filter_filters($filters, $linetype, $fields)
{
    $r = [];

    foreach ($filters as $filter) {
        $filter = clone $filter;

        if ($filter->field == 'deepid') {
            $filter->field = 'id';
            $ids = [];

            foreach ((is_array($filter->value) ? $filter->value : [$filter->value]) as $deepid) {
                $parts = explode(':', $deepid);
                $type = $parts[0];
                $id = $parts[1];

                if ($type == $linetype->name) {
                    $ids[] = $id;
                }
            }

            if (!count($ids)) {
                return false;
            }

            $filter->value = $ids;
        }

        if ($filter->field == 'id') {
            $r[] = $filter;
            continue;
        }

        $linetype_field = @array_values(array_filter($linetype->fields, function ($v) use ($filter) {
            return $v->name == $filter->field;
        }))[0];

        if ($linetype_field) {
            $r[] = $filter;
            continue;
        }

        // can't find the field, apply default

        $field = @array_values(array_filter($fields, function ($v) use ($filter) {
            return $v->name == $filter->field;
        }))[0];

        if (
            $filter->cmp == '=' && (
                is_array($filter->value) && !in_array($field->default, $filter->value)
                ||
                !is_array($filter->value) && $field->default != $filter->value
            )
            ||
            $filter->cmp == 'like' && !preg_match('/' . str_replace('%', '.*', $filter->value) . '/i', $field->default)
            ||
            $filter->cmp == 'custom' && !($filter->cmp->php)($field->default)
        ) {
            return false;
        }
    }

    return $r;
}

function ff($date, $day = 'Mon')
{
    while (date('D', strtotime($date)) != $day) {
        $date = date_shift($date, '1 day');
    }

    return $date;
}

function load_children($linetype, $parent)
{
    $child_sets = [];

    foreach ($linetype->children as $child) {
        $child_sets[$child->label] = load_childset($linetype, $parent, $child);
    }

    return $child_sets;
}

function load_childset($linetype, $parent, $descriptor)
{
    $id = $parent->id;

    $child_linetype = Linetype::load(@$descriptor->linetype);
    $fields = $child_linetype->fields;

    $childset = (object) [];
    $childset->lines = $child_linetype->find_lines(null, $id, $descriptor->parent_link);

    $summary = (object) [];
    $hasSummaries = array_reduce($fields, function ($c, $v) {
        return $c || @$v->summary == 'sum';
    }, false);

    if ($hasSummaries) {
        foreach ($childset->lines as $line) {
            foreach ($fields as $field) {
                if (@$field->summary != 'sum') {
                    continue;
                }

                if (!@$summary->{$field->name}) {
                    $summary->{$field->name} = '0.00';
                }

                $summary->{$field->name} = bcadd($summary->{$field->name}, $line->{$field->name}, @$field->dp ?: 0);
            }
        }

        $childset->summary = $summary;
    }

    return $childset;
}

function join_r($tablelink, $alias, $base_alias, $otherside = 1, $left = true) {
    $myside = ($otherside + 1) % 2;
    $join = $left ? 'left join' : 'join';
    $jointable = Table::load($tablelink->tables[$otherside]);
    $join_db_table = $jointable->table;

    $my_id = ($tablelink->ids[$myside] ? $tablelink->ids[$myside] . '_' : '') . 'id';
    $other_id = ($tablelink->ids[$otherside] ? $tablelink->ids[$otherside] . '_' : '') . 'id';

    return "$join {$tablelink->middle_table} {$alias}_m on {$alias}_m.{$my_id} = {$base_alias}.id left join {$join_db_table} {$alias} on {$alias}.id = {$alias}_m.{$other_id}";
}

function generate_link_join_clause(
    $tablelink,
    $alias,
    $base_alias,
    $otherside = 1,
    $left = true
) {
    $myside = ($otherside + 1) % 2;
    $join = $left ? 'left join' : 'join';
    $jointable = Table::load($tablelink->tables[$otherside]);
    $join_db_table = $jointable->table;

    $my_id = ($tablelink->ids[$myside] ? $tablelink->ids[$myside] . '_' : '') . 'id';
    $other_id = ($tablelink->ids[$otherside] ? $tablelink->ids[$otherside] . '_' : '') . 'id';

    $q =
        "$join
            {$tablelink->middle_table} {$alias}_m
        on
            {$alias}_m.{$my_id} = {$base_alias}.id
        left join
            {$join_db_table} {$alias}
        on
            {$alias}.id = {$alias}_m.{$other_id}
        ";
    $prefix = $tablelink->ids[$otherside];

    $fields = ["{$alias}.id {$prefix}_id"];
    $groupby = ["{$prefix}_id"];

    foreach ($jointable->additional_fields as $field) {
        $fields[] = "{$alias}.{$field->name} {$tablelink->ids[$otherside]}_{$field->name}";
        $groupby[] = "{$tablelink->ids[$otherside]}_{$field->name}";
    }

    return [
        trim(preg_replace('/\s+/', ' ', $q)),
        $fields,
        $groupby,
        "{$join_db_table} {$alias}",
        "{$alias}.id {$alias}_id"
    ];
}

function print_line($linetype, $line, $child_sets)
{
    if (!method_exists($linetype, 'astext')) {
        return;
    }

    $contents = $linetype->astext($line, $child_sets);

    if (!defined('PRINTER_FILE')) {
        error_log("\n" . $contents);
        return; // lets not and say we did - for testing!
    }

    $logofile = @Config::get()->logofile;

    $printout = '';
    $printout .= ESC."@"; // Reset to defaults

    if ($logofile && file_exists($logofile)) {
        $printout .= file_get_contents($logofile);
        $printout .= "\n\n";
    }

    $printout .= wordwrap($contents, 42, "\n", true);
    $printout .= ESC."d".chr(4);
    $printout .= GS."V\x41".chr(3);

    file_put_contents(PRINTER_FILE, $printout, FILE_APPEND);
}

// TODO: remove this (again)
function get_sku_meta()
{
    $r = Db::succeed("select * from record_skumeta order by sku");
    $metas = [];

    while ($meta = mysqli_fetch_assoc($r)) {
        $metas[$meta['sku']] = (object) $meta;
    }

    return $metas;
}


function delete_record($table_name, $id)
{
    $assocs = find_related_records($table_name, $id);

    foreach ($assocs as $assoc) {
        $assoc_table = Table::load($assoc->table);
        $id_field = @$assoc_table->id_field ?: 'id';
        $assoc_db_table = $assoc_table->table;

        Db::succeed("delete from `{$assoc->middle_table}` where {$assoc->left}_id = $id and {$assoc->right}_id = {$assoc->id}");
        Db::succeed("delete from `{$assoc_db_table}` where {$id_field} = '{$assoc->id}'");
    }

    $table = Table::load($table_name);
    $id_field = @$table->id_field ?: 'id';
    $db_table = $table->table;

    Db::succeed("delete from `{$db_table}` where {$id_field} = '{$id}'");
}

function unlink_record($id, $parentid, $tablelink)
{
    $q = "delete from {$tablelink->middle_table} where {$tablelink->ids[0]}_id = {$parentid} and {$tablelink->ids[1]}_id = {$id}";

    $result = Db::succeed($q);
}

function find_related_records($table, $id)
{
    $assocs = [];

    foreach (get_all_tablelinks() as $tablelink_name) {
        $tablelink = Tablelink::load($tablelink_name);

        foreach (array_merge([0], ($tablelink->type == "oneone" ? [1] : [])) as $side) {
            if ($tablelink->tables[$side] != $table) {
                continue;
            }

            $otherside = ($side + 1) % 2;
            $left_table = $tablelink->tables[$side];
            $right_table = $tablelink->tables[$otherside];
            $middle_table = $tablelink->middle_table;
            $left_id = $tablelink->ids[$side];
            $right_id = $tablelink->ids[$otherside];
            $left_db_table = Table::load($left_table)->table;

            list($joinClause, $fields, $groupby) = generate_link_join_clause($tablelink, 'tt', 't', $otherside, false);
            $fieldsClause = implode(', ', $fields);
            $groupbyClause = implode(', ', $groupby);

            $r = Db::succeed("select tt.id, {$fieldsClause} from `{$left_db_table}` t {$joinClause} where t.id = {$id} group by {$groupbyClause}");

            while ($row = mysqli_fetch_assoc($r)) {
                $assocs[] = (object)[
                    'table' => $right_table,
                    'middle_table' => $middle_table,
                    'id' => $row['id'],
                    'left' => $left_id,
                    'right' => $right_id,
                ];
            }
        }
    }

    return $assocs;
}

function get_all_tablelinks()
{
    $tablelinks = [];
    $seen = [];

    foreach (array_keys(Config::get()->blends) as $_blend_name) {
        foreach (Blend::load($_blend_name)->linetypes as $_linetype_name) {
            if (@$seen[$_linetype_name]) {
                continue;
            }

            $seen[$_linetype_name] = true;
            $_linetype = Linetype::load($_linetype_name);

            foreach ($_linetype->inlinelinks as $_link) {
                if (!in_array($_link->tablelink, $tablelinks)) {
                    $tablelinks[] = $_link->tablelink;
                }
            }

            foreach ($_linetype->children as $_child) {
                if (!in_array($_child->parent_link, $tablelinks)) {
                    $tablelinks[] = $_child->parent_link;
                }
            }
        }
    }

    return $tablelinks;
}

function find_parent_linetypes($linetype_name, &$child_descriptors)
{
    $parents = [];
    $child_descriptors = [];
    $seen = [];

    foreach (array_keys(Config::get()->blends) as $_blend_name) {
        foreach (Blend::load($_blend_name)->linetypes as $_linetype_name) {
            if (@$seen[$_linetype_name]) {
                continue;
            }

            $seen[$_linetype_name] = true;
            $_linetype = Linetype::load($_linetype_name);
            $mes = @filter_objects($_linetype->children, 'linetype', 'is', $linetype_name);

            foreach ($mes as $me) {
                $parents[] = $_linetype;
                $child_descriptors[] = $me;
            }
        }
    }

    return $parents;
}

function get_values($table, $field, $clause = null)
{
    $values = [];

    $db_table = Table::load($table)->table;

    $and = $clause ? "and {$clause}" : '';

    $r = Db::succeed("select `{$field}` from `{$db_table}` t where `{$field}` is not null and `{$field}` != '' {$and} group by `{$field}` order by `{$field}`");

    while ($value = mysqli_fetch_row($r)) {
        $values[] = $value[0];
    }

    return $values;
}

function get_file_info($name)
{
    if (preg_match('@/\.\.@', $name) || preg_match('@^\.\.@', $name)) {
        error_response('Bad file path');
    }

    $file = FILES_HOME . '/' . $name;

    if (!file_exists($file)) {
        error_response('No such file');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $content_type = $finfo->file($file);

    return [
        'filedata' => base64_encode(file_get_contents($file)),
        'content_type' => $content_type,
        'filename' => basename($name),
    ];
}
