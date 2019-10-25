<?php

foreach (PLUGINS as $plugin) {
    $dir = BLENDS_HOME . "/plugins/{$plugin}/icon";

    if (!is_dir($dir)) {
        continue;
    }

    $handle = opendir($dir);

    while ($file = readdir($handle)) {
        if (!preg_match('/(.*)\.png$/', $file, $groups)) {
            continue;
        }

        $icon = $groups[1];

        echo ".icon--{$icon} { background-image: url(/img/icon/{$icon}.{$latests['icon']}.png); }\n";
    }

    closedir($handle);
}
