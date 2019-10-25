<?php
class CvsBool extends ContextVariableSet
{
    public $vars = [
        'bool',
    ];

    public function display($prefix, $nickname)
    {
        $prefix_r = $prefix ? "{$prefix}_" : '';
        $name = $prefix_r . 'bool'; ?><small style="float: left; width: 6em"><?= $nickname ?: $prefix ?></small><?php
        ?><input class="cv cv-surrogate" type="checkbox" <?= $GLOBALS[$name] ?  'checked="checked"' : '' ?> data-for="<?= $name ?>" value="yes"><?php
        ?><div class="clear"></div><?php
    }
}

return new CvsBool();
