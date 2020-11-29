<?php
namespace blends\linetype;

class token extends \Linetype
{
    function __construct()
    {
        $this->label = 'Token';
        $this->icon = 'ticket';
        $this->table = 'token';
        $this->showass = ['list'];
        $this->fields = [
            (object) [
                'name' => 'icon',
                'type' => 'icon',
                'fuse' => "'ticket'",
                'derived' => true,
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
            (object) [
                'name' => 'used',
                'type' => 'timestamp',
                'fuse' => "{t}.used",
            ],
            (object) [
                'name' => 'hits',
                'type' => 'number',
                'fuse' => "{t}.hits",
            ],
            (object) [
                'name' => 'expired',
                'type' => 'text',
                'fuse' => "if ({t}.used + interval {t}.ttl second < current_timestamp, 'yes', 'no')",
            ],
        ];
        $this->unfuse_fields = [
            '{t}.token' => ':{t}_token',
            '{t}.ttl' => ':{t}_ttl',
        ];
    }

    function complete($line)
    {
        $line->ttl = $line->ttl ?? 3600;
    }
}