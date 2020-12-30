<?php
echo "Finding Collisions\n\n";

$sequence = @Config::get()->sequence;

if (!$sequence) {
    error_log('Sequence not set up');
    die();
}

$lookup = [];
$collisions = [];
$tables = TABLE ? [TABLE] : array_keys(Config::get()->tables);

foreach ($tables as $table) {
    $lookup[$table] = [];
}

foreach ($tables as $table) {
    echo str_pad("Processing {$table}...", 40, '.');

    for ($i = 1; $i < MAX; $i++) {
        $id = n2h($table, $i);

        if (isset($lookup[$table][$id])) {
            echo str_pad($table, 10, ' ') . ' ' . str_pad($i, 10, ' ', STR_PAD_LEFT) . ' <=> ' . str_pad($lookup[$id], 10);
            echo $id . ' <=> ' . n2h($table, $lookup[$table][$id]);
            echo "\n";

            if (!isset($collisions[$table])) {
                $collisions[$table] = [];
            }

            $collisions[$table][] = $i;
        }

        $lookup[$table][$id] = $i;
    }

    echo "done\n";
}

return [
    'collisions' => $collisions,
];