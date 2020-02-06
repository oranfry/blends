<?php
define('LAYOUT', 'download');

if (preg_match('@/\.\.@', FILE) || preg_match('@^\.\.@', FILE)) {
    error_response('Bad file path');
}

$file = FILES_HOME . '/' . FILE;

if (!file_exists($file)) {
    error_response('No such file');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$content_type = $finfo->file($file);

return [
    'filedata' => file_get_contents($file),
    'content_type' => $content_type,
    'filename' => basename(FILE),
];