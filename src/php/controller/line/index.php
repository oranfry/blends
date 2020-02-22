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

$line->type = $linetype->name;
$line->astext = $linetype->astext($line, $child_sets);

return [
    'data' => $line,
];
