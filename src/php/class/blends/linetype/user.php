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
                'fuse' => function($record) : string {
                    return 'person';
                },
                'derived' => true,
            ],
            (object) [
                'name' => 'username',
                'type' => 'text',
                'fuse' => function($record) : string {
                    return $record->username;
                },
            ],
            (object) [
                'name' => 'updatepassword',
                'type' => 'text',
                'fuse' => function($record) : string {},
            ],
            (object) [
                'name' => 'password',
                'type' => 'text',
                'fuse' => function($record) : string {},
            ],
            (object) [
                'name' => 'salt',
                'type' => 'text',
                'fuse' => function($record) : string {},
            ],
        ];
        $this->unfuse_fields = [
            '{t}.username' => function($line, $oldline) : string {
                return $line->username;
            },
            '{t}.password' => function($line, $oldline) : string {
                return $line->password ?? @$oldline->password;
            },
            '{t}.salt' => function($line, $oldline) : string {
                return $line->password ? $line->salt : @$oldline->salt;
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
