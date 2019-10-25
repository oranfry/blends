<?php
if (empty($summaries)) {
    return;
}
?>
<table class="easy-table">
    <thead>
        <tr>
            <th><?= $groupfield ?></th>
            <?php foreach ($fields as $field): ?>
                <?php if (@$field->summary != 'sum') {
    continue;
} ?>
                <th <?= $field->type == 'number' ? 'class="right"' : '' ?>><?= !@$field->supress_header && @$field->type != 'icon' ? $field->name : '' ?></th>
            <?php endforeach ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($summaries as $group => $summary): ?>
            <tr>
                <td><?= $group ?></td>
                <?php foreach ($fields as $field): ?>
                    <?php if (@$field->summary != 'sum') {
    continue;
} ?>
                    <td <?= $field->type == 'number' ? 'class="right"' : '' ?>><strong><?= @$summary->{$field->name} ?: '0.00' ?></strong></td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>
