<?php

return array(
    "key" => "home-energy",
    "label" => "Home Energy Consumption",
    "generator" => "emailreport_generate_home",
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
        )
    )
);
