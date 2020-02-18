<?php
use contextvariableset\Daterange;
use contextvariableset\Hidden;
use contextvariableset\Repeater;
use contextvariableset\Value;
use contextvariableset\Filter;

define('REFCOL', 'd8b0b0');
define('MAX_COLUMN_WIDTH', 25);

const ESC = "\x1b";
const GS = "\x1d";
const NUL = "\x00";

spl_autoload_register(function ($class_name) {
    $class_translated = str_replace('\\', '/', $class_name);
    $file = BLENDS_HOME . "/src/php/class/{$class_translated}.php";

    if (file_exists($file)) {
        require $file;

        return;
    }

    foreach (PLUGINS as $plugin) {
        $file = BLENDS_HOME . "/plugins/{$plugin}/src/php/class/{$class_translated}.php";

        if (file_exists($file)) {
            require $file;

            return;
        }
    }

    if (preg_match(',^(linetype|blend)\\\\([a-z]+)$,', $class_name, $groups)) {
        error_response("Could not load {$groups[1]} {$groups[2]}", 500);
    }

    error_response("Could not autoload class {$class_name}", 500);
});

function load_config()
{
    $config = require BLENDS_HOME . '/config.php';

    Config::set($config);
}

function error_response($message, $code = 400)
{
    http_response_code($code);

    $error = $message;
    $layout = defined('LAYOUT') ? LAYOUT : 'main';

    require BLENDS_HOME . '/src/php/layout/' . $layout . '-error.php';
    die();
}

function latest($type)
{
    $data = json_decode(file_get_contents(BLENDS_HOME . '/build/latest.json'));

    return @$data->{$type} ?: 0;
}

function route()
{
    $path = strtok($_SERVER["REQUEST_URI"], '?');

    if (preg_match(',^/$,', $path, $groups)) {
        require BLENDS_HOME . '/src/php/script/login.php';

        die();
    }

    if (@$_SESSION["AUTH"] != Config::get()->password && @getallheaders()['X-Auth'] != Config::get()->password) {
        header("Location: /");

        die();
    }

    if (preg_match(',^/change-(instances|context)$,', $path, $groups)) {
        require BLENDS_HOME . "/src/php/script/change-{$groups[1]}.php";

        die();
    }

    if (!Router::match($path)) {
        error_response('Not found', 404);
    }

    if (!defined('PAGE') || !file_exists(BLENDS_HOME . '/src/php/controller/' . PAGE . '.php')) {
        error_response('Not set up', 500);
    }
}

function search_path($type, $name)
{
    if (preg_match('/^([a-z]+) as ([a-z]+)$/', $name, $groups)) {
        $name = $groups[2];
    } elseif (preg_match('/^([a-z]+)$/', $name, $groups)) {
        $name = $groups[1];
    } else {
        error_response("Invalid {$type} name {$name}");
    }

    $file = null;

    foreach (PLUGINS as $plugin) {
        $try = BLENDS_HOME . '/plugins/' . $plugin . '/' . $type . '/' . $name . '.php';

        if (file_exists($try)) {
            $file = $try;

            break;
        }
    }

    return $file;
}

function map_objects($objectArray, $property)
{
    return array_map(
        function ($o) use ($property) {
            return $o->{$property};
        },
        $objectArray
    );
}

function filter_objects($objectArray, $property, $cmp = 'exists', $value = null)
{
    return array_values(
        array_filter(
            $objectArray,
            function ($o) use ($property, $cmp, $value) {
                if (!property_exists($o, $property)) {
                    if ($cmp == 'notexists') {
                        return true;
                    }

                    if ($cmp == 'exists') {
                        return false;
                    }

                    if ($cmp == 'is') {
                        return !$value;
                    }

                    if ($cmp == 'not') {
                        return (bool) $value;
                    }

                    if ($cmp == 'in') {
                        return in_array('', $value) || in_array(null, $value);
                    }

                    if ($cmp == 'notin') {
                        return !in_array('', $value) || in_array(null, $value);
                    }

                    return false; //unsupported comparison
                }

                if ($cmp == 'exists') {
                    return true;
                }

                if ($cmp == 'notexists') {
                    return false;
                }

                if ($cmp == 'is') {
                    return $o->{$property} == $value;
                }

                if ($cmp == 'not') {
                    return $o->{$property} != $value;
                }

                if ($cmp == 'in') {
                    return in_array($o->{$property}, $value);
                }

                if ($cmp == 'notin') {
                    return !in_array($o->{$property}, $value);
                }

                return false; //unsupported comparison
            }
        )
    );
}

