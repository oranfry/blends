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

    private static $incoming_links = null;

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

    public final function find_incoming_links()
    {
        if (self::$incoming_links == null) {
            self::$incoming_links = [];

            foreach (Config::get()->linetypes as $name => $class) {
                $linetype = Linetype::load($name);

                foreach ($linetype->children as $child) {
                    $link = clone $child;
                    $link->parent_linetype = $name;

                    if (!isset(self::$incoming_links[$child->linetype])) {
                        self::$incoming_links[$child->linetype] = [];
                    }

                    self::$incoming_links[$child->linetype][] = $link;
                }
            }
        }

        return @self::$incoming_links[$this->name] ?: [];
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
    }

    public function ashtml($line)
    {
        $text = $this->astext($line);

        if ($text) {
            return '<pre>' . $text . '</pre>';
        }
    }

    public function aspdf($line)
    {
        $cmd = "/usr/bin/xvfb-run -- /usr/bin/wkhtmltopdf -s A4 - -";
        $descriptorspec = [
           ['pipe', 'r'],
           ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptorspec, $pipes, '/tmp');

        if (!is_resource($process)) {
            error_response('Failed to create pdf (1)');
        }

        fwrite($pipes[0], $this->ashtml($line));
        fclose($pipes[0]);

        $filedata = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $return_value = proc_close($process);

        return $filedata;
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
        $oldids = [];

        foreach ($lines as $i => $line) {
            $oldids[$i] = @$line->id;
        }

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

                foreach ($this->find_incoming_links() as $incoming) {
                    $tablelink = Tablelink::load($incoming->parent_link);
                    $parentside = @$incoming->reverse ? 1 : 0;
                    $childside = ($parentside + 1) % 2;
                    $parentaliasshort = $incoming->parent_link . '_' . $incoming->parent_linetype;

                    $newparent = @$line->{$parentaliasshort};
                    $oldparent = @$oldline->{$parentaliasshort};

                    if ($newparent == $oldparent) {
                        continue;
                    }

                    if ($oldparent) {
                        $query = "delete from {$tablelink->middle_table} where {$tablelink->ids[$parentside]}_id = :parentid and {$tablelink->ids[$childside]}_id = :lineid";
                        $querydata = [
                            'parentid' => $oldparent,
                            'lineid' => $oldline->id
                        ];
                        $stmt = Db::prepare($query);
                        $result = $stmt->execute($querydata);

                        if (!$result) {
                            error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$query}\n" . var_export($querydata, true));
                        }
                    }

                    if ($newparent) {
                        $query = "insert into {$tablelink->middle_table} ({$tablelink->ids[$parentside]}_id, {$tablelink->ids[$childside]}_id) values (:parentid, :lineid) on duplicate key update {$tablelink->ids[$parentside]}_id = :parentid, {$tablelink->ids[$childside]}_id = :lineid";
                        $querydata = [
                            'parentid' => $newparent,
                            'lineid' => $line->id
                        ];
                        $stmt = Db::prepare($query);
                        $result = $stmt->execute($querydata);

                        if (!$result) {
                            error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$query}\n" . var_export($querydata, true));
                        }
                    }
                }

                foreach ($this->children as $child) {
                    if (property_exists($line, $child->label)) {
                        foreach ($line->{$child->label} as $childline) {
                            $childline->{$child->parent_link} = $line->id;
                        }

                        Linetype::load($child->linetype)->save($line->{$child->label});
                    }
                }

                $this->upload_r($line);

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

    public function find_lines($filters = null, $parentId = null, $parentLink = null, $summary = false, $load_children = false, $load_files = false)
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
                if (count($filter->value)) {
                    $value =  '(' . implode(',', array_map(function($e){ return "'{$e}'"; }, $filter->value)) . ')';
                    $wheres[] = "{$expression} in {$value}";
                } else {
                    if ($summary) {
                        return (object) [];
                    }

                    return [];
                }
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

            $this->build_r('t', $row, $line, $summary, $load_children, $load_files);

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

        if (!$summary) {
            foreach ($this->find_incoming_links() as $incoming) {
                $tablelink = Tablelink::load($incoming->parent_link);
                $side = @$incoming->reverse ? 1 : 0;
                $leftJoin = @$child->required ? false : true;
                $parentaliasshort = $incoming->parent_link . '_' . $incoming->parent_linetype;
                $parentalias = $alias . '_'  . $parentaliasshort;

                $joins[] = make_join($tablelink, $parentalias, $alias, $side, $leftJoin);
                $selects[] = "{$parentalias}.id {$parentalias}_id";
            }
        }
    }

    private function build_r($alias, &$row, $line, $summary = false, $load_children = false, $load_files = false)
    {
        if (!$summary) {
            $line->id = $row["{$alias}_id"];
        }

        foreach ($this->fields as $field) {
            if ($summary && !@$field->summary == 'sum') {
                continue;
            }

            if (!$summary && $field->type == 'file' && defined('FILES_HOME')) {
                $path = $this->file_path($line, $field);
                $file = FILES_HOME . '/' . $path;

                if (file_exists($file)) {
                    if ($load_files) {
                        $line->{$field->name} = base64_encode(file_get_contents($file));
                    } else {
                        $line->{"{$field->name}_path"} = $path;
                    }
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

            $childlinetype->build_r($childalias, $row, $childline, $summary, $load_children, $load_files);

            $line->{$childaliasshort} = $childline;
        }

        if (!$summary) {
            foreach ($this->find_incoming_links() as $incoming) {
                $tablelink = Tablelink::load($incoming->parent_link);
                $side = @$incoming->reverse ? 1 : 0;
                $leftJoin = @$child->required ? false : true;
                $parentaliasshort = $incoming->parent_link . '_' . $incoming->parent_linetype;
                $parentalias = $alias . '_'  . $parentaliasshort;

                if ($row["{$parentalias}_id"]) {
                    $line->{$parentaliasshort} = $row["{$parentalias}_id"];
                }
            }
        }

        if (!$summary && method_exists($this, 'fuse')) {
            $this->fuse($line);
        }

        if ($load_children) {
            $this->load_children($line);
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
        if (@$line->{$field->name}) {
            if (@$field->generate_only) {
                error_response("File field {$this->name}.{$field->name} marked as generate only");
            }

            $filedata = base64_decode($line->{$field->name});

            if ($filedata === false) {
                error_response("Failed to base64 decode the uploaded file");
            }

            if (@$field->mimetype) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);

                if ($finfo->buffer($filedata) !== $field->mimetype) {
                    error_response("Uploaded file is of the wrong type");
                }
            }

            $this->save_file($line, $field, $filedata);
        } elseif (@$line->{"{$field->name}_generate"}) {
            if (!@$field->generable) {
                error_response("File field {$this->name}.{$field->name} not marked as generable");
            }

            $clone = clone $line;

            $this->load_children($clone);
            $filedata = $this->aspdf($clone);

            $this->save_file($line, $field, $filedata);
        } elseif (@$line->{"{$field->name}_delete"}) {
            $this->delete_file($line, $field);
        } else {
            return; //nothing to do
        }

        unset($line->{$field->name});
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
                    if (!@$line->{$field->name}) {
                        $value = null;
                    } else {
                        $value = $line->{$field->name};
                    }

                    $data["{$alias}_{$field->name}"] = $value;
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
                    $this->delete_file($line, $field);
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

    public function load_childset($line, $descriptor)
    {
        $child_linetype = Linetype::load(@$descriptor->linetype);
        $fields = $child_linetype->fields;

        $line->{$descriptor->label} = $child_linetype->find_lines(null, $line->id, $descriptor->parent_link);

        if (filter_objects($child_linetype->fields, 'summary', 'is', 'sum')) {
            $line->{"{$descriptor->label}_summary"} = $child_linetype->find_lines(null, $line->id, $descriptor->parent_link, true);
        }

        return $line->{$descriptor->label};
    }

    public function filter_filters($filters, $fields)
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

    private function file_path($line, $field)
    {
        if (!@$field->path) {
            error_response("No path defined for file field {$this->name}.{$field->name}");
        }

        $hash = md5($field->path . ':' . $line->id);
        $intermediate = substr($hash, 0, 3);

        return "{$field->path}/{$intermediate}/{$line->id}.pdf";
    }

    private function delete_file($line, $field)
    {
        $shortpath = $this->file_path($line, $field);
        $filepath = FILES_HOME . '/' . $shortpath;
        $dirs = [];

        for ($parent = dirname($shortpath); $parent != '.'; $parent = dirname($parent)) {
            array_unshift($dirs, FILES_HOME . '/' . $parent);
        }

        if (file_exists($filepath)) {
            unlink($filepath);
        }

        foreach (array_reverse($dirs) as $dir) {
            if (dir_is_empty($dir)) {
                rmdir($dir);
            }
        }

        $line->{$field->name} = null;
    }

    private function save_file($line, $field, $filedata)
    {
        $shortpath = $this->file_path($line, $field);
        $filepath = FILES_HOME . '/' . $shortpath;
        $dirs = [];

        for ($parent = dirname($shortpath); $parent != '.'; $parent = dirname($parent)) {
            array_unshift($dirs, FILES_HOME . '/' . $parent);
        }

        @mkdir(FILES_HOME);

        foreach ($dirs as $dir) {
            @mkdir($dir);
        }

        if (!is_dir(dirname($filepath))) {
            error_response("Failed to create intermediate directories");
        }

        file_put_contents($filepath, $filedata);

        if (!file_exists($filepath)) {
            error_response("Failed to create the file for field {$field->name}");
        }

        $line->{"{$field->name}_path"} = $shortpath;
    }

    public function strip_r($line)
    {
        unset($line->id);
        unset($line->type);

        foreach ($this->fields as $field) {
            if (@$field->derived) {
                unset($line->{$field->name});
            }

            if (!@$line->{$field->name}) {
                unset($line->{$field->name});
            }
        }

        foreach ($this->find_incoming_links() as $incoming) {
            $parentaliasshort = $incoming->parent_link . '_' . $incoming->parent_linetype;
            unset($line->{$parentaliasshort});
        }

        foreach ($this->children as $child) {
            if (!property_exists($line, $child->label) || !is_array($line->{$child->label})) {
                continue;
            }

            if (count($line->{$child->label})) {
                foreach ($line->{$child->label} as $childline) {
                    Linetype::load($child->linetype)->strip_r($childline);
                }
            } else {
                unset($line->{$child->label});
            }
        }
    }
}
