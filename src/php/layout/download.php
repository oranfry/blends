<?php
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo $filedata;
