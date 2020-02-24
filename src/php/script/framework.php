<?php
if (!defined('BLENDS_HOME')) {
    echo "Please define BLENDS_HOME\n";
    die();
}

require BLENDS_HOME . '/src/php/script/lib.php';

load_config();
define('PLUGINS', Config::get()->plugins);
set_highlight(@Config::get()->highlight ?: REFCOL);
Db::connect();
route();
define('BACK', @$_GET['back'] ? base64_decode($_GET['back']) : null);

$viewdata = do_controller();

if (!defined('LAYOUT')) {
    define('LAYOUT', 'main');
}

do_layout($viewdata);
