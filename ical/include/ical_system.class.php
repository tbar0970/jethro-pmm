<?php
require_once JETHRO_ROOT.'/include/general.php';
include_once JETHRO_ROOT.'/include/db_object.class.php';
include_once JETHRO_ROOT.'/db_objects/roster_role_assignment.class.php';
include_once JETHRO_ROOT.'/db_objects/service.class.php';

class Ical_System
{
        private $rosterAssignments;
        private $personId;
        private $services;
        
        /**
         * Get the instance of the Ical System.
         * 
         * Singleton pattern.
         * 
         * @param type $base_dir The base directory.
         * @return \Ical_System
         */
        public static function get($base_dir=NULL)
        {
            static $instance = NULL;
            if ($instance == NULL) {
                $instance = new Ical_System($base_dir);
            }
            
            return $instance;                
        }

	private function __construct()
	{
	}

	public function run() {
            if (!empty($_REQUEST['mode'])) {
                $mode = $_REQUEST['mode'];
                if ($mode == 'roster') {
                    $this->handleRoster();
                } else if ($mode == 'services') {
                    $this->handleServices();
                }
                else {
                    http_response_code(404);
                    ?><p>Not found</p><?php
                    exit;
                }
            } else {
                http_response_code(404);
                ?><p>Not found</p><?php
                exit;
            }
	}
	
        public function getPersonId()
        {
            return $this->personId;
        }
        
        public function getRosterAssignments()
        {
            return $this->rosterAssignments;
        }

        public function getServices()
        {
            return $this->services;
        }

        private function handleServices()
        {
            $this->services = Service::findAllByDateAndCong(time());
            if (empty($this->services)) {
                http_response_code(404);
                ?><p>Not found</p><?php
                exit;                                                
            }
            else {
                require_once('templates/service_ical.template.php');
                exit;
            }
        }
        
        private function handleRoster()
        {
            if (empty($_REQUEST['uuid']))
            {
                // UUID field not supplied
                http_response_code(404);
                ?><p>Not found</p><?php
                exit;
            }
            else
            {
		if (($this->personId = $this->_findCandidateMember($_REQUEST['uuid']))) {
                    $this->rosterAssignments = Roster_Role_Assignment::getUpcomingAssignments($this->personId, '6 months');

                    require_once('templates/roster_ical.template.php');
                    exit;			
		} else {
                    $this->personId = NULL;
                    $this->rosterAssignments = NULL;
                    // This uuid does not map to a person
                    http_response_code(404);
                    ?><p>Not found</p><?php
                    exit;
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
                if ($res == null) {
                    return null;
                }
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
