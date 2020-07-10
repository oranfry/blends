<?php
namespace blends\linetype;

class token extends \Linetype
{
    function __construct()
    {
        $this->label = 'Token';
        $this->icon = 'doc';
        $this->table = 'token';
        $this->showass = ['list'];
        $this->fields = [
            (object) [
                'name' => 'icon',
                'type' => 'icon',
                'fuse' => "'person'",
                'derived' => true,
            ],
            (object) [
                'name' => 'username',
                'type' => 'text',
                'fuse' => '{t}.username',
            ],
            (object) [
                'name' => 'token',
                'type' => 'text',
                'fuse' => "{t}.token",
            ],
            (object) [
                'name' => 'ttl',
                'type' => 'number',
                'fuse' => "{t}.ttl",
            ],
        ];
        $this->unfuse_fields = [
            '{t}.username' => ':{t}_username',
            '{t}.token' => ':{t}_token',
            '{t}.ttl' => ':{t}_ttl',
        ];
    }

    function complete($line)
    {
        $line->ttl = $line->ttl ?? 3600;
    }
}