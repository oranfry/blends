<?php
Linetype::load(null, 'token')->delete(
    Blends::login(USERNAME, PASSWORD, true),
    [(object) [
        'field' => 'expired',
        'cmp' => '=',
        'value' => 'yes',
    ]]
);

return [];
