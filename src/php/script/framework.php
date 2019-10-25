<?php
if (!defined('BLENDS_HOME')) {
    echo "Please define BLENDS_HOME\n";
    die();
}

require BLENDS_HOME . '/src/php/script/lib.php';

if (!preg_match('/^[a-z]*$/', @$_GET['context'])) {
    error_response("Invalid context");
}

define('CONTEXT', @$_GET['context'] ?: 'default');
load_config();
define('PLUGINS', Config::get()->plugins);
set_highlight(@Config::get()->highlight ?: REFCOL);
Db::connect();
session_start();
route();
define('BACK', @$_GET['back'] ? base64_decode($_GET['back']) : null);

$viewdata = do_controller();

if (!defined('LAYOUT')) {
    define('LAYOUT', 'main');
}

do_layout($viewdata);