function get_current_filters($fields)
{
    $filters = [];

    $daterange = new Daterange('daterange');
    $repeater = new Repeater(BLEND_NAME . "_repeater");
    $datefield = null;

    foreach ($fields as $field) {
        if (!@$field->main) {
            continue;
        }

        if ($field->type == 'date') {
            $datefield = $field;

            if ($daterange->from) {
                $filters[] = (object)[
                    'field' => 'date',
                    'value' => $daterange->from,
                    'cmp' => '>=',
                ];
            }

            if ($daterange->to) {
                $filters[] = (object)[
                    'field' => 'date',
                    'value' => $daterange->to,
                    'cmp' => '<=',
                ];
            }
        } else {
            $csv = new Value(BLEND_NAME . "_{$field->name}");
            if ($csv->value) {
                $filters[] = (object) [
                    'field' => $field->name,
                    'value' => $csv->value,
                    'cmp' => ($field->type == 'number' ? '=' : 'like'),
                ];
            }
        }
    }

    if ($datefield && $repeater->period) {
        $filters = array_merge($filters, get_repeater_filters($repeater, $datefield->name));
    }

    return array_merge($filters, get_adhoc_filters());
}

function get_past_filters($fields)
{
    $filters = [];
    $daterange = new Daterange('daterange');
    $repeater = new Repeater(BLEND_NAME . "_repeater");
    $datefield = null;

    foreach ($fields as $field) {
        if ($field->type == 'date') {
            $datefield = $field;

            $filters[] = (object)[
                'field' => 'date',
                'value' => $daterange->from,
                'cmp' => '<',
            ];
        } else {
            $csv = new Value(BLEND_NAME . "_{$field->name}");
            if ($csv->value) {
                $filters[] = (object) [
                    'field' => $field->name,
                    'value' => $csv->value,
                    'cmp' => ($field->type == 'number' ? '=' : 'like'),
                ];
            }
        }
    }

    if ($datefield && $repeater->period) {
        $filters = array_merge($filters, get_repeater_filters($repeater, $datefield->name));
    }

    return array_merge($filters, get_adhoc_filters());
}

function get_adhoc_filters()
{
    $filters = [];
    $adhocfilters = ContextVariableSet::get("adhocfilters");

    if ($adhocfilters->value) {
        foreach (explode(',', $adhocfilters->value) as $filterid) {
            $filter = ContextVariableSet::get($filterid);
            // var_die($filterid);

            $filters[] = (object) [
                'field' => $filter->field,
                'value' => $filter->value,
                'cmp' => $filter->cmp,
            ];
        }
    }

    return $filters;
}

