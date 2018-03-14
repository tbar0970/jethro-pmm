<?php
abstract class Abstract_User_System
{
	/**
	 * Get details of the currently-authorised person
	 * (may be via a user account (user_system) or a member account (member_user_system).
	 * Call this method when you don't care how they've logged in.
	 * @param string $field	Particular field to return; null=return all fields
	 * @return mixed
	 */

	abstract public function getCurrentPerson();

	public function getCurrentUser($field='')
	{
		return NULL;
	}

	public function getCurrentMember($field='')
	{
		return NULL;
	}

	public function getCurrentRestrictions($type=NULL)
	{
		return Array();
	}

}
