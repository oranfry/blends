<?php
$blend = Blend::load(AUTH_TOKEN, BLEND_NAME);
$data = json_decode(file_get_contents('php://input'));

return [
    'data' => $blend->update(AUTH_TOKEN, get_query_filters(), $data),
];
