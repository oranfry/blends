<?php
$blend = Blend::load(AUTH_TOKEN, BLEND_NAME);

return [
    'data' => $blend->delete(AUTH_TOKEN, get_query_filters()),
];
