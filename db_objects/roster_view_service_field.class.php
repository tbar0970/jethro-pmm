<?php
include_once 'include/db_object.class.php';
class roster_view_service_field extends db_object
{
	// NB This class only exists for the following SQL
	// It has no ID
	function getInitSql($tablename=NULL)
	{
		return 'create table roster_view_service_field (
					roster_view_id int(5) not null,
					congregationid int(5) not null,
					service_field varchar(32) not null,
					order_num int(5) not null,
					constraint primary key (congregationid, roster_view_id, service_field)
				) ENGINE=InnoDB ;';
	}
}