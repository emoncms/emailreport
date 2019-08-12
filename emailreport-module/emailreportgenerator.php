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
    $usefeedid = $config["feedid"];
    $ukenergy = $config["ukenergy"];
    
    if (!$timezone) return false;

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
    $date->modify("+1 day");
    $endofweek = $date->getTimestamp();

    // Start and end time in seconds
    $start = $startofweek*1000; $end = $endofweek*1000;
    // Fetch the week of data
    $data = json_decode(file_get_contents("$host/feed/data.json?id=$usefeedid&start=$start&end=$end&mode=daily&apikey=$apikey"));

    // print json_encode($data);

    if (count($data)<8) {
        echo "Not enough days returned in data request \n"; return false;
    }

    // Calculate daily consumption for the week
    $total = 0;
    $daily = array();
    $days = array("Mon"=>"Monday","Tue"=>"Tuesday","Wed"=>"Wednesday","Thu"=>"Thursday","Fri"=>"Friday","Sat"=>"Saturday","Sun"=>"Sunday");
    for ($i=1; $i<count($data); $i++) {
        $timestamp = round($data[$i-1][0]*0.001/3600.0)*3600.0;
        $date->setTimestamp($timestamp);
        $day = $data[$i][1] - $data[$i-1][1];
        $daily[] = array("day"=>$days[$date->format('D')], "kwh"=>$day);
        $total += $day;
    }

    // Calculate average kWh per day
    $totaltime = ($data[7][0] - $data[0][0])*0.001;
    $kwhday = (($total * 3600000) / $totaltime) * 0.024;

    // Calculate saving vs previous week
    $startofpreviousweek *= 1000;
    $tmp = json_decode(file_get_contents("$host/feed/data.json?id=$usefeedid&start=$startofpreviousweek&end=".($startofpreviousweek+1000)."&interval=1&apikey=$apikey"));
    
    $text_lastweek = "";
    if (count($tmp)>0) {
        $kwhpreviousweek = $data[0][1] - $tmp[0][1];

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
    $use_kwh = $config["use_kwh"];
    $solar_kwh = $config["solar_kwh"];
    $ukenergy = $config["ukenergy"];
    
    if (!$timezone) return false;

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
    $date->modify("+1 day");
    $endofweek = $date->getTimestamp();

    // Start and end time in seconds
    $start = $startofweek*1000; $end = $endofweek*1000;
    // Fetch the week of data
    $use_data = json_decode(file_get_contents("$host/feed/data.json?id=$use_kwh&start=$start&end=$end&mode=daily&apikey=$apikey"));
    $solar_data = json_decode(file_get_contents("$host/feed/data.json?id=$solar_kwh&start=$start&end=$end&mode=daily&apikey=$apikey"));

    if (count($use_data)!=8) {
        echo "Not enough days returned in data request\n"; return false;
    }
    
    if (count($solar_data)!=8) {
        echo "Not enough days returned in data request\n"; return false;
    }
    
    if (count($solar_data)!=count($use_data)) {
        echo "Mismatch between solar and use feeds\n"; return false;
    }

    // Calculate daily consumption for the week
    $use_total = 0;
    $solar_total = 0;
    $daily = array();
    $days = array("Mon"=>"Monday","Tue"=>"Tuesday","Wed"=>"Wednesday","Thu"=>"Thursday","Fri"=>"Friday","Sat"=>"Saturday","Sun"=>"Sunday");
    for ($i=1; $i<count($use_data); $i++) {
        $date->setTimestamp($use_data[$i-1][0]*0.001);
        $use_day = $use_data[$i][1] - $use_data[$i-1][1];
        $solar_day = $solar_data[$i][1] - $solar_data[$i-1][1];
        $daily[] = array("day"=>$days[$date->format('D')], "use_kwh"=>$use_day, "solar_kwh"=>$solar_day);
        $use_total += $use_day;
        $solar_total += $solar_day;
    }

    // Calculate average kWh per day
    $totaltime = ($use_data[7][0] - $use_data[0][0])*0.001;
    $usekwhday = (($use_total * 3600000) / $totaltime) * 0.024;
    $solarkwhday = (($solar_total * 3600000) / $totaltime) * 0.024;
    
    // Calculate saving vs previous week
    $startofpreviousweek *= 1000;
    $tmp = json_decode(file_get_contents("$host/feed/data.json?id=$use_kwh&start=$startofpreviousweek&end=".($startofpreviousweek+1000)."&interval=1&apikey=$apikey"));

    $text_lastweek = "";
    if (count($tmp)>0) {
        $kwhpreviousweek = $use_data[0][1] - $tmp[0][1];

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
