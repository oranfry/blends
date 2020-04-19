<?php
class Linetype
{
    public $children = [];
    public $links = [];
    public $links_reversed = [];
    public $links_required = [];
    public $clause;
    public $icon = 'doc';
    public $table;
    public $fields = [];
    public $fuse_fields = [];
    public $unfuse_fields = [];
    public $label;

    public static function load($name)
    {
        $linetypeclass = @Config::get()->linetypes[$name];

        if (!$linetypeclass) {
            error_response("No such linetype '{$name}'");
        }

        $linetype = new $linetypeclass();
        $linetype->name = $name;

        return $linetype;
    }

    public function complete($line)
    {
    }

    public function get_suggested_values()
    {
        return [];
    }

    public function validate($line)
    {
        return [];
    }

    public function astext($line, $child_sets)
    {
        return null;
    }

    public function ashtml($line, $child_sets)
    {
        return null;
    }


    public static function childset($name, $id, $childsetname)
    {
        $linetype = Linetype::load($name);
        $parenttype = null;
        $parentlink = null;
        $parentid = null;

        $line = @find_lines($linetype, [(object)['field' => 'id', 'value' => $id]])[0];

        if (!$line) {
            error_response('No such line', 400);
        }

        $child_sets = load_children($linetype, $line);

        if (!isset($child_sets[$childsetname])) {
            error_response('No such childset', 400);
        }

        return $child_sets[$childsetname];
    }

    public static function delete($name, $id)
    {
        $linetype = Linetype::load($name);

        if (isset($_GET['parentid']) || isset($_GET['parenttype'])) {
            if (!preg_match('/^[0-9]+$/', @$_GET['parentid']) || !preg_match('/^[a-z]+$/', @$_GET['parenttype'])) {
                error_response('Invalid parent specifications');
            }

            $parentid = @$_GET['parentid'];
            $parentlinetype = Linetype::load($_GET['parenttype']);

            $tablelink = null;

            foreach ($parentlinetype->children as $child) {
                if ($child->linetype == $linetype->name) {
                    $tablelink = Tablelink::load($child->parent_link);

                    break;
                }
            }

            unlink_record($id, $parentid, $tablelink);
        }

        $result = delete_record($linetype->table, $id);

        if ($result) {
            error_response($result);
        }
    }

    public static function html($name, $id)
    {
        $linetype = Linetype::load($name);
        $parenttype = null;
        $parentlink = null;
        $parentid = null;

        $line = @find_lines($linetype, [(object)['field' => 'id', 'value' => $id]])[0];

        if (!$line) {
            error_response('No such line', 400);
        }

        $line->type = $linetype->name;
        $child_sets = load_children($linetype, $line);

        return $linetype->ashtml($line, $child_sets);
    }

    public static function get($name, $id)
    {
        $linetype = Linetype::load($name);
        $parenttype = null;
        $parentlink = null;
        $parentid = null;

        $line = @find_lines($linetype, [(object)['field' => 'id', 'value' => $id]])[0];

        if (!$line) {
            error_response('No such line', 400);
        }

        $child_sets = load_children($linetype, $line);

        $line->type = $linetype->name;
        $line->astext = $linetype->astext($line, $child_sets);

        return $line;
    }

    public static function info($name)
    {
        $linetype = Linetype::load($name);

        $parents = find_parent_linetypes($linetype->name, $children);
        $parenttypes = [];

        foreach ($parents as $parent) {
            $parenttypes[] = preg_replace('/.*\\\\/', '', get_class($parent));
        }

        $linetype->parenttypes = $parenttypes;

        return $linetype;
    }

    public static function print($name, $id)
    {
        $linetype = Linetype::load($name);
        $line = find_lines($linetype, [(object)['field' => 'id', 'value' => $id]])[0];

        if (!$line) {
            error_response('No such line', 400);
        }

        $child_sets = load_children($linetype, $line);

        print_line($linetype, $line, $child_sets);

        $messages = ["Printed Happily"];

        return $messages;
    }

    public static function save($name, $line, $id = null)
    {
        $linetype = Linetype::load($name);
        $linetype_db_table = Table::load($linetype->table)->table;

        $datefield = null;

        if ($datefield && defined('BULK_ADD')) {
            $line->{$datefield->name} = $date;
        }

        $linetype->complete($line);
        $errors = $linetype->validate($line);
        $unfuse_fields = $linetype->unfuse_fields;

        if (count($errors)) {
            error_response("invalid " . $name . ": "  . implode(', ', $errors));
        }

        if ($id) {
            $line->id = $id;
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

        $collected_inlinelinks = collect_inline_links($name);
        $ids = [$name => $line->id];

        foreach ($collected_inlinelinks as $link) {
            if (@$link->norecurse) {
                continue;
            }

            $side = @$link->reverse ? 0 : 1;
            $tablelink = Tablelink::load($link->tablelink);
            $parenttype = $link->parenttype;

            $assocname = $link->alias;
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
                    $querydata[$nv] = @$line->{$nv} ?: null;
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

        if (count($unfuse_fields)) {
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

        return $line;
    }

    public static function suggested($name)
    {
        $linetype = Linetype::load($name);
        return $linetype->get_suggested_values();
    }

    public static function unlink($name, $id, $parentname, $parentid)
    {
        $linetype = Linetype::load($name);
        $parentlinetype = Linetype::load($parentname);

        $tablelink = null;

        foreach ($parentlinetype->children as $child) {
            if ($child->linetype == $linetype->name) {
                $tablelink = Tablelink::load($child->parent_link);

                break;
            }
        }

        if (!$tablelink) {
            error_response('Could not find the table link');
        }

        unlink_record($id, $parentid, $tablelink);
    }

    public function build_class_field_fuse($fieldname)
    {
        $field = @filter_objects($this->fields, 'name', 'is', $fieldname)[0];

        if (!$field) {
            return;
        }

        $field->fuse = "if ((" . implode(') or (', $field->clauses) . "), '{$fieldname}', '')";
    }
}
