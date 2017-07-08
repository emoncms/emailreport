<div id="emailouter" style="background-color:#eee; padding:20px; font-size:18px">
<div style="background-color:#fff; padding:20px;">
<table style="width:100%">
<tr>
  <td style="width:50%; vertical-align:top; padding:10px">
    <h3>Your energy in the last week</h3>
    <div style="font-size:28px; line-height:35px;">Total Use: <?php echo number_format($use_total,1); ?> kWh</div>
    <div style="font-size:28px; line-height:40px;"><?php echo number_format($usekwhday,1); ?> kWh per day</div>
    <div style="font-size:28px; line-height:35px;">Total Solar: <?php echo number_format($solar_total,1); ?> kWh</div>
    <div style="font-size:28px; line-height:40px;"><?php echo number_format($solarkwhday,1); ?> kWh per day</div>   
    <p><b>Daily breakdown:</b></p>
    <table style="width:100%; border-collapse: collapse">
    <tr>
      <th style="border: 1px solid #ddd; font-size:18px; padding:8px; text-align:left"></th>
      <th style="border: 1px solid #ddd; font-size:18px; padding:8px; text-align:left">Use</th>
      <th style="border: 1px solid #ddd; font-size:18px; padding:8px; text-align:left">Solar</th>
    </tr>
    
    <?php 
    foreach ($daily as $day) { 
        echo "<tr>";
        echo "<td style='border: 1px solid #ddd; font-size:18px; padding:8px'>".$day['day']."</td>";
        echo "<td style='border: 1px solid #ddd; font-size:18px; padding:8px'>".number_format($day['use_kwh'],1)." kWh</td>";
        echo "<td style='border: 1px solid #ddd; font-size:18px; padding:8px'>".number_format($day['solar_kwh'],1)." kWh</td>";
        echo "</tr>";
    } 
    ?>
    </table>
    <br>
    <p><?php echo $text_lastweek; ?></p>
    <p><?php echo $text_averagecmp; ?></p>
  
  </td>
  
  <td style="width:50%; vertical-align:top;padding:10px">
    <h3>Renewable Energy in the UK last week</h3>
    
    <h3>UK Solar</h3> 
    <p>Last week Solar PV generated an estimated <?php echo round($solarGWh); ?> GWh of energy across the UK covering <?php echo round($solarprc); ?>% of total demand.</p>
    
    <h3>UK Wind</h3> 
    <p>Last week metered wind generated <?php echo round($ukwindGWh); ?> GWh of energy across the UK covering <?php echo round($windprc); ?>% of total demand.</p>
    
    <h3>UK Hydro</h3> 
    <p>Last week metered hydro generated <?php echo round($ukhydroGWh); ?> GWh of energy across the UK covering <?php echo round($hydroprc); ?>% of total demand.</p>
  </td>
</tr>
</table>
</div>
</div>
