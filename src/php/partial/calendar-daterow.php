<?php
for ($j = 0; $j < 7; $j++) {
    $date = date_shift($from, '+' . ($i + $j - $backtrack) . ' day');

    if (strcmp($date, $from) < 0 || strcmp($date, $to) > 0) {
        echo '<div class="cell datecell void">&nbsp;</div>';
        continue;
    }

    $monthLabel = date('M', strtotime($date));
    $celllabel = ($prevMonthLabel != $monthLabel ? "{$monthLabel} " : '') . date('j', strtotime($date)); ?><div class="cell datecell <?= @$currentgroup == $date ? 'today' : '' ?>">
        <?php if (!@$hideadd): ?>
            <?php if (count($types) > 1): ?>
                <div class="inline-modal inline-modal--right">
                    <nav>
                        <?php foreach ($types as $_type): ?>
                            <a href="<?= addlink($_type, $date, @$groupfield, @$defaultgroup, @$parent_query, $prepop) ?>"><i class="icon icon--mono icon--<?= $_type ?>"></i></a>
                        <?php endforeach ?>
                    </nav>
                </div>
                <a class="inline-modal-trigger"><?= $celllabel ?></a>
            <?php else: ?>
                <a href="<?= addlink($types[0], $date, @$groupfield, @$defaultgroup, @$parent_query, $prepop) ?>"><?= $celllabel ?></a>
            <?php endif ?>
        <?php endif ?>
    </div><?php
}
