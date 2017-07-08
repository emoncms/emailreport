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
    
    public function __construct($mysqli,$emailreports)
    {
        $this->mysqli = $mysqli;
        $this->emailreports = $emailreports;
    }
    
    public function validate_config($report,$config_in) 
    {
        if (!isset($this->emailreports[$report])) return array("valid"=>false, "message"=>"Error: report does not exist");
        if ($config_in==null) return array("valid"=>false, "message"=>"Error: decoding config json");
        $config_options = $this->emailreports[$report];
        
        $config = array();
        
        foreach ($config_options as $key=>$option) {
        
            if ($option["type"]=="checkbox") {
                if (intval($config_in->$key)!=$config_in->$key) return array("valid"=>false, "message"=>"Error: $key format error");
                $config[$key] = (int) $config_in->$key;
            }
            
            if ($option["type"]=="text") {
                if (preg_replace('/[^\w\s-@.]/','',$config_in->$key)!=$config_in->$key) return array("valid"=>false, "message"=>"Error: $key format error");
                $config[$key] = $config_in->$key;
            }
            
            if ($option["type"]=="feedselect") {
                if (intval($config_in->$key)!=$config_in->$key) return array("valid"=>false, "message"=>"Error: $key format error");
                $config[$key] = (int) $config_in->$key;
            }
        }
        
        return array("valid"=>true, "config"=>$config);
    }
    
    public function set($userid,$report,$config_in)
    {
        // Basic validation
        $userid = (int) $userid;
        
        $result = $this->validate_config($report,$config_in);
        if (!$result["valid"]) return $result["message"]; else $config = $result["config"];
        
        /*
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return array('success'=>false, 'message'=>_("Email address format error"));
        
        */
        
        
        $config = json_encode($config);
        
        $result = $this->mysqli->query("SELECT `userid` FROM emailreport WHERE `userid`='$userid' AND `report`='$report'");
        if ($result->num_rows) {
        
            $stmt = $this->mysqli->prepare("UPDATE emailreport SET `config`=? WHERE `userid`=? AND `report`=?");
            $stmt->bind_param("sis",$config,$userid,$report);
            if (!$stmt->execute()) {
                return array('success'=>false, 'message'=>"Error saving email report settings");
            }
            return array('success'=>true);
            
        } else {
            $stmt = $this->mysqli->prepare("INSERT INTO emailreport (`userid`,`report`,`config`) VALUES (?,?,?)");
            $stmt->bind_param("iss", $userid,$report,$config);
            if (!$stmt->execute()) {
                return array('success'=>false, 'message'=>"Error saving email report settings");
            }
            return array('success'=>true);
        }
    }
    
    public function get($userid,$report)
    {
        $userid = (int) $userid;
        if (!isset($this->emailreports[$report])) return "Error: report does not exist";
        
        $result = $this->mysqli->query("SELECT config FROM emailreport WHERE `userid`='$userid' AND `report`='$report'");
        if ($row = $result->fetch_object()) {
            return json_decode($row->config);
        } else {
            return new stdClass();
        }
    }
}
