<?php

$blend = Blend::load(BLEND_NAME);
$linetypes = array_map(function ($linetype_name) {
    return Linetype::load($linetype_name);
}, $blend->linetypes);

$fields = $blend->fields;

$data = json_decode(file_get_contents('php://input'));

apply_filters();

$filters = get_current_filters($fields);

$invalids = [];
$ids = [];

foreach ($linetypes as $linetype) {
    $_filters = filter_filters($filters, $linetype, $fields);

    if ($_filters === false) {
        continue;
    }

    $linetype_db_table = Table::load($linetype->table)->table;
    $updates = [];
    $needed_vars = [];

    foreach ($linetype->unfuse_fields as $field => $expression) {
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

    $lines = find_lines($linetype, $_filters);

    foreach ($lines as $line) {
        foreach ($linetype->fields as $field) {
            if (property_exists($data, $field->name)) {
                $line->{$field->name} = $data->{$field->name} ?: null;
            }
        }

        $linetype->complete($line);
        $errors = $linetype->validate($line);

        if (count($errors)) {
            $invalids[] = implode(', ', $errors);

            continue;
        }

        $linedata = ['id' => $line->id];

        foreach ($needed_vars as $nv) {
            $linedata[$nv] = $line->{$nv};
        }

        $result = $stmt->execute($linedata);

        if (!$result) {
            $error = "Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$q}\n" . print_r($linedata, true);

            return;
        }

        $ids[] = "{$linetype->name}({$line->id})";
    }
}

return [
    'data' => $ids
];
