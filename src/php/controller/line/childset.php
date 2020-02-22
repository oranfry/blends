<?php
$linetype = Linetype::load(LINETYPE_NAME);
$parenttype = null;
$parentlink = null;
$parentid = null;

$line = @find_lines($linetype, [(object)['field' => 'id', 'value' => LINE_ID]])[0];

if (!$line) {
    error_response('No such line', 400);
}

$child_sets = load_children($linetype, $line);

if (!isset($child_sets[CHILDSET])) {
    error_response('No such childset', 400);
}

return [
    'data' => $child_sets[CHILDSET],
];
