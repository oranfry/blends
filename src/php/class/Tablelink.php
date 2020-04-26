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

    public function reverse()
    {
        $reversed = clone $this;

        $reversed->ids = [$reversed->ids[1], $reversed->ids[0]];
        $reversed->tables = [$reversed->tables[1], $reversed->tables[0]];

        return $reversed;
    }
}
