<?php
$lastgroup = 'initial';
$daterange = ContextVariableSet::get('daterange');

if ($daterange) {
    $from = $daterange->from;
    $to = $daterange->to;
}

$num_cols = 1 + count($fields);
$num_sacrifice_cols = count(filter_objects($fields, 'sacrifice', 'is', true));
$num_nonsacrifice_cols = $num_cols - $num_sacrifice_cols;
$seen_today = !@$currentgroup || !$daterange || strcmp($currentgroup, $from) < 0 || strcmp($currentgroup, $to) > 0;
?>

<table class="easy-table">
    <thead>
        <tr>
            <?php foreach ($fields as $field): ?>
                <th class="<?= $field->type == 'number' ? 'right' : '' ?> <?= @$field->sacrifice ? 'sacrifice' : '' ?>"><?= !@$field->supress_header && @$field->type != 'icon' ? $field->name : '' ?></th>
            <?php endforeach ?>
            <?php if (!@$hideadd): ?>
                <th></th>
            <?php endif ?>
        </tr>
    </thead>
    <tbody>
        <?php for ($i = 0; $i <= count($records); $i++): ?>
            <?php
                $skip = false;
                unset($record);

                if ($i == count($records)) {
                    if (!$seen_today) {
                        $record = (object) [$groupfield => $currentgroup];
                    } else {
                        $record = (object) [@$groupfield => null];
                    }

                    $skip = true;
                } else {
                    $record =& $records[$i];
                }
            ?>

            <?php if (@$summaries[@$lastgroup] && ($i == count($records) || @$groupfield && $record->{$groupfield} != $lastgroup)): ?>
                <?php $summary = $summaries[$lastgroup]; ?>
                <tr>
                    <?php foreach ($fields as $field): ?>
                        <td class="<?= @$field->sacrifice ? 'sacrifice' : '' ?> <?= $field->type == 'number' ? 'right' : '' ?>">
                            <?php if (@$summary->{$field->name}): ?>
                                <strong><?= @$field->prefix . $summary->{$field->name} ?></strong>
                            <?php endif ?>
                        </td>
                    <?php endforeach ?>
                    <td class="printhide"></td>
                </tr>
            <?php endif ?>

            <?php if (@$groupfield && $record->{$groupfield} != $lastgroup): ?>
                <?php
                    if (!$seen_today && strcmp($currentgroup, $record->{$groupfield}) < 0) {
                        unset($record);

                        $record = (object) [$groupfield => $currentgroup];
                        $i--;
                        $skip = true;
                    }
                ?>

                <?php if (@$record->{$groupfield}): ?>
                    <?php if ($i): ?>
                        </tbody>
                        <tbody>
                    <?php endif ?>

                    <tr class="<?= strcmp($record->{$groupfield}, @$currentgroup) ? '' : 'today' ?>">
                        <td class="sacrifice" colspan="<?= $num_cols - 1 ?>" style="line-height: 2em; font-weight: bold"><?= $record->{$groupfield} ?></td>
                        <td class="nsacrifice first-child" colspan="<?= $num_nonsacrifice_cols - 1 ?>" style="line-height: 2em; font-weight: bold"><?= $record->{$groupfield} ?></td>
                        <td class="printhide" style="text-align: right; color: black; position: relative; vertical-align: middle">
                            <?php if (!@$hideadd && count(@$types ?: []) > 0): ?>
                                <?php if (count($types) > 1): ?>
                                    <div class="inline-modal inline-modal--right">
                                        <nav>
                                            <?php foreach ($types as $_type): ?>
                                                <a href="<?= addlink($_type, @$record->{$groupfield}, @$groupfield, @$defaultgroup, @$parent_query, $prepop) ?>"><i class="icon icon--mono icon--<?= Linetype::load($_type)->icon ?>"></i></a>
                                            <?php endforeach ?>
                                        </nav>
                                    </div>
                                    <a class="inline-modal-trigger"><i class="icon icon--mono icon--plus"></i></a>
                                <?php else: ?>
                                    <a href="<?= addlink($types[0], @$record->{$groupfield}, @$groupfield, @$defaultgroup, @$parent_query, @$prepop) ?>"><i class="icon icon--mono icon--plus"></i></a>
                                <?php endif ?>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endif ?>
            <?php endif ?>

            <?php if (!@$skip): ?>
                <tr data-id="<?= $record->id ?>" <?php if (@$groupfield): ?>data-group="<?= $record->{$groupfield} ?>"<?php endif ?> data-type="<?= $record->type ?>" data-parent-type="<?= @$parentType ?>" data-parent-id="<?= @$parentId ?>" class="<?php foreach (@$classes ?: [] as $_class) {
                    echo @$record->{"{$_class->name}"} . ' ';
                } ?> ">
                    <?php foreach ($fields as $field): ?>
                        <?php $value = @$field->value ? computed_field_value($record, $field->value) : @$record->{$field->name}; ?>
                        <td data-value="<?= $value ?>" style="<?= $field->type == "number" ? 'text-align: right;' : '' ?>" class="<?= @$field->sacrifice ? 'sacrifice' : '' ?>"><?php
                            if (@$field->customlink) {
                                ?><a class="incog" href="<?= is_string($field->customlink) ? computed_field_value($record, $field->customlink) : $record->{"{$field->name}_link"} ?>" <?= @$field->download ? 'download' : '' ?>><?php
                            }

                            if ($field->type == 'icon') {
                                ?><i class="icon icon--mono icon--<?= @$field->translate[$value] ?? $value ?>"></i><?php
                            } elseif ($field->type == 'color') {
                                ?><span style="display: inline-block; height: 1em; width: 1em; background-color: #<?= $value ?>;">&nbsp;</span><?php
                            } else {
                                echo is_callable(@$field->prefix) ? ($field->prefix)($record) : @$field->prefix;
                                echo $field->type == 'fake' ? $field->value : (strlen($value) > MAX_COLUMN_WIDTH ? substr($value, 0, MAX_COLUMN_WIDTH - 1) . "&hellip;" : $value);
                                echo is_callable(@$field->suffix) ? ($field->suffix)($record) : @$field->suffix;
                            }

                            if (@$field->customlink) {
                                ?></a><?php
                            }
                        ?></td>
                    <?php endforeach ?>
                    <td class="printhide" style="text-align: right; vertical-align: middle">
                        <i class="trigger-edit-line icon icon--edit"></i>
                        <?php if (@$parentId): ?>
                            <i class="trigger-unlink-line icon icon--unlink"></i>
                        <?php endif ?>
                        <i class="trigger-delete-line icon icon--times"></i>
                    </td>
                </tr>
            <?php endif ?>

            <?php
                if (@$groupfield) {
                    $lastgroup = $record->{$groupfield};
                    $seen_today |= (strcmp($lastgroup, date('Y-m-d')) == 0);
                }
            ?>
        <?php endfor ?>
    </tbody>
</table>

<nav>
    <?php foreach ($types as $_type): ?>
        <a href="<?= addlink($_type, @$defaultgroup, @$groupfield, @$defaultgroup, @$parent_query, @$prepop) ?>"><i class="icon icon--mono icon--plus"></i> <i class="icon icon--mono icon--<?= Linetype::load($_type)->icon ?>"></i></a>
    <?php endforeach ?>
    </nav>
