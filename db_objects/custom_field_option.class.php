<?php
include_once 'include/db_object.class.php';
class Custom_Field_Option extends db_object
{
	protected $_save_permission_level = PERM_SYSADMIN;

	public function __construct($id=NULL) {
		parent::__construct($id);
	}

	protected static function _getFields()
	{
		return Array(
			'value'	=> Array(
							'type'		=> 'text',
							'width'		=> 40,
							'maxlength'	=> 128,
							'allow_empty'	=> FALSE,
						),
			'rank'	=> Array(
							'type'			=> 'int',
							'editable'		=> true,
							'allow_empty'	=> false,
						),
			'fieldid'	=> Array(
							'type'		=> 'reference',
							'references' => 'custom_field',
							'allow_empty'	=> false,
						   ),
		);
	}

	public function getForeignKeys()
	{
		return Array(
			'fieldid'  => 'custom_field(id) ON DELETE CASCADE',
		);
	}

	function delete()
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		parent::delete();

		// delete data here

		$GLOBALS['system']->doTransaction('COMMIT');
	}

	function printFieldInterface($fieldname, $prefix='')
	{
		return parent::printFieldInterface($fieldname, $prefix);
	}
}