<?php

    $domain = "messages";
    bindtextdomain($domain, "Modules/vis/locale");
    bind_textdomain_codeset($domain, 'UTF-8');

    $menu_dropdown[] = array('name'=> dgettext($domain, "Email Reports"),'icon'=>'icon-envelope', 'path'=>"emailreport" , 'session'=>"write", 'order' => 21);