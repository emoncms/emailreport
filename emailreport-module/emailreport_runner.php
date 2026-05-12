<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

require_once "Modules/emailreport/emailreport_registry.php";

class EmailReportRunner
{
    public static function build_generation_config($config, $context = array())
    {
        if (!is_array($config)) {
            $config = array();
        }

        if (!is_array($context)) {
            $context = array();
        }

        // Generation does not need delivery/enable flags.
        unset($config["enable"], $config["email"]);

        // Context values (host, apikey, timezone, ukenergy) override config keys.
        return array_merge($config, $context);
    }

    public static function view($filepath, array $args)
    {
        extract($args);
        ob_start();
        include $filepath;
        return ob_get_clean();
    }

    public static function generate_by_type($report, $config)
    {
        $registry = EmailReportRegistry::get_registry();
        if (!isset($registry[$report])) {
            return false;
        }

        $definition = $registry[$report];
        require_once $definition["include"];

        $generator = $definition["generator"];
        if (!function_exists($generator)) {
            return false;
        }

        return call_user_func($generator, $config);
    }

    public static function send_delivery($redis, $emailto, $emailreport, $emoncmsorg = true)
    {
        if ($emoncmsorg) {
            self::send_queue($redis, $emailto, $emailreport);
        } else {
            self::send_swift($emailto, $emailreport);
        }
    }

    public static function send_queue($redis, $emailto, $emailreport)
    {
        $redis->rpush("emailqueue", json_encode(array(
            "emailto" => $emailto,
            "type" => "weeklyenergyupdate",
            "subject" => $emailreport['subject'],
            "message" => $emailreport['message']
        )));
    }

    public static function send_swift($emailsto, $emailreport)
    {
        require "Lib/email.php";

        $email = new Email();
        $emailsto = explode(",", $emailsto);
        $email->to($emailsto);
        $email->subject($emailreport['subject']);
        $email->body($emailreport['message']);
        $email->send();
    }
}
