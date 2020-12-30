<?php
define('LAYOUT', 'download');

if (preg_match('@/\.\.@', FILE) || preg_match('@^\.\.@', FILE)) {
    error_response('Bad file path');
}

return get_file_info(FILE);
