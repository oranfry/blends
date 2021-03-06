<?php
define('LAYOUT', 'pdf');

$linetype = Linetype::load(AUTH_TOKEN, LINETYPE_NAME);
$line = @$linetype->find_lines(AUTH_TOKEN, [(object)['field' => 'id', 'value' => LINE_ID]])[0];

if (!$line) {
    error_response('No such line', 400);
}

$linetype->load_children(AUTH_TOKEN, $line);
$filedata = $linetype->aspdf($line);

return [
    'filedata' => $filedata,
    'filename' => LINE_ID . '.pdf',
];

