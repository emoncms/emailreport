<?php

return array(
    "key" => "solar-pv",
    "label" => "Solar PV & Self consumption",
    "generator" => "emailreport_generate_solarpv",
    "config" => array(
        "enable" => array(
            "description" => "Enable weekly email report",
            "type" => "checkbox"
        ),
        "title" => array(
            "description" => "Email title:",
            "type" => "text"
        ),
        "email" => array(
            "description" => "Email address to send email to:",
            "type" => "email"
        ),
        "use_kwh" => array(
            "description" => "Select cumulative kwh consumptiion feed:",
            "type" => "feedselect",
            "autoname" => "use_kwh"
        ),
        "solar_kwh" => array(
            "description" => "Select cumulative kwh solar feed:",
            "type" => "feedselect",
            "autoname" => "solar_kwh"
        ),
        "show_ukenergy" => array(
            "description" => "Show UK energy statistics",
            "type" => "checkbox",
            "optional" => true,
            "default" => 0
        )
    )
);
