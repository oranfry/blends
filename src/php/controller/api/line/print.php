<?php
$linetype = Linetype::load(AUTH_TOKEN, LINETYPE_NAME);

return [
    'data' => $linetype->print(AUTH_TOKEN, get_query_filters()),
];
