<?php
$blend = Blend::load(AUTH_TOKEN, BLEND_NAME);

$result = $blend->search(AUTH_TOKEN, get_query_filters());

if ($result === false) {
    error_response('Invalid / expired token', 400, ['invalid_token' => true]);
}

return [
    'data' => $result,
];
