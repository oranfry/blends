<?php
$linetype = Linetype::load(AUTH_TOKEN, LINETYPE_NAME);

return [
    'data' => $linetype->get_suggested_values(AUTH_TOKEN),
];
