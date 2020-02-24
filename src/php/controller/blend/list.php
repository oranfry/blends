<?php
$blends = [];

foreach (Config::get()->blends as $blend) {
    $blends[] = Blend::load($blend);
}

return [
    'data' => $blends,
];
