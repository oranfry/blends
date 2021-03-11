<?php
$data = json_decode(file_get_contents('php://input'));
$username = @$data->username;
$password = @$data->password;

if (!$username) {
    error_response('Missing: username');
}

if (!$password) {
    error_response('Missing: username');
}

$token = Blends::login($username, $password);

if (!$token) {
    error_response('Invalid username / password');
}

return [
    'data' => (object) ['token' => $token],
];
