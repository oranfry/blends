<?php
namespace blends\linetype;
use \Blends;

class user extends \Linetype
{
    function __construct()
    {
        $this->label = 'User';
        $this->icon = 'person';
        $this->table = 'user';
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
                'fuse' => '{t}.user',
                'derived' => true,
            ],
            (object) [
                'name' => 'updatepassword',
                'type' => 'text',
                'fuse' => "''",
            ],
        ];
        $this->unfuse_fields = [
            '{t}.user' => ':{t}_username',
            '{t}.salt' => 'if (:{t}_updatepassword is null, {t}.salt, substring(md5(rand()), 1, 4))',
            '{t}.password' => 'if (:{t}_updatepassword is null, {t}.password, sha2(concat(:{t}_updatepassword, {t}.salt), 256))',
        ];
    }

    function validate($line)
    {
        $errors = [];

        if (@$line->updatepassword && !Blends::validate_password($line->updatepassword)) {
            $errors[] = "Invalid password";
        }

        return $errors;
    }
}
