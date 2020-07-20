<?php
namespace blends\blend;

class groups extends \Blend
{
    public function __construct()
    {
        $this->label = 'Groups';
        $this->linetypes = ['group'];
        $this->fields = [
            (object) [
                'name' => 'icon',
                'type' => 'icon',
                'derived' => true,
            ],
            (object) [
                'name' => 'groupname',
                'type' => 'text',
            ],
        ];
        $this->showass = ['list'];
    }
}
