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

    public static function load($token, $name)
    {
        $linetypeclass = @BlendsConfig::get($token)->linetypes[$name]->class;

        if (!$linetypeclass) {
            error_response("No such linetype '{$name}'");
        }

        $linetype = new $linetypeclass();
        $linetype->name = $name;

        return $linetype;
    }

    public final function find_incoming_links($token)
    {
        if (self::$incoming_links == null) {
            self::$incoming_links = [];

            foreach (BlendsConfig::get($token)->linetypes as $name => $class) {
                $linetype = Linetype::load($token, $name);

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

    public function get_suggested_values($token)
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

    public function delete($token, $filters)
    {
        if (!Blends::verify_token($token)) {
            return false;
        }

        $this->_delete($token, $filters);
    }

    private function _delete($token, $filters)
    {
        $oldlines = $this->_find_lines($token, $filters);
        $lines = [];

        foreach ($oldlines as $line) {
            $lines[] = (object)['id' => $line->id, '_is' => false];
        }

        $this->save($token, $lines);
    }

    public function print($token, $filters)
    {
        if (!Blends::verify_token($token)) {
            return false;
        }

        return $this->_print($token, $filters);
    }

    private function _print($token, $filters)
    {
        $lines = $this->find_lines($token, $filters);

        foreach ($lines as $line) {
            $this->_load_children($token, $line);

            $contents = $this->astext($line);

            if (!defined('PRINTER_FILE')) {
                error_log("\n" . $contents);
                continue; // lets not and say we did - for testing!
            }

            $logofile = @BlendsConfig::get($token)->logofile;

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

    public function save($token, $lines, $timestamp = null, $keep_filedata = false)
    {
        if (!Blends::verify_token($token)) {
            return false;
        }

        $commits = [];

        Db::startTransaction();

        $data = $this->_save($token, $lines, $timestamp, $keep_filedata, $commits);

        foreach ($commits as $commit) {
            commit($commit->timestamp, $commit->linetype, $commit->data);
        }

        Db::commit();

        return $data;
    }

    private function _save($token, $lines, $timestamp = null, $keep_filedata = false, &$commits = null)
    {
        $user = Blends::token_user($token);

        if ($user && !@BlendsConfig::get($token)->linetypes[$this->name]->canwrite) {
            error_response("No write access for linetype {$this->name}");
        }

        $sequence = @BlendsConfig::get()->sequence;

        if (!$sequence) {
            error_response("Sequences not set up");
        }

        if (!$timestamp) {
            $timestamp = date('Y-m-d H:i:s');
        }

        if (!is_array($lines)) {
            error_response("Linetype::save - please pass in an array of lines");
        }

        $dbtable = @BlendsConfig::get($token)->tables[$this->table];

        if (!$dbtable) {
            error_response("Could not resolve table {$this->table} to a database table");
        }

        $oldlines = [];
        $oldids = [];

        foreach ($lines as $i => $line) {
            $oldids[$i] = @$line->id;
        }

        if (count($oldids)) {
            foreach ($this->_find_lines($token, [(object)['field' => 'id', 'value' => $oldids]]) as $oldline) {
                $oldlines[$oldline->id] = $oldline;
            }
        }

        masterlog_check();

        foreach ($lines as $line) {
            $unfuse_fields = [];
            $data = [];
            $statements = [];
            $ids = [];
            $oldline = @$line->id ? @$oldlines[$line->id] : null;

            $this->save_r($token, 't', $line, $oldline, null, null, $unfuse_fields, $data, $statements, $ids, $timestamp, $keep_filedata);

            foreach ($statements as $statement) {
                @list($query, $querydata, $statement_table, $saveto) = $statement;

                if ($saveto) {
                    $stmt = Db::prepare("select pointer from sequence_pointer where `table` = :table for update");
                    $result = $stmt->execute(['table' => $statement_table]);

                    if (!$result) {
                        Db::rollback();
                        error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()));
                    }

                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$row) {
                        $stmt = Db::prepare("insert into sequence_pointer values (:table, 1)");
                        $result = $stmt->execute(['table' => $statement_table]);

                        if (!$result) {
                            Db::rollback();
                            error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()));
                        }

                        $row = ['pointer' => 1];
                    }

                    $inc = 1;
                    $pointer = $row['pointer'];
                    $table_collisions = @$sequence->collisions[$statement_table] ?? [];

                    while (in_array($pointer, $table_collisions)) {
                        $pointer++;
                        $inc++;
                    }

                    if ($pointer > @$sequence->max ?? 1) {
                        Db::rollback();
                        error_response("Sequence for table {$statement_table} exhausted");
                    }

                    $id = n2h($statement_table, $pointer);
                    $ids[$saveto] = $id;
                    $querydata[$saveto] = $id;

                    $stmt = Db::prepare("update sequence_pointer set pointer = pointer + :inc where `table` = :table");
                    $result = $stmt->execute(['table' => $statement_table, 'inc' => $inc]);

                    if (!$result) {
                        Db::rollback();
                        error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$query}\n" . var_export($querydata, true));
                    }
                }

                preg_match_all('/:([a-z_]+_id)/', $query, $matches);

                for ($i = 0; $i < count($matches[1]); $i++) {
                    $querydata[$matches[1][$i]] = $ids[$matches[1][$i]];
                }

                $stmt = Db::prepare($query);
                $result = $stmt->execute($querydata);

                if (!$result) {
                    Db::rollback();
                    error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$query}\n" . var_export($querydata, true));
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
                $wheres = [];
                $selects = []; // ignore

                $this->_find_r($token, 't', $selects, $joins, $wheres);

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
                    Db::rollback();
                    error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$query}\n" . var_export($querydata, true));
                }
            }

            if (@$line) {
                $line->id = $ids['t_id'];

                foreach ($this->find_incoming_links($token) as $incoming) {
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
            }
        }

        $lines_clone = [];

        foreach ($lines as $i => $line) {
            $line_clone = $this->clone_r($token, $line);
            $this->strip_children($line_clone);
            $this->strip_r($line_clone);

            $lines_clone[] = $line_clone;
        }

        $commits[] = (object) [
            'timestamp' => $timestamp,
            'linetype' => $this->name,
            'data' => $lines_clone,
        ];

        foreach ($lines as $line) {
            if (@$line) {
                foreach ($this->children as $child) {
                    if (property_exists($line, $child->label)) {
                        foreach ($line->{$child->label} as $childline) {
                            $parentaliasshort = $child->parent_link . '_' . $this->name;
                            $childline->{$parentaliasshort} = $line->id;
                        }

                        Linetype::load($token, $child->linetype)->_save($token, $line->{$child->label}, $timestamp, $keep_filedata, $commits);
                    }
                }

                $this->_upload_r($token, $line);

                if (@$this->printonsave) {
                    $this->print($token, ['field' => 'id', 'cmp' => '=', 'value' => $line->id]); // not implemented
                }
            }
        }

        if (!$keep_filedata) {
            foreach ($lines as $line) {
                $this->stripfiledata_r($token, $line);
            }
        }

        return $lines;
    }

    public function unlink($token, $line, $from)
    {
        if (!Blends::verify_token($token)) {
            return false;
        }

        return $this->_unlink($token, $line, $from);
    }

    private function _unlink($token, $line, $from)
    {
        $parentaliasshort = null;

        foreach ($this->find_incoming_links($token) as $incoming) {
            $_parentaliasshort = $incoming->parent_link . '_' . $incoming->parent_linetype;

            if ($from == $_parentaliasshort) {
                $parentaliasshort = $_parentaliasshort;
                break;
            }
        }

        if (!$parentaliasshort) {
            error_response('Invalid parent specification');
        }

        unset($line->{$parentaliasshort});

        return $this->save($token, [$line]);
    }

    public function build_class_field_fuse($fieldname)
    {
        $field = @filter_objects($this->fields, 'name', 'is', $fieldname)[0];

        if (!$field) {
            return;
        }

        $field->fuse = "if ((" . implode(') or (', $field->clauses) . "), '{$fieldname}', '')";
    }

    public function find_lines($token, $filters = null, $parentId = null, $parentLink = null, $summary = false, $load_children = false, $load_files = false)
    {
        return $this->_find_lines($token, $filters, $parentId, $parentLink, $summary, $load_children, $load_files);
    }

    private function _find_lines($token, $filters = null, $parentId = null, $parentLink = null, $summary = false, $load_children = false, $load_files = false)
    {
        if (!Blends::verify_token($token)) {
            return false;
        }

        $user = Blends::token_user($token);
        $filters = $filters ?? [];
        $dbtable = @BlendsConfig::get($token)->tables[$this->table];

        if (!$dbtable) {
            error_response("Could not resolve table {$this->table} to a database table");
        }

        $selects = [];
        $joins = [];
        $wheres = [];
        $orderbys = ['t.created'];
        $groupbys = [];

        foreach ($filters as $filter) {
            $cmp = @$filter->cmp ?: '=';

            $is_parentage_filter = (function($linetype, $filter) use ($token) {
                foreach ($linetype->find_incoming_links($token) as $parent) {
                    $parentaliasshort = $parent->parent_link . '_' . $parent->parent_linetype;

                    if ($filter->field == $parentaliasshort) {
                        return true;
                    }
                }
            })($this, $filter);

            if ($is_parentage_filter) {
                $cmpvalue = is_array($filter->value) ? 'in (' . implode(', ', array_map(function($v){ return "'{$v}'"; }, $filter->value)) . ')' : ' = ' . "'{$filter->value}'";
                $wheres[] = "t_{$filter->field}.id {$cmpvalue}";
                continue;
            }

            if ($cmp == 'custom') {
                $field = @filter_objects($this->fields, 'name', 'is', $filter->field)[0];

                $wheres[] = ($filter->sql)(str_replace('{t}', 't', $field->fuse));
                continue;
            }

            if ($filter->field == 'id') {
                $expression = 't.id';
            } elseif ($filter->field == 'user') {
                $expression = 't.user';
            } else {
                $field = @filter_objects($this->fields, 'name', 'is', $filter->field)[0];

                if (!$field) {
                    error_response("Cant find fuse expression for filter field {$this->name} {$filter->field} (1)\n\n" . var_export($this->fields, 1));
                }

                $expression = $this->render_fuse_expression($token, $field->name, 't');

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

        $this->_find_r($token, 't', $selects, $joins, $wheres, $summary);

        if ($parentLink && $parentId) {
            $tablelink = Tablelink::load($parentLink);
            $joins[] = make_join($token, $tablelink, 'parent', 't', 0, false);
            $wheres[] = "parent.id = '{$parentId}'";
        }

        // top-level join to logged-in user

        if ($user) {
            $joins[] = "join record_user u on u.user = :user";
        }

        $select = implode(', ', $selects);
        $join = implode(' ', $joins);
        $where = count($wheres) ? 'where ' . implode(' AND ', array_map(function($c){ return "({$c})"; }, $wheres)) : '';
        $orderby = implode(', ', $orderbys);

        $q = "select {$select} from `{$dbtable}` t {$join} {$where} order by {$orderby}";

        $stmt = Db::prepare($q);
        $result = $stmt->execute(['user' => $user]);

        if (!$result) {
            error_response("Execution problem\n" . implode("\n", $stmt->errorInfo()) . "\n{$q}");
        }

        $lines = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $line = (object) [];

            $line->type = $this->name;

            $this->build_r($token, 't', $row, $line, $summary, $load_children, $load_files);

            if ($summary) {
                return $line;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function _find_r($token, $alias, &$selects, &$joins, &$wheres, $summary = false)
    {
        if (!$summary) {
            $selects[] = "{$alias}.id {$alias}_id";
            $selects[] = "{$alias}.user {$alias}_user";
        }

        $user = Blends::token_user($token);

        foreach ($this->fields as $field) {
            if ($summary && !@$field->summary == 'sum') {
                continue;
            }

            $fuse = $this->render_fuse_expression($token, $field->name, $alias, $summary);

            if (!$fuse) {
                continue;
            }

            $selects[] = $fuse . " `{$alias}_{$field->name}`";
        }

        foreach (@$this->inlinelinks ?? [] as $child) {
            $childlinetype = Linetype::load($token, $child->linetype);
            $tablelink = Tablelink::load($child->tablelink);
            $side = @$child->reverse ? 0 : 1;
            $leftJoin = @$child->required ? false : true;
            $childalias = $alias . '_'  . (@$child->alias ?? $tablelink->ids[$side]);

            $joins[] = make_join($token, $tablelink, $childalias, $alias, $side, $leftJoin);

            if (@$child->norecurse) {
                continue;
            }

            $childlinetype->_find_r($token, $childalias, $selects, $joins, $wheres, $summary);
        }

        if (!$summary) {
            foreach ($this->find_incoming_links($token) as $incoming) {
                $tablelink = Tablelink::load($incoming->parent_link);
                $side = @$incoming->reverse ? 1 : 0;
                $leftJoin = @$child->required ? false : true;
                $parentaliasshort = $incoming->parent_link . '_' . $incoming->parent_linetype;
                $parentalias = $alias . '_'  . $parentaliasshort;

                $joins[] = make_join($token, $tablelink, $parentalias, $alias, $side, $leftJoin);
                $selects[] = "{$parentalias}.id {$parentalias}_id";
            }
        }

        if ($user) {
            $wheres[] = "{$alias}.user = u.user";
        }
    }

    private function build_r($token, $alias, &$row, $line, $summary = false, $load_children = false, $load_files = false)
    {
        if (!$summary) {
            $line->id = $row["{$alias}_id"];
            $line->user = $row["{$alias}_user"];
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

            $fuse = $this->render_fuse_expression($token, $field->name, $alias, $summary);

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

            $childlinetype = Linetype::load($token, $child->linetype);
            $childline = (object) [];

            $childlinetype->build_r($token, $childalias, $row, $childline, $summary, $load_children, $load_files);

            $line->{$childaliasshort} = $childline;
        }

        if (!$summary) {
            foreach ($this->find_incoming_links($token) as $incoming) {
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
            $this->_load_children($token, $line);
        }
    }

    private function render_fuse_expression($token, $fieldname, $alias, $summary = false)
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
            $this->borrow_r($token, $alias, 't', $raw);
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

    private function borrow_r($token, $root, $alias, &$expression)
    {
        foreach (@$this->inlinelinks ?: [] as $child) {
            if (@$child->norecurse) {
                continue;
            }

            $childaliasshort = @$child->alias ?? Tablelink::load($child->tablelink)->ids[@$child->reverse ? 0 : 1];
            $childlinetype = Linetype::load($token, $child->linetype);
            $childalias = "{$alias}_{$childaliasshort}";

            foreach ($childlinetype->fields ?: [] as $field) {
                $fuse = $childlinetype->render_fuse_expression($token, $field->name, "{$root}_{$childaliasshort}");

                if ($fuse) {
                    $expression = str_replace('{' . "{$childalias}_{$field->name}" . '}', $fuse, $expression);
                }
            }

            $childlinetype->borrow_r(
                $token,
                "{$root}_{$childaliasshort}",
                "{$alias}_{$childaliasshort}",
                $expression
            );
        }
    }

    private function _handle_upload($token, $field, $line)
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

            $this->_save_file($line, $field, $filedata);
        } elseif (@$line->{"{$field->name}_generate"}) {
            if (!@$field->generable) {
                error_response("File field {$this->name}.{$field->name} not marked as generable");
            }

            $clone = clone $line;

            $this->_load_children($token, $clone);
            $filedata = $this->aspdf($clone);

            $this->_save_file($line, $field, $filedata);
        } elseif (@$line->{"{$field->name}_delete"}) {
            $this->_delete_file($line, $field);
        } else {
            return; //nothing to do
        }

        unset($line->{"{$field->name}_delete"});
    }

    private function save_r($token, $alias, $line, $oldline, $tablelink, $parentalias, &$unfuse_fields, &$data, &$statements, &$ids, $timestamp, $keep_filedata)
    {
        $user = Blends::token_user($token);

        if (!empty($line) && !is_object($line)) {
            error_response('Lines must be objects');
        }

        if (!empty($oldline) && !is_object($oldline)) {
            error_response('Old lines must be objects');
        }

        foreach ($this->unfuse_fields as $field => $expression) {
            $field_full = str_replace('{t}', $alias, $field);

            if (!isset($unfuse_fields[$field_full])) {
                $expression_full = str_replace('{t}', $alias, $expression);
                $unfuse_fields[$field_full] = $expression_full;
            }
        }

        $unfuse_fields["{$alias}.user"] = ":{$alias}_user";

        $is = is_object($line) && !(@$line->_is === false);
        $was = is_object($oldline);

        if (!$was && @$line->id) {
            error_response("Cannot update: original line not found: {$this->name} {$line->id}");
        }

        if (@$line->id) {
            $line->given_id = $line->id;
        }

        if ($user) {
            if ($is && !$was && !@BlendsConfig::get($token)->linetypes[$this->name]->cancreate) {
                error_response("No create access for linetype {$this->name}");
            }

            if (!$is && $was && !@BlendsConfig::get($token)->linetypes[$this->name]->candelete) {
                error_response("No delete access for linetype {$this->name}");
            }
        }

        if ($is) {
            if ($user) {
                $line->user = $was ? $oldline->user : $user;

                if ($user != $line->user) {
                    error_response('You do not have permission to update this ' . $this->name);
                }
            } elseif (!property_exists($line, 'user') && $was) {
                $line->user = $oldline->user;
            }

            $this->complete($line);

            foreach ($this->fields as $field) {
                if ($field->type != 'file') {
                    if (!@$line->{$field->name}) {
                        $line->{$field->name} = null;
                    }

                    $data["{$alias}_{$field->name}"] = $line->{$field->name};
                }
            }

            $data["{$alias}_user"] = @$line->user;

            $errors = $this->validate($line);

            if (count($errors)) {
                error_response("Invalid {$this->name} ({$alias}): "  . implode(', ', $errors));
            }

            $this->unpack($line);
        }

        $dbtable = @BlendsConfig::get($token)->tables[$this->table];

        if ($was) {
            $ids["{$alias}_id"] = $oldline->id;

            if ($is) {
                $line->id = $oldline->id;
            }
        } elseif ($is) {
            $fields = [];
            $values = [];
            $needed_vars = [];

            $fields[] = 'id';
            $values[] = ":{$alias}_id";

            foreach ($unfuse_fields as $field => $expression) {
                if (preg_match("/^{$alias}\.([a-z_]+)$/", $field, $groups)) {
                    $fields[] = $groups[1];
                    $values[] = str_replace('t.', '', $expression);

                    preg_match_all('/:([a-z_]+)/', $expression, $matches);

                    for ($i = 0; $i < count($matches[1]); $i++) {
                        $needed_vars[] = $matches[1][$i];
                    }
                }
            }

            if ($timestamp !== null) {
                $fields[] = 'created';
                $values[] = ":created";
            }

            $fieldsClause = implode(', ', $fields);
            $valuesClause = implode(', ', $values);

            $q = "insert into {$dbtable} ({$fieldsClause}) values ({$valuesClause})";

            $querydata = [];

            foreach ($needed_vars as $nv) {
                $querydata[$nv] = $data[$nv];
            }

            if ($timestamp !== null) {
                $querydata['created'] = $timestamp;
            }

            $statements[] = [$q, $querydata, $this->table, "{$alias}_id"];

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

            $childlinetype = Linetype::load($token, $child->linetype);
            $childaliasshort = (@$child->alias ?? $childtablelink->ids[1]);
            $childline = $is && $this->has($line, $childaliasshort) ? (@$line->{$childaliasshort} ?? (object) []) : null;
            $childoldline = @$oldline->{$childaliasshort};

            $childlinetype->save_r(
                $token,
                $alias . '_'  . $childaliasshort,
                $childline,
                $childoldline,
                $childtablelink,
                $alias,
                $unfuse_fields,
                $data,
                $statements,
                $ids,
                $timestamp,
                $keep_filedata
            );
        }

        if ($was && !$is) {
            foreach ($this->fields as $field) {
                if ($field->type == 'file') {
                    $this->_delete_file($line, $field);
                }
            }

            foreach (@$this->children ?? [] as $child) {
                if (@$child->ondelete == 'orphan') {
                    continue;
                }

                $childlinetype = Linetype::load($token, $child->linetype);
                $childlines = $childlinetype->_find_lines($token, null, $line->id, $child->parent_link);

                foreach ($childlines as $childline) {
                    $childline->_is = false;
                }

                $childlinetype->_save($token, $childlines, $timestamp, $keep_filedata, $commits);
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

    private function _upload_r($token, $line)
    {
        foreach ($this->fields as $field) {
            if ($field->type == 'file') {
                $this->_handle_upload($token, $field, $line);
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

            $childlinetype = Linetype::load($token, $child->linetype);
            $childaliasshort = (@$child->alias ?? $childtablelink->ids[1]);
            $childline = @$line->{$childaliasshort};

            if ($childline) {
                $childlinetype->_upload_r($token, $childline);
            }
        }
    }

    public function load_children($token, $line)
    {
        if (!Blends::verify_token($token)) {
            return false;
        }
;
        return $this->_load_children($token, $line);
    }

    private function _load_children($token, $line)
    {
        $sets = [];

        foreach ($this->children as $child) {
            $sets[] = $this->_load_childset($token, $line, $child);
        }

        return $sets;
    }

    public function load_childset($token, $line, $descriptor)
    {
        if (!Blends::verify_token($token)) {
            return false;
        }
;
        return $this->_load_childset($token, $line, $descriptor);
    }

    private function _load_childset($token, $line, $descriptor)
    {
        $child_linetype = Linetype::load($token, @$descriptor->linetype);
        $fields = $child_linetype->fields;

        $line->{$descriptor->label} = $child_linetype->find_lines($token, null, $line->id, $descriptor->parent_link);

        if (filter_objects($child_linetype->fields, 'summary', 'is', 'sum')) {
            $line->{"{$descriptor->label}_summary"} = $child_linetype->find_lines($token, null, $line->id, $descriptor->parent_link, true);
        }

        return $line->{$descriptor->label};
    }

    public function filter_filters($token, $filters, $fields)
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

            if (in_array($filter->field, ['id', 'user'])) {
                $r[] = $filter;
                continue;
            }

            // look for standard field

            $linetype_field = @array_values(array_filter($this->fields, function ($v) use ($filter) {
                return $v->name == $filter->field;
            }))[0];

            if ($linetype_field) {
                $r[] = $filter;
                continue;
            }

            // try a reference field

            foreach ($this->find_incoming_links($token) as $parent) {
                $parentaliasshort = $parent->parent_link . '_' . $parent->parent_linetype;

                if ($filter->field == $parentaliasshort) {
                    $r[] = $filter;
                    continue 2;
                }
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

        $intermediate = substr($line->id, 0, 3);

        return "{$field->path}/{$intermediate}/{$line->id}.pdf";
    }

    private function _delete_file($line, $field)
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
            if (is_dir($dir) && dir_is_empty($dir)) {
                rmdir($dir);
            }
        }

        $line->{$field->name} = null;
    }

    private function _save_file($line, $field, $filedata)
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
        $line->{$field->name} = base64_encode($filedata);
    }

    public function strip_children($line)
    {
        foreach ($this->children as $child) {
            unset($line->{$child->label});
        }
    }

    public function strip_r($line)
    {
        unset($line->id);
        unset($line->type);

        if (@$line->given_id) {
            $line->id = $line->given_id;
            unset($line->given_id);
        }

        foreach ($this->fields as $field) {
            if (@$field->derived) {
                unset($line->{$field->name});
            }

            if (!@$line->{$field->name}) {
                unset($line->{$field->name});
            }
        }
    }

    public function stripfiledata_r($token, $line)
    {
        foreach ($this->fields as $field) {
            if ($field->type == 'file') {
                unset($line->{$field->name});
            }
        }

        foreach ($this->children as $child) {
            if (!property_exists($line, $child->label) || !is_array($line->{$child->label})) {
                continue;
            }

            foreach ($line->{$child->label} as $childline) {
                Linetype::load($token, $child->linetype)->stripfiledata_r($token, $childline);
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

            $childaliasshort = (@$child->alias ?? $childtablelink->ids[1]);
            $childline = @$line->{$childaliasshort};

            if ($childline) {
                Linetype::load($token, $child->linetype)->stripfiledata_r($token, $childline);
            }
        }
    }

    public function clone_r($token, $line)
    {
        $clone = clone $line;

        foreach ($this->children as $child) {
            if (!property_exists($line, $child->label) || !is_array($line->{$child->label})) {
                continue;
            }

            foreach ($line->{$child->label} as $i => $childline) {
                $line->{$child->label}[$i] = Linetype::load($token, $child->linetype)->clone_r($token, $childline);
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

            $childaliasshort = (@$child->alias ?? $childtablelink->ids[1]);
            $childline = @$line->{$childaliasshort};

            if ($childline) {
                $line->{$childaliasshort} = Linetype::load($token, $child->linetype)->clone_r($token, $childline);
            }
        }

        return $clone;
    }
}
