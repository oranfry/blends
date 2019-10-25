<?php
$linetype = Linetype::load(LINETYPE_NAME);
$parenttype = null;
$parentlink = null;
$parentid = null;

if (LINE_ID) {
    $line = @find_lines($linetype, [(object)['field' => 'id', 'value' => LINE_ID]])[0];

    if (!$line) {
        error_response('No such line', 400);
    }

    $line->type = $linetype->name;
    $child_sets = load_children($linetype, $line);
}

if (@$_GET['parentlink']) {
    if (!preg_match('/^[a-z]+$/', $_GET['parentlink'])) {
        error_response('Invalid parent specification (1)');
    }

    $parentlink = $_GET['parentlink'];

    find_parent_linetypes($linetype->name, $children);

    if (!count($children)) {
        error_response('Invalid parent specification (2)');
    }

    foreach ($children as $child) {
        $tablelink = Tablelink::load($child->parent_link);
        foreach ([0, 1] as $side) {
            if (@$_GET[$tablelink->ids[$side]]) {
                if (!preg_match('/^[1-9][0-9]*$/', $_GET[$tablelink->ids[$side]])) {
                    error_response('Invalid parent id');
                }

                $parenttype = $tablelink->ids[$side];
                $parentid = $_GET[$tablelink->ids[$side]];

                break;
            }
        }
    }

    if (!$parenttype) {
        error_response('No recognised parent specified');
    }
}

$suggested_values = $linetype->get_suggested_values();
$hasFileFields = in_array('file', array_map(function ($f) {
    return $f->type;
}, $linetype->fields));

return [
    'linetype' => $linetype,
    'line' => @$line,
    'hasFileFields' => $hasFileFields,
    'suggested_values' => $suggested_values,
    'parentlink' => $parentlink,
    'parenttype' => $parenttype,
    'parentid' => $parentid,
    'child_sets' => @$child_sets,
];
