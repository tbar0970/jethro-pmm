<?php
include_once 'include/db_object.class.php';
class roster_role_volunteer_team extends db_object
{
	// NB This class only exists for the following SQL
	// It has no ID
	function getInitSql($tablename=NULL)
	{
		return 'CREATE TABLE `roster_role_volunteer_team` (
			`roster_role_id` INT NOT NULL,
			`person_group_id` INT NOT NULL,
			PRIMARY KEY (roster_role_id, person_group_id),
			CONSTRAINT `roster_role_volunteer_team_roster_role` FOREIGN KEY (`roster_role_id`) REFERENCES `roster_role` (`id`),
			CONSTRAINT `roster_role_volunteer_team_person_group` FOREIGN KEY (`person_group_id`) REFERENCES `_person_group` (`id`)
		) ENGINE=InnoDB;';
	}
}