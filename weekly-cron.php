<?php

require "ukenergy.php";
chdir("/var/www/emoncms");
require "Lib/load_emoncms.php";

$emoncmsorg = !isset($settings['domain']) || strtolower($settings['domain']) === "emoncms.org";
$path = $emoncmsorg ? "https://emoncms.org" : "http://localhost/emoncms";

require "Modules/emailreport/emailreport_registry.php";
require "Modules/emailreport/emailreport_runner.php";
require "Modules/emailreport/emailreport_model.php";

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

$ereport = new EmailReport($mysqli,EmailReportRegistry::get_config_options());

$result = $mysqli->query("SELECT * FROM emailreport");
if (!$result) {
    print "Error querying emailreport table\n";
    exit(1);
}
while($row = $result->fetch_object()) {
    $u = $user->get($row->userid);

    if (!$u) {
        print " - userid " . $row->userid . " not found, skipping\n";
        continue;
    }

    print " - ".$u->username."\n";
    
    $config = json_decode($row->config);
    $validation = $ereport->validate_config($row->report, $config);
    if (!$validation["valid"]) {
        print "   invalid config for report " . $row->report . ": " . $validation["message"] . "\n";
        continue;
    }

    $config = $validation["config"];
    if (!isset($config["enable"]) || (int) $config["enable"] !== 1) {
        continue;
    }

    $generation_config = EmailReportRunner::build_generation_config($config, array(
        "host"=>$path,
        "apikey"=>$u->apikey_read,
        "timezone"=>$u->timezone,
        "userid"=>$row->userid,
        "report"=>$row->report,
        "ukenergy"=>$ukenergy
    ));

    $emailreport = EmailReportRunner::generate_by_type($row->report, $generation_config);

    if ($emailreport) {
        send_report_paced($config["email"], $emailreport);
    }
}

/**
 * Send one report, pacing the run and waiting out a rate limit.
 *
 * This is the only place in emoncms that sends email in bulk, so this is where
 * the pacing belongs. It used to be a side effect of the email queue worker
 * sleeping between messages; with the queue gone the job that actually sends in
 * a loop has to pace itself, and it is the right place for it because nothing
 * here is waiting on a response, so the run can take as long as it needs.
 *
 * A 429 is the one failure worth waiting out: it says the message was fine and
 * we simply asked too fast. Everything else is reported and skipped, and the
 * report goes out next week.
 */
function send_report_paced($emailto, $emailreport)
{
    // ~5 messages a second, comfortably inside any provider's limit
    $pacing = 200000;

    $result = EmailReportRunner::send_delivery($emailto, $emailreport);

    if (!$result['success'] && isset($result['status']) && $result['status'] == 429) {
        print "   rate limited, waiting 60s\n";
        sleep(60);
        $result = EmailReportRunner::send_delivery($emailto, $emailreport);
    }

    if (!$result['success']) {
        print "   send failed: ".$result['message']."\n";
    }

    usleep($pacing);
    return $result;
}
