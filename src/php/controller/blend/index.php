<?php
use contextvariableset\Daterange;
use contextvariableset\Value;
use contextvariableset\Filter;
use contextvariableset\Hidden;
use contextvariableset\Showas;

if (defined('JSON') && JSON) {
    define('LAYOUT', 'json');
}

$blend = Blend::load(BLEND_NAME);

$linetypes = array_map(function ($linetype_name) {
    return Linetype::load($linetype_name);
}, $blend->linetypes);

$all_fields = $blend->fields;

$types = array_values(
    array_filter(
        map_objects($linetypes, 'name'),
        function ($v) use ($blend) {
            return !@$blend->hide_types || !in_array($v, array_keys($blend->hide_types));
        }
    )
);

$classes = filter_objects($all_fields, 'type', 'is', 'class');
$fields = filter_objects(filter_objects($all_fields, 'hide', 'not', true), 'type', 'not', 'class');

$generic = (object) [];
$generic_builder = [];

foreach ($all_fields as $field) {
    $generic_builder[$field->name] = [];
}

if (@$blend->groupby) {
    $groupfield = $blend->groupby;
} else {
    $groupable_fields = filter_objects($fields, 'groupable', 'is', true);
    $groupfield = 'group';

    if (count($groupable_fields)) {
        if (count($groupable_fields) > 1) {
            $groupby = new Value('groupby');
            $groupby->options = map_objects($groupable_fields, 'name');

            ContextVariableSet::put('groupby', $groupby);

            foreach ($groupable_fields as $groupable_field) {
                if ($groupby->value == $groupable_field->name) {
                    $groupfield = $groupable_field->name;
                }
            }
        }
    }
}

foreach ($all_fields as $field) {
    if (!@$field->main) {
        continue;
    }

    if ($field->type == 'date') {
        ContextVariableSet::put('daterange', new Daterange('daterange'));
    } else {
        $cvs = new Value("{$blend->name}_{$field->name}");
        $cvs->label = $field->name;

        if (@$field->filteroptions) {
            $cvs->options = method_exists($field, 'filteroptions') ? $field->filteroptions() : $field->filteroptions;
        }

        ContextVariableSet::put($field->name, $cvs);
    }
}

apply_filters();

$filters = get_current_filters($all_fields);
$summary_filters = get_past_filters($all_fields);

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

foreach ($fields as $field) {
    if (@$field->summary_if) {
        $field_summary = $field->summary;
        $field->summary = false;

        foreach ($filters as $filter) {
            if ($filter->field == $field->summary_if) {
                $field->summary = $field_summary;
            }
        }
    }
}

$records = [];

foreach ($linetypes as $linetype) {
    $_filters = filter_filters($filters, $linetype, $all_fields);

    if ($_filters === false) {
        continue;
    }

    if (@$blend->amountltzero) {
        $_filters[] = (object)[
            'field' => 'amount',
            'cmp' => '<',
            'value' => 0,
        ];

        $_filters[] = (object)[
            'field' => 'account',
            'cmp' => '!=',
            'value' => "transfer",
        ];
    }

    $_records = find_lines($linetype, $_filters);

    foreach ($_records as $record) {
        $record->type = @$blend->hide_types[$linetype->name] ?: $linetype->name;
        $record->group = @$_GET['linetype'];

        if (@$blend->amountltzero) {
            $record->amount = bcmul($record->amount, '-1', 2);
        }

        foreach ($all_fields as $field) {
            if (!property_exists($record, $field->name)) {
                if (!property_exists($field, 'default')) {
                    error_response("Blend field {$field->name} requires a default value");
                }

                $record->{$field->name} = $field->default;
            }

            if (!in_array($record->{$field->name}, $generic_builder[$field->name])) {
                $generic_builder[$field->name][] = $record->{$field->name};
            }
        }
    }

    $records = array_merge($records, $_records);
}

foreach ($filters as $filter) {
    if (
        @$filter->cmp == 'like'
        &&
        strpos($filter->value, '%') === false
    ) {
        $fields = filter_objects($fields, 'name', 'not', $filter->field);
    }
}

if ($groupfield) {
    $fields = filter_objects($fields, 'name', 'not', $groupfield);
    $groupby_field = @filter_objects($all_fields, 'name', 'is', $groupfield)[0];

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

if ($blend->past_toggle && !@$blend->past) {
    activate_contextvariableset('boolean', 'summarise_past', 'past');
}

if ($blend->cum_toggle && !@$cum) {
    activate_contextvariableset('boolean', 'cum_summaries', 'cumulative');
}

if (count(filter_objects($fields, 'summary', 'is', 'sum'))) {
    $summaries = [];
    $balances = [];

    if (@$summarise_past_bool == 'yes' || @$blend->past) {
        foreach ($linetypes as $linetype) {
            $_summary_filters = [];

            foreach ($summary_filters as $filter) {
                $linetype_field = @filter_objects($linetype->fields, 'name', 'is', $filter->field)[0];
                $field = @filter_objects($all_fields, 'name', 'is', $filter->field)[0];

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

            foreach ($fields as $field) {
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
        foreach ($fields as $_field) {
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

foreach ($generic_builder as $field => $values) {
    if (count($values) == 1) {
        $generic->{$field} = $values[0];
    }
}

if (count($blend->showass) > 1) {
    $showas = new Showas("{$blend->name}_showas");
    $showas->options = $blend->showass;
    ContextVariableSet::put('showas', $showas);
    define('SHOWAS', $showas->value ?: @$blend->showass[0] ?: 'list');
    $showas->value = SHOWAS;
} else {
    define('SHOWAS', @$blend->showass[0] ?: 'list');
}

$graphfield = @$blend->graphfield;
$datefieldwhichisgroupfield = @filter_objects(@filter_objects($all_fields, 'name', 'is', $groupfield), 'type', 'is', 'date')[0];

if ($datefieldwhichisgroupfield) {
    $currentgroup = date('Y-m-d');
    $defaultgroup = date('Y-m-d');
}

$prepop = [];

foreach ($filters as $filter) {
    if (property_exists($filter, 'value') && !is_array($filter->value)) {
        if ($filter->cmp == '=') {
            $prepop[$filter->field] = $filter->value;
        } elseif ($filter->cmp == 'like') {
            $prepop[$filter->field] = str_replace('%', '', $filter->value);
        }
    }
}

return [
    'data' => $records,
    'records' => $records,
    'classes' => $classes,
    'fields' => $fields,
    'all_fields' => $all_fields,
    'types' => $types,
    'generic' => $generic,
    'groupfield' => $groupfield,
    'currentgroup' => @$currentgroup,
    'defaultgroup' => @$defaultgroup,
    'graphfield' => $graphfield,
    'summaries' => @$summaries,
    'prepop' => $prepop,
];
