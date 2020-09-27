<?php
$config = Config::get();

foreach (['linetypes', 'blends', 'tables'] as $listname) {
    if (!property_exists($config, $listname)) {
        $config->{$listname} = [];
    }
}

if (!in_array('user', array_keys($config->tables))) {
    error_response('User table not defined');
}

if (!in_array('user', array_keys($config->linetypes))) {
    $config->linetypes['user'] = (object) ['canwrite' => true, 'class' => 'blends\\linetype\\user'];
}

if (!in_array('token', array_keys($config->linetypes))) {
    $config->linetypes['token'] = (object) ['cancreate' => true, 'canwrite' => true, 'candelete' => true, 'class' => 'blends\\linetype\\token'];
}

if (!in_array('users', array_keys($config->blends))) {
    $config->blends['users'] = 'blends\\blend\\users';
}

if (!in_array('tokens', array_keys($config->blends))) {
    $config->blends['tokens'] = 'blends\\blend\\tokens';
}

Db::connect();

const ESC = "\x1b";
const GS = "\x1d";
const NUL = "\x00";

function ff($date, $day = 'Mon')
{
    while (date('D', strtotime($date)) != $day) {
        $date = date_shift($date, '1 day');
    }

    return $date;
}

function make_join($tablelink, $alias, $base_alias, $otherside = 1, $left = true)
{
    $myside = ($otherside + 1) % 2;
    $join = $left ? 'left join' : 'join';
    $dbtable = Config::get()->tables[$tablelink->tables[$otherside]];

    $my_id = ($tablelink->ids[$myside] ? $tablelink->ids[$myside] . '_' : '') . 'id';
    $other_id = ($tablelink->ids[$otherside] ? $tablelink->ids[$otherside] . '_' : '') . 'id';

    return "$join {$tablelink->middle_table} {$alias}_m on {$alias}_m.{$my_id} = {$base_alias}.id left join {$dbtable} {$alias} on {$alias}.id = {$alias}_m.{$other_id}";
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


function get_values($table, $field, $clause = null, $label = null)
{
    $values = [];

    $db_table = @Config::get()->tables[$table];

    if (!$db_table) {
        error_response("Could not resolve {$table} to a database table name");
    }

    $labelselector = $label ? ", {$label}" : '';

    $and = $clause ? "and {$clause}" : '';

    $r = Db::succeed("select `{$field}` {$labelselector} from `{$db_table}` t where `{$field}` is not null and `{$field}` != '' {$and} group by `{$field}` {$labelselector} order by `{$field}`");

    while ($value = mysqli_fetch_row($r)) {
        if ($label) {
            $values[$value[1]] = $value[0];
        } else {
            $values[] = $value[0];
        }
    }

    return $values;
}

function get_file_info($name)
{
    if (preg_match('@/\.\.@', $name) || preg_match('@^\.\.@', $name)) {
        error_response('Bad file path');
    }

    $file = FILES_HOME . '/' . $name;

    if (!file_exists($file)) {
        error_response('No such file');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $content_type = $finfo->file($file);

    return [
        'filedata' => base64_encode(file_get_contents($file)),
        'content_type' => $content_type,
        'filename' => basename($name),
    ];
}

function dir_is_empty($dir)
{
    $handle = opendir($dir);

    if (!$handle) {
        return true;
    }

    while (false !== ($entry = readdir($handle))) {
        if ($entry == '.' || $entry == '..') {
           continue;
        }

        closedir($handle);
        return false;
    }

    closedir($handle);
    return true;
}

function masterlog_check()
{
    $result = Db::succeed('select counter from master_record_lock');

    if (!$result) {
        error_response('Master record lock not set up correctly - table error');
    }

    $count = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $count++;
    }

    if ($count < 1) {
        Db::succeed('insert into master_record_lock values (0)');
    } elseif ($count > 1) {
        error_response('Master record lock not set up correctly - multiple rows');
    }

    $master_record_file = @Config::get()->master_record_file;

    if (!$master_record_file) {
        error_response('Master record file not configured');
    }

    if (!touch($master_record_file) || !is_writable($master_record_file)) {
        error_response('Master record file not writable');
    }
}

function commit($timestamp, $linetype, $data)
{
    if (!count($data)) {
        return;
    }

    Db::succeed('start transaction');
    Db::succeed('select counter from master_record_lock for update');

    $master_record_file = @Config::get()->master_record_file;
    $export = $timestamp . ' ' . $linetype . ' ' . json_encode($data);

    file_put_contents($master_record_file, $export . "\n", FILE_APPEND);

    Db::succeed('update master_record_lock set counter = counter + 1');
    Db::succeed('commit');
}

function n2h($table, $n)
{
    // Generate a sequence secret: php -r 'echo base64_encode(random_bytes(32)) . "\n";'

    $banned = @Config::get()->sequence->banned_chars ?? [];
    $replace = array_fill(0, count($banned), '');
    $sequence_secret = @Config::get()->sequence->secret;
    $table_subs = @Config::get()->sequence->subs[$table] ?? [];

    if (!$sequence_secret) {
        error_response('Sequence Secret not defined');
    }

    if (strlen($sequence_secret) < 8) {
        error_response('Sequence Secret too weak (8-char minimum)');
    }

    if (isset($table_subs[$n])) {
        return $table_subs[$n];
    }

    return strtoupper(substr(str_replace($banned, $replace, base64_encode(hex2bin(hash('sha256', $n . '--' . $table . '--' . $sequence_secret)))), 0, 10));
}
