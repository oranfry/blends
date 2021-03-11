<?php
echo "Importing\n\n";

$issued = [];
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

    foreach ($data as $datum) {
        translate_r($token, $linetype, $datum);
    }

    $data = $linetype->save($token, $data, $timestamp);

    echo str_pad(' ' . '(' . count($data) . ')', 12, '.', STR_PAD_LEFT) . ' ' . ($verb ?? '') . ' ' . implode(', ', array_map(function($v, $i) use($verb, $verbs) { return ($verb ? '' : $verbs[$i]) . $v->id; }, $data, array_keys($data))) . "\n";
}
echo "\n";

return [];


function translate_r($token, $linetype, $line)
{
    global $issued;

    if (@$line->id) {
        $line->id = n2h($linetype->table, $line->id);
    }

    foreach ($linetype->find_incoming_links() as $parent) {
        $parentaliasshort = $parent->parent_link . '_' . $parent->parent_linetype;

        if (@$line->{$parentaliasshort}) {
            $parentlinetype = Linetype::load($token, $parent->parent_linetype);
            $line->{$parentaliasshort} = n2h($parentlinetype->table, $line->{$parentaliasshort});
        }
    }

    foreach ($linetype->children as $child) {
        if (!property_exists($line, $child->label) || !is_array($line->{$child->label})) {
            continue;
        }

        foreach ($line->{$child->label} as $childline) {
            translate_r($token, Linetype::load($token, $child->linetype), $childline);
        }
    }
}