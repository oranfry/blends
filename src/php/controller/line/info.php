<?php
$linetype = Linetype::load(LINETYPE_NAME);

$parents = find_parent_linetypes($linetype->name, $children);
$parenttypes = [];

foreach ($parents as $parent) {
    $parenttypes[] = preg_replace('/.*\\\\/', '', get_class($parent));
}

$linetype->parenttypes = $parenttypes;

return [
    'data' => $linetype,
];
