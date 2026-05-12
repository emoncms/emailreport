<?php

function fetch_feed_average($id, $start, $end)
{
    $raw = file_get_contents("https://emoncms.org/feed/data.json?id=$id&start=$start&end=$end&interval=1800&skipmissing=0&limitinterval=1");
    if ($raw === false) return null;
    $data = json_decode($raw);
    if (!is_array($data)) return null;
    return average($data);
}

function load_ukenergy_stats($start) 
{
    // --------------------------------------------------------------------------------------------------------
    // UK Renewable
    // --------------------------------------------------------------------------------------------------------
    $end = $start + (3600*24*7*1000);
    $totaltime = ($end - $start) * 0.001;

    // Demand
    $ukdemand = fetch_feed_average(476659, $start, $end);
    if ($ukdemand === null) return false;
    $ukdemandGWh = ($ukdemand * $totaltime) / 3600000;

    // Wind
    $ukwind = fetch_feed_average(97699, $start, $end);
    if ($ukwind === null) return false;
    $ukwindGWh = ($ukwind * $totaltime) / 3600000;

    // Hydro
    $ukhydro = fetch_feed_average(97703, $start, $end);
    if ($ukhydro === null) return false;
    $ukhydroGWh = ($ukhydro * $totaltime) / 3600000;

    // Solar
    $solar = fetch_feed_average(477236, $start, $end);
    if ($solar === null) return false;
    $solarGWh = ($solar * $totaltime) / 3600000;

    // Solar is embedded in demand so add it back to get total demand for percentage calculations
    $ukdemandGWh += $solarGWh;
    if ($ukdemandGWh == 0) return false;
    
    $ukenergy = new stdClass();
    $ukenergy->solarGWh = $solarGWh;
    $ukenergy->solarprc = 100 * ($solarGWh / $ukdemandGWh);
    $ukenergy->ukwindGWh = $ukwindGWh;  
    $ukenergy->windprc = 100 * ($ukwindGWh / $ukdemandGWh);
    $ukenergy->ukhydroGWh = $ukhydroGWh;
    $ukenergy->hydroprc = 100 * ($ukhydroGWh / $ukdemandGWh);
    
    return $ukenergy;
}

function average($data) {
    if (empty($data)) return 0;
    $sum = 0; $n = 0; $val = 0;
    for ($i=0; $i<count($data); $i++) {
        if ($data[$i][1]!=null) {
            $val = $data[$i][1]; 
        }
        $sum += $val;
        $n++;
    }
    return $n > 0 ? $sum / $n : 0;
}
