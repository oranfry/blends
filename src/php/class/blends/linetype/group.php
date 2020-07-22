<?php
namespace blends\linetype;

class group extends \Linetype
{
    function __construct()
    {
        $this->label = 'Group';
        $this->icon = 'people';
        $this->table = 'group';
        $this->showass = ['list'];
        $this->fields = [
            (object) [
                'name' => 'icon',
                'type' => 'icon',
                'fuse' => "'people'",
                'derived' => true,
            ],
            (object) [
                'name' => 'groupname',
                'type' => 'text',
                'fuse' => '{t}.groupname',
            ],
        ];
        $this->unfuse_fields = [
            '{t}.groupname' => ':{t}_groupname',
        ];
        $this->children = [
            (object) [
                'label' => 'users',
                'linetype' => 'user',
                'rel' => 'many',
                'parent_link' => 'groupuser',
           ],
       ];
    }
}