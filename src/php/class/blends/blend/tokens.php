<?php
namespace blends\blend;

class tokens extends \Blend
{
    public function __construct()
    {
        $this->label = 'Tokens';
        $this->linetypes = ['token'];
        $this->fields = [
            (object) [
                'name' => 'icon',
                'type' => 'icon',
                'derived' => true,
            ],
            (object) [
                'name' => 'token',
                'type' => 'text',
            ],
            (object) [
                'name' => 'username',
                'type' => 'text',
            ],
            (object) [
                'name' => 'ttl',
                'type' => 'number',
            ],
        ];
        $this->showass = ['list'];
    }
}
