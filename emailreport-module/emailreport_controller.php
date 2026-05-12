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
    global $path,$mysqli,$session, $route, $redis, $user;
    
    $result = false;
    $emoncmsorg = true;

    if (!$session['write']) return false;
    
    include "Modules/emailreport/emailreports.php";
    include "Modules/emailreport/emailreportgenerator.php";
    include "Modules/emailreport/emailreport_model.php";
    $ereport = new EmailReport($mysqli,$emailreports);
    
    if ($route->action=="") {
        $route->format = "html";
        return view("Modules/emailreport/emailreport_configview.php",array("emailreports"=>$emailreports));
    }
    
    if ($route->action=="save") {
        $route->format = "json";
        return $ereport->set($session['userid'],get("report"),json_decode(get("config")));
    }
    
    if ($route->action=="config") {
        $route->format = "json";
        return $ereport->get($session['userid'],get("report"));
    }

    if ($route->action=="preview") {
        $route->format = "text";
        $u = $user->get($session['userid']);
        
        $report = get("report");
        $result = $ereport->validate_config($report,json_decode(get("config"))); 
        if ($result["valid"]) {
            $config = $result["config"];
        
            $emailreport = false;
            
            if ($report=="home-energy") {
                $emailreport = emailreport_generate(array(
                    "host"=>$path,
                    "title"=>$config["title"],
                    "feedid"=>$config["use_kwh"],
                    "apikey"=>$u->apikey_read,
                    "timezone"=>$u->timezone,
                    "ukenergy"=>json_decode($redis->get("ukenergy-stats"))
                ));
            }
            
            if ($report=="solar-pv") {
                $emailreport = emailreport_generate_solarpv(array(
                    "host"=>$path,
                    "title"=>$config["title"],
                    "use_kwh"=>$config["use_kwh"],
                    "solar_kwh"=>$config["solar_kwh"],
                    "apikey"=>$u->apikey_read,
                    "timezone"=>$u->timezone,
                    "ukenergy"=>json_decode($redis->get("ukenergy-stats"))
                ));
            }
            
            if ($emailreport!=false) {
                if ($route->subaction=="sendtest") {
                    if ($emoncmsorg) {
                        emailreport_send($redis,$config["email"],$emailreport);
                    } else {
                        emailreport_send_swift($config["email"],$emailreport);
                    }
                    return "email report sent";
                } else {
                    return "<div style='background-color:#fafafa; padding:10px; border-bottom:1px solid #ddd'><b>EMAIL PREVIEW:</b> ".htmlspecialchars($emailreport['subject'], ENT_QUOTES, 'UTF-8')."</div>".$emailreport['message'];
                }
            } else {
                return "";
            }
            
        } else {
            return $result["message"];
        }
    }
    
    return false;
}
