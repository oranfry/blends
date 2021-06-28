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
            'icon' => function($records) : string {
                return 'person';
            },
            'username' => function($records) : string {
                return $records['/']->username;
            },
            'updatepassword' => function($records) : ?string {
                return null;
            },
            'password' => function($records) : ?string {
                return null;
            },
            'salt' => function($records) : ?string {
                return null;
            },
        ];
        $this->unfuse_fields = [
            'username' => function($line, $oldline) : string {
                return $line->username;
            },
            'password' => function($line, $oldline) : ?string {
                return $line->password ?? @$oldline->password;
            },
            'salt' => function($line, $oldline) : ?string {
                return @$line->password ? $line->salt : @$oldline->salt;
            },
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
