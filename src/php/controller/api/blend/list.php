<?php
$blends = [];

foreach (array_keys(BlendsConfig::get(AUTH_TOKEN)->blends) as $name) {
    $blends[] = Blend::load(AUTH_TOKEN, $name);
}

return [
    'data' => $blends,
];
