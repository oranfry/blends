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
            'icon' => function($records) : string {
                return 'ticket';
            },
            'token' => function($records) : string {
                return $records['/']->token;
            },
            'ttl' => function($records) : int {
                return $records['/']->ttl;
            },
            'used' => function($records) : int {
                if (!is_int($records['/']->used)) {
                    var_dump($record);
                    die();
                }
                return $records['/']->used;
            },
            'hits' => function($records) : int {
                return $records['/']->hits;
            },
            'expired' => function($records) : bool {
                return strtotime($records['/']->used) + $records['/']->ttl < time();
            },
        ];
        $this->unfuse_fields = [
            'token' => function($line, $oldline) : string {
                return $line->token;
            },
            'ttl' => function($line, $oldline) : int {
                return $line->ttl;
            },
            'used' => function($line, $oldline) : int {
                return time();
            },
            'hits' => function($line, $oldline) : int {
                return @$line->hits ?? 0;
            },
        ];
    }

    function complete($line)
    {
        $line->ttl = @$line->ttl ?? 86400;
        $line->hits = @$line->hits ?? 0;
    }
}
