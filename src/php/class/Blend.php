<?php
class Blend
{
    public $label;
    public $groupby;
    public $printable = false;
    public $showass = [];
    public $linetypes = [];
    public $fields = [];

    public static function load($token, $name)
    {
        $blendclass = @BlendsConfig::get($token)->blends[$name];

        if (!$blendclass) {
            error_response("No such blend '{$name}'");
        }

        $blend = new $blendclass();
        $blend->name = $name;

        return $blend;
    }

    public function delete($token, $filters)
    {
        $linetypes = array_map(function ($linetype_name) use ($token) {
            return Linetype::load($token, $linetype_name);
        }, $this->linetypes);

        $numQueries = 0;
        $affected = 0;
        $recordDeletes = [];
        $linkDeletes = [];

        foreach ($linetypes as $linetype) {
            $_filters = $linetype->filter_filters($token, $filters, $this->fields);

            if ($_filters === false) {
                continue;
            }

            $linetype->delete($token, $_filters);
        }
    }

    public function search($token, $filters)
    {
        if (!Blends::verify_token($token)) {
            return false;
        }

        $linetypes = array_map(function ($linetype_name) use ($token) {
            return Linetype::load($token, $linetype_name);
        }, $this->linetypes);

        $records = [];

        foreach ($linetypes as $linetype) {
            $_filters = $linetype->filter_filters($token, $filters, $this->fields);

            if ($_filters === false) {
                continue;
            }

            $_records = $linetype->find_lines($token, $_filters);

            foreach ($_records as $record) {
                foreach ($this->fields as $field) {
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

        return  $records;
    }

    public function print($token, $filters)
    {
        if (!Blends::verify_token($token)) {
            return false;
        }

        $linetypes = array_map(function ($linetype_name) use ($token) {
            return Linetype::load($token, $linetype_name);
        }, $this->linetypes);

        foreach ($linetypes as $linetype) {
            $_filters = $linetype->filter_filters($token, $filters, $this->fields);

            if ($_filters === false) {
                continue;
            }

            $linetype->print($token, $_filters);
        }
    }

    public function summary($token, $filters)
    {
        if (!Blends::verify_token($token)) {
            return false;
        }

        $summary_fields = filter_objects($this->fields, 'summary', 'is', 'sum');

        if (!count($summary_fields)) {
            return [];
        }

        $linetypes = array_map(function ($linetype_name) use ($token) {
            return Linetype::load($token, $linetype_name);
        }, $this->linetypes);

        $balances = (object) [];

        foreach ($summary_fields as $field) {
            $balances->{$field->name} = '0.00';
        }

        foreach ($linetypes as $linetype) {
            $linetype_filters = [];

            foreach ($filters as $filter) {
                $linetype_field = @filter_objects($linetype->fields, 'name', 'is', $filter->field)[0];
                $field = @filter_objects($this->fields, 'name', 'is', $filter->field)[0];

                if ($filter->field == 'user' || $linetype_field) {
                    $linetype_filters[] = $filter;
                } elseif (
                    $filter->cmp == '=' && $filter->value != @$field->default
                    ||
                    $filter->cmp == 'like' && !preg_match('/' . str_replace('%', '.*', $filter->value) . '/i', $field->default)
                ) {
                    continue 2;
                }
            }

            $summary = $linetype->find_lines($token, $linetype_filters, null, null, true);

            foreach ($summary_fields as $field) {
                $balances->{$field->name} = bcadd($balances->{$field->name}, @$summary->{$field->name} ?? '0.00', 2);
            }
        }

        return $balances;
    }

    public function update($token, $filters, $data)
    {
        if (!Blends::verify_token($token)) {
            return false;
        }

        $linetypes = array_map(function ($linetype_name) use ($token) {
            return Linetype::load($token, $linetype_name);
        }, $this->linetypes);

        foreach ($linetypes as $linetype) {
            $_filters = $linetype->filter_filters($token, $filters, $this->fields);

            if ($_filters === false) {
                continue;
            }

            $lines = $linetype->find_lines($token, $_filters);

            foreach ($lines as $line) {
                foreach ($linetype->fields as $field) {
                    if (property_exists($data, $field->name)) {
                        $line->{$field->name} = $data->{$field->name};
                    }

                    if ($field->type == 'file') {
                        if (property_exists($data, $field->name)) {
                            $line->{$field->name} = $data->{$field->name};
                        }

                        if (property_exists($data, $field->name . '_delete')) {
                            $line->{"{$field->name}_delete"} = $data->{"{$field->name}_delete"};
                        }
                    }
                }

                $linetype->complete($line);
                $errors = $linetype->validate($line);

                if (count($errors)) {
                    error_response("{$linetype->name} {$line->id} is invalid:" . implode(', ', $errors));
                }
            }

            $linetype->save($token, $lines);
        }
    }
}
