<?php
$token = Blends::login(USERNAME, PASSWORD, true);
$linetype = Linetype::load($token, LINETYPE);
$lines = json_decode(fgets(STDIN));

if (!is_array($lines)) {
    error_response('Invalid lines json');
}

$lines = $linetype->save($token, $lines);

return [
    'lines' => $lines,
];
