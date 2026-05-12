<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

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

function emailreport_build_unsubscribe_token($userid, $report, $apikey_read)
{
    return hash_hmac('sha256', (int) $userid . '|' . $report, (string) $apikey_read);
}

function emailreport_build_unsubscribe_url($host, $userid, $report, $apikey_read)
{
    if (!$host || !$userid || !$report || !$apikey_read) {
        return '';
    }

    $base = rtrim($host, '/');
    $token = emailreport_build_unsubscribe_token($userid, $report, $apikey_read);

    return $base . '/emailreport/unsubscribe?userid=' . (int) $userid . '&report=' . rawurlencode($report) . '&token=' . rawurlencode($token);
}

function emailreport_render_unsubscribe_footer($unsubscribe_url)
{
    if (!$unsubscribe_url) {
        return '';
    }

    $safe_url = htmlspecialchars($unsubscribe_url, ENT_QUOTES, 'UTF-8');

    return '<div style="margin-top:20px; padding-top:10px; border-top:1px solid #ddd; font-size:13px; color:#666">'
        . 'If you no longer want these emails, you can <a href="' . $safe_url . '">unsubscribe</a>.'
        . '</div>';
}
