<?php

$emoncmsorg = false;
$path = "http://localhost/emoncms";

define('EMONCMS_EXEC', 1);

require "ukenergy.php";

chdir("/var/www/emoncms");

require "process_settings.php";
require "Lib/EmonLogger.php";
$mysqli = @new mysqli($server,$username,$password,$database);

if (!$redis_enabled) { echo "ERROR: Redis required for this module\n"; die; }


$redis = new Redis();

if ($emoncmsorg) {
    $redis->connect($redis_server);
} else {
    $connected = $redis->connect($redis_server['host'], $redis_server['port']);
    if (!$connected) { echo "Can't connect to redis at ".$redis_server['host'].":".$redis_server['port']." , it may be that redis-server is not installed or started see readme for redis installation"; die; }
    if (!empty($redis_server['prefix'])) $redis->setOption(Redis::OPT_PREFIX, $redis_server['prefix']);
    if (!empty($redis_server['auth'])) {
        if (!$redis->auth($redis_server['auth'])) {
            echo "Can't connect to redis at ".$redis_server['host'].", autentication failed"; die;
        }
    }
}

if ($emoncmsorg) {
    // 3) User sessions
    //require_once "Modules/user/rememberme_model.php";
    //$rememberme = new Rememberme($mysqli);
    require("Modules/user/user_model.php");
    $user = new User($mysqli,$redis);
} else {
    require("Modules/user/user_model.php");
    $user = new User($mysqli,$redis);
}

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
