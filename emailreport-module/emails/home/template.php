<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

if ($total !== null) {
    $total = number_format($total, 1);
} else {
    $total = "---";
}

if ($kwhday !== null) {
    $kwhday = number_format($kwhday, 1);
} else {
    $kwhday = "---";
}

$show_ukenergy = !empty($show_ukenergy);
?>

<div id="emailouter" style="background-color:#eee; padding:20px; font-size:18px">
<div style="background-color:#fff; padding:20px;">
<div style="display:flex; flex-wrap:wrap; width:100%">
  <div style="flex:1 1 400px; vertical-align:top; padding:10px; box-sizing:border-box">
    <h3>Your energy in the last week</h3>
    <div style="font-size:28px; line-height:35px;">Total: <?php echo $total; ?> kWh</div>
    <div style="font-size:22px; line-height:35px; margin-bottom:20px;"><?php echo $kwhday; ?> kWh per day</div>

    <p><b>Daily breakdown:</b></p>
    <table style="width:100%; border-collapse: collapse">
    <?php
    foreach ($daily as $day) {
        if (isset($day['kwh']) && $day['kwh'] !== null) {
            echo "<tr><td style='border: 1px solid #ddd; font-size:18px; padding:8px'>" . $day['day'] . "</td><td style='border: 1px solid #ddd; font-size:18px; padding:8px'>" . number_format($day['kwh'], 1) . " kWh</td></tr>";
        }
    }
    ?>
    </table>
    <br>
    <p><?php echo $text_lastweek; ?></p>
    <p><?php echo $text_averagecmp; ?></p>

  </div>

  <?php if ($show_ukenergy) { ?>
  <div style="flex:1 1 400px; vertical-align:top; padding:10px; box-sizing:border-box">
    <h3>Renewable Energy in the UK last week</h3>

    <h3>UK Solar</h3>
    <p>Last week Solar PV generated an estimated <?php echo round($solarGWh); ?> GWh of energy across the UK covering <?php echo round($solarprc); ?>% of total demand.</p>

    <h3>UK Wind</h3>
    <p>Last week metered wind generated <?php echo round($ukwindGWh); ?> GWh of energy across the UK covering <?php echo round($windprc); ?>% of total demand.</p>

    <h3>UK Hydro</h3>
    <p>Last week metered hydro generated <?php echo round($ukhydroGWh); ?> GWh of energy across the UK covering <?php echo round($hydroprc); ?>% of total demand.</p>
  </div>
  <?php } ?>
</div>
<div style="padding:10px">
  <?php echo emailreport_render_unsubscribe_footer($unsubscribe_url ?? ''); ?>
</div>
</div>
</div>
