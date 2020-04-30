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

    public static function find_parent_linetypes($linetype_name, &$child_descriptors = [])
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

    public function astext($line)
    {
        return null;
    }

    public function ashtml($line)
    {
        return null;
    }

    public function delete($filters)
    {
        $oldlines = $this->find_lines($filters);
        $lines = [];

        foreach ($oldlines as $line) {
            $lines[] = (object)['id' => $line->id, '_is' => false];
        }

        $this->save($lines);
    }

    public function print($filters)
    {
        $lines = $this->find_lines($filters);

        foreach ($lines as $line) {
            $this->load_children($line);

            $contents = $this->astext($line);

            if (!defined('PRINTER_FILE')) {
                error_log("\n" . $contents);
                continue; // lets not and say we did - for testing!
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

        return ['messages' => ['Printed Happily']];
    }

    public function save($lines)
    {
        if (!is_array($lines)) {
            error_response("Linetype::save - please pass in an array of lines");
        }

        $dbtable = @Config::get()->tables[$this->table];

        if (!$dbtable) {
            error_response("Could not resolve table {$this->table} to a database table");
        }

        $oldlines = [];
        $oldids = array_filter(map_objects($lines, 'id'));

        if (count($oldids)) {
            foreach ($this->find_lines([(object)['field' => 'id', 'value' => $oldids]]) as $oldline) {
                $oldlines[$oldline->id] = $oldline;
            }
        }

        foreach ($lines as $line) {
            $unfuse_fields = [];
            $data = [];
            $statements = [];
            $ids = [];
            $oldline = @$line->id ? $oldlines[$line->id] : null;

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

                $this->upload_r($line);

                if (@$line->parent) {
                    if (!preg_match('/^([a-z]+):([a-z]+)=([0-9][0-9]*)$/', $line->parent, $groups)) {
                        error_response('Invalid parent specification: ' . $line->parent);
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
        }

        return $lines;
    }

    public function unlink($lines)
    {
        foreach ($lines as $line) {
            $id = $line->id;

            if (!preg_match('/^([a-z]+):([a-z]+)=([0-9][0-9]*)$/', $line->parent, $groups)) {
                error_response('Invalid parent specification');
            }

            $tablelink = Tablelink::load($groups[1]);
            $parentside = @array_flip($tablelink->ids)[$groups[2]];
            $childside = ($parentside + 1) % 2;
            $parentid = intval($groups[3]);

            $query = "delete from {$tablelink->middle_table} where {$tablelink->ids[$parentside]}_id = :parentid and {$tablelink->ids[$childside]}_id = :lineid";
            $querydata = [
                'parentid' => $parentid,
                'lineid' => $line->id,
            ];

            $stmt = Db::prepare($query);
            $result = $stmt->execute($querydata);

            if (!$result) {
                error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$query}\n" . var_export($querydata, true));
            }
        }
    }

    public function build_class_field_fuse($fieldname)
    {
        $field = @filter_objects($this->fields, 'name', 'is', $fieldname)[0];

        if (!$field) {
            return;
        }

        $field->fuse = "if ((" . implode(') or (', $field->clauses) . "), '{$fieldname}', '')";
    }

    public function find_lines($filters = null, $parentId = null, $parentLink = null, $summary = false)
    {
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
        if (!@$line->{"{$field->name}_upload"} && @$line->{"{$field->name}_delete"} !== true) {
            return; // no changes
        }

        $shortpath = ($field->path)($line);
        $filepath = FILES_HOME . '/' . $shortpath;
        $dirs = [];

        for ($parent = dirname($shortpath); $parent != '.'; $parent = dirname($parent)) {
            array_unshift($dirs, FILES_HOME . '/' . $parent);
        }

        if (@$line->{"{$field->name}_upload"}) {
            $result = base64_decode($line->{"{$field->name}_upload"});

            if ($result === false) {
                return;
            }

            if (@$field->mimetype) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);

                if ($finfo->buffer($result) !== $field->mimetype) {
                    return;
                }
            }

            @mkdir(FILES_HOME);

            foreach ($dirs as $dir) {
                @mkdir($dir);
            }

            if (!is_dir(dirname($filepath))) {
                error_response("Failed to create intermediate directories");
            }

            file_put_contents($filepath, $result);

            if (!file_exists($filepath)) {
                error_response("Failed to create the file for field {$field->name}");
            }

            $line->{$field->name} = $shortpath;
        } else {
            unlink($filepath);

            foreach (array_reverse($dirs) as $dir) {
                if (dir_is_empty($dir)) {
                    rmdir($dir);
                }
            }

            $line->{$field->name} = null;
        }

        unset($line->{"{$field->name}_upload"});
        unset($line->{"{$field->name}_delete"});
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

        $is = is_object($line) && !(@$line->_is === false);
        $was = is_object($oldline);

        if ($is) {
            $this->complete($line);
            $errors = $this->validate($line);
            $this->unpack($line);

            if (count($errors)) {
                error_response("Invalid {$this->name} ({$alias}): "  . implode(', ', $errors));
            }

            foreach ($this->fields as $field) {
                if ($field->type != 'file') {
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
            foreach ($this->fields as $field) {
                if ($field->type == 'file') {
                    $filepath = FILES_HOME . '/' . ($field->path)($oldline);
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                }
            }

            foreach (@$this->children ?? [] as $child) {
                if (@$child->ondelete == 'orphan') {
                    continue;
                }

                $childlinetype = Linetype::load($child->linetype);
                $childlines = $childlinetype->find_lines(null, $line->id, $child->parent_link);

                foreach ($childlines as $childline) {
                    $childline->_is = false;
                }

                $childlinetype->save($childlines);
            }

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

    private function upload_r($line)
    {
        foreach ($this->fields as $field) {
            if ($field->type == 'file') {
                $this->handle_upload($field, $line);
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
            $childline = @$line->{$childaliasshort};

            if ($childline) {
                $childlinetype->upload_r($childline);
            }
        }
    }

    public function load_children($line)
    {
        $sets = [];

        foreach ($this->children as $child) {
            $sets[] = $this->load_childset($line, $child);
        }

        return $sets;
    }

    function load_childset($line, $descriptor)
    {
        $child_linetype = Linetype::load(@$descriptor->linetype);
        $fields = $child_linetype->fields;

        $line->{$descriptor->label} = $child_linetype->find_lines(null, $line->id, $descriptor->parent_link);

        if (filter_objects($child_linetype->fields, 'summary', 'is', 'sum')) {
            $line->{"{$descriptor->label}_summary"} = $child_linetype->find_lines(null, $line->id, $descriptor->parent_link, true);
        }

        return $line->{$descriptor->label};
    }

    function filter_filters($filters, $fields)
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

                    if ($type == $this->name) {
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

            $linetype_field = @array_values(array_filter($this->fields, function ($v) use ($filter) {
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

            if (!is_object($field)) {
                error_response("Filter refers to non-existent field {$filter->field} in linetype {$this->name}");
            }

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
}
