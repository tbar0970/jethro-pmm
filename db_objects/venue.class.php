<?php
class Venue extends db_object
{
	/**
	 * Instances of this class represent venues at which people can check in.
	 */
	protected $_load_permission_level = NULL;
	protected $_save_permission_level = PERM_SYSADMIN;

	protected static function _getFields()
	{

		$fields = Array(
			'name'	=> Array(
									'type'				=> 'text',
									'note'				=> 'Displayed on the check-in page',
								   ),
			'set_attendance'		=> Array(
									'type'		=> 'select',
									'options' => Array('No', 'Yes'),
									'label'	=> 'Set attendance?',
									'note' => 'Whether to attempt to set congregational attendance based on checkins to this venue. Persons will be matched on email and/or mobile, work or home phone (not name).',
								   ),
			'thanks_message'		=> Array(
									'type'	=> 'html',
									'default' => 'Thank you for checking in!',
									'note'	=> 'Message to show after a successful check in',
									),
			'is_archived'	=> Array(
									'type' => 'select',
									'options'	=> Array('Active', 'Archived'),
									'label' => 'Status',
									'default'	=> 0,
									'note' => 'Archived venues can no longer be checked into'
								),
		);
		return $fields;
	}


}