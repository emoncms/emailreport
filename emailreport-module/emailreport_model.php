<?php

/*
     All Emoncms code is released under the GNU Affero General Public License.
     See COPYRIGHT.txt and LICENSE.txt.

     ---------------------------------------------------------------------
     Emoncms - open source energy visualisation
     Part of the OpenEnergyMonitor project:
     http://openenergymonitor.org

*/

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class EmailReport
{
    private $mysqli;
    private $emailreports;

    // Standard validation error payload used by validate_config().
    private function validation_error($message)
    {
        return array("valid" => false, "message" => $message);
    }

    // Standard persistence error payload used by set().
    private function save_error()
    {
        return array('success' => false, 'message' => "Error saving email report settings");
    }

    // Centralized integer validation for numeric option types.
    private function is_valid_int($value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    // Returns true/false when the lookup succeeds, null on DB failure.
    private function report_exists($userid, $report)
    {
        $stmt = $this->mysqli->prepare("SELECT `userid` FROM emailreport WHERE `userid`=? AND `report`=?");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("is", $userid, $report);
        if (!$stmt->execute()) {
            return null;
        }

        $stmt->store_result();
        return $stmt->num_rows > 0;
    }

    // Updates an existing report config row.
    private function update_config($userid, $report, $config)
    {
        $stmt = $this->mysqli->prepare("UPDATE emailreport SET `config`=? WHERE `userid`=? AND `report`=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("sis", $config, $userid, $report);
        return $stmt->execute();
    }

    // Inserts a new report config row.
    private function insert_config($userid, $report, $config)
    {
        $stmt = $this->mysqli->prepare("INSERT INTO emailreport (`userid`,`report`,`config`) VALUES (?,?,?)");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("iss", $userid, $report, $config);
        return $stmt->execute();
    }

    public function __construct($mysqli, $emailreports)
    {
        $this->mysqli = $mysqli;
        $this->emailreports = $emailreports;
    }

    public function validate_config($report, $config_in)
    {
        // Ensure report exists and posted JSON decoded into an object.
        if (!isset($this->emailreports[$report])) {
            return $this->validation_error("Error: report does not exist");
        }

        if ($config_in == null || !is_object($config_in)) {
            return $this->validation_error("Error: decoding config json");
        }

        $config_options = $this->emailreports[$report];

        $config = array();

        // Validate only configured keys and normalize values for storage.
        foreach ($config_options as $key => $option) {
            if (!property_exists($config_in, $key)) {
                if (!empty($option["optional"])) {
                    $config[$key] = $option["default"] ?? null;
                    continue;
                }
                return $this->validation_error("Error: missing $key");
            }

            $value = $config_in->$key;

            switch ($option["type"]) {
                case "checkbox":
                case "feedselect":
                    if (!$this->is_valid_int($value)) {
                        return $this->validation_error("Error: $key format error");
                    }

                    $config[$key] = (int) $value;
                    break;

                case "text":
                    if (!is_string($value)) {
                        return $this->validation_error("Error: $key format error");
                    }

                    if (preg_replace('/[^\w\s\-@.,]/', '', $value) != $value) {
                        return $this->validation_error("Error: $key format error");
                    }

                    $config[$key] = $value;
                    break;

                case "email":
                    if (!is_string($value)) {
                        return $this->validation_error("Error: $key format error");
                    }

                    if ($value !== "") {
                        // Restrict characters before splitting and validating each address.
                        if (preg_replace('/[^\w\s\-@.,]/', '', $value) != $value) {
                            return $this->validation_error("Error: $key format error");
                        }

                        $emails = explode(",", $value);
                        if (count($emails) > 5) {
                            return $this->validation_error("Error: max number of email addresses limited to 5");
                        }

                        foreach ($emails as $email) {
                            if (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                                return array('valid' => false, 'message' => "Error: Email address format error");
                            }
                        }
                    }

                    $config[$key] = $value;
                    break;
            }
        }

        return array("valid" => true, "config" => $config);
    }

    public function set($userid, $report, $config_in)
    {
        // Normalize user id and validate report-specific config payload.
        $userid = (int) $userid;

        $result = $this->validate_config($report, $config_in);
        if (!$result["valid"]) {
            return $result["message"];
        }
        $config = $result["config"];

        // Store validated config as JSON.
        $config = json_encode($config);

        // Keep update/insert decision explicit for current table design.
        $exists = $this->report_exists($userid, $report);
        if ($exists === null) {
            return $this->save_error();
        }

        if ($exists) {
            if (!$this->update_config($userid, $report, $config)) {
                return $this->save_error();
            }

            return array('success' => true);
        }

        if (!$this->insert_config($userid, $report, $config)) {
            return $this->save_error();
        }

        return array('success' => true);
    }

    public function get($userid, $report)
    {
        // Limit reads to known report types and normalized user id.
        $userid = (int) $userid;
        if (!isset($this->emailreports[$report])) {
            return "Error: report does not exist";
        }

        $stmt = $this->mysqli->prepare("SELECT config FROM emailreport WHERE `userid`=? AND `report`=?");
        if (!$stmt) {
            return new stdClass();
        }

        $stmt->bind_param("is", $userid, $report);
        if (!$stmt->execute()) {
            return new stdClass();
        }

        // Return decoded config when present; otherwise an empty object.
        $config = null;
        $stmt->bind_result($config);
        if ($stmt->fetch()) {
            return json_decode($config);
        }

        return new stdClass();
    }
}
