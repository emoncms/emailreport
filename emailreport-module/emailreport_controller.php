<?php
/*
 All Emoncms code is released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.

  ---------------------------------------------------------------------
  Emoncms - open source energy visualisation
  Part of the OpenEnergyMonitor project:
  http://openenergymonitor.org
*/

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function emailreport_controller()
{
    global $mysqli,$session, $route, $redis, $user;
    
    $result = false;

    if (!$session['write']) return false;
    
    include "Modules/emailreport/emailreports.php";
    include "Modules/emailreport/emailreportgenerator.php";
    include "Modules/emailreport/emailreport_model.php";
    $ereport = new EmailReport($mysqli,$emailreports);
    
    if ($route->action=="") {
        $route->format = "html";
        $result = view("Modules/emailreport/emailreport_configview.php",array("emailreports"=>$emailreports));
    }
    
    if ($route->action=="save") {
        $route->format = "json";
        $result = $ereport->set($session['userid'],get("report"),json_decode(get("config")));
    }
    
    if ($route->action=="config") {
        $route->format = "json";
        $result = $ereport->get($session['userid'],get("report"));
    }

    if ($route->action=="preview") {
        $route->format = "text";
        $u = $user->get($session['userid']);
        
        $report = get("report");
        $result = $ereport->validate_config($report,json_decode(get("config"))); 
        if ($result["valid"]) {
            $config = $result["config"];
        
            if ($report=="home-energy") {
                $emailreport = emailreport_generate(array(
                    "title"=>$config["title"],
                    "email"=>$config["email"],
                    "feedid"=>$config["use_kwh"],
                    "apikey"=>$u->apikey_read,
                    "timezone"=>$u->timezone,
                    "ukenergy"=>json_decode($redis->get("ukenergy-stats"))
                ));
            }
            $result = "<div style='background-color:#fafafa; padding:10px; border-bottom:1px solid #ddd'><b>EMAIL PREVIEW:</b> ".$emailreport['subject']."</div>".$emailreport['message'];    
        } else {
            $result = $result["message"];
        }
    }
    
    if ($route->action=="sendtest") {
        $route->format = "text";
        $u = $user->get($session['userid']);
        
        $report = get("report");
        $result = $ereport->validate_config($report,json_decode(get("config"))); 
        if ($result["valid"]) {
            $config = $result["config"];
        
            if ($report=="home-energy") {
                $emailreport = emailreport_generate(array(
                    "title"=>$config["title"],
                    "email"=>$config["email"],
                    "feedid"=>$config["use_kwh"],
                    "apikey"=>$u->apikey_read,
                    "timezone"=>$u->timezone,
                    "ukenergy"=>json_decode($redis->get("ukenergy-stats"))
                ));
                emailreport_send($redis,$emailreport);
                $result = "email report sent";
            }
        } else {
            $result = $result["message"];
        }
    }

    return array('content'=>$result);
}