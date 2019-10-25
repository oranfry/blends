<?php
for ($j = 0; $j < 7; $j++) {
    $date = date_shift($from, '+' . ($i + $j - $backtrack) . ' day');

    if (strcmp($date, $from) < 0 || strcmp($date, $to) > 0) {
        echo '<div class="cell eventcell void">&nbsp;</div>';
        continue;
    } ?><div class="cell eventcell">
        <?php for (; $c < count($records); $c++): ?>
            <?php unset($record);
    $record = $records[$c]; ?>
            <?php if ($record->date != $date) {
        break;
    } ?>
            <a class="col" href="<?= @$record->link ?>" style="white-space: nowrap;" title="<?php record_title($record, $fields) ?>"><?php
                $iconfield = @filter_objects($fields, 'type', 'is', 'icon')[0];
    $firstfield = @filter_objects($fields, 'type', 'notin', ['date', 'icon'])[0];

    if ($iconfield) {
        ?><i class="icon icon--<?= $record->{$iconfield->name} ?>" alt=""></i><?php
    }

    if ($firstfield) {
        ?><span><?= $record->{$firstfield->name} ?></span><?php
    } ?></a>
        <?php endfor ?>
    </div><?php
}
