<?php
$blend = Blend::load(AUTH_TOKEN, BLEND_NAME);

return [
    'data' => $blend->summary(AUTH_TOKEN, get_query_filters()),
];
