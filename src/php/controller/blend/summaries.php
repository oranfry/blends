<?php
require __DIR__ . '/index.php';

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

return [
    'data' => $summaries,
];
