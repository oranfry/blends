<?php

@include APP_HOME . "/import_translate.php";

echo "Importing\n\n";

$token = Blends::login(USERNAME, PASSWORD, true);

while ($f = fgets(STDIN)) {
    list($date, $time, $linetype_name, $rawdata) = explode(' ', $f, 4);
    $linetype = Linetype::load($token, $linetype_name);
    $timestamp = "{$date} {$time}";

    $print = "{$date} {$time} {$linetype_name} ";
    echo str_pad($print, 49, '.');

    $verb = null;
    $verbs = [];

    $data = json_decode($rawdata);

    foreach ($data as $line) {
        $verbs[] = @$line->_is === false ? '-' : (@$line->id ? '~' : '+');
    }

    if (count(array_unique($verbs)) == 1) {
        $verb = reset($verbs);
    }

    if (function_exists('import_presave')) {
        $result = import_presave($linetype, $data, $token, $timestamp);

        if (is_array($result)) {
            extract($result);
        }
    }

    $data = $linetype->save($token, $data, $timestamp);

    if ($data === false) {
        error_log("Error importing a {$linetype->name}\n");
        die();
    }

    if (function_exists('import_postsave')) {
        $result = import_postsave($linetype, $data, $token, $timestamp);

        if (is_array($result)) {
            extract($result);
        }
    }

    echo str_pad(' ' . '(' . count($data) . ')', 12, '.', STR_PAD_LEFT) . ' ' . ($verb ?? '') . ' ' . implode(', ', array_map(function($v, $i) use($verb, $verbs) { return ($verb ? '' : $verbs[$i]) . $v->id; }, $data, array_keys($data))) . "\n";
}
echo "\n";

return [];
