<?php
if (!defined('APP_HOME')) {
    die('Please define APP_HOME');
}

$command = array_shift($argv);
$authargs = [];

$username = null;
$password = null;
$token = null;

$rem_argv = [];

for ($i = 0; $i < count($argv); $i++) {
    if ($argv[$i] == '-u') {
        $username = @$argv[++$i];
    } elseif ($argv[$i] == '-p') {
        $password = @$argv[++$i];
    } elseif ($argv[$i] == '-t') {
        $token = @$argv[++$i];
    } else {
        $rem_argv[] = $argv[$i];
    }
}

$argv = array_merge([$command], $rem_argv);

if ($token) {
    define('AUTH_TOKEN', $token);
} elseif ($username) {
    define('USERNAME', $username);

    if (!$password) {
        echo "Password: ";
        $password = read_password();
    }

    define('PASSWORD', $password);
}

unset($username);
unset($password);
unset($token);

require WWW_HOME . '/plugins/subsimple/subsimple.php';

function read_password()
{
    $f = popen("/bin/bash -c 'read -s'; echo \$REPLY", "r");
    $input = fgets($f, 100);
    pclose($f);
    echo "\n";

    return $input;
}
