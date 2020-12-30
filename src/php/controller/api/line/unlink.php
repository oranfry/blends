<?php
$linetype = Linetype::load(AUTH_TOKEN, LINETYPE_NAME);

@list($line) = $linetype->find_lines(AUTH_TOKEN, [(object)['field' => 'id', 'cmp' => '=', 'value' => LINE_ID]]);

if (!$line) {
    return [
        'data' => (object) [],
    ];
}

return [
    'data' => $linetype->unlink(AUTH_TOKEN, $line, PARNT),
];
