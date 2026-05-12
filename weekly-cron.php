<?php

$emoncmsorg = true;
$path = "https://emoncms.org";

require "ukenergy.php";
chdir("/var/www/emoncms");
require "Lib/load_emoncms.php";
require "Modules/emailreport/emailreportgenerator.php";

// ----------------------------------------------------
// Load UK Energy statistics for last week
// These are loaded to redis and are then accessible in the report preview as well
// ----------------------------------------------------

$date = new DateTime();
$date->setTimezone(new DateTimeZone("Europe/London"));
// Get start and end time of weeks
$date->setTimestamp(time());
$date->modify("this monday");
if ($date->getTimestamp()>time()) {
    $date->modify("last monday");
}
$date->modify("-1 weeks");
$startofweek = $date->getTimestamp();
$start = $startofweek*1000;

$ukenergy = load_ukenergy_stats($start);
$redis->set("ukenergy-stats",json_encode($ukenergy));
// ----------------------------------------------------

print "Sending energy update emails\n";

$result = $mysqli->query("SELECT * FROM emailreport");
while($row = $result->fetch_object()) {
    $u = $user->get($row->userid);
    
    print " - ".$u->username."\n";
    
    if ($row->report=="home-energy") {
        $row->config = json_decode($row->config);
        if ($row->config->enable==1) {  
            $emailreport = emailreport_generate(array(
                "host"=>$path,
                "title"=>$row->config->title,
                "feedid"=>$row->config->use_kwh,
                "apikey"=>$u->apikey_read,
                "timezone"=>$u->timezone,
                "ukenergy"=>$ukenergy
            ));

            if ($emailreport) {
                if ($emoncmsorg) {
                    emailreport_send($redis,$row->config->email,$emailreport);
                } else {
                    emailreport_send_swift($row->config->email,$emailreport);
                }
            }
        }
    }
    
    if ($row->report=="solar-pv") {
        $row->config = json_decode($row->config);
        if ($row->config->enable==1) {  
            $emailreport = emailreport_generate_solarpv(array(
                "host"=>$path,
                "title"=>$row->config->title,
                "use_kwh"=>$row->config->use_kwh,
                "solar_kwh"=>$row->config->solar_kwh,
                "apikey"=>$u->apikey_read,
                "timezone"=>$u->timezone,
                "ukenergy"=>$ukenergy
            ));
            if ($emailreport) {
                if ($emoncmsorg) {
                    emailreport_send($redis,$row->config->email,$emailreport);
                } else {
                    emailreport_send_swift($row->config->email,$emailreport);
                }
            }
        }
    }
}
  
function view($filepath, array $args)
{
    extract($args);
    ob_start();
    include "$filepath";
    $content = ob_get_clean();
    return $content;
}
