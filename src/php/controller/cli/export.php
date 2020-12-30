<?php
$ids = [];
$output = "";
$timestamp = date('Y-m-d H:i:s');

$token = Blends::login(USERNAME, PASSWORD, true);

foreach (Config::get()->export_linetypes as $econfig) {
    if (!preg_match('/^([a-z]+)(\*?)/', $econfig, $groups)) {
        error_response("Invalid export linetype export config {$econfig}");
    }

    $export_linetype = $groups[1];
    $include_children = $groups[2] == '*';

    $id = 1;
    $ids[$export_linetype] = [];

    $linetype = Linetype::load($token, $export_linetype);
    $lines = $linetype->find_lines($token, null, null, null, false, $include_children, true);

    foreach ($lines as $line) {
        // remove inline children

        foreach ($line as $key => $value) {
            if (is_object($value)) {
                unset($line->{$key});
            }
        }

        // save map of old to new ids

        $ids[$export_linetype][$line->id] = $id++;

        $linetype->strip_r($line);

        // translate parent refs to new ids
        foreach ($linetype->find_incoming_links() as $incoming) {
            $parentaliasshort = $incoming->parent_link . '_' . $incoming->parent_linetype;

            if (@$line->{$parentaliasshort}) {
                if (isset($ids[$incoming->parent_linetype])) {
                    if (!isset($ids[$incoming->parent_linetype][$line->{$parentaliasshort}])) {
                        error_log("Not set: \$ids['" . $incoming->parent_linetype . "']['" . $line->{$parentaliasshort} . "']");
                    }
                    $line->{$parentaliasshort} = $ids[$incoming->parent_linetype][$line->{$parentaliasshort}];
                } else {
                    unset($line->{$parentaliasshort});
                }
            }
        }
    }

    if (count($lines)) {
        echo $timestamp . ' ' . $export_linetype . ' ' . json_encode($lines) . "\n";
    }
}

return [];
