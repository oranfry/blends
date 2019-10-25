<?php
namespace contextvariableset;

use \Config;
use \Period;

class Repeater extends \ContextVariableSet
{
    public $period;
    public $n;
    public $pegdate;
    public $day;
    public $month;
    public $round;
    public $offset;
    public $ff;

    public function __construct($prefix)
    {
        parent::__construct($prefix);

        $data = $this->getRawData();

        if (@$data['period']) {
            $this->period = @$data['period'];
            $this->n = @$data['n'] ?: 7;
            $this->pegdate = @$data['pegdate'] ?: date('Y-m-d');
            $this->day = @$data['day'];
            $this->month = @$data['month'];
            $this->round = @$data['round'];
            $this->offset = @$data['offset'];
            $this->ff = @$data['ff'];
        }
    }

    public function display()
    {
        ?>
        <div style="display: none">
            <input class="cv" type="hidden" name="<?= $this->prefix ?>__period" value="<?= $this->period ?>">
            <input class="cv" type="hidden" name="<?= $this->prefix ?>__n" value="<?= $this->n ?>">
            <input class="cv" type="hidden" name="<?= $this->prefix ?>__pegdate" value="<?= $this->pegdate ?>">
            <input class="cv" type="hidden" name="<?= $this->prefix ?>__day" value="<?= $this->day ?>">
            <input class="cv" type="hidden" name="<?= $this->prefix ?>__month" value="<?= $this->month ?>">
            <input class="cv" type="hidden" name="<?= $this->prefix ?>__round" value="<?= $this->round ?>">
            <input class="cv" type="hidden" name="<?= $this->prefix ?>__offset" value="<?= $this->offset ?>">
            <input class="cv" type="hidden" name="<?= $this->prefix ?>__ff" value="<?= $this->ff ?>">
        </div>
        <?php
    }
}
