<?php
use contextvariableset\Showas;

$blend = Blend::load(BLEND_NAME);
?>
<h2 class="only-wide"><?= @$blend->label ?: $blend->name ?></h2>

<?php
require BLENDS_HOME . '/src/php/partial/showas/' . SHOWAS . '.php';
?>

<div class="modal" id="bulk-edit-modal" style="background-color: #eee">
    <form method="post" class="bulk-edit-form">
        <?php
            foreach ($all_fields as $field) {
                $options = @$suggested_values[$field->name];
                $value = @$generic->{$field->name};
                $checked = property_exists($generic, $field->name);
                $field_inc = BLENDS_HOME . "/src/php/partial/fieldtype/{$field->type}.php";

                if (!file_exists($field_inc)) {
                    continue;
                } ?>

                <div class="form-row">
                    <div class="form-row__label"><?= $field->name ?></div>
                    <div class="form-row__value">
                        <div style="position: absolute; left: 0;">
                            <input type="checkbox" <?= $checked ? 'checked="checked"' : '' ?> name="apply_<?= $field->name ?>">
                        </div>
                        <?php require $field_inc; ?>
                    </div>
                    <div style="clear: both"></div>
                </div>
                <?php
            }
        ?>

        <div class="form-row">
            <div class="form-row__label">&nbsp;</div>
            <div class="form-row__value">
                <input class="button" name="action" value="update" type="submit">
                <div class="button close-modal">cancel</div>
            </div>
            <div style="clear: both"></div>
        </div>
    </form>

    <pre id="output"></pre>
</div>


<?php foreach ($types as $type): ?>
    <?php $linetype = Linetype::load($type); ?>
    <div class="modal" id="bulk-add-modal_<?= $type ?>">
        <form method="post" class="bulk-add-form" data-linetype="<?= $type ?>" data-blend="<?= BLEND_NAME ?>">
            <?php
                foreach ($linetype->fields as $field) {
                    if ($field->type == 'date') {
                        continue;
                    }

                    $options = @$suggested_values[$field->name];
                    $value = @$generic->{$field->name};
                    $checked = property_exists($generic, $field->name);
                    $field_inc = BLENDS_HOME . "/src/php/partial/fieldtype/{$field->type}.php";

                    if (!file_exists($field_inc)) {
                        continue;
                    } ?>

                    <div class="form-row">
                        <div class="form-row__label"><?= $field->name ?></div>
                        <div class="form-row__value">
                            <?php require $field_inc; ?>
                        </div>
                        <div style="clear: both"></div>
                    </div>
                    <?php
                }
            ?>

            <div class="form-row">
                <div class="form-row__label">&nbsp;</div>
                <div class="form-row__value">
                    <input class="button" name="action" value="add" type="submit">
                    <div class="button close-modal">cancel</div>
                </div>
                <div style="clear: both"></div>
            </div>
        </form>

        <pre id="output"></pre>
    </div>
<?php endforeach ?>