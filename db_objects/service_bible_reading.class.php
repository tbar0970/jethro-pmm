<?php
include_once 'include/db_object.class.php';
class service_bible_reading extends db_object
{
	// NB This class only exists for the following SQL
	// It has no ID
	function getInitSql($tablename=NULL)
	{
		return 'create table service_bible_reading (
					service_id int(5) not null,
					order_num int(1) not null,
					bible_ref varchar(32) not null,
					to_read tinyint(1) unsigned,
					to_preach tinyint(1) unsigned,
					constraint primary key (service_id, order_num)
				) ENGINE=InnoDB ;';
	}
}