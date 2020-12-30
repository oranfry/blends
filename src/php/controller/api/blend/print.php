<?php
$blend = Blend::load(AUTH_TOKEN, BLEND_NAME);

return [
    'data' => $blend->print(AUTH_TOKEN, get_query_filters()),
];