function get_repeater_filters($repeater, $datefield_name)
{
    return [(object) [
        'cmp' => 'custom',
        'field' => $datefield_name,
        'php' => function($value) use ($repeater) {
            return false;
        },
        'sql' => function($field_name) use ($repeater) {
            $period = $repeater->period;

            if ($period == 'day') {
                $n = $repeater->n;
                $pegdate = $repeater->pegdate;
                $fastforward = $repeater->ff;
                $offset = '';
            } elseif ($period == 'month') {
                $day = $repeater->day;
                $round = $repeater->round;
                $fastforward = $repeater->ff;
                $offset = $repeater->offset;
            } elseif ($period == 'year') {
                $month = $repeater->month;
                $day = $repeater->day;
                $round = $repeater->round;
                $fastforward = $repeater->ff;
                $offset = $repeater->offset;
            } else {
                error_response("Invalid period");
            }

            if ($offset) {
                if (!preg_match('/^([+-][1-9][0-9]*) (day|month|year)$/', $offset, $groups)) {
                    error_response('Invalid offset');
                }

                $offsetMagnitude = intval(preg_replace('/[+-]/', '', $groups[1]));
                $offsetSign = preg_match('/-/', $groups[1]) ? '-' : '+';
                $offsetPeriod = $groups[2];
            }

            if (!$offset || !$offsetMagnitude) {
                $offsetR = '';
            } elseif ($offsetSign == '-') {
                $offsetR = "+ interval {$offsetMagnitude} {$offsetPeriod}";
            } else {
                $offsetR = "- interval {$offsetMagnitude} {$offsetPeriod}";
            }

            if ($offsetR) {
                $field = "{$field_name} {$offsetR}";
            } else {
                $field = "{$field_name}";
            }

            $whereClauses = [];

            if ($period == 'day') {
                $expr = "(to_days({$field}) - to_days('{$pegdate}')) % {$n}";

                if ($fastforward) {
                    $whereClauses[] = "{$expr} < 7";
                } else {
                    $whereClauses[] = "{$expr} = 0";
                }
            } else {
                if ($round) {
                    $left = "least(day({$field}), day(last_day({$field})))";
                    $right = "least({$day}, day(last_day({$field})))";
                } else {
                    $left = "day({$field})";
                    $right = "{$day}";
                }

                if ($fastforward) {
                    $whereClauses[] = "{$left} >= {$right} and {$left} < {$right} + 7";

                } else {
                    $whereClauses[] = "{$left} = {$right}";
                }

                if ($period == 'year') {
                    $whereClauses[] = "month({$field}) = {$month}";
                }
            }

            if ($fastforward) {
                $whereClauses[] = "dayofweek({$field}) = {$fastforward}";
            }

            return implode(' and ', $whereClauses);
        },
    ]];
}

function filter_filters($filters, $linetype, $fields)
{
    $r = [];

    foreach ($filters as $filter) {
        $linetype_field = @array_values(array_filter($linetype->fields, function ($v) use ($filter) {
            return $v->name == $filter->field;
        }))[0];
        $field = @array_values(array_filter($fields, function ($v) use ($filter) {
            return $v->name == $filter->field;
        }))[0];

        if ($linetype_field) {
            $r[] = $filter;
        } elseif (
            $filter->cmp == '=' && (
                is_array($filter->value) && !in_array($field->default, $filter->value)
                ||
                !is_array($filter->value) && $field->default != $filter->value
            )
            ||
            $filter->cmp == 'like' && !preg_match('/' . str_replace('%', '.*', $filter->value) . '/i', $field->default)
            ||
            $filter->cmp == 'custom' && !($filter->cmp->php)($field->default)
        ) {
            return false;
        }
    }

    return $r;
}


function date_shift($date, $offset)
{
    return date('Y-m-d', strtotime($offset, strtotime($date)));
}

function ff($date, $day = 'Mon')
{
    while (date('D', strtotime($date)) != $day) {
        $date = date_shift($date, '1 day');
    }

    return $date;
}

function find_lines(
    $linetype,
    $filters = null,
    $parentIdField = null,
    $parentId = null,
    $parentLink = null,
    $customClause = null
) {
    $idField = @$linetype->id_field ?: 'id';

    list($joinClauses, $orderbys, $filterClauses, $parentClauses, $linetypeClauses, , $idClauses, $parentTypeSelectors) = lines_prepare_search($linetype, $filters, $parentIdField, $parentId, $parentLink);

    $whereClauses = array_merge(
        $linetype->clause ? ["({$linetype->clause})"] : [],
        $filterClauses,
        $parentClauses,
        $customClause ? [$customClause] : [],
        $linetypeClauses
    );

    $fieldsClause = array_merge(
        ['t.id id'],
        $idClauses,
        array_map(
            function ($v) {
                return "{$v->fuse} `{$v->name}`";
            },
            array_filter($linetype->fields, function($v){
                return $v->type != 'file';
            })
        ),
        $parentTypeSelectors ? ['concat(' . implode(', ', $parentTypeSelectors) . ') parenttype'] : []
    );

    $joinClause = implode(' ', $joinClauses);
    $orderByClause = implode(', ', $orderbys);
    $fieldsClause = implode(', ', $fieldsClause);
    $whereClause = count($whereClauses) ? implode(' and ', $whereClauses) : '1';

    $linetype_db_table = Table::load($linetype->table)->table;

    $q = "select {$fieldsClause} from `{$linetype_db_table}` t {$joinClause} where {$whereClause} order by {$orderByClause}";

    $r = Db::succeed($q);

    if (!$r) {
        error_response(Db::error() . "\n\n$q\n\nlinetype: \"{$linetype->name}\"", 500);
    }

    $lines = [];

    while ($row = mysqli_fetch_assoc($r)) {
        $line = (object) $row;

        $line->type = $linetype->name;
        $line->parenttype = @$row['parenttype'];
        $line->parentid = @$row[$row['parenttype'] . '_id'];

        if ($parentId) {
            $line->parent = $parentId;
            $line->parent_link = $parentLink;
        }

        $lines[] = $line;
    }

    return $lines;
}

