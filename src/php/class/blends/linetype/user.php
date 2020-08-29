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
            (object) [
                'name' => 'password',
                'type' => 'text',
                'fuse' => "''",
            ],
            (object) [
                'name' => 'salt',
                'type' => 'text',
                'fuse' => "''",
            ],
        ];
        $this->unfuse_fields = [
            '{t}.user' => ':{t}_username',
            '{t}.salt' => 'if(:{t}_password is null, {t}.salt, :{t}_salt)',
            '{t}.password' => 'ifnull(:{t}_password, {t}.password)',
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

    function complete($line)
    {
        if (@$line->updatepassword) {
            $line->salt = substr(md5(rand()), 0, 4);
            $line->password = hash('sha256', $line->updatepassword . $line->salt);
            unset($line->updatepassword);
        } elseif (!@$line->password) {
            $line->salt = null;
        } elseif (!@$line->salt) {
            $line->password = null;
        }
    }
}
