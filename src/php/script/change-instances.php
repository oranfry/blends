<?php
if (defined('DEBUG_CI')) {
    echo "<pre>";
}

$context = @$_POST['context'] ?: 'default';

@$_SESSION['contextvariables'] = [];

foreach ($_POST as $name => $value) {
    if (preg_match('/^_/', $name)) {
        continue;
    }

    list($prefix, $var) = explode('__', $name);

    @$_SESSION['contextvariables'][CONTEXT][$prefix][$var] = $value;

    if (defined('DEBUG_CI')) {
        echo "_SESSION[contextvariables][" . CONTEXT . "][{$prefix}][$var] = {$value}\n";
    }
}

if (defined('DEBUG_CI')) {
    die();
}

header("Location: {$_POST['_returnurl']}");
die();
