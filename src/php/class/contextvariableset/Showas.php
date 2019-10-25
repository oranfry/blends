<?php
namespace contextvariableset;

class Showas extends \ContextVariableSet
{
    public $value;
    public $options = [];
    public static $icons = [
        'graph' => 'linegraph',
        'pie' => 'piegraph',
        'list' => 'list',
        'summaries' => 'sigma',
        'calendar' => 'calendar',
    ];


    public function __construct($prefix)
    {
        parent::__construct($prefix);

        $data = $this->getRawData();

        $this->value = @$data['value'];
    }

    public function display()
    {
        ?>
        <div class="navset">
            <div class="inline-modal">
                <div class="nav-dropdown">
                    <?php foreach ($this->options as $showas): ?><a class="cv-manip <?= $showas == $this->value ? 'current' : '' ?>" data-manips="<?= $this->prefix ?>__value=<?= $showas ?>"><i class="icon icon--<?= static::$icons[$showas] ?>" alt="<?= $showas ?>"></i></a><?php endforeach ?>
                </div>
            </div>
            <i class="inline-modal-trigger current icon icon--<?= static::$icons[$this->value] ?>" alt="<?= $this->value ?>"></i>
            <input class="cv" type="hidden" name="<?= $this->prefix ?>__value" value="<?= $this->value ?>">
        </div>
        <?php
    }

    public function enddisplay()
    {
        ?>
        <?php
    }
}
