<?php
require_once JETHRO_ROOT.'/include/general.php';
include_once JETHRO_ROOT.'/include/db_object.class.php';
include_once JETHRO_ROOT.'/db_objects/roster_role_assignment.class.php';

class Ical_System
{
	private $_error;

	public function __construct()
	{
	}

	public function run() {
		if (!empty($_REQUEST['mode'])) {
                        $mode = $_REQUEST['mode'];
                        if ($mode == 'roster') {
                            $this->handleRoster();
                        }
                        else {
                            trigger_error('Unknown mode');                            
                        }
                } else {
                        trigger_error('Bad input');                                                			
		}
	}
	
        private function handleRoster()
        {
            if (empty($_REQUEST['uuid']))
            {
                // UUID field not supplied
                trigger_error("Bad format");
            }
            else
            {
		if ($personid = $this->_findCandidateMember($_REQUEST['uuid'])) {
                    $GLOBALS['roster_personid'] = $personid;
                    $rallocs = Roster_Role_Assignment::getUpcomingAssignments($personid, '6 months');
                    $GLOBALS['roster_assignments'] = $rallocs;

                    require_once('templates/roster_ical.template.php');
                    exit;			
		} else {
                    // This uuid does not map to a person
                    trigger_error("Bad ID");
		}
            }
        }
	
	/**
	 * Find a person record to which we could attach a member account
	 * @param string $uuid The UUID of the member
	 * @return mixed.
	 */
	private function _findCandidateMember($uuid)
	{
		$db =& $GLOBALS['db'];
		$sql = 'SELECT pu.personid from person_uuid pu, _person p ' .
                        'where p.status <> "archived" ' .
                        'and p.id = pu.personid ' .
                        'and pu.uuid = ' . $db->quote($uuid);
		$res = $db->queryRow($sql);
       		check_db_result($res);
                foreach ($res as $row) {
                    return $row;
                }
                return null;
	}
        
        public function featureEnabled($feature) 
	{
		$enabled_features = explode(',', strtoupper(ENABLED_FEATURES));
		return in_array(strtoupper($feature), $enabled_features);
	}
}
