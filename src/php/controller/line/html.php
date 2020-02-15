<?php
define('LAYOUT', 'line');

$linetype = Linetype::load(LINETYPE_NAME);
$parenttype = null;
$parentlink = null;
$parentid = null;

$line = @find_lines($linetype, [(object)['field' => 'id', 'value' => LINE_ID]])[0];

if (!$line) {
    error_response('No such line', 400);
}

$line->type = $linetype->name;
$child_sets = load_children($linetype, $line);

return [
    'linehtml' => $linetype->ashtml($line, $child_sets),
];
