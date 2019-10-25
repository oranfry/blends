<?php
class Period extends Thing
{
    public $step;

    public function rawstart($date)
    {
        return $date;
    }

    public function start($date)
    {
        return $date;
    }
}
