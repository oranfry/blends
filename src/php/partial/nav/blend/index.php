<?php
use contextvariableset\Filter;

$blend = Blend::load(BLEND_NAME);
$repeater = ContextVariableSet::get('repeater');
$adhocfilters = ContextVariableSet::get('adhocfilters');
?>

<div class="navset">
    <i class="icon icon--edit modal-trigger" data-for="bulk-edit-modal"></i>
    <i class="icon icon--times trigger-bulk-delete-lines" data-blend="<?= BLEND_NAME ?>"></i>
    <?php if ($repeater->period): ?>
        <?php if (count($types) > 1): ?>
            <div class="inline-modal inline-modal--right">
                <div class="nav-dropdown" style="padding: 0.5em">
                    <nav>
                        <?php foreach ($types as $_type): ?>
                            <i class="icon icon--<?= Linetype::load($_type)->icon ?> modal-trigger" data-for="bulk-add-modal_<?= $_type ?>"></i>
                        <?php endforeach ?>
                    </nav>
                </div>
            </div>
            <i class="inline-modal-trigger icon icon--mono icon--plus"></i>
        <?php else: ?>
            <i class="icon icon--plus modal-trigger" data-for="bulk-add-modal_<?= $types[0] ?>"></i>
        <?php endif ?>
    <?php endif ?>

    <?php if (@$blend->printable): ?><i class="icon icon--printer trigger-bulk-print-lines" data-blend="<?= BLEND_NAME ?>"></i><?php endif ?>
</div>

<div class="navset">
    <div class="inline-modal">
        <div id="filter-form" class="nav-dropdown nav-dropdown--spacey">
            <select>
                <option></option>
                <?php
                foreach ($all_fields as $field) {
                    if (@$field->main) {
                        continue;
                    } ?><option><?= $field->name ?></option><?php
                }
                ?>
            </select>
            <div class="standard-filter-value">
                <input type="text" style="width: 15em">
            </div>
            <div class="repeater-filter-value" style="display: none">
            </div>
            <button class="button" type="button">Add Filter</button>

            <?php
            if ($adhocfilters->value) {
                foreach (explode(',', $adhocfilters->value) as $filterid) {
                    $filter = Filter::get($filterid);

                    $manips = [];

                    foreach (explode(',', $adhocfilters->value) as $filterid2) {
                        if ($filterid != $filterid2) {
                            $manips[] = $filterid2;
                        }
                    } ?><a class="filter cv-manip" data-manips="<?= $adhocfilters->prefix ?>__value=<?= implode(',', $manips) ?>">
                        <span><?= $filter->field ?> <?= $filter->cmp ?> <?= $filter->value ?></span>
                        <i class="icon icon--times"></i>
                    </a><?php
                }
            }
            ?>
        </div>
    </div>
    <div class="inline-modal-trigger drnav <?= $adhocfilters->value ? 'current' : '' ?>"><i class="icon icon--funnel"></i></div>

    <div class="inline-modal repeater-modal">
        <div class="nav-dropdown--spacey" style="white-space: nowrap; width: 17em;">
            <div class="form-row">
                <div class="form-row__label">Repeater</div>
                <div class="form-row__value">
                    <select class="repeater-select cv-surrogate no-autosubmit" data-for="<?= $repeater->prefix ?>__period">
                        <option></option>
                        <?php foreach (['day', 'month', 'year'] as $period): ?>
                            <option <?= ($period == $repeater->period) ? 'selected="selected"' : '' ?> value="<?= $period ?>"><?= $period ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div style="clear: both"></div>
            </div>

            <div class="form-row" data-repeaters="day">
                <div class="form-row__label">n</div>
                <div class="form-row__value">
                    <input class="cv-surrogate no-autosubmit" data-for="<?= $repeater->prefix ?>__n" type="number" step="1" min="1" value="<?= $repeater->n ?>" style="width: 4em">
                </div>
                <div style="clear: both"></div>
            </div>

            <div class="form-row" data-repeaters="day">
                <div class="form-row__label">Peg Date</div>
                <div class="form-row__value">
                    <input class="cv-surrogate no-autosubmit" data-for="<?= $repeater->prefix ?>__pegdate" type="text" value="<?= $repeater->pegdate ?>" style="width: 7em">
                </div>
                <div style="clear: both"></div>
            </div>

            <div class="form-row" data-repeaters="month year">
                <div class="form-row__label">Day</div>
                <div class="form-row__value">
                    <input class="cv-surrogate no-autosubmit" data-for="<?= $repeater->prefix ?>__day" type="text" value="<?= $repeater->day ?>" style="width: 7em">
                </div>
                <div style="clear: both"></div>
            </div>

            <div class="form-row" data-repeaters="year">
                <div class="form-row__label">Month</div>
                <div class="form-row__value">
                    <input class="cv-surrogate no-autosubmit" data-for="<?= $repeater->prefix ?>__month" type="text" value="<?= $repeater->month ?>" style="width: 7em">
                </div>
                <div style="clear: both"></div>
            </div>

            <div class="form-row" data-repeaters="month year">
                <div class="form-row__label">Offset</div>
                <div class="form-row__value">
                    <input class="cv-surrogate no-autosubmit" data-for="<?= $repeater->prefix ?>__offset" type="text" value="<?= $repeater->offset ?>" style="width: 7em">
                </div>
                <div style="clear: both"></div>
            </div>

            <div class="form-row" data-repeaters="day month year">
                <div class="form-row__label">F/F</div>
                <div class="form-row__value">
                     <select class="cv-surrogate no-autosubmit" data-for="<?= $repeater->prefix ?>__ff">
                        <option></option>
                        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $i => $ff): ?>
                            <option <?= ($i + 1 == $repeater->ff) ? 'selected="selected"' : '' ?> value="<?= $i + 1?>"><?= $ff ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div style="clear: both"></div>
            </div>

            <div class="form-row" data-repeaters="month year">
                <div class="form-row__label">Round</div>
                <div class="form-row__value">
                     <select class="cv-surrogate no-autosubmit" data-for="<?= $repeater->prefix ?>__round">
                        <option></option>
                        <option <?= $repeater->round == 'Yes' ? 'selected': '' ?>>Yes</option>
                    </select>
                </div>
                <div style="clear: both"></div>
            </div>

            <div class="form-row">
                <div class="form-row__label">&nbsp;</div>
                <div class="form-row__value">
                    <a class="button cv-manip" data-manips="<?= $repeater->prefix ?>__period=">Clear</a>
                    <a class="button cv-manip" data-manips="">Apply</a>
                </div>
                <div style="clear: both"></div>
            </div>
        </div>
    </div>
    <div class="inline-modal-trigger drnav <?= $repeater->period ? 'current' : '' ?>"><i class="icon icon--repeat"></i></div>
</div>
