<?php
$blend = Blend::load(BLEND_NAME);
$filters = get_query_filters();

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

return [
    'data' => $records,
];
