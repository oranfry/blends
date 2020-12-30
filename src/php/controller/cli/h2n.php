<?php
$sequence = @BlendsConfig::get()->sequence;
$found = null;

for ($n = 1; $n <= $sequence->max; $n++) {
    $h = n2h(TABLE, $n);

    if ($h == H) {
        $found = $n;
        break;
    }
}

return [
    'found' => $found,
];
