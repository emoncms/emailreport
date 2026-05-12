<?php

function emailreport_get_week_window($timezone)
{
    $date = new DateTime();

    try {
        $date->setTimezone(new DateTimeZone($timezone));
    } catch (Exception $e) {
        $date->setTimezone(new DateTimeZone("UTC"));
    }

    $date->setTimestamp(time());
    $date->modify("this monday");
    if ($date->getTimestamp() > time()) {
        $date->modify("last monday");
    }

    $date->modify("-2 weeks");
    $startofpreviousweek = $date->getTimestamp();
    $date->modify("+1 week");
    $startofweek = $date->getTimestamp();
    $date->modify("+1 week");
    $date->modify("-1 day");
    $endofweek = $date->getTimestamp();

    return array(
        "startofpreviousweek" => $startofpreviousweek,
        "startofweek" => $startofweek,
        "endofweek" => $endofweek,
        "date" => $date
    );
}

function emailreport_extract_ukenergy_metrics($ukenergy)
{
    $metrics = array(
        "solarGWh" => 0,
        "solarprc" => 0,
        "ukwindGWh" => 0,
        "windprc" => 0,
        "ukhydroGWh" => 0,
        "hydroprc" => 0
    );

    if (is_object($ukenergy)) {
        $metrics["solarGWh"] = $ukenergy->solarGWh ?? 0;
        $metrics["solarprc"] = $ukenergy->solarprc ?? 0;
        $metrics["ukwindGWh"] = $ukenergy->ukwindGWh ?? 0;
        $metrics["windprc"] = $ukenergy->windprc ?? 0;
        $metrics["ukhydroGWh"] = $ukenergy->ukhydroGWh ?? 0;
        $metrics["hydroprc"] = $ukenergy->hydroprc ?? 0;
    }

    return $metrics;
}
