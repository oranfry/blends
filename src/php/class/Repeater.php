<?php
class Repeater
{
    private $period;
    private $pegdate;
    private $n;
    private $day;
    private $month;
    private $round;
    private $ff;
    private $offsetSign;
    private $offsetMagnitude;
    private $offsetPeriod;

    public function __construct($period, $pegdate, $n, $day, $month, $round, $fastforward, $offsetSign, $offsetMagnitude, $offsetPeriod)
    {
        switch ($period) {
            case 'day':
                $this->period = 'day';
                $this->n = $n;
                $this->pegdate = $pegdate;
                $this->fastforward = $fastforward;
                break;

            case 'month':
                $this->period = 'month';
                $this->day = $day;
                $this->round = $round;
                $this->fastforward = $fastforward;
                $this->offsetSign = $offsetSign;
                $this->offsetMagnitude = $offsetMagnitude;
                $this->offsetPeriod = $offsetPeriod;
                break;

            case 'year':
                $this->period = 'year';
                $this->month = $month;
                $this->day = $day;
                $this->round = $round;
                $this->fastforward = $fastforward;
                $this->offsetSign = $offsetSign;
                $this->offsetMagnitude = $offsetMagnitude;
                $this->offsetPeriod = $offsetPeriod;

                break;

            default:
                error_response("Invalid period");
        }
    }

    static function create($serialised)
    {
        //                    1     2                             3     4       5     6      7      8     9       10       11    12    13
        if (!preg_match('/^(?:(day):([0-9]{4}-[0-9]{2}-[0-9]{2})\.(\d+)|(month):(\d+)|(year):(\d+)\/(\d+))(r)?(?:f(\d))?(?:([+-])(\d+)([dwmy]))?$/', $serialised, $groups)) {
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

    public function generate_dates($from, $to)
    {
        if ($this->offsetPeriod) {
            $offsetSignNegated = $this->offsetSign == '-' ? '+' : '-';
        }

        $start = $from;
        $end = $to;

        if ($this->offsetPeriod && $this->offsetMagnitude) {
            $start = date_shift($start, "{$offsetSignNegated}{$this->offsetMagnitude} {$this->offsetPeriod}");
            $end = date_shift($end, "{$offsetSignNegated}{$this->offsetMagnitude} {$this->offsetPeriod}");
        }

        if ($this->fastforward) {
            $start = date_shift($start, "-6 day");
            $end = date_shift($end, "-6 day");
        }

        $dates = [];

        for ($d = $start; $d <= $end; $d = date_shift($d, '+1 day')) {
            if ($this->period == 'day') {
                $a = strtotime("{$d} 00:00:00 +0000") / 86400;
                $b = strtotime("{$this->pegdate} 00:00:00 +0000") / 86400;

                if (($a - $b) % $this->n == 0) {
                    $dates[] = $d;
                }
            } elseif (
                preg_replace('/.*-/', '', $d) == ($this->round ? min($this->day, date('t', strtotime($d))) : $this->day) &&
                ($this->period != 'year' || preg_replace('/.*-(.*)-.*/', '$1', $d) == $this->month)
            ) {
                $dates[] = $d;
            }
        }

        // fastforward and offset

        for ($i = 0; $i < count($dates); $i++) {
            if ($this->fastforward) {
                while (date('w', strtotime($dates[$i])) != $this->fastforward - 1) {
                    $dates[$i] = date_shift($dates[$i], "+1 day");
                }
            }

            if ($this->offsetPeriod && $this->offsetMagnitude) {
                $dates[$i] = date_shift($dates[$i], "{$this->offsetSign}{$this->offsetMagnitude} {$this->offsetPeriod}");
            }
        }

        return $dates;
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
