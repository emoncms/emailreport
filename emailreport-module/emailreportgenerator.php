<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access'); 

// --------------------------------------------------------------------------------------------------------------------------------------------
// Home Energy Consumption
// --------------------------------------------------------------------------------------------------------------------------------------------
function emailreport_generate($config) 
{
    $host = $config["host"];
    $title = $config["title"];
    $apikey = $config["apikey"];
    $timezone = $config["timezone"];
    $usefeedid = (int) $config["feedid"];
    $ukenergy = $config["ukenergy"];
    
    if (!$timezone) return false;
    if (!$usefeedid) return false;
    $date = new DateTime();
    
    try {
        $date->setTimezone(new DateTimeZone($timezone));
    } catch (Exception $e) {
        $date->setTimezone(new DateTimeZone("UTC"));
    }

    // Get start and end time of weeks
    $date->setTimestamp(time());
    //$date->modify("midnight");
    $date->modify("this monday");
    if ($date->getTimestamp()>time()) {
        $date->modify("last monday");
    }
    $date->modify("-2 weeks");
    $startofpreviousweek = $date->getTimestamp();
    $date->modify("+1 week");
    $startofweek = $date->getTimestamp();
    $date->modify("+1 week");
    $date->modify("-1 day");
    $endofweek = $date->getTimestamp();

    // Start and end time in seconds
    $start = $startofweek; $end = $endofweek;
    // Fetch the week of data
    $data = json_decode(file_get_contents("$host/feed/data.json?id=$usefeedid&start=$start&end=$end&mode=daily&delta=1&apikey=$apikey"));

    // print json_encode($data);

    if (!is_array($data)) {
        echo "consumption data is not an array? ".json_encode($data)."\n";
        return false;
    }

    if (!$data || count($data)<8) {
        // echo "Not enough days returned in data request \n"; return false;
    }

    // Calculate daily consumption for the week
    $total = 0;
    $daily = array();
    $days = array("Mon"=>"Monday","Tue"=>"Tuesday","Wed"=>"Wednesday","Thu"=>"Thursday","Fri"=>"Friday","Sat"=>"Saturday","Sun"=>"Sunday");
    for ($i=0; $i<count($data); $i++) {
        $date->setTimestamp($data[$i][0]*0.001);
        $daily[] = array("day"=>$days[$date->format('D')]." ".$date->format("jS"), "kwh"=>$data[$i][1]);
        $total += $data[$i][1];
    }

    // Calculate average kWh per day
    $totaltime = ($data[6][0] - $data[0][0])*0.001;
    $kwhday = (($total * 3600000) / $totaltime) * 0.024;

    // Calculate saving vs previous week
    $startofpreviousweek_value = json_decode(file_get_contents("$host/feed/value.json?id=$usefeedid&time=$startofpreviousweek&apikey=$apikey"));
    $startofweek_value = json_decode(file_get_contents("$host/feed/value.json?id=$usefeedid&time=$startofweek&apikey=$apikey"));

    $text_lastweek = "";
    $kwhpreviousweek = $startofweek_value - $startofpreviousweek_value;

    if ($kwhpreviousweek>0) {
        $prcless = 100 * (1 - ($total / $kwhpreviousweek));
    } else {
        $prcless = 0;
    }

    if ($prcless>=0) {
        $text_lastweek = "You used <b>".round($prcless)."% less</b> than the previous week\n";
    } else {
        $prcless = 100 * (1 - ($kwhpreviousweek/$total));
        $text_lastweek = "You used <b>".round($prcless)."% more</b> than the previous week\n";
    }
    // Calculate saving vs average household
    $prcless = 100 * (1 - ($kwhday / 9.0));
    $text_averagecmp = "";
    if ($prcless>=0) $text_averagecmp = "You used <b>".round($prcless)."% less</b> than UK household average\n";

    // --------------------------------------------------------------------------------------------------------

    $message = view("Modules/emailreport/emailview.php",array(
        "total"=>$total,
        "kwhday"=>$kwhday,
        "daily"=>$daily,
        "text_lastweek"=>$text_lastweek,
        "text_averagecmp"=>$text_averagecmp,
        "solarGWh"=>$ukenergy->solarGWh,
        "solarprc"=>$ukenergy->solarprc,
        "ukwindGWh"=>$ukenergy->ukwindGWh,
        "windprc"=>$ukenergy->windprc,
        "ukhydroGWh"=>$ukenergy->ukhydroGWh,
        "hydroprc"=>$ukenergy->hydroprc
    ));
    
    $subject = "Emoncms ";
    if ($title && $title!="") $subject = $title.": ";
    $subject .= "Energy Update: ".number_format($kwhday,1)."kWh/d";
    
    return array(
        "subject"=>$subject,
        "message"=>$message
    );
}

