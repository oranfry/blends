<?php
$linetype = Linetype::load(LINETYPE_NAME);

return [
    'data' => $linetype->get_suggested_values(),
];
