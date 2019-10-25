#!/usr/bin/php
<?php
define('BLENDS_HOME', dirname(__DIR__));
require BLENDS_HOME . '/src/php/script/lib.php';
load_config();
define('PLUGINS', Config::get()->plugins);

$latests = [];

$types = json_decode(file_get_contents(__DIR__ . '/collect.json'));

foreach ($types as $type => $props) {
    $latest = 0;
    $schedule = [];

    foreach (PLUGINS as $plugin) {
        $dir = BLENDS_HOME . "/plugins/{$plugin}/" . $props->directory;

        if (!is_dir($dir)) {
            continue;
        }

        $handle = opendir($dir);

        while ($file = readdir($handle)) {
            if (preg_match('/^\./', $file)) {
                continue;
            }

            $filepath = $dir . '/' . $file;
            $latest = max(filemtime($filepath), $latest);

            $schedule[] = $filepath;
        }

        closedir($handle);
    }


    $into = __DIR__ . '/' . $props->into;

    shell_exec("rm -rf \"{$into}\"");
    mkdir($into);

    foreach ($schedule as $filepath) {
        if (preg_match('/(.*)(\..*)$/', basename($filepath), $groups)) {
            $filename = $groups[1];
            $ext = $groups[2];
        } else {
            $filename = baename($filepath);
            $ext = '';
        }

        $dest = $into . '/' . $filename . '.' . $latest . $ext;

        shell_exec("cp '{$filepath}' '{$dest}'");
    }

    $latests[$type] = $latest;
}

$types = json_decode(file_get_contents(__DIR__ . '/combine.json'));

foreach ($types as $type => $props) {
    $filedata = "";
    $latest = 0;

    if (!@$props->into) {
        echo "skipping {$type} (no into defined)\n";
        continue;
    }

    if (!@$props->basename) {
        echo "skipping {$type} (no basename defined)\n";
        continue;
    }

    if (!@$props->extension) {
        echo "skipping {$type} (no extension defined)\n";
        continue;
    }

    foreach ($props->files as $file) {
        $filepath = __DIR__ . '/' . preg_replace('/.*:/', '', $file);

        if (!file_exists($filepath)) {
            echo "skipping {$type} (file {$filepath} does not exist)\n";
            continue 2;
        }

        if (preg_match('/^php:(.*)/', $file, $groups)) {
            ob_start();

            require $filepath;

            $filedata .= ob_get_contents();

            ob_end_clean();
        } else {
            $filedata .= file_get_contents($filepath);
        }

        $latest = max(filemtime($filepath), $latest);
    }

    $into = __DIR__ . '/' . $props->into;

    shell_exec("rm -rf \"{$into}\"");
    mkdir($into);
    file_put_contents($into . '/' . $props->basename . '.' . $latest . '.' . $props->extension, $filedata);

    $latests[$type] = $latest;
}

file_put_contents(__DIR__ . '/latest.json', json_encode($latests));
