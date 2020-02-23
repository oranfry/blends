<?php
$blend = Blend::load(BLEND_NAME);
$filters = get_query_filters();

$linetypes = array_map(function ($linetype_name) {
    return Linetype::load($linetype_name);
}, $blend->linetypes);

$fields = $blend->fields;

$numQueries = 0;
$affected = 0;
$recordDeletes = [];
$linkDeletes = [];

foreach ($linetypes as $linetype) {
    $_filters = filter_filters($filters, $linetype, $fields);

    if ($_filters === false) {
        continue;
    }

    $linetype_db_table = Table::load($linetype->table)->table;

    list(, , $filterClauses) = lines_prepare_search($linetype, $_filters);

    $allLinks = array_merge(
        $linetype->inlinelinks ?: [],
        array_map(function($c){
            return (object)['linetype' => $c->linetype, 'tablelink' => $c->parent_link];
        }, $linetype->children ?: [])
    );

    $joinClauses = [];
    $idfields = [];

    foreach ($allLinks as $_link) {
        $side = 1;

        if (@$_link->reverse) {
            $side = 0;
        }

        $otherside = ($side + 1) % 2;

        $tablelink = Tablelink::load($_link->tablelink);
        $assocdbtable = Table::load($tablelink->tables[$side])->table;

        $joinClauses[] = "left join {$tablelink->middle_table} {$tablelink->ids[$side]}_m on {$tablelink->ids[$side]}_m.{$tablelink->ids[$otherside]}_id = t.id";
        $joinClauses[] = "left join {$assocdbtable} {$tablelink->ids[$side]} on {$tablelink->ids[$side]}.id = {$tablelink->ids[$side]}_m.{$tablelink->ids[$side]}_id";
        $idfields[] = "{$tablelink->ids[$side]}_m.{$tablelink->ids[0]}_id {$tablelink->ids[0]}_id";
        $idfields[] = "{$tablelink->ids[$side]}_m.{$tablelink->ids[1]}_id {$tablelink->ids[1]}_id";
    }

    $selectClause = implode(', ', array_merge(['t.id id'], $idfields));
    $joinClause = implode(' ', $joinClauses);
    $whereClause = implode(' and ', array_merge($filterClauses, $linetype->clause ? ["({$linetype->clause})"] : [])) ?: '1';

    $q = "select {$selectClause} from {$linetype_db_table} t {$joinClause} where {$whereClause}";

    $result = DB::succeed($q);

    $numQueries++;

    while ($row = mysqli_fetch_assoc($result)) {
        @$recordDeletes[$linetype_db_table][] = $row['id'];

        $reverse = @$linetype->links_reversed ?: [];

        foreach ($allLinks as $_link) {
            $tablelink = Tablelink::load($_link->tablelink);

            $side = 0;

            if (@$_link->$reverse) {
                $side = 1;
            }

            $otherside = ($side + 1) % 2;

            $associd = $row["{$tablelink->ids[$otherside]}_id"];

            if ($associd === null) {
                continue;
            }

            $assocdbtable = Table::load($tablelink->tables[$otherside])->table;
            @$recordDeletes[$assocdbtable][] = $associd;

            @$linkDeletes[$tablelink->middle_table][] = [
                "{$tablelink->ids[$side]}_id" => $row['id'],
                "{$tablelink->ids[$otherside]}_id" => $associd,
            ];
        }
    }
}

$numQueries = 0;
$affectedLinks = 0;
$affectedRecords = 0;
$starttime = (string) microtime(true);

foreach ($linkDeletes as $dbtable => $deletes) {
    $linkDeleteClauses = array_map(function ($delete) {
        return implode(' and ', array_map(function ($field, $id) {
            return "$field = $id";
        }, array_keys($delete), $delete));
    }, $deletes);

    $linkDeleteClause = implode(' or ', $linkDeleteClauses);

    $q = "delete from {$dbtable} where {$linkDeleteClause}";

    $result = Db::succeed($q);

    $affectedLinks += Db::affected();
    $numQueries++;
}

foreach ($recordDeletes as $dbtable => $deletes) {
    $recordDeleteClause = implode(', ', $deletes);

    $q = "delete from {$dbtable} where id in ($recordDeleteClause)";

    $result = Db::succeed($q);

    $affectedRecords += Db::affected();
    $numQueries++;
}

$duration = bcsub((string) microtime(true), $starttime, 4);

return [
    'data' => [
        $numQueries,
        $duration,
        $affectedRecords,
        $affectedLinks,
    ],
];