function lines_prepare_search(
    $linetype,
    $filters = null,
    $parentIdField = null,
    $parentId = null,
    $parentLink = null,
    $customClause = null
) {
    $filters = $filters ?? [];
    $idField = @$linetype->id_field ?: 'id';
    $reverse = $linetype->links_reversed;

    $parentLinks = [];
    $parentTypeSelectors = [];
    $parentLinetypes = find_parent_linetypes($linetype->name, $children);

    foreach ($parentLinetypes as $i => $parentLinetype) {
        $parentLinks[] = $children[$i]->parent_link;
        $reverse[] = $children[$i]->parent_link;
        $parentlink = Tablelink::load($children[$i]->parent_link);
        $parentTypeSelectors[] = "if({$parentlink->ids[0]}.id, '{$parentlink->name}', '')";
    }

    $orderbys = ["t.{$idField}"];
    $filterClauses = [];

    foreach ($filters as $filter) {
        $cmp = @$filter->cmp ?: '=';

        if ($cmp == 'custom') {
            $field = @filter_objects($linetype->fields, 'name', 'is', $filter->field)[0];

            $filterClauses[] = ($filter->sql)($field->fuse);
            continue;
        }

        if ($filter->field == 'id') {
            $expression = 't.id';
        } else {
            $field = @filter_objects($linetype->fields, 'name', 'is', $filter->field)[0];

            if (!$field) {
                error_response("Cant find fuse expression for filter field {$linetype->name} {$filter->field}\n\n" . var_export($linetype->fields, 1));
            }

            $expression = $field->fuse;
        }

        if (is_array($filter->value)) {
            if ($cmp != '=') {
                error_response('Array filter values should be used with =', 500);
            }

            $cmp = 'in';
            $value = "(" . implode(",", array_map(function ($v) {
                return "'{$v}'";
            }, $filter->value)) . ")";
        } else {
            $value = "'{$filter->value}'";
        }

        $filterClauses[] = "{$expression} {$cmp} {$value}";
    }

    $linetype_db_table = Table::load($linetype->table)->table;

    $joinClauses = [];
    $idClauses = [];
    $joinTables = ["{$linetype_db_table} t"];
    $parentClauses = [];

    $_tablelinks = array_merge(@$linetype->links ?: [], $parentLinks);

    for ($i = count($_tablelinks) - 1; $i >= 0; $i--) {
        $_link = $_tablelinks[$i];

        $tablelink = Tablelink::load($_link);
        $leftJoin = !property_exists($linetype, 'links_required') || !in_array($_link, $linetype->links_required);
        $side = 1;

        if (in_array($_link, $reverse)) {
            $side = 0;
            array_splice($reverse, array_search($_link, $reverse), 1);
        }

        list($_joinClause, $_fields, $_groupbys, $_joinTable, $_idClause) = generate_link_join_clause($tablelink, $tablelink->ids[$side], 't', $side, $leftJoin);

        $joinClauses[] = $_joinClause;
        $idClauses[] = $_idClause;
        $joinTables[] = $_joinTable;

        if ($parentId && $parentLink == $_link) {
            $parentClauses[] = "{$tablelink->ids[$side]}.{$parentIdField} = '{$parentId}'";
        }
    }

    $inlinejoins = get_inline_joins(@$linetype->inlinelinks ?? []);

    foreach ($inlinejoins as $inlinejoin) {
        list($_joinClause, $_fields, $_groupbys, $_joinTable, $_idClause) = $inlinejoin;

        $joinClauses[] = $_joinClause;
        $idClauses[] = $_idClause;
        $joinTables[] = $_joinTable;
    }

    $linetypeClauses = $linetype->clause ? ["({$linetype->clause})"] : [];

    return [$joinClauses, $orderbys, $filterClauses, $parentClauses, $linetypeClauses, $joinTables, $idClauses, $parentTypeSelectors,];
}

