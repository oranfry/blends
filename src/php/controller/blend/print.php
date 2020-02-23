<?php
$blend = Blend::load(BLEND_NAME);
$filters = get_query_filters();
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
