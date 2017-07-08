<?php

define('EMONCMS_EXEC', 1);

require "ukenergy.php";

chdir("/var/www/emoncms");

require "process_settings.php";
require "Modules/log/EmonLogger.php";
$mysqli = @new mysqli($server,$username,$password,$database);

$redis = new Redis();
$redis->connect("127.0.0.1");

// 3) User sessions
require "Modules/user/rememberme_model.php";
$rememberme = new Rememberme($mysqli);

require("Modules/user/user_model.php");
$user = new User($mysqli,$redis,$rememberme);

include "Modules/emailreport/emailreportgenerator.php";

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
                "title"=>$row->config->title,
                "feedid"=>$row->config->use_kwh,
                "apikey"=>$u->apikey_read,
                "timezone"=>$u->timezone,
                "ukenergy"=>$ukenergy
            ));
            emailreport_send($redis,$row->config->email,$emailreport);
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
