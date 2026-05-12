<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

require_once "Modules/emailreport/emails/common.php";

function emailreport_generate_home($config)
{
    $host = $config["host"];
    $title = $config["title"];
    $apikey = $config["apikey"];
    $timezone = $config["timezone"];
    $usefeedid = (int) $config["use_kwh"];
    $ukenergy = $config["ukenergy"] ?? null;
    $show_ukenergy = !empty($config["show_ukenergy"]);

    if (!$timezone || !$usefeedid) {
        return false;
    }

    $metrics = emailreport_extract_ukenergy_metrics($ukenergy);
    $window = emailreport_get_week_window($timezone);
    $date = $window["date"];

    $start = $window["startofweek"];
    $end = $window["endofweek"];

    $data = json_decode(file_get_contents("$host/feed/data.json?id=$usefeedid&start=$start&end=$end&mode=daily&delta=1&apikey=$apikey"));

    if (!is_array($data)) {
        return false;
    }

    $total = 0;
    $daily = array();
    $days = array("Mon" => "Monday", "Tue" => "Tuesday", "Wed" => "Wednesday", "Thu" => "Thursday", "Fri" => "Friday", "Sat" => "Saturday", "Sun" => "Sunday");
    for ($i = 0; $i < count($data); $i++) {
        $date->setTimestamp($data[$i][0] * 0.001);
        $daily[] = array("day" => $days[$date->format('D')] . " " . $date->format("jS"), "kwh" => $data[$i][1]);
        $total += $data[$i][1];
    }

    $days_count = max(1, count($daily));
    $kwhday = $total / $days_count;

    $startofpreviousweek_value = json_decode(file_get_contents("$host/feed/value.json?id=$usefeedid&time=" . $window["startofpreviousweek"] . "&apikey=$apikey"));
    $startofweek_value = json_decode(file_get_contents("$host/feed/value.json?id=$usefeedid&time=" . $window["startofweek"] . "&apikey=$apikey"));

    $kwhpreviousweek = $startofweek_value - $startofpreviousweek_value;
    if ($kwhpreviousweek > 0) {
        $prcless = 100 * (1 - ($total / $kwhpreviousweek));
    } else {
        $prcless = 0;
    }

    if ($prcless >= 0) {
        $text_lastweek = "You used <b>" . round($prcless) . "% less</b> than the previous week\n";
    } else {
        $prcless = 100 * (1 - ($kwhpreviousweek / $total));
        $text_lastweek = "You used <b>" . round($prcless) . "% more</b> than the previous week\n";
    }

    $prcless = 100 * (1 - ($kwhday / 9.0));
    $text_averagecmp = "";
    if ($prcless >= 0) {
        $text_averagecmp = "You used <b>" . round($prcless) . "% less</b> than UK household average\n";
    }

    $message = EmailReportRunner::view("Modules/emailreport/emails/home/template.php", array(
        "total" => $total,
        "kwhday" => $kwhday,
        "daily" => $daily,
        "text_lastweek" => $text_lastweek,
        "text_averagecmp" => $text_averagecmp,
        "show_ukenergy" => $show_ukenergy,
        "solarGWh" => $metrics["solarGWh"],
        "solarprc" => $metrics["solarprc"],
        "ukwindGWh" => $metrics["ukwindGWh"],
        "windprc" => $metrics["windprc"],
        "ukhydroGWh" => $metrics["ukhydroGWh"],
        "hydroprc" => $metrics["hydroprc"]
    ));

    $subject = "Emoncms ";
    if ($title && $title != "") {
        $subject = $title . ": ";
    }
    $subject .= "Energy Update: " . number_format($kwhday, 1) . "kWh/d";

    return array(
        "subject" => $subject,
        "message" => $message
    );
}
