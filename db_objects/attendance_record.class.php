<?php
include_once 'include/db_object.class.php';
class Attendance_Record extends db_object
{
	// NB This class only exists for the following SQL
	// See Attendance_Record_Set for CRUD functionality of this table

	function getInitSQL($table_name=NULL)
	{
		return "
			CREATE TABLE `attendance_record` (
			  `date` date NOT NULL,
			  `personid` int(11) NOT NULL,
			  `groupid` int(11) NOT NULL,
			  `present` tinyint(1) unsigned NOT NULL,
			  PRIMARY KEY  (`date`,`personid`,`groupid`)
			) ENGINE=InnoDB ;
		";
	}

	public function getForeignKeys()
	{
		return Array('personid' => '`_person` (`id`) ON DELETE CASCADE');
	}
}
?>
