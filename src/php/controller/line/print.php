<?php
$linetype = Linetype::load(LINETYPE_NAME);
$line = find_lines($linetype, [(object)['field' => 'id', 'value' => LINE_ID]])[0];

if (!$line) {
    error_response('No such line', 400);
}

$child_sets = load_children($linetype, $line);

print_line($linetype, $line, $child_sets);

$messages = ["Printed Happily"];

return [
    'data' => $messages,
];
