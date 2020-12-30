<?php
$linetype = Linetype::load(AUTH_TOKEN, LINETYPE_NAME);
$template = json_decode(file_get_contents('php://input'));
$keep_filedata = @$_GET['keepfiledata'] === '1';
$repeater = Repeater::create($_GET['repeater']);
$from = @$_GET['from'];
$to = @$_GET['to'];

return [
    'data' => $linetype->add(AUTH_TOKEN, $repeater, $from, $to, $template, $keep_filedata),
];
