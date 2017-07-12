<?php
include_once 'include/db_object.class.php';
class roster_view_role_membership extends db_object
{
	// NB This class only exists for the following SQL
	// It has no ID
	function getInitSql($tablename=NULL)
	{
		return 'create table roster_view_role_membership (
					roster_role_id int(5) not null,
					roster_view_id int(5) not null,
					order_num int(5) not null,
					constraint primary key (roster_role_id, roster_view_id)
				) ENGINE=InnoDB ;';
	}
}
?>
