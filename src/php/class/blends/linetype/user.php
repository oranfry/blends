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
                'fuse' => '{t}.username',
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
            '{t}.username' => (object) [
                'expression' => ':{t}_username',
                'type' => 'varchar(255)',
            ],
            '{t}.salt' => (object) [
                'expression' => 'if(:{t}_password is null, {t}.salt, :{t}_salt)',
                'type' => 'char(4)',
            ],
            '{t}.password' => (object) [
                'expression' => 'ifnull(:{t}_password, {t}.password)',
                'type' => 'char(64)',
            ],
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
