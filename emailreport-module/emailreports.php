<?php

$emailreports = array(
    "home-energy"=>array(
        "enable"=>array(
            "description"=>"Enable weekly email report", 
            "type"=>"checkbox"
        ),
        "title"=>array(
            "description"=>"Email title:",
            "type"=>"text"
        ),
        "email"=>array(
            "description"=>"Email address to send email to:",
            "type"=>"text"
        ),
        "use_kwh"=>array(
            "description"=>"Select cumulative kwh consumptiion feed:", 
            "type"=>"feedselect",
            "autoname"=>"use_kwh"
        )
    ),
    
    "solar-pv"=>array(
        "enable"=>array(
            "description"=>"Enable weekly email report", 
            "type"=>"checkbox"
        ),
        "title"=>array(
            "description"=>"Email title:",
            "type"=>"text"
        ),
        "email"=>array(
            "description"=>"Email address to send email to:",
            "type"=>"text"
        ),
        "use_kwh"=>array(
            "description"=>"Select cumulative kwh consumptiion feed:", 
            "type"=>"feedselect",
            "autoname"=>"use_kwh"
        ),
        "solar_kwh"=>array(
            "description"=>"Select cumulative kwh solar feed:", 
            "type"=>"feedselect",
            "autoname"=>"solar_kwh"
        )
    )
);
