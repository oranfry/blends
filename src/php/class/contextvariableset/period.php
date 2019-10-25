<?php
class CvsPeriod extends ContextVariableSet
{
    public $vars = [
        'iperiod', /* not period, keep separate from daterange */
    ];

    public function postload(&$iperiod)
    {
        global $periods, $period, $current_period;

        if (!$iperiod) {
            $iperiod = @array_values($periods)[0]->id;
        }

        $period = $iperiod;

        foreach ($periods as $_period) {
            if ($_period->id == $period) {
                $current_period = $_period;

                break;
            }
        }
    }

    public function display()
    {
        global $periods, $period, $current_period; ?>
        <nav>
            <?php
                $c = 0;
        foreach ($periods as $_period) {
            $highlight = (!@$rawto && $period == $_period->id) ? 'current' : '';
            $label = strtoupper($_period->id); ?><a class="cv-manip <?= $highlight ?>" data-manips="iperiod=<?= $_period->id ?>"><?= $label ?></a><?php

                    if (++$c == 5) {
                        echo "<br>";
                        $c = 0;
                    }
        } ?>
        </nav>
        <?php
    }
}

return new CvsPeriod();
