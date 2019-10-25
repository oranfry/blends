<?php
namespace contextvariableset;

class Hidden extends \ContextVariableSet
{
    public $value;

    public function __construct($prefix)
    {
        parent::__construct($prefix);

        $data = $this->getRawData();

        $this->value = @$data['value'];
    }

    public function display()
    {
        ?>
        <div style="display: none">
            <input class="cv" type="hidden" name="<?= $this->prefix ?>__value" value="<?= $this->value ?>">
        </div>
        <?php
    }
}
