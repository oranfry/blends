<?php
define('LAYOUT', 'json');

$linetype = Linetype::load(LINETYPE_NAME);

if (!isset($_GET['parentid']) || !isset($_GET['parenttype'])) {
    error_response('Parent not specified');
}

if (!preg_match('/^[0-9]+$/', @$_GET['parentid']) || !preg_match('/^[a-z]+$/', @$_GET['parenttype'])) {
    error_response('Invalid parent specifications');
}

$parentid = @$_GET['parentid'];
$parentlinetype = Linetype::load($_GET['parenttype']);

$tablelink = null;

foreach ($parentlinetype->children as $child) {
    if ($child->linetype == $linetype->name) {
        $tablelink = Tablelink::load($child->parent_link);

        break;
    }
}

unlink_record(LINE_ID, $parentid, $tablelink);
