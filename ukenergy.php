<?php

function load_ukenergy_stats($start) 
{
    // --------------------------------------------------------------------------------------------------------
    // UK Renewable
    // --------------------------------------------------------------------------------------------------------
    $end = $start + (3600*24*7*1000);
    $totaltime = ($end - $start) * 0.001;

    // Demand
    $data = json_decode(file_get_contents("http://emoncms.org/feed/data.json?id=97736&start=$start&end=$end&interval=1800&skipmissing=0&limitinterval=1"));
    $ukdemand = average($data);
    $ukdemandGWh = ($ukdemand * $totaltime) / 3600000;
    
    // Wind
    $data = json_decode(file_get_contents("http://emoncms.org/feed/data.json?id=67088&start=$start&end=$end&interval=1800&skipmissing=0&limitinterval=1"));
    $ukwind = average($data);
    $ukwindGWh = ($ukwind * $totaltime) / 3600000;

    // Hydro
    $data = json_decode(file_get_contents("http://emoncms.org/feed/data.json?id=97703&start=$start&end=$end&interval=1800&skipmissing=0&limitinterval=1"));
    $ukhydro = average($data);
    $ukhydroGWh = ($ukhydro * $totaltime) / 3600000;

    // Get average solar output in this time
    $data = json_decode(file_get_contents("http://emoncms.org/feed/data.json?id=114934&start=$start&end=$end&interval=1800&skipmissing=0&limitinterval=1"));
    $sum = 0; $n = 0;
    for ($i=0; $i<count($data); $i++) {
        if ($data[$i][1]!=null) $sum += $data[$i][1];
        $n++;
    }
    $solar = $sum / $n;
    $solarGWh = ($solar * $totaltime) / 3600000;

    $ukdemandGWh += $solarGWh;
    $windprc = 100 * ($ukwindGWh / $ukdemandGWh);
    $solarprc = 100 * ($solarGWh / $ukdemandGWh);
    $hydroprc = 100 * ($ukhydroGWh / $ukdemandGWh);
    
    $ukenergy = new stdClass();
    $ukenergy->solarGWh = $solarGWh;
    $ukenergy->solarprc = $solarprc;
    $ukenergy->ukwindGWh = $ukwindGWh;  
    $ukenergy->windprc = $windprc;
    $ukenergy->ukhydroGWh = $ukhydroGWh;
    $ukenergy->hydroprc = $hydroprc;    
    
    return $ukenergy;
}

function average($data) {
    $sum = 0; $n = 0; $val = 0;
    for ($i=0; $i<count($data); $i++) {
        if ($data[$i][1]!=null) {
            $val = $data[$i][1]; 
        }
        $sum += $val;
        $n++;
    }
    return $sum / $n;
}
