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

    /**
     * Deliver one generated report.
     *
     * There used to be two paths here, a redis queue for emoncms.org and a
     * direct send everywhere else, because the two installs delivered email in
     * different ways. Which transport is used is now settings['email']
     * ['transport'], so this module no longer needs to know which install it
     * is running on.
     *
     * Returns the transport's result so a caller sending in bulk can pace
     * itself and report what happened, rather than sending into silence.
     *
     * @param string $emailsto  one address, or several separated by commas
     * @param array  $emailreport  generated report, subject and message
     * @return array array('success'=>bool, 'message'=>string, 'status'=>int|null)
     */
    public static function send_delivery($emailsto, $emailreport)
    {
        require_once "Lib/email.php";

        $recipients = array_filter(array_map('trim', explode(",", (string) $emailsto)));
        if (empty($recipients)) {
            return array('success'=>false, 'message'=>"No recipient");
        }

        $email = new Email();
        $email->to($recipients);
        $email->subject($emailreport['subject']);
        $email->body($emailreport['message']);
        return $email->send();
    }
}
