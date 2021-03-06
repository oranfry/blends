<?php
namespace blends\blend;

class users extends \Blend
{
    public function __construct()
    {
        $this->label = 'Users';
        $this->linetypes = ['user'];
        $this->fields = [
            (object) [
                'name' => 'icon',
                'type' => 'icon',
                'derived' => true,
            ],
            (object) [
                'name' => 'username',
                'type' => 'text',
            ],
        ];
        $this->showass = ['list'];
    }
}
