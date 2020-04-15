<?php
class Blend extends Thing
{
    public $label;
    public $groupby;
    public $past_toggle = false;
    public $cum_toggle = false;
    public $printable = false;
    public $showass = [];
    public $linetypes = [];
    public $fields = [];

    public static function delete($name, $filters)
    {
        $blend = Blend::load($name);

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
                collect_inline_links($linetype->name),
                array_map(function($c){
                    return (object)['linetype' => $c->linetype, 'tablelink' => $c->parent_link, 'alias' => $c->label];
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
                $alias = @$_link->alias ?: $tablelink->ids[$side];

                $joinClauses[] = "left join {$tablelink->middle_table} {$alias}_m on {$alias}_m.{$tablelink->ids[$otherside]}_id = t.id";
                $joinClauses[] = "left join {$assocdbtable} {$alias} on {$alias}.id = {$alias}_m.{$tablelink->ids[$side]}_id";
                $idfields[] = "{$alias}_m.{$tablelink->ids[0]}_id {$tablelink->ids[0]}_id";
                $idfields[] = "{$alias}_m.{$tablelink->ids[1]}_id {$tablelink->ids[1]}_id";
            }

            $selectClause = implode(', ', array_merge(['t.id id'], $idfields));
            $joinClause = implode(' ', $joinClauses);
            $whereClause = implode(' and ', array_merge($filterClauses, array_map(function($c){ return "({$c})"; }, @$linetype->clauses ?? []))) ?: '1';

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
            $numQueries,
            $duration,
            $affectedRecords,
            $affectedLinks,
        ];
    }

    public static function search($name, $filters)
    {
        $blend = Blend::load($name);

        $linetypes = array_map(function ($linetype_name) {
            return Linetype::load($linetype_name);
        }, $blend->linetypes);

        if (@$blend->groupby) {
            $groupfield = $blend->groupby;
        }

        $records = [];

        foreach ($linetypes as $linetype) {
            $_filters = filter_filters($filters, $linetype, $blend->fields);

            if ($_filters === false) {
                continue;
            }

            $_records = find_lines($linetype, $_filters);

            foreach ($_records as $record) {
                $record->type = @$blend->hide_types[$linetype->name] ?: $linetype->name;

                foreach ($blend->fields as $field) {
                    if (!property_exists($record, $field->name)) {
                        if (!property_exists($field, 'default')) {
                            error_response("Blend field {$field->name} requires a default value");
                        }

                        $record->{$field->name} = $field->default;
                    }
                }
            }

            $records = array_merge($records, $_records);
        }

        if ($groupfield) {
            $groupby_field = @filter_objects($blend->fields, 'name', 'is', $groupfield)[0];

            if ($groupby_field) {
                usort($records, function ($a, $b) use ($groupby_field) {
                    $fieldname = $groupby_field->name;

                    if (in_array($groupby_field->type, ['date', 'text'])) {
                        return
                            strcmp($a->{$fieldname}, $b->{$fieldname}) ?:
                            ($a->id - $b->id) ?:
                            0;
                    }

                    if ($groupby_field->type == 'number') {
                        return
                            ($a->{$fieldname} <=> $b->{$fieldname}) ?:
                            ($a->id - $b->id) ?:
                            0;
                    }

                    error_response("cant sort by {$fieldname}, type {$groupby_field->type}");
                });
            }
        }

        return  $records;
    }

    public static function info($name)
    {
        return Blend::load($name);
    }

    public static function list()
    {
        $blends = [];

        foreach (Config::get()->blends as $blend) {
            $blends[] = Blend::load($blend);
        }

        return $blends;
    }

    public static function print($name, $filters)
    {
        $blend = Blend::load($name);
        $linetypes = array_map(function ($linetype_name) {
            return Linetype::load($linetype_name);
        }, $blend->linetypes);
        $fields = $blend->fields;

        foreach ($linetypes as $linetype) {
            $_filters = filter_filters($filters, $linetype, $fields);

            if ($_filters === false) {
                continue;
            }

            $lines = find_lines($linetype, $_filters);

            foreach ($lines as $line) {
                $children = load_children($linetype, $line);

                print_line($linetype, $line, $children);
            }
        }
    }

    public static function summaries($name, $filters)
    {
        $blend = Blend::load($name);
        $records = static::search($name, $filters);
        $linetypes = array_map(function ($linetype_name) {
            return Linetype::load($linetype_name);
        }, $blend->linetypes);

        if (@$blend->groupby) {
            $groupfield = $blend->groupby;
        }

        if (is_string(@$blend->cum)) {
            $cum = false;

            foreach ($filters as $filter) {
                if ($filter->field == $blend->cum) {
                    $cum = true;
                }
            }
        } else {
            $cum = @$blend->cum;
        }

        $summaries = [];

        if (count(filter_objects($blend->fields, 'summary', 'is', 'sum'))) {
            $balances = [];

            if (@$blend->past) {
                foreach ($linetypes as $linetype) {
                    $_summary_filters = [];

                    foreach ($filters as $filter) {
                        $linetype_field = @filter_objects($linetype->fields, 'name', 'is', $filter->field)[0];
                        $field = @filter_objects($blend->fields, 'name', 'is', $filter->field)[0];

                        if ($linetype_field) {
                            $_summary_filters[] = $filter;
                        } elseif (
                            $filter->cmp == '=' && $filter->value != @$field->default
                            ||
                            $filter->cmp == 'like' && !preg_match('/' . str_replace('%', '.*', $filter->value) . '/i', $field->default)
                        ) {
                            continue 2;
                        }
                    }

                    $_balances = summarise_lines($linetype, $_summary_filters);

                    foreach ($blend->fields as $field) {
                        if (@$field->summary != 'sum') {
                            continue;
                        }

                        $balances[$field->name] = bcadd(@$balances[$field->name] ?: '0.00', @$_balances[$field->name] ?: '0.00', 2);
                    }
                }
            }

            if ($cum) {
                $summaries['initial'] = (object) $balances;
            }

            foreach ($records as $record) {
                foreach ($blend->fields as $_field) {
                    if (!@$_field->summary == 'sum') {
                        continue;
                    }

                    if (!isset($summaries[$record->{$groupfield}])) {
                        $summaries[$record->{$groupfield}] = (object) [];
                    }

                    if (!property_exists($summaries[$record->{$groupfield}], $_field->name)) {
                        $summaries[$record->{$groupfield}]->{$_field->name} = (@$cum_summaries_bool || @$cum) ? $balances[$_field->name] : '0.00';
                    }

                    $new_balance = bcadd($summaries[$record->{$groupfield}]->{$_field->name}, $record->{$_field->name}, 2);

                    $summaries[$record->{$groupfield}]->{$_field->name} = $new_balance;
                    $balances[$_field->name] = $new_balance;
                }
            }
        }

        return $summaries;
    }

    public static function update($name, $filters, $data)
    {
        $blend = Blend::load($name);
        $linetypes = array_map(function ($linetype_name) {
            return Linetype::load($linetype_name);
        }, $blend->linetypes);
        $fields = $blend->fields;

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

        return $ids;
    }
}