function get_inline_joins($links, $basealias = 't')
{
    $joins = [];

    foreach ($links as $link) {
        $childlinetype = Linetype::load($link->linetype);
        $tablelink = Tablelink::load($link->tablelink);
        $side = @$link->reverse ? 0 : 1;
        $leftJoin = @$link->required ? false : true;

        $joins[] = generate_link_join_clause($tablelink, $tablelink->ids[$side], $basealias, $side, $leftJoin);
        $joins = array_merge($joins, get_inline_joins(@$childlinetype->inlinelinks ?? [], $tablelink->ids[$side]));
    }

    return $joins;
}

function collect_inline_links($linetype)
{
    $links = [];

    foreach (@Linetype::load($linetype)->inlinelinks ?: [] as $link) {
        $link->parenttype = $linetype;
        $links[] = $link;
        $links = array_merge($links, collect_inline_links($link->linetype));
    }

    return $links;
}

function summarise_lines(
    $linetype,
    $filters = [],
    $parentIdField = null,
    $parentId = null,
    $parentLink = null,
    $customClause = null
) {
    $idField = @$linetype->id_field ?: 'id';

    list($joinClauses, $orderbys, $filterClauses, $parentClauses, $linetypeClauses) = lines_prepare_search($linetype, $filters, $parentIdField, $parentId, $parentLink);

    $whereClauses = array_merge(
        $linetype->clause ? ["({$linetype->clause})"] : [],
        $filterClauses,
        $parentClauses,
        $customClause ? [$customClause] : [],
        $linetypeClauses
    );

    $fields = [];

    foreach ($linetype->fields as $field) {
        if (@$field->summary == 'sum') {
            if (!@$field->fuse) {
                die("Fuse expression missing for {$field->name}");
            }

            $fields[] = "sum({$field->fuse}) {$field->name}";
        }
    }

    if (!count($fields)) {
        return [];
    }

    $joinClause = implode(' ', $joinClauses);
    $orderByClause = implode(', ', $orderbys);
    $fieldsClause = implode(', ', $fields);
    $whereClause = count($whereClauses) ? implode(' and ', $whereClauses) : '1';

    $linetype_db_table = Table::load($linetype->table)->table;

    $q = "select {$fieldsClause} from `{$linetype_db_table}` t {$joinClause} where {$whereClause} order by {$orderByClause}";
    $r = DB::succeed($q);

    if (!$r) {
        error_response(Db::error() . "\n\n$q\n\nlinetype: \"{$linetype->name}\"", 500);
    }

    return mysqli_fetch_assoc($r) ?: [];
}

function computed_field_value($record, $expression)
{
    extract((array)$record);

    return eval("return {$expression};");
}

function var_die($var)
{
    $function = implode('_', ['var', 'dump']);

    $function($var);
    die('-');
}

function set_highlight($hex)
{
    list($h) = hexToHsl($hex);
    list(, $s, $l) = hexToHsl(REFCOL);

    define('HIGHLIGHT', hslToHex([$h, $s, $l]));
}

