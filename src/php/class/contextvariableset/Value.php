<?php
namespace contextvariableset;

class Value extends \ContextVariableSet
{
    public $value;
    public $options;

    public function __construct($prefix)
    {
        parent::__construct($prefix);

        $data = $this->getRawData();

        $this->value = @$data['value'];
    }

    public function display()
    {
        ?><div class="navset">
            <input class="cv" name="<?= $this->prefix ?>__value" placeholder="<?= $this->prefix ?>" value="<?= $this->value ?>" style="display: none">
            <div class="inline-modal">
                <div class="nav-dropdown">
                    <a class="cv-manip <?= $this->value ? '' : 'current' ?>" data-manips="<?= $this->prefix ?>__value=">-</a>
                    <?php if ($this->options): ?>
                        <?php foreach ($this->options as $option): ?>
                            <a class="cv-manip <?= $this->value == $option ? 'current' : ''?>" data-manips="<?= $this->prefix ?>__value=<?= $option ?>"><?= $option ?></a>
                        <?php endforeach ?>
                    <?php else: ?>
                    <?php endif ?>
                </div>
            </div>
            <span class="inline-modal-trigger"><?= $this->value ?: $this->prefix ?></span>
        </div><?php
    }
}
