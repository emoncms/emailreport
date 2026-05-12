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
    global $path, $mysqli,$session, $route, $redis, $user, $settings;
    
    $result = false;
    $emoncmsorg = !isset($settings['domain']) || strtolower($settings['domain']) === "emoncms.org";

    include "Modules/emailreport/emailreport_registry.php";
    include "Modules/emailreport/emailreport_runner.php";
    include "Modules/emailreport/emails/common.php";
    include "Modules/emailreport/emailreport_model.php";

    $emailreports = EmailReportRegistry::get_config_options();
    $reportlabels = EmailReportRegistry::get_report_labels();
    $ereport = new EmailReport($mysqli,$emailreports);

    // -----------------------------------------------------------------------------------------------------
    // Unsubscribe route handling
    // This is a public route that allows users to unsubscribe from email reports without logging in,
    // using a secure token to verify the request.
    // -----------------------------------------------------------------------------------------------------

    $render_unsubscribe_alert = function ($message, $type) {
        $type = $type === 'success' ? 'success' : 'error';
        return '<div style="max-width:640px; margin:20px auto; padding:0 15px">'
            . '<div class="alert alert-' . $type . '" style="margin-bottom:0">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</div>'
            . '</div>';
    };

    if ($route->action=="unsubscribe") {
        $route->format = "html";

        $userid = (int) get("userid");
        $report = get("report");
        $token = get("token");

        if (!$userid || $report === "" || $token === "") {
            return $render_unsubscribe_alert("Invalid unsubscribe link.", "error");
        }

        // Reject obviously invalid lengths before touching the DB.
        // HMAC-SHA256 hex output is always exactly 64 characters.
        if (strlen($report) > 64 || strlen($token) !== 64) {
            return $render_unsubscribe_alert("Invalid unsubscribe link.", "error");
        }

        // Validate report against the known registry before any DB access.
        if (!isset($emailreports[$report])) {
            return $render_unsubscribe_alert("Invalid unsubscribe link.", "error");
        }

        $u = $user->get($userid);
        if (!$u || !isset($u->apikey_read)) {
            return $render_unsubscribe_alert("Invalid unsubscribe link.", "error");
        }

        $expected = emailreport_build_unsubscribe_token($userid, $report, $u->apikey_read);
        // Fail closed if hash_equals is absent rather than falling back to non-constant-time ===.
        if (!function_exists("hash_equals") || !hash_equals($expected, $token)) {
            return $render_unsubscribe_alert("Invalid unsubscribe link.", "error");
        }

        $config = $ereport->get($userid, $report);
        if (!is_object($config)) {
            return $render_unsubscribe_alert("Invalid unsubscribe link.", "error");
        }

        $config->enable = 0;
        $save = $ereport->set($userid, $report, $config);
        if (is_array($save) && isset($save['success']) && $save['success']) {
            return $render_unsubscribe_alert("You have been unsubscribed from this email report.", "success");
        }

        return $render_unsubscribe_alert("Unable to unsubscribe.", "error");
    }

    // -----------------------------------------------------------------------------------------------------
    // Authenticated routes for managing email report configurations and previews
    // -----------------------------------------------------------------------------------------------------

    if (!$session['write']) return false;
    
    if ($route->action=="") {
        $route->format = "html";
        return view("Modules/emailreport/emailreport_configview.php",array(
            "emailreports"=>$emailreports,
            "reportlabels"=>$reportlabels
        ));
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

            $generation_config = EmailReportRunner::build_generation_config($config, array(
                "host"=>$path,
                "apikey"=>$u->apikey_read,
                "timezone"=>$u->timezone,
                "userid"=>$session['userid'],
                "report"=>$report,
                "ukenergy"=>json_decode($redis->get("ukenergy-stats"))
            ));

            $emailreport = EmailReportRunner::generate_by_type($report, $generation_config);
            
            if ($emailreport!=false) {
                if ($route->subaction=="sendtest") {
                    EmailReportRunner::send_delivery($redis,$config["email"],$emailreport,$emoncmsorg);
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
