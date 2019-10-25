<?php
$graphfield = @$graphfield ?: 'amount';

$dataPoints = [];

foreach ($summaries as $group => $summary) {
    $dataPoints[] = [
        'label' => $group ?: 'unknown',
        'y' => $summary->{$graphfield},
    ];
}

usort($dataPoints, function ($a, $b) {
    if ($b['y'] == $a['y']) {
        return 0;
    }

    if ($b['y'] > $a['y']) {
        return -1;
    }

    return 1;
});
?>

<script>
window.onload = function() {
    var chart = new CanvasJS.Chart("chartContainer", {
        animationEnabled: false,
        data: [{
            type: "pie",
            yValueFormatString: "$#,##0.00",
            indexLabel: "{label} ({y})",
            dataPoints: <?php echo json_encode($dataPoints, JSON_NUMERIC_CHECK); ?>
        }]
    });

    chart.render();
}
</script>
<br><br>
<div id="chartContainer" style="height: 370px; width: 100%;"></div>
