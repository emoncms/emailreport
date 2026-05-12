<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class EmailReportRegistry
{
    private static $registry = null;

    private static function is_valid_definition($definition)
    {
        if (!is_array($definition)) {
            return false;
        }

        return isset($definition["key"], $definition["label"], $definition["config"], $definition["generator"])
            && is_string($definition["key"])
            && is_string($definition["label"])
            && is_array($definition["config"])
            && is_string($definition["generator"]);
    }

    private static function load_definition($folder)
    {
        $manifest = __DIR__ . "/emails/" . $folder . "/config.php";
        if (!file_exists($manifest)) {
            return null;
        }

        $definition = include $manifest;
        if (!self::is_valid_definition($definition)) {
            return null;
        }

        if (!isset($definition["include"])) {
            $definition["include"] = "Modules/emailreport/emails/" . $folder . "/report.php";
        }

        return $definition;
    }

    public static function get_registry()
    {
        if (self::$registry !== null) {
            return self::$registry;
        }

        self::$registry = array();
        $base = __DIR__ . "/emails";
        if (!is_dir($base)) {
            return self::$registry;
        }

        $entries = scandir($base);
        if (!is_array($entries)) {
            return self::$registry;
        }

        foreach ($entries as $entry) {
            if ($entry === "." || $entry === "..") {
                continue;
            }

            if (!is_dir($base . "/" . $entry)) {
                continue;
            }

            $definition = self::load_definition($entry);
            if ($definition === null) {
                continue;
            }

            self::$registry[$definition["key"]] = array(
                "label" => $definition["label"],
                "config" => $definition["config"],
                "generator" => $definition["generator"],
                "include" => $definition["include"]
            );
        }

        return self::$registry;
    }

    public static function get_config_options()
    {
        $registry = self::get_registry();
        $config = array();

        foreach ($registry as $key => $report) {
            $config[$key] = $report["config"];
        }

        return $config;
    }

    public static function get_report_labels()
    {
        $registry = self::get_registry();
        $labels = array();

        foreach ($registry as $key => $report) {
            $labels[$key] = $report["label"];
        }

        return $labels;
    }
}
