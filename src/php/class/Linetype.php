<?php
class Linetype extends Thing
{
    public $children = [];
    public $links = [];
    public $links_reversed = [];
    public $links_required = [];
    public $clause;
    public $icon = 'doc';
    public $table;
    public $fields = [];
    public $fuse_fields = [];
    public $unfuse_fields = [];
    public $label;

    public function complete($line)
    {
    }

    public function get_suggested_values()
    {
        return [];
    }

    public function validate($line)
    {
        return [];
    }
}