// --------------------------------------------------------------------------------------------------------------------------------------------
// Home Energy Consumption
// --------------------------------------------------------------------------------------------------------------------------------------------
function emailreport_generate_solarpv($config) 
{
    $host = $config["host"];
    $title = $config["title"];
    $apikey = $config["apikey"];
    $timezone = $config["timezone"];
    $use_kwh = (int) $config["use_kwh"];
    $solar_kwh = (int) $config["solar_kwh"];
    $ukenergy = $config["ukenergy"];
    
    if (!$timezone) return false;
    if (!$use_kwh) return false;
    if (!$solar_kwh) return false;
    
    $date = new DateTime();

    try {
        $date->setTimezone(new DateTimeZone($timezone));
    } catch (Exception $e) {
        $date->setTimezone(new DateTimeZone("UTC"));
    }

    // Get start and end time of weeks
    $date->setTimestamp(time());
    //$date->modify("midnight");
    $date->modify("this monday");
    if ($date->getTimestamp()>time()) {
        $date->modify("last monday");
    }
    $date->modify("-2 weeks");
    $startofpreviousweek = $date->getTimestamp();
    $date->modify("+1 week");
    $startofweek = $date->getTimestamp();
    $date->modify("+1 week");
    $date->modify("-1 day");
    $endofweek = $date->getTimestamp();

    // Start and end time in seconds
    $start = $startofweek; $end = $endofweek;
    // Fetch the week of data
    $use_data = json_decode(file_get_contents("$host/feed/data.json?id=$use_kwh&start=$start&end=$end&mode=daily&delta=1&apikey=$apikey"));
    
    $solar_data = json_decode(file_get_contents("$host/feed/data.json?id=$solar_kwh&start=$start&end=$end&mode=daily&delta=1&apikey=$apikey"));

    if (!is_array($use_data)) {
        echo "consumption data is not an array? ".json_encode($data)."\n";
        return false;
    }

    if (!$use_data || count($use_data)!=8) {
        //echo "Not enough days returned in data request\n"; return false;
    }
    
    if (!$solar_data || count($solar_data)!=8) {
        //echo "Not enough days returned in data request\n"; return false;
    }
    
    if (!$solar_data || count($solar_data)!=count($use_data)) {
        echo "Mismatch between solar and use feeds\n"; return false;
    }

    // Calculate daily consumption for the week
    $use_total = 0;
    $solar_total = 0;
    $daily = array();
    $days = array("Mon"=>"Monday","Tue"=>"Tuesday","Wed"=>"Wednesday","Thu"=>"Thursday","Fri"=>"Friday","Sat"=>"Saturday","Sun"=>"Sunday");
    for ($i=0; $i<count($use_data); $i++) {
        $date->setTimestamp($use_data[$i][0]*0.001);
        $daily[] = array("day"=>$days[$date->format('D')]." ".$date->format("jS"), "use_kwh"=>$use_data[$i][1], "solar_kwh"=>$solar_data[$i][1]);
        $use_total += $use_data[$i][1];
        $solar_total += $solar_data[$i][1];
    }

    // Calculate average kWh per day
    $totaltime = ($use_data[6][0] - $use_data[0][0])*0.001;
    $usekwhday = (($use_total * 3600000) / $totaltime) * 0.024;
    $solarkwhday = (($solar_total * 3600000) / $totaltime) * 0.024;
    
    // Calculate saving vs previous week
    $startofpreviousweek_value = json_decode(file_get_contents("$host/feed/value.json?id=$use_kwh&time=$startofpreviousweek&apikey=$apikey"));
    $startofweek_value = json_decode(file_get_contents("$host/feed/value.json?id=$use_kwh&time=$startofweek&apikey=$apikey"));

    $text_lastweek = "";
    
    $kwhpreviousweek = $startofweek_value - $startofpreviousweek_value;

    if ($kwhpreviousweek>0) {
        $prcless = 100 * (1 - ($use_total / $kwhpreviousweek));
    } else {
        $prcless = 0;
    }

    if ($prcless>=0) {
        $text_lastweek = "You used <b>".round($prcless)."% less</b> than the previous week\n";
    } else {
        $prcless = 100 * (1 - ($kwhpreviousweek/$use_total));
        $text_lastweek = "You used <b>".round($prcless)."% more</b> than the previous week\n";
    }

    // Calculate saving vs average household
    $prcless = 100 * (1 - ($usekwhday / 9.0));
    $text_averagecmp = "";
    if ($prcless>=0) $text_averagecmp = "You used <b>".round($prcless)."% less</b> than UK household average\n";

    // --------------------------------------------------------------------------------------------------------

    $message = view("Modules/emailreport/emailview-solarpv.php",array(
        "use_total"=>$use_total,
        "solar_total"=>$solar_total,
        "usekwhday"=>$usekwhday,
        "solarkwhday"=>$solarkwhday,
        "daily"=>$daily,
        "text_lastweek"=>$text_lastweek,
        "text_averagecmp"=>$text_averagecmp,
        "solarGWh"=>$ukenergy->solarGWh,
        "solarprc"=>$ukenergy->solarprc,
        "ukwindGWh"=>$ukenergy->ukwindGWh,
        "windprc"=>$ukenergy->windprc,
        "ukhydroGWh"=>$ukenergy->ukhydroGWh,
        "hydroprc"=>$ukenergy->hydroprc
    ));
    
    $subject = "Emoncms ";
    if ($title && $title!="") $subject = $title.": ";
    $subject .= "Energy Update: ".number_format($usekwhday,1)."kWh/d";
    
    return array(
        "subject"=>$subject,
        "message"=>$message
    );
}

function emailreport_send($redis,$emailto,$emailreport)
{
    $redis->rpush("emailqueue",json_encode(array(
        "emailto"=>$emailto,
        "type"=>"weeklyenergyupdate",
        "subject"=>$emailreport['subject'],
        "message"=>$emailreport['message']
    )));
}

function emailreport_send_swift($emailsto,$emailreport)
{
    require "Lib/email.php";
    $email = new Email();
    //$email->from(from);
    $emailsto = explode(",",$emailsto);
    $email->to($emailsto);
    $email->subject($emailreport['subject']);
    $email->body($emailreport['message']);
    $result = $email->send();
    if (!$result['success']) {
        //$this->log->error("Email send returned error. emailto=" + $emailto . " message='" . $result['message'] . "'");
    } else {
        //$this->log->info("Email sent to $emailto");
    }
}
