<h2><?= @$linetype->label ?: ucwords($linetype->name) ?></h2>

<div class="samewidth">
    <div class="line">
        <form method="post" class="edit-form" <?= $hasFileFields ? 'enctype="multipart/form-data"' : '' ?>>
            <?php
                if (@$parentid) {
                    $value = "{$parentlink}:{$parenttype}={$parentid}";
                    $field = (object) ['name' => 'parent'];
                    $options = []; ?>
                    <div class="form-row">
                        <div class="form-row__label">parent</div>
                        <div class="form-row__value">
                            <?php require BLENDS_HOME . "/src/php/partial/fieldtype/text.php"; ?>
                        </div>
                        <div style="clear: both"></div>
                    </div>
                    <?php
                }

                foreach ($linetype->fields as $field) {
                    if (@$field->derived && !@$field->show_derived) {
                        continue;
                    }

                    $value = @$line->{$field->name} ?: @$_GET[$field->name] ?: @$field->default;
                    $options = @$suggested_values[$field->name];

                    if ($value && $options && !in_array($value, $options)) {
                        array_unshift($options, $value);
                    } ?>
                    <div class="form-row">
                        <div class="form-row__label"><?= @$field->label ?? $field->name ?></div>
                        <div class="form-row__value">
                            <?php require BLENDS_HOME . "/src/php/partial/fieldtype/{$field->type}.php"; ?>
                        </div>
                        <div style="clear: both"></div>
                    </div>
                    <?php
                }
            ?>

            <div class="form-row">
                <div class="form-row__label">&nbsp;</div>
                <div class="form-row__value">
                    <input class="button button--main" name="action" value="<?= LINE_ID ? 'update' : 'create' ?>" type="submit">
                </div>
                <div style="clear: both"></div>
            </div>
        </form>
    </div>
    <?php
    if (isset($child_sets)) {
        $parentType = $linetype->name;
        $parentId = $line->id;

        foreach (@$linetype->children ?: [] as $child) {
            $child_linetype = Linetype::load($child->linetype);
            $records = $child_sets[$child->label]->lines;
            $types = [$child_linetype->name];
            $label = @$child->label ?: $child_linetype->name;
            $fields = @$child->list_fields ?: $child_linetype->fields;
            $summaries = [$label => @$child_sets[$child->label]->summary ?: []];
            $tablelink = Tablelink::load($child->parent_link);
            $parent_query = "parentlink={$child->parent_link}&{$tablelink->ids[0]}=" . LINE_ID;
            $groupfield = 'group';

            foreach ($records as $record) {
                $record->group = $label;
            }

            if (property_exists($line, 'date')) {
                $parent_query .= '&date=' . $line->date;
            }

            require BLENDS_HOME . "/src/php/partial/showas/list.php";
        }
    }

    if (method_exists($linetype, 'asText') && @$line) {
        ?><pre id="printpreview" class="printpreview"><?= $linetype->asText($line, $child_sets) ?><button class="print-line">Print</button></pre><br><?php
    }
    ?>
    <pre id="output" style="display: inline-block"></pre><br>
</div>