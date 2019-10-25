<?php if ($options): ?>
    <select name="<?= $field->name ?>" style="width: 80%" tabindex="1">
        <?php if (!@$field->constrained || count($options) > 1): ?><option></option><?php endif ?>
        <?php foreach ($options as $_value): ?>
            <option <?= $_value == @$value ? 'selected="selected"' : '' ?>><?= $_value ?></option>
        <?php endforeach ?>
    </select>
    <?php if (!@$field->constrained): ?>
        <button type="button" class="adhoc-toggle">&hellip;</button>
    <?php endif ?>
<?php else: ?>
    <input class="field value" type="text" name="<?= $field->name ?>" value="<?= $value ?>" autocomplete="off">
<?php endif ?>

