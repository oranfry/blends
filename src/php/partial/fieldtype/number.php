<input class="field value" type="number" step="<?= 1 / pow(10, (@$field->dp ?: 0)) ?>" name="<?= $field->name?>" style="width: 8em" value="<?= $value ?>" autocomplete="off">