function hexToHsl($hex)
{
    $hex = array($hex[0].$hex[1], $hex[2].$hex[3], $hex[4].$hex[5]);
    $rgb = array_map(function ($part) {
        return hexdec($part) / 255;
    }, $hex);

    $max = max($rgb);
    $min = min($rgb);

    $l = ($max + $min) / 2;

    if ($max == $min) {
        $h = $s = 0;
    } else {
        $diff = $max - $min;
        $s = $l > 0.5 ? $diff / (2 - $max - $min) : $diff / ($max + $min);

        switch ($max) {
            case $rgb[0]:
                $h = ($rgb[1] - $rgb[2]) / $diff + ($rgb[1] < $rgb[2] ? 6 : 0);
                break;
            case $rgb[1]:
                $h = ($rgb[2] - $rgb[0]) / $diff + 2;
                break;
            case $rgb[2]:
                $h = ($rgb[0] - $rgb[1]) / $diff + 4;
                break;
        }

        $h /= 6;
    }

    return array($h, $s, $l);
}

function hslToHex($hsl)
{
    list($h, $s, $l) = $hsl;

    if ($s == 0) {
        $r = $g = $b = 1;
    } else {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $r = hue2rgb($p, $q, $h + 1/3);
        $g = hue2rgb($p, $q, $h);
        $b = hue2rgb($p, $q, $h - 1/3);
    }

    return rgb2hex($r) . rgb2hex($g) . rgb2hex($b);
}

function hue2rgb($p, $q, $t)
{
    if ($t < 0) {
        $t += 1;
    }
    if ($t > 1) {
        $t -= 1;
    }
    if ($t < 1/6) {
        return $p + ($q - $p) * 6 * $t;
    }
    if ($t < 1/2) {
        return $q;
    }
    if ($t < 2/3) {
        return $p + ($q - $p) * (2/3 - $t) * 6;
    }

    return $p;
}

function rgb2hex($rgb)
{
    return str_pad(dechex($rgb * 255), 2, '0', STR_PAD_LEFT);
}

function adjustBrightness($hex, $steps)
{
    $steps = max(-255, min(255, $steps));
    preg_match('/^(#)/', $hex, $groups);
    $hash = @$groups[1] ?: '';
    $hex = str_replace('#', '', $hex);

    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex, 0, 1), 2).str_repeat(substr($hex, 1, 1), 2).str_repeat(substr($hex, 2, 1), 2);
    }

    $color_parts = str_split($hex, 2);
    $return = $hash;

    foreach ($color_parts as $color) {
        $color = hexdec($color);
        $color = max(0, min(255, $color + $steps));
        $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT);
    }

    return $return;
}

function apply_filters()
{
    $adhocfilters = new Hidden(BLEND_NAME . "_filters");
    ContextVariableSet::put('adhocfilters', $adhocfilters);

    if ($adhocfilters->value) {
        foreach (explode(',', $adhocfilters->value) as $filterid) {
            ContextVariableSet::put($filterid, new Filter($filterid));
        }
    }

    $repeater = new Repeater(BLEND_NAME . "_repeater");
    ContextVariableSet::put('repeater', $repeater);
}

function load_children($linetype, $parent)
{
    $child_sets = [];

    foreach ($linetype->children as $child) {
        $child_sets[$child->label] = load_childset($linetype, $parent, $child);
    }

    return $child_sets;
}

function load_childset($linetype, $parent, $descriptor)
{
    $idField = @$linetype->id_field ?: 'id';
    $id = $parent->{$idField};

    $child_linetype = Linetype::load(@$descriptor->linetype);
    $fields = $child_linetype->fields;

    $childset = (object) [];
    $childset->lines = find_lines($child_linetype, null, $idField, $id, $descriptor->parent_link);

    $summary = (object) [];
    $hasSummaries = array_reduce($fields, function ($c, $v) {
        return $c || @$v->summary == 'sum';
    }, false);

    if ($hasSummaries) {
        foreach ($childset->lines as $line) {
            foreach ($fields as $field) {
                if (@$field->summary != 'sum') {
                    continue;
                }

                if (!@$summary->{$field->name}) {
                    $summary->{$field->name} = '0.00';
                }

                $summary->{$field->name} = bcadd($summary->{$field->name}, $line->{$field->name}, @$field->dp ?: 0);
            }
        }

        $childset->summary = $summary;
    }

    return $childset;
}

