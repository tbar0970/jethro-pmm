<?php
include_once 'include/db_object.class.php';
class Custom_Field_Value extends db_object
{
	function getInitSQL($table_name=NULL) {
		return '
			CREATE TABLE custom_field_value (
				id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
				personid INT(11) NOT NULL,
				fieldid INT(11) NOT NULL,
				value_text VARCHAR(255) DEFAULT NULL,
				value_date CHAR(10) DEFAULT NULL,
				value_optionid INT(11) DEFAULT NULL
			) Engine=InnoDB;';
	}

	/**
	 *
	 * @return Array (columnName => referenceExpression) eg 'tagid' => 'tagoption.id ON DELETE CASCADE'
	 */
	public function getForeignKeys()
	{
		return Array(
			'personid' => '_person(id) ON DELETE CASCADE',
			'fieldid'  => 'custom_field(id) ON DELETE CASCADE',
			'value_optionid' => 'custom_field_option(id) ON DELETE CASCADE',
		);
	}
}