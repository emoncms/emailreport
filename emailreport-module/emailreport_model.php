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

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }
    
    public function set($userid,$title,$weekly,$email,$feedid)
    {
        // Basic validation
        $userid = (int) $userid;
        $weekly = (int) $weekly;
        $feedid = (int) $feedid;
        $title = preg_replace('/[^\w\s-]/','',$title);
        if ($userid<1) return array('success'=>false, "message"=>"Invalid user");
        if ($feedid<1) return array('success'=>false, "message"=>"Invalid feed");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return array('success'=>false, 'message'=>_("Email address format error"));
        
        $result = $this->mysqli->query("SELECT `userid` FROM emailreport WHERE `userid`='$userid'");
        if ($result->num_rows) {
        
            $stmt = $this->mysqli->prepare("UPDATE emailreport SET `title`=?, `weekly`=?, `email`=?, `feedid`=? WHERE `userid`=?");
            $stmt->bind_param("sisii",$title,$weekly,$email,$feedid,$userid);
            if (!$stmt->execute()) {
                return array('success'=>false, 'message'=>"Error saving emailreport settings");
            }
            return array('success'=>true);
            
        } else {
            $stmt = $this->mysqli->prepare("INSERT INTO emailreport (`userid`,`title`,`weekly`,`email`,`feedid`) VALUES (?,?,?,?)");
            $stmt->bind_param("isisi", $userid,$title,$weekly,$email,$feedid);
            if (!$stmt->execute()) {
                return array('success'=>false, 'message'=>"Error saving emailreport settings");
            }
            return array('success'=>true);
        }
    }
    
    public function get($userid)
    {
        $userid = (int) $userid;
        $result = $this->mysqli->query("SELECT title,weekly,email,feedid FROM emailreport WHERE `userid`='$userid'");
        if ($row = $result->fetch_object()) {
            return $row;
        } else {
            return new stdClass();
        }
    }
}
