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

    if (!$session['write']) return false;
    
    include "Modules/emailreport/emailreportgenerator.php";
    include "Modules/emailreport/emailreport_model.php";
    $ereport = new EmailReport($mysqli);
    
    if ($route->action=="") {
        $route->format = "html";
        $result = view("Modules/emailreport/emailreport_configview.php",array());
    }
    
    if ($route->action=="save") {
        $route->format = "json";
        if (!isset($_GET['weekly'])) $result = false;
        if (!isset($_GET['email'])) $result = false;
        if (!isset($_GET['feedid'])) $result = false;
        $result = $ereport->set($session['userid'],get("title"),get("weekly"),get("email"),get("feedid"));
    }
    
    if ($route->action=="config") {
        $route->format = "json";
        $result = $ereport->get($session['userid']);
    }

    if ($route->action=="sendtest") {
        $route->format = "text";
        if (!isset($_GET['email'])) $result = false;
        if (!isset($_GET['feedid'])) $result = false;
        if (!filter_var(get("email"), FILTER_VALIDATE_EMAIL)) return array('success'=>false, 'message'=>_("Email address format error"));
        
        $u = $user->get($session['userid']);
        $emailreport = emailreport_generate(array(
            "title"=>get("title"),
            "email"=>get("email"),
            "feedid"=>get("feedid"),
            "apikey"=>$u->apikey_read,
            "timezone"=>$u->timezone
        ));
        emailreport_send($redis,$emailreport);
        $result = "email report sent";
    }
    
    if ($route->action=="preview") {
        $route->format = "text";
        if (!isset($_GET['feedid'])) $result = false;
        
        $u = $user->get($session['userid']);
        $emailreport = emailreport_generate(array(
            "title"=>get("title"),
            "email"=>"",
            "feedid"=>get("feedid"),
            "apikey"=>$u->apikey_read,
            "timezone"=>$u->timezone
        ));
        $result = "<div style='background-color:#fafafa; padding:10px; border-bottom:1px solid #ddd'><b>EMAIL PREVIEW:</b> ".$emailreport['subject']."</div>".$emailreport['message'];
    }

    return array('content'=>$result);
}
