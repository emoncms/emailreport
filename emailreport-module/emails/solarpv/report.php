<?php

require_once "Modules/emailreport/emails/common.php";

function emailreport_generate_solarpv($config)
{
    $host = $config["host"];
    $title = $config["title"];
    $apikey = $config["apikey"];
    $timezone = $config["timezone"];
    $use_kwh = (int) $config["use_kwh"];
    $solar_kwh = (int) $config["solar_kwh"];
    $ukenergy = $config["ukenergy"];

    if (!$timezone || !$use_kwh || !$solar_kwh) {
        return false;
    }

    $metrics = emailreport_extract_ukenergy_metrics($ukenergy);
    $window = emailreport_get_week_window($timezone);
    $date = $window["date"];

    $start = $window["startofweek"];
    $end = $window["endofweek"];

    $use_data = json_decode(file_get_contents("$host/feed/data.json?id=$use_kwh&start=$start&end=$end&mode=daily&delta=1&apikey=$apikey"));
    $solar_data = json_decode(file_get_contents("$host/feed/data.json?id=$solar_kwh&start=$start&end=$end&mode=daily&delta=1&apikey=$apikey"));

    if (!is_array($use_data) || !is_array($solar_data) || count($solar_data) != count($use_data)) {
        return false;
    }

    $use_total = 0;
    $solar_total = 0;
    $daily = array();
    $days = array("Mon" => "Monday", "Tue" => "Tuesday", "Wed" => "Wednesday", "Thu" => "Thursday", "Fri" => "Friday", "Sat" => "Saturday", "Sun" => "Sunday");

    for ($i = 0; $i < count($use_data); $i++) {
        $date->setTimestamp($use_data[$i][0] * 0.001);
        $daily[] = array(
            "day" => $days[$date->format('D')] . " " . $date->format("jS"),
            "use_kwh" => $use_data[$i][1],
            "solar_kwh" => $solar_data[$i][1]
        );
        $use_total += $use_data[$i][1];
        $solar_total += $solar_data[$i][1];
    }

    $days_count = max(1, count($daily));
    $usekwhday = $use_total / $days_count;
    $solarkwhday = $solar_total / $days_count;

    $startofpreviousweek_value = json_decode(file_get_contents("$host/feed/value.json?id=$use_kwh&time=" . $window["startofpreviousweek"] . "&apikey=$apikey"));
    $startofweek_value = json_decode(file_get_contents("$host/feed/value.json?id=$use_kwh&time=" . $window["startofweek"] . "&apikey=$apikey"));

    $kwhpreviousweek = $startofweek_value - $startofpreviousweek_value;
    if ($kwhpreviousweek > 0) {
        $prcless = 100 * (1 - ($use_total / $kwhpreviousweek));
    } else {
        $prcless = 0;
    }

    if ($prcless >= 0) {
        $text_lastweek = "You used <b>" . round($prcless) . "% less</b> than the previous week\n";
    } else {
        $prcless = 100 * (1 - ($kwhpreviousweek / $use_total));
        $text_lastweek = "You used <b>" . round($prcless) . "% more</b> than the previous week\n";
    }

    $prcless = 100 * (1 - ($usekwhday / 9.0));
    $text_averagecmp = "";
    if ($prcless >= 0) {
        $text_averagecmp = "You used <b>" . round($prcless) . "% less</b> than UK household average\n";
    }

    $message = EmailReportRunner::view("Modules/emailreport/emails/solarpv/template.php", array(
        "use_total" => $use_total,
        "solar_total" => $solar_total,
        "usekwhday" => $usekwhday,
        "solarkwhday" => $solarkwhday,
        "daily" => $daily,
        "text_lastweek" => $text_lastweek,
        "text_averagecmp" => $text_averagecmp,
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
    $subject .= "Energy Update: " . number_format($usekwhday, 1) . "kWh/d";

    return array(
        "subject" => $subject,
        "message" => $message
    );
}
