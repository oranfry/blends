<?php

$linetype = Linetype::load(LINETYPE_NAME);
$parentlinetype = Linetype::load(PARENTTYPE_NAME);

$tablelink = null;

foreach ($parentlinetype->children as $child) {
    if ($child->linetype == $linetype->name) {
        $tablelink = Tablelink::load($child->parent_link);

        break;
    }
}

if (!$tablelink) {
    error_response('Could not find the table link');
}

unlink_record(LINE_ID, PARENT_ID, $tablelink);
