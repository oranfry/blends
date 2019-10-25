<?php
$daterange = ContextVariableSet::get('daterange');

if (!$daterange) {
    return;
}

$from = $daterange->from;
$to = $daterange->to;
$graphfield = @$graphfield ?: 'amount';
$graphfrom = @$from ?? @array_keys($summaries)[1];
$graphto = @$to ?? @array_keys($summaries)[count($summaries) - 1];
$current_period = Period::load($daterange->period);

if (!@$summaries) {
    echo 'cant show a graph';
    return;
}

$max = 0;
$min = 0;
$rmin = INF;
$rmax = -INF;

foreach ($summaries as $summary) {
    $max = max($max, @$summary->{$graphfield} ?: 0);
    $min = min($min, @$summary->{$graphfield} ?: INF);
    $rmax = max($rmax, @$summary->{$graphfield} ?: 0);
    $rmin = min($rmin, @$summary->{$graphfield} ?: INF);
}

// TODO: decide whether to go this way
// $min = $rmin; $max = $rmax;

$range = $max - $min;

if (!$range) {
    echo 'cant show a graph';
    return;
}

$graphtotal_days = 0;
$date = $graphfrom;

while (strcmp($graphto, $date) >= 0) {
    $date = date_shift($date, '+1 day');
    $graphtotal_days++;
}

$day = 1;
$graphtoday = null;
$date = $graphfrom;
$points = [];
$summary = $summaries['initial'];
$points = [
    [0, ((@$summary->{$graphfield} ?: 0) - $min) / $range]
];

$graphdiv = $daterange->rawto ? '1 month' : @$current_period->graphdiv;
$graphdivff = $daterange->rawto ? false : @$current_period->graphdivff;

if ($graphdiv) {
    $divs = [];

    if ($graphdivff) {
        $nextdiv = ff($date);
    } else {
        $nextdiv = date_shift($date, '+' . $graphdiv);
    }
}

while (strcmp($graphto, $date) >= 0) {
    if (isset($summaries[$date])) {
        $summary = $summaries[$date];
    }

    $final = @$summary->{$graphfield} ?: 0;

    $points[] = [$day / $graphtotal_days, ((@$summary->{$graphfield} ?: 0) - $min) / $range];

    if ($date == date('Y-m-d')) {
        $graphtoday = $day / $graphtotal_days;
    }

    if ($graphdiv && !strcmp($date, $nextdiv)) {
        $divs[] = $day / $graphtotal_days;
        $nextdiv = date_shift($nextdiv, '+' . $graphdiv);
    }

    $date = date_shift($date, '+1 day');
    $day++;
}

$xAxis = -$min / $range;
?>

<div id="bg-container" style="position: relative; margin: 0 auto; font-size: 0.8em;">
    <canvas id="bg" width="960" height="540"></canvas>
    <?php if ($max): ?>
        <div style="position: absolute; top: 0; right: 101%"><?= $max ?></div>
    <?php endif ?>

    <?php if ($min): ?>
        <div style="position: absolute; top: 100%; right: 101%"><?= $min ?></div>
    <?php endif ?>

    <div style="position: absolute; top: <?= round(100 * $max / $range, 2) ?>%; right: 101%"><?= date_shift($graphfrom, '-1 day') ?></div>
    <div style="position: absolute; top: <?= round(100 * $max / $range, 2) ?>%; left: 101%"><?= $graphto ?></div>

    <?php if ((abs($min - $rmin)) / $range > 0.03 && (abs($max - $rmin)) / $range > 0.03): ?>
        <div style="position: absolute; top: <?= round(100 * (1 - ($rmin / $range)), 2) ?>%; right: 101%"><?= $rmin ?></div>
    <?php endif ?>

    <?php if ((abs($max - $rmax)) / $range > 0.03 && (abs($min - $rmax)) / $range > 0.03): ?>
        <div style="position: absolute; top: <?= round(100 * (($max - $rmax) / $range), 2) ?>%; right: 101%"><?= $rmax ?></div>
    <?php endif ?>
</div>

<script>
    var points = <?= json_encode($points); ?>;
    var xAxisProp = <?= $xAxis ?>;
    var highlight = '<?= adjustBrightness(defined('HIGHLIGHT') ? HIGHLIGHT : REFCOL, -30); ?>';
    var today = <?= $graphtoday ?: 'null' ?>;
    <?php if (@$graphdiv): ?>
        var divs = <?= json_encode($divs); ?>;
    <?php endif ?>
</script>

<br><br>
<p style="font-size: 14px">Δ <?= number_format($final - (@$summaries['initial']->{$graphfield} ?: 0), 2, '.', '') ?></p>
