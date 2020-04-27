<?php
class Repeater
{
    public function __construct($period, $pegdate, $n, $day, $month, $round, $ff, $offsetSign, $offsetMagnitude, $offsetPeriod)
    {
        $this->period = $period;
        $this->pegdate = $pegdate;
        $this->n = $n;
        $this->day = $day;
        $this->month = $month;
        $this->round = $round;
        $this->ff = $ff;
        $this->offsetSign = $offsetSign;
        $this->offsetMagnitude = $offsetMagnitude;
        $this->offsetPeriod = $offsetPeriod;
    }

    static function create($serialised)
    {
        if (!preg_match('/^(?:(day):([0-9]{4}-[0-9]{2}-[0-9]{2})\.(\d+)|(month):(\d+)|(year):(\d+)\/(\d+))(r)?(?:f(\d))?(?:([+-])(\d+)([dwmy]))?$/', $serialised, $groups)) {
            //                1     2                             3     4       5     6      7      8     9       10       11    12    13
            error_response('invalid repeater: ' . $serialised);
        }

        $pegdate = null;
        $n = null;
        $day = null;
        $month = null;
        $ff = null;
        $offsetSign = null;
        $offsetMagnitude = null;
        $offsetPeriod = null;

        $period = @$groups[1] . @$groups[4] . @$groups[6];

        if ($period == 'day') {
            $pegdate = $groups[2];
            $n = $groups[3];
        } elseif ($period == 'month') {
            $day = $groups[5];
        } elseif ($period == 'year') {
            $day = $groups[7];
            $month = $groups[8];
        }

        $round = (bool) @$groups[9];
        $ff = @$groups[10];

        if (@$groups[11]) {
            $offsetSign = $groups[11];
            $offsetMagnitude = $groups[12];
            $offsetPeriod = ['d' => 'day', 'w' => 'week', 'm' => 'month', 'y' => 'year',][$groups[13]];
        }

        return new static($period, $pegdate, $n, $day, $month, $round, $ff, $offsetSign, $offsetMagnitude, $offsetPeriod);
    }

    public function get_clause($field_name)
    {
        if (!$this->offsetSign || !$this->offsetMagnitude) {
            $offsetR = '';
        } elseif ($this->offsetSign == '-') {
            $offsetR = "+ interval {$this->offsetMagnitude} {$this->offsetPeriod}";
        } else {
            $offsetR = "- interval {$this->offsetMagnitude} {$this->offsetPeriod}";
        }

        if ($offsetR) {
            $field = "({$field_name}) {$offsetR}";
        } else {
            $field = "{$field_name}";
        }

        $whereClauses = [];

        if ($this->period == 'day') {
            $expr = "(to_days({$field}) - to_days('{$this->pegdate}')) % {$this->n}";

            if ($this->ff) {
                $whereClauses[] = "{$expr} < 7";
            } else {
                $whereClauses[] = "{$expr} = 0";
            }
        } else {
            if ($this->round) {
                $left = "least(day({$field}), day(last_day({$field})))";
                $right = "least({$this->day}, day(last_day({$field})))";
            } else {
                $left = "day({$field})";
                $right = "{$this->day}";
            }

            if ($this->ff) {
                $whereClauses[] = "{$left} >= {$right} and {$left} < {$right} + 7";

            } else {
                $whereClauses[] = "{$left} = {$right}";
            }

            if ($this->period == 'year') {
                $whereClauses[] = "month({$field}) = {$this->month}";
            }
        }

        if ($this->ff) {
            $whereClauses[] = "dayofweek({$field}) = {$this->ff}";
        }

        return implode(' and ', $whereClauses);
    }
}
