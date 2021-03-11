<?php
$linetype = Linetype::load(AUTH_TOKEN, LINETYPE_NAME);

return [
    'data' => $linetype->delete(AUTH_TOKEN, get_query_filters()),
];
