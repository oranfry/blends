<?php
use contextvariableset\Repeater;

define('LAYOUT', 'json');

$linetype = Linetype::load(LINETYPE_NAME);
$linetype_db_table = Table::load($linetype->table)->table;
$line_template =  json_decode(file_get_contents('php://input'));
$datefield = null;

foreach ($linetype->fields as $field) {
    if ($field->type == 'date') {
        $datefield = $field;
    }
}

if ($datefield && defined('BULK_ADD')) {
    $repeater = new Repeater(BLEND_NAME . "_repeater");
    $dates = get_repeater_dates($linetype, $repeater);
} else {
    $dates = [null];
}

foreach ($dates as $date) {
    $line = clone $line_template;

    if ($datefield && defined('BULK_ADD')) {
        $line->{$datefield->name} = $date;
    }

    $linetype->complete($line);
    $errors = $linetype->validate($line);
    $unfuse_fields = $linetype->unfuse_fields;

    if (count($errors)) {
        error_response("invalid " . LINETYPE_NAME . ": "  . implode(', ', $errors));
    }

    if (LINE_ID) {
        $line->id = LINE_ID;
    } else {
        $needed_vars = [];
        $fields = [];
        $values = [];

        foreach ($unfuse_fields as $field => $expression) {
            if (preg_match('/^t\.([a-z]+)$/', $field, $groups)) {
                $fields[] = $groups[1];
                $values[] = $expression;

                preg_match_all('/:([a-z]+)/', $expression, $matches);

                for ($i = 0; $i < count($matches[1]); $i++) {
                    $needed_vars[] = $matches[1][$i];
                }

                unset($unfuse_fields[$field]);
            }
        }

        $querydata = [];

        foreach ($needed_vars as $nv) {
            $querydata[$nv] = @$line->{$nv} ?: null;
        }

        $fieldsClause = implode(', ', $fields);
        $valuesClause = implode(', ', $values);

        $q = "insert into {$linetype_db_table} ({$fieldsClause}) values ({$valuesClause})";
        $stmt = Db::prepare($q);
        $result = $stmt->execute($querydata);

        if (!$result) {
            error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$q}\n" . var_export($querydata, true));
        }

        $line->id = Db::pdo_insert_id();

    }

    if (@$line->parent) {
        if (!preg_match('/^([a-z]+):([a-z]+)=([0-9][0-9]*)$/', $line->parent, $groups)) {
            error_response('Invalid parent specification');
        }

        $parentlink = Tablelink::load($groups[1]);
        $parentside = @array_flip($parentlink->ids)[$groups[2]];
        $childside = ($parentside + 1) % 2;
        $parentid = intval($groups[3]);

        Db::succeed("insert into {$parentlink->middle_table} ({$parentlink->ids[$parentside]}_id, {$parentlink->ids[$childside]}_id) values ({$parentid}, {$line->id}) on duplicate key update {$parentlink->ids[$parentside]}_id = {$parentid}, {$parentlink->ids[$childside]}_id = {$line->id}");
    }

    foreach ($linetype->fields as $field) {
        if ($field->type == 'file' && @$line->{$field->name}) {
            $filepath = FILES_HOME . '/' . ($field->path)($line);
            $result = base64_decode($line->{$field->name});

            if (@$field->mimetype) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                if ($finfo->buffer($result) !== $field->mimetype) {
                    continue;
                }
            }

            if ($result === false) {
                continue;
            }

            $mkdirs = [];

            for ($parent = dirname($filepath); !is_dir($parent); $parent = dirname($parent)) {
                array_unshift($mkdirs, $parent);
            }

            foreach ($mkdirs as $dir) {
                @mkdir($dir);
            }

            if (!is_dir(dirname($filepath))) {
                continue;
            }

            file_put_contents($filepath, $result);
        }
    }

    $oldlines = find_lines($linetype, [(object)['field' => 'id', 'value' => $line->id,]]);
    $oldline = @$oldlines[0] ?: (object) [];
    $reverse = $linetype->links_reversed;

    $collected_inlinelinks = collect_inline_links(LINETYPE_NAME);
    $ids = [LINETYPE_NAME => $line->id];

    foreach ($collected_inlinelinks as $link) {
        $side = @$link->reverse ? 0 : 1;
        $tablelink = Tablelink::load($link->tablelink);
        $parenttype = $link->parenttype;

        $assocname = $tablelink->ids[$side];
        $associd_field = "{$assocname}_id";
        $otherside = ($side + 1) % 2;
        $dbtable = Table::load($tablelink->tables[$side])->table;

        $has = $linetype->has($line, $assocname);
        $had = @$oldline->{$associd_field} != null;

        if ($has && !$had) {
            $querydata = [];
            $fields = [];
            $values = [];
            $needed_vars = [];

            foreach ($unfuse_fields as $field => $expression) {
                if (preg_match("/^{$assocname}\.([a-z]+)$/", $field, $groups)) {
                    $fields[] = $groups[1];
                    $values[] = $expression;

                    preg_match_all('/:([a-z]+)/', $expression, $matches);

                    for ($i = 0; $i < count($matches[1]); $i++) {
                        $needed_vars[] = $matches[1][$i];
                    }
                }
            }

            $querydata = [];

            foreach ($needed_vars as $nv) {
                $querydata[$nv] = $line->{$nv} ?: null;
            }

            $fieldsClause = implode(', ', $fields);
            $valuesClause = implode(', ', $values);

            $q = "insert into {$dbtable} ({$fieldsClause}) values ({$valuesClause})";
            $stmt = Db::prepare($q);
            $result = $stmt->execute($querydata);

            if (!$result) {
                error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$q}\n" . var_export($querydata, true));
            }

            $associd = Db::pdo_insert_id();

            Db::succeed(
                "insert into {$tablelink->middle_table} ({$tablelink->ids[$otherside]}_id, {$tablelink->ids[$side]}_id) values ({$ids[$parenttype]}, {$associd})",
                "Problem creating assoc link"
            );

            $ids[$link->linetype] = Db::pdo_insert_id();
        } elseif ($had && !$has) {
            $assoc_idfield = "{$assocname}_id";
            $assoc_id = @$oldline->{$assoc_idfield};

            Db::succeed(
                "delete from {$tablelink->middle_table} where {$tablelink->ids[$otherside]}_id = {$ids[$parenttype]} and {$tablelink->ids[$side]}_id = {$assoc_id}",
                "Problem deleting unneeded assoc link"
            );

            Db::succeed(
                "delete from {$dbtable} where id = {$assoc_id}",
                "Problem deleting unneeded assoc"
            );
        }

        if (!$had || !$has) {
            foreach ($unfuse_fields as $field => $expression) {
                if (preg_match("/^{$assocname}\.([a-z]+)$/", $field, $groups)) {
                    unset($unfuse_fields[$field]);
                }
            }
        }
    }

    if (!count($unfuse_fields)) {
        continue;
    }

    $updates = [];
    $needed_vars = [];

    foreach ($unfuse_fields as $field => $expression) {
        $updates[] = "{$field} = {$expression}";
        preg_match_all('/:([a-z]+)/', $expression, $matches);

        for ($i = 0; $i < count($matches[1]); $i++) {
            $needed_vars[] = $matches[1][$i];
        }
    }

    list($joinClauses, $orderbys, $filterClauses, $parentClauses, $linetypeClauses, $joinTables) = lines_prepare_search($linetype);

    $joinClause = implode(' ', $joinClauses);
    $orderByClause = implode(', ', $orderbys);
    $fieldsClause = implode(', ', array_map(function ($v) {
        return "{$v->fuse} `{$v->name}`";
    }, array_filter($linetype->fields, function($v){
        return $v->type != 'file';
    })));
    $updatesClause = implode(', ', $updates);
    $joinTablesClause = implode(', ', $joinTables);

    $q = "update {$linetype_db_table} t {$joinClause} set {$updatesClause} where t.id = :id";
    $stmt = Db::prepare($q);

    $querydata = ['id' => $line->id];

    foreach ($needed_vars as $nv) {
        $querydata[$nv] = $line->{$nv} ?: null;
    }

    $result = $stmt->execute($querydata);

    if (!$result) {
        error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$q}\n" . var_export($querydata, true));
    }
}

if (@$linetype->printonsave) {
    print_line($linetype, $line, load_children($linetype, $line));
}

return [
    'data' => $line,
];
