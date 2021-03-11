<?php

if (!Blends::verify_token(AUTH_TOKEN)) {
    error_response('Invalid / Expired Token');
}

return [
    'data' => (object) [],
];