function generate_link_join_clause(
    $tablelink,
    $alias,
    $base_alias,
    $otherside = 1,
    $left = true
) {
    $myside = ($otherside + 1) % 2;
    $join = $left ? 'left join' : 'join';
    $jointable = Table::load($tablelink->tables[$otherside]);
    $join_db_table = $jointable->table;

    $q =
        "$join
            {$tablelink->middle_table} {$alias}_m
        on
            {$alias}_m.{$tablelink->ids[$myside]}_id = {$base_alias}.id
        left join
            {$join_db_table} {$alias}
        on
            {$alias}.id = {$alias}_m.{$tablelink->ids[$otherside]}_id
        ";
    $prefix = $tablelink->ids[$otherside];

    $fields = ["{$alias}.id {$prefix}_id"];
    $groupby = ["{$prefix}_id"];

    foreach ($jointable->additional_fields as $field) {
        $fields[] = "{$alias}.{$field->name} {$tablelink->ids[$otherside]}_{$field->name}";
        $groupby[] = "{$tablelink->ids[$otherside]}_{$field->name}";
    }

    return [
        trim(preg_replace('/\s+/', ' ', $q)),
        $fields,
        $groupby,
        "{$join_db_table} {$alias}",
        "{$alias}.id {$alias}_id"
    ];
}

function print_line($linetype, $line, $child_sets)
{
    if (!method_exists($linetype, 'astext')) {
        return;
    }

    if (!defined('PRINTER_FILE')) {
        return; // lets not and say we did - for testing!
    }

    $logofile = @Config::get()->logofile;

    $printout = '';
    $printout .= ESC."@"; // Reset to defaults

    if ($logofile && file_exists($logofile)) {
        $printout .= file_get_contents($logofile);
        $printout .= "\n\n";
    }

    $printout .= wordwrap($linetype->astext($line, $child_sets), 42, "\n", true);
    $printout .= ESC."d".chr(4);
    $printout .= GS."V\x41".chr(3);

    file_put_contents(PRINTER_FILE, $printout, FILE_APPEND);
}

// TODO: remove this (again)
function get_sku_meta()
{
    $r = Db::succeed("select * from record_skumeta order by sku");
    $metas = [];

    while ($meta = mysqli_fetch_assoc($r)) {
        $metas[$meta['sku']] = (object) $meta;
    }

    return $metas;
}


function delete_record($table_name, $id)
{
    $assocs = find_related_records($table_name, $id);

    foreach ($assocs as $assoc) {
        $assoc_table = Table::load($assoc->table);
        $id_field = @$assoc_table->id_field ?: 'id';
        $assoc_db_table = $assoc_table->table;

        Db::succeed("delete from `{$assoc->middle_table}` where {$assoc->left}_id = $id and {$assoc->right}_id = {$assoc->id}");
        Db::succeed("delete from `{$assoc_db_table}` where {$id_field} = '{$assoc->id}'");
    }

    $table = Table::load($table_name);
    $id_field = @$table->id_field ?: 'id';
    $db_table = $table->table;

    Db::succeed("delete from `{$db_table}` where {$id_field} = '{$id}'");
}

function unlink_record($id, $parentid, $tablelink)
{
    $q = "delete from {$tablelink->middle_table} where {$tablelink->ids[0]}_id = {$parentid} and {$tablelink->ids[1]}_id = {$id}";

    $result = Db::succeed($q);
}

function addlink($type, $group, $groupfield, $defaultgroup, $parent_query, $prepop = [])
{
    $url = "/{$type}";

    $query = $prepop;

    if ($groupfield) {
        $query[$groupfield] = $group ?: @$defaultgroup;
    }

    $query['back'] = base64_encode($_SERVER['REQUEST_URI']);

    $url .= '?' . http_build_query($query) . (@$parent_query ? '&' . $parent_query : '');

    return $url;
}

