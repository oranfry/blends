<?php
$context = @$_POST['context'] ?: 'default';
$parsed = parse_url($_POST['returnurl']);
parse_str(@$parsed['query'] ?: '', $query);

if ($context == 'default') {
    unset($query['context']);
} else {
    $query['context'] = $context;
}

$returnurl = preg_replace('/\?.*/', '', $_POST['returnurl']) . (count($query) ? '?' . http_build_query($query) : '');

header("Location: {$returnurl}");
die();
