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
                'fuse' => function($record) : string {
                    return 'ticket';
                },
                'derived' => true,
            ],
            (object) [
                'name' => 'token',
                'type' => 'text',
                'fuse' => function($record) : string {
                    return $record->token;
                },
            ],
            (object) [
                'name' => 'ttl',
                'type' => 'number',
                'fuse' => function($record) : int {
                    return $record->ttl;
                },
            ],
            (object) [
                'name' => 'used',
                'type' => 'timestamp',
                'fuse' => function($record) : int {
                    return $record->used;
                },
            ],
            (object) [
                'name' => 'hits',
                'type' => 'number',
                'fuse' => function($record) : int {
                    return $record->hits;
                },
            ],
            (object) [
                'name' => 'expired',
                'type' => 'text',
                'fuse' => function($record) : bool {
                    return strtotime($record->used) + $record->ttl < time();
                },
            ],
        ];
        $this->unfuse_fields = [
            '{t}.token' => function($line) : string {
                return $line->token;
            },
            '{t}.ttl' => function($line) : int {
                return $line->ttl;
            },
            '{t}.used' => function($line) : int {
                return time();
            },
            '{t}.hits' => function($line) : int {
                return @$line->hits ?? 0;
            },
        ];
    }

    function complete($line)
    {
        $line->ttl = @$line->ttl ?? 3600;
        $line->hits = @$line->hits ?? 0;
    }
}
