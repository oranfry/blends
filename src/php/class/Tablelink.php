<?php
class Tablelink extends Thing
{
    public $tables;
    public $middle_table;
    public $ids;
    public $type = 'oneone';

    public static function info($name)
    {
        $tablelink = Tablelink::load($name);
        return $tablelink;
    }
}
