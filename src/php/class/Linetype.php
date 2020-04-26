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

    public function unpack($line)
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

        $line = @$linetype->find_lines([(object)['field' => 'id', 'value' => $id]])[0];

        if (!$line) {
            error_response('No such line', 400);
        }

        $child_sets = load_children($linetype, $line);

        if (!isset($child_sets[$childsetname])) {
            error_response('No such childset', 400);
        }

        return $child_sets[$childsetname];
    }

    public function delete($id)
    {
        $this->save(null, $id);
    }

    public static function html($name, $id)
    {
        $linetype = Linetype::load($name);
        $parenttype = null;
        $parentlink = null;
        $parentid = null;

        $line = @$linetype->find_lines([(object)['field' => 'id', 'value' => $id]])[0];

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

        $line = @$linetype->find_lines([(object)['field' => 'id', 'value' => $id]])[0];

        foreach (get_object_vars($line) as $prop => $value) {
            if (is_object($value)) {
                unset($line->{$prop});
            }
        }

        if (!$line) {
            error_response('No such line', 400);
        }

        $child_sets = load_children($linetype, $line);

        $line->type = $linetype->name;
        $astext = $linetype->astext($line, $child_sets);

        if ($astext) {
            $line->astext = $astext;
        }

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
        $line = @$linetype->find_lines([(object)['field' => 'id', 'value' => $id]])[0];

        if (!$line) {
            error_response('No such line', 400);
        }

        $child_sets = load_children($linetype, $line);

        print_line($linetype, $line, $child_sets);

        $messages = ["Printed Happily"];

        return $messages;
    }

    public function save($line, $id = null)
    {
        $dbtable = @Config::get()->tables[$this->table];

        if (!$dbtable) {
            error_response("Could not resolve table {$this->table} to a database table");
        }

        if (is_object($line)) {
            $line->id = $id;
        }

        $oldline = $id ? @$this->find_lines([(object)['field' => 'id', 'value' => $id]])[0] : null;

        $unfuse_fields = [];
        $data = [];
        $statements = [];
        $ids = [];

        $this->save_r('t', $line, $oldline, null, null, $unfuse_fields, $data, $statements, $ids);

        foreach ($statements as $statement) {
            @list($query, $querydata, $saveto) = $statement;

            preg_match_all('/:([a-z_]+_id)/', $query, $matches);

            for ($i = 0; $i < count($matches[1]); $i++) {
                $querydata[$matches[1][$i]] = $ids[$matches[1][$i]];
            }

            $stmt = Db::prepare($query);
            $result = $stmt->execute($querydata);

            if (!$result) {
                error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$query}\n" . var_export($querydata, true));
            }

            if ($saveto) {
                $ids[$saveto] = Db::pdo_insert_id();
            }
        }

        if (count($unfuse_fields)) {
            $updates = [];
            $needed_vars = [];

            foreach ($unfuse_fields as $field => $expression) {
                $updates[] = "{$field} = {$expression}";
                preg_match_all('/:([a-z_]+)/', $expression, $matches);

                for ($i = 0; $i < count($matches[1]); $i++) {
                    $needed_vars[] = $matches[1][$i];
                }
            }

            sort($needed_vars);
            $needed_vars = array_unique($needed_vars);

            $joins = [];
            $selects = []; // ignore

            $this->find_r('t', $selects, $joins);

            $join = implode(' ', $joins);
            $set = implode(', ', $updates);

            $query = "update {$dbtable} t {$join} set {$set} where t.id = :id";
            $stmt = Db::prepare($query);

            $querydata = ['id' => $line->id];

            foreach ($needed_vars as $nv) {
                $querydata[$nv] = $data[$nv] ?: null;
            }

            $result = $stmt->execute($querydata);

            if (!$result) {
                error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$query}\n" . var_export($querydata, true));
            }
        }

        if (@$line) {
            $line->id = $ids['t_id'];

            if (@$line->parent) {
                if (!preg_match('/^([a-z]+):([a-z]+)=([0-9][0-9]*)$/', $line->parent, $groups)) {
                    error_response('Invalid parent specification');
                }

                $parentlink = Tablelink::load($groups[1]);
                $parentside = @array_flip($parentlink->ids)[$groups[2]];
                $childside = ($parentside + 1) % 2;
                $parentid = intval($groups[3]);

                $query = "insert into {$parentlink->middle_table} ({$parentlink->ids[$parentside]}_id, {$parentlink->ids[$childside]}_id) values (:parentid, :lineid) on duplicate key update {$parentlink->ids[$parentside]}_id = :parentid, {$parentlink->ids[$childside]}_id = :lineid";
                $querydata = [
                    'parentid' => $parentid,
                    'lineid' => $line->id
                ];
                $stmt = Db::prepare($query);
                $result = $stmt->execute($querydata);

                if (!$result) {
                    error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$query}\n" . var_export($querydata, true));
                }
            }

            if (@$this->printonsave) {
                print_line($this, $line, load_children($this, $line));
            }
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

    public function find_lines($filters = null, $parentId = null, $parentLink = null, $customClause = null, $summary = false)
    {
        if ($customClause) {
            error_response("Linetype::find_lines argument customClause is deprecated");
        }

        $filters = $filters ?? [];

        $dbtable = @Config::get()->tables[$this->table];

        if (!$dbtable) {
            error_response("Could not resolve table {$this->table} to a database table");
        }

        $selects = [];
        $joins = [];
        $wheres = [];
        $orderbys = ['t.id'];
        $groupbys = [];

        foreach ($filters as $filter) {
            $cmp = @$filter->cmp ?: '=';

            if ($cmp == 'custom') {
                $field = @filter_objects($this->fields, 'name', 'is', $filter->field)[0];

                $wheres[] = ($filter->sql)(str_replace('{t}', 't', $field->fuse));
                continue;
            }

            if ($filter->field == 'id') {
                $expression = 't.id';
            } else {
                $field = @filter_objects($this->fields, 'name', 'is', $filter->field)[0];

                if (!$field) {
                    error_response("Cant find fuse expression for filter field {$this->name} {$filter->field} (1)\n\n" . var_export($this->fields, 1));
                }

                $expression = $this->render_fuse_expression($field->name, 't');

                if (!$expression) {
                    error_response("Cant find fuse expression for filter field {$this->name} {$filter->field} (2)\n\n" . var_export($this->fields, 1));
                }
            }

            if ($cmp == '*=') {
                $repeater = Repeater::create($filter->value);
                $wheres[] = $repeater->get_clause($expression);
            } elseif (is_array($filter->value) && $cmp == '=') {
                $value =  '(' . implode(',', array_map(function($e){ return "'{$e}'"; }, $filter->value)) . ')';
                $wheres[] = "{$expression} in {$value}";
            } else {
                $wheres[] = "{$expression} {$cmp} '{$filter->value}'";
            }
        }

        foreach (@$this->clauses ?: [] as $clause) {
            $wheres[] = str_replace('{t}', 't', $clause);
        }

        $this->find_r('t', $selects, $joins, $summary);

        if ($parentLink && $parentId) {
            $tablelink = Tablelink::load($parentLink);
            $joins[] = make_join($tablelink, 'parent', 't', 0, false);
            $wheres[] = "parent.id = {$parentId}";
        }

        $select = implode(', ', $selects);
        $join = implode(' ', $joins);
        $where = count($wheres) ? 'where ' . implode(' AND ', array_map(function($c){ return "({$c})"; }, $wheres)) : '';
        $orderby = implode(', ', $orderbys);

        $q = "select {$select} from `{$dbtable}` t {$join} {$where} order by {$orderby}";
        $r = Db::succeed($q);

        if (!$r) {
            error_response(Db::error() . "\n\n$q\n\nlinetype: \"{$this->name}\"", 500);
        }

        $lines = [];

        while ($row = mysqli_fetch_assoc($r)) {
            $line = (object) [];

            $line->type = $this->name;

            if ($parentId) {
                $line->parent = $parentId;
                $line->parent_link = $parentLink;
            }

            $this->build_r('t', $row, $line, $summary);

            if ($summary) {
                return $line;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function find_r($alias, &$selects, &$joins, $summary = false)
    {
        if (!$summary) {
            $selects[] = "{$alias}.id {$alias}_id";
        }

        foreach ($this->fields as $field) {
            if ($summary && !@$field->summary == 'sum') {
                continue;
            }

            $fuse = $this->render_fuse_expression($field->name, $alias, $summary);

            if (!$fuse) {
                continue;
            }

            $selects[] = $fuse . " `{$alias}_{$field->name}`";
        }

        foreach (@$this->inlinelinks ?? [] as $child) {
            $childlinetype = Linetype::load($child->linetype);
            $tablelink = Tablelink::load($child->tablelink);
            $side = @$child->reverse ? 0 : 1;
            $leftJoin = @$child->required ? false : true;
            $childalias = $alias . '_'  . (@$child->alias ?? $tablelink->ids[$side]);

            $joins[] = make_join($tablelink, $childalias, $alias, $side, $leftJoin);

            if (@$child->norecurse) {
                continue;
            }

            $childlinetype->find_r($childalias, $selects, $joins, $summary);
        }
    }

    private function build_r($alias, &$row, $line, $summary = false)
    {
        if (!$summary) {
            $line->id = $row["{$alias}_id"];
        }

        foreach ($this->fields as $field) {
            if ($summary && !@$field->summary == 'sum') {
                continue;
            }

            if (!$summary && $field->type == 'file' && defined('FILES_HOME')) {
                $path = ($field->path)($line);
                $file = FILES_HOME . '/' . $path;

                if (file_exists($file)) {
                    $line->{$field->name} = $path;
                }
            }

            $fuse = $this->render_fuse_expression($field->name, $alias, $summary);

            if (!$fuse) {
                continue;
            }

            $line->{$field->name} = $row["{$alias}_{$field->name}"];

            if (!$summary && property_exists($field, 'calc')) {
                $line->{$field->name} = ($field->calc)($line);
            }
        }

        foreach (@$this->inlinelinks ?? [] as $child) {
            if (@$child->norecurse) {
                continue;
            }

            $tablelink = Tablelink::load($child->tablelink);
            $side = @$child->reverse ? 0 : 1;
            $childaliasshort = (@$child->alias ?? $tablelink->ids[$side]);
            $childalias = $alias . '_'  . $childaliasshort;

            if (!@$row["{$childalias}_id"]) {
                continue;
            }

            $childlinetype = Linetype::load($child->linetype);
            $childline = (object) [];

            $childlinetype->build_r($childalias, $row, $childline, $summary);

            $line->{$childaliasshort} = $childline;
        }

        if (!$summary && method_exists($this, 'fuse')) {
            $this->fuse($line);
        }
    }

    private function render_fuse_expression($fieldname, $alias, $summary = false)
    {
        $raw = null;

        foreach ($this->fields as $_field) {
            if ($_field->name == $fieldname) {
                $field = $_field;
            }
        }

        if (!$field) {
            error_response("Cant find field {$this->name} {$field->name} to render fuse expression");
        }

        if (@$field->fuse) {
            $raw = $field->fuse;
        } elseif (@$field->borrow) {
            $raw = $field->borrow;
            $this->borrow_r($alias, 't', $raw);
        }

        if (!$raw) {
            return;
        }

        $fuse = str_replace('{t}', $alias, $raw);

        if ($summary) {
            $fuse = "sum({$fuse})";
        }

        return $fuse;
    }

    private function borrow_r($root, $alias, &$expression)
    {
        foreach (@$this->inlinelinks ?: [] as $child) {
            if (@$child->norecurse) {
                continue;
            }

            $childaliasshort = @$child->alias ?? Tablelink::load($child->tablelink)->ids[@$child->reverse ? 0 : 1];
            $childlinetype = Linetype::load($child->linetype);
            $childalias = "{$alias}_{$childaliasshort}";

            foreach ($childlinetype->fields ?: [] as $field) {
                $fuse = $childlinetype->render_fuse_expression($field->name, "{$root}_{$childaliasshort}");

                if ($fuse) {
                    $expression = str_replace('{' . "{$childalias}_{$field->name}" . '}', $fuse, $expression);
                }
            }

            $childlinetype->borrow_r(
                "{$root}_{$childaliasshort}",
                "{$alias}_{$childaliasshort}",
                $expression
            );
        }
    }

    private function handle_upload($field, $line)
    {
        $filepath = FILES_HOME . '/' . ($field->path)($line);
        $result = base64_decode($line->{$field->name});

        if ($result === false) {
            return;
        }

        if (@$field->mimetype) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);

            if ($finfo->buffer($result) !== $field->mimetype) {
                return;
            }
        }

        $mkdirs = [];

        for ($parent = dirname($filepath); !is_dir($parent); $parent = dirname($parent)) {
            array_unshift($mkdirs, $parent);
        }

        foreach ($mkdirs as $dir) {
            @mkdir($dir);
        }

        if (!is_dir(dirname($filepath))) {
            return;
        }

        file_put_contents($filepath, $result);
    }

    private function save_r($alias, $line, $oldline, $tablelink, $parentalias, &$unfuse_fields, &$data, &$statements, &$ids)
    {
        foreach ($this->unfuse_fields as $field => $expression) {
            $field_full = str_replace('{t}', $alias, $field);

            if (!isset($unfuse_fields[$field_full])) {
                $expression_full = str_replace('{t}', $alias, $expression);
                $unfuse_fields[$field_full] = $expression_full;
            }
        }

        $is = $line != null;

        $is = is_object($line) && !(@$line->_is === false);
        $was = is_object($oldline);

        if ($is) {
            $this->complete($line);
            $errors = $this->validate($line);
            $this->unpack($line);

            if (count($errors)) {
                error_response("Invalid {$this->name}: "  . implode(', ', $errors));
            }

            foreach ($this->fields as $field) {
                if ($field->type == 'file' && @$line->{$field->name}) {
                    $this->handle_upload($field, $line);
                } else {
                    $data["{$alias}_{$field->name}"] = @$line->{$field->name};
                }
            }
        }

        $dbtable = @Config::get()->tables[$this->table];

        if ($was) {
            $ids["{$alias}_id"] = $oldline->id;

            if ($is) {
                $line->id = $oldline->id;
            }
        } elseif ($is) {
            $fields = [];
            $values = [];
            $needed_vars = [];

            foreach ($unfuse_fields as $field => $expression) {
                if (preg_match("/^{$alias}\.([a-z_]+)$/", $field, $groups)) {
                    $fields[] = $groups[1];
                    $values[] = $expression;

                    preg_match_all('/:([a-z_]+)/', $expression, $matches);

                    for ($i = 0; $i < count($matches[1]); $i++) {
                        $needed_vars[] = $matches[1][$i];
                    }
                }
            }

            $fieldsClause = implode(', ', $fields);
            $valuesClause = implode(', ', $values);

            $q = "insert into {$dbtable} ({$fieldsClause}) values ({$valuesClause})";

            $querydata = [];

            foreach ($needed_vars as $nv) {
                $querydata[$nv] = $data[$nv];
            }

            $statements[] = [$q, $querydata, "{$alias}_id"];

            if ($tablelink) {
                $q = "insert into {$tablelink->middle_table} ({$tablelink->ids[0]}_id, {$tablelink->ids[1]}_id) values (:{$parentalias}_id, :{$alias}_id)";
                $statements[] = [$q, []];
            }
        }

        if (!$is || !$was) {
            foreach ($unfuse_fields as $field => $expression) {
                if (preg_match("/^{$alias}\.([a-z_]+)$/", $field, $groups)) {
                    unset($unfuse_fields[$field]);
                }
            }
        }

        foreach (@$this->inlinelinks ?? [] as $child) {
            if (@$child->norecurse) {
                continue;
            }

            $childtablelink = Tablelink::load($child->tablelink);

            if (@$child->reverse) {
                $childtablelink = $childtablelink->reverse();
            }

            $childlinetype = Linetype::load($child->linetype);
            $childaliasshort = (@$child->alias ?? $childtablelink->ids[1]);
            $childline = $is && $this->has($line, $childaliasshort) ? (@$line->{$childaliasshort} ?? (object) []) : null;
            $childoldline = @$oldline->{$childaliasshort};

            $childlinetype->save_r(
                $alias . '_'  . $childaliasshort,
                $childline,
                $childoldline,
                $childtablelink,
                $alias,
                $unfuse_fields,
                $data,
                $statements,
                $ids
            );
        }

        if ($was && !$is) {
            if ($tablelink) {
                $q = "delete from {$tablelink->middle_table} where {$tablelink->ids[0]}_id = :{$parentalias}_id and {$tablelink->ids[1]}_id = :oldlineid";
                $querydata = ['oldlineid' => $oldline->id];
                $statements[] = [$q, $querydata];
            }

            $q = "delete from {$dbtable} where id = :oldlineid";
            $querydata = ['oldlineid' => $oldline->id];
            $statements[] = [$q, $querydata];
        }
    }
}