function find_related_records($table, $id)
{
    $assocs = [];

    foreach (get_all_tablelinks() as $tablelink_name) {
        $tablelink = Tablelink::load($tablelink_name);

        foreach (array_merge([0], ($tablelink->type == "oneone" ? [1] : [])) as $side) {
            if ($tablelink->tables[$side] != $table) {
                continue;
            }

            $otherside = ($side + 1) % 2;
            $left_table = $tablelink->tables[$side];
            $right_table = $tablelink->tables[$otherside];
            $middle_table = $tablelink->middle_table;
            $left_id = $tablelink->ids[$side];
            $right_id = $tablelink->ids[$otherside];
            $left_db_table = Table::load($left_table)->table;

            list($joinClause, $fields, $groupby) = generate_link_join_clause($tablelink, 'tt', 't', $otherside, false);
            $fieldsClause = implode(', ', $fields);
            $groupbyClause = implode(', ', $groupby);

            $r = Db::succeed("select tt.id, {$fieldsClause} from `{$left_db_table}` t {$joinClause} where t.id = {$id} group by {$groupbyClause}");

            while ($row = mysqli_fetch_assoc($r)) {
                $assocs[] = (object)[
                    'table' => $right_table,
                    'middle_table' => $middle_table,
                    'id' => $row['id'],
                    'left' => $left_id,
                    'right' => $right_id,
                ];
            }
        }
    }

    return $assocs;
}


function get_all_tablelinks()
{
    $tablelinks = [];
    $seen = [];

    foreach (Config::get()->blends as $_blend_name) {
        foreach (Blend::load($_blend_name)->linetypes as $_linetype_name) {
            if (@$seen[$_linetype_name]) {
                continue;
            }

            $seen[$_linetype_name] = true;
            $_linetype = Linetype::load($_linetype_name);

            foreach ($_linetype->links as $_link) {
                if (!in_array($_link, $tablelinks)) {
                    $tablelinks[] = $_link;
                }
            }

            foreach ($_linetype->children as $_child) {
                if (!in_array($_child->parent_link, $tablelinks)) {
                    $tablelinks[] = $_child->parent_link;
                }
            }
        }
    }

    return $tablelinks;
}

function find_parent_linetypes($linetype_name, &$child_descriptors)
{
    $parents = [];
    $child_descriptors = [];
    $seen = [];

    foreach (Config::get()->blends as $_blend_name) {
        foreach (Blend::load($_blend_name)->linetypes as $_linetype_name) {
            if (@$seen[$_linetype_name]) {
                continue;
            }

            $seen[$_linetype_name] = true;
            $_linetype = Linetype::load($_linetype_name);
            $mes = @filter_objects($_linetype->children, 'linetype', 'is', $linetype_name);

            foreach ($mes as $me) {
                $parents[] = $_linetype;
                $child_descriptors[] = $me;
            }
        }
    }

    return $parents;
}

function get_values($table, $field)
{
    $values = [];

    $db_table = Table::load($table)->table;

    $r = Db::succeed("select `{$field}` from `{$db_table}` t where `{$field}` is not null and `{$field}` != '' group by `{$field}` order by `{$field}`");

    while ($value = mysqli_fetch_row($r)) {
        $values[] = $value[0];
    }

    return $values;
}

function do_controller()
{
    return require BLENDS_HOME . '/src/php/controller/' . PAGE . '.php';
}

function do_layout($viewdata)
{
    extract((array) $viewdata);

    require BLENDS_HOME . '/src/php/layout/' . LAYOUT . '.php';
}

function get_repeater_dates($linetype, $repeater)
{
    $linetype_table = Table::load($linetype->table);

    foreach ($linetype->fields as $field) {
        if ($field->type == 'date') {
            $datefield = $field;
        }
    }

    $repeater_filters = get_repeater_filters($repeater, $datefield->name);
    $daterange = new Daterange('daterange');

    $whereClauses = [];

    foreach ($repeater_filters as $filter) {
        $field = @filter_objects($linetype->fields, 'name', 'is', $filter->field)[0];
        $whereClauses[] = ($filter->sql)($field->fuse);
    }

    if ($daterange->from) {
        $whereClauses[] = "date >= '{$daterange->from}'";
    }

    if ($daterange->to) {
        $whereClauses[] = "date <= '{$daterange->to}'";
    }

    $whereClauses = implode(' and ', $whereClauses);
    $query = "select date from internal_date t where {$whereClauses}";
    $r = Db::succeed($query);
    $values = [];

    while ($value = mysqli_fetch_row($r)) {
        $values[] = $value[0];
    }

    return $values;
}
