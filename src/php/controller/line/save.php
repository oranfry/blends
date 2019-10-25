<?php
use contextvariableset\Repeater;

define('LAYOUT', 'json');

$linetype = Linetype::load(LINETYPE_NAME);
$linetype_db_table = Table::load($linetype->table)->table;
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
    $line = (object) array_merge($_POST, LINE_ID ? ['id' => LINE_ID] : []);

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
            $querydata[$nv] = $line->{$nv} ?: null;
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

        if (@$_POST['parent']) {
            if (!preg_match('/^([a-z]+):([a-z]+)=([0-9][0-9]*)$/', $_POST['parent'], $groups)) {
                error_response('Invalid parent specification');
            }

            $parentlink = Tablelink::load($groups[1]);
            $parentside = @array_flip($parentlink->ids)[$groups[2]];
            $childside = ($parentside + 1) % 2;
            $parentid = intval($groups[3]);

            Db::succeed("insert into {$parentlink->middle_table} ({$parentlink->ids[$parentside]}_id, {$parentlink->ids[$childside]}_id) values ({$parentid}, {$line->id})");
        }
    }

    $oldline = find_lines($linetype, [(object)['field' => 'id', 'value' => $line->id,]])[0];
    $reverse = $linetype->links_reversed;

    foreach ($linetype->links as $linkname) {
        $side = 0;

        if (in_array($linkname, $reverse)) {
            array_splice($reverse, array_search($linkname, $reverse), 1);
            $side = 1;
        }

        $tablelink = Tablelink::load($linkname);
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

            $q = "insert into {$linetype_db_table} ({$fieldsClause}) values ({$valuesClause})";
            $stmt = Db::prepare($q);
            $result = $stmt->execute($querydata);

            if (!$result) {
                error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$q}\n" . var_export($querydata, true));
            }

            $associd = Db::pdo_insert_id();

            Db::succeed(
                "insert into {$tablelink->middle_table} ({$tablelink->ids[$otherside]}_id, {$tablelink->ids[$side]}_id) values ({$line->id}, {$associd})",
                "Problem creating assoc link"
            );
        } elseif ($had && !$has) {
            $assoc_idfield = "{$assocname}_id";
            $assoc_id = @$oldline->{$assoc_idfield};

            Db::succeed(
                "delete from {$tablelink->middle_table} where {$tablelink->ids[$otherside]}_id = {$line->id} and {$tablelink->ids[$side]}_id = {$assoc_id}",
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
    }, $linetype->fields));
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

return [
    'data' => $line,
];
