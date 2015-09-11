<?php
include_once 'include/db_object.class.php';
class Person_UUID extends db_object
{
	var $_save_permission_level = PERM_EDITPERSON;

	function _getFields()
	{
		return Array(
			'personid'		=> Array(
									'type'		=> 'reference',
                                                                        'references'    => '_person',
                                                                        'label'         => 'Person',
                                                                        'allow_empty'  => FALSE,
								   ),
			'uuid'	=> Array(
									'type'		=> 'text',
									'width'		=> 64,
									'maxlength'	=> 64,
									'allow_empty'	=> false,
									'initial_cap'	=> false,
									'trim'			=> TRUE,
								   ),
                    
		);
	}

	function __construct($id=NULL) {
		parent::__construct($id);

	}

	function getInitSQL()
	{
		return Array(
				parent::getInitSQL('_person_uuid'),
				"CREATE TABLE person_uuid (
					personid INT PRIMARY KEY,
					uuid VARCHAR(64) NOT NULL,
					CONSTRAINT UNIQUE INDEX (uuid),
                                        constraint person_uuid_fk foreign key (personid) references _person (id) on delete cascade
				) ENGINE=InnoDB;",
		);
	}


	function toString()
	{
		return $this->values['uuid'];
	}

	function getPersonID()
	{
		$db =& $GLOBALS['db'];
		$sql = 'SELECT personid
                    FROM person_uuid where uuid = ' . $db->quote($this->values['uuid']);
                $res = $db->queryOne($sql);
		check_db_result($res);
		return $res;
	}
	
	function getUUID()
	{
		$db =& $GLOBALS['db'];
		$sql = 'SELECT uuid
                    FROM person_uuid where personid = ' . $db->quote($this->values['personid']);
                $res = $db->queryOne($sql);
		check_db_result($res);
		return $res;
	}
        
        function generateUUID()
        {
            $this->delete();
            $db =& $GLOBALS['db'];
            $this->values['uuid'] = generate_random_string(60);
            $SQL = 'insert into person_uuid values '
                . '(' . $db->quote($this->values['personid']) . ', '
                . $db->quote($this->values['uuid']) . ')';
            $res = $GLOBALS['db']->exec($SQL);
            check_db_result($res);
        }
		
	function delete()
	{
		$db =& $GLOBALS['db'];
		$sql = 'DELETE FROM person_uuid WHERE personid = ' . $db->quote($this->values['personid']);
                $res = $db->queryOne($sql);
		check_db_result($res);
	}
}
