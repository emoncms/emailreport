<?php

define('EMONCMS_EXEC', 1);

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

print "Sending energy update emails\n";

$result = $mysqli->query("SELECT * FROM emailreport");
while($row = $result->fetch_object()) {
    $u = $user->get($row->userid);
    
    print " - ".$u->username."\n";
    
    if ($row->weekly==1) {  
        $emailreport = emailreport_generate(array(
            "title"=>$row->title,
            "email"=>$row->email,
            "feedid"=>$row->feedid,
            "apikey"=>$u->apikey_read,
            "timezone"=>$u->timezone
        ));
        emailreport_send($redis,$emailreport);
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
