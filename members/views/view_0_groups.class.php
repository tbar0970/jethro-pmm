<?php
class View__Groups extends View
{
	private $group = NULL;
	// TRUE if the current member may view this group's directory listing
	private $canView = FALSE;

	function getTitle()
	{
		if ($this->group) return $this->group->getValue('name');
	}

	function processView()
	{
		$this->group = $GLOBALS['system']->getDBObject('person_group', (int)$_REQUEST['groupid']);
		if (empty($this->group)) return;
		$GLOBALS['system']->includeDBClass('person_group');
		// Members may only browse the directory of could-share groups they belong
		// to (#792).  Before consent was per-person this page listed every member
		// of any shared group without a membership or consent check.
		$myGroups = Person_Group::getGroups($GLOBALS['user_system']->getCurrentMember('id'));
		$this->canView = !empty($myGroups[$this->group->id]) && !empty($myGroups[$this->group->id]['share_member_details']);
	}

	function printView()
	{
		if (empty($_REQUEST['groupid'])) {
			trigger_error("Group ID missing");
			return;
		}
		if (empty($this->group)) {
			trigger_error("Group not found");
			return;
		}
		if (!$this->canView) {
			?>
			<p><em>This group is not available.  You can view the member directory of groups that you belong to which have sharing enabled.</em></p>
			<?php
			return;
		}

		$persons = $this->getDirectoryMembers($this->group->id);
		$emails = Array();
		foreach ($persons as $member) {
			if ($e = $member['email']) $emails[] = $e;
		}

		include 'templates/member_list.template.php';

		if (count($persons) < EMAIL_CHUNK_SIZE) {
			echo '<a href="mailto:'.implode(',', $emails).'">Email all members</a>';
		}

	}

	/**
	 * Active members of one group who have consented to share their details
	 * with that group, plus the viewer themself.
	 *
	 * Local to this view: the shared member view cannot be reused here because
	 * its consent join matches a person's consent for *any* group shared with
	 * the viewer, whereas this page must apply this group's consent row.
	 *
	 * @return array<int,array> personid => row, keyed by person id
	 */
	private function getDirectoryMembers($groupid)
	{
		$db =& $GLOBALS['db'];
		$viewerid = (int)$GLOBALS['user_system']->getCurrentMember('id');
		$contactMask = '(CASE WHEN psc.personid IS NULL OR (psc.inherit_mask & 2) <> 0 THEN COALESCE(pscd.data_permissions, 0) ELSE psc.data_permissions END)';
		$visibleMask = '(CASE WHEN psc.personid IS NULL OR (psc.inherit_mask & 1) <> 0 THEN COALESCE(pscd.data_permissions, 0) ELSE psc.data_permissions END)';
		$sql = 'SELECT mp.id, mp.first_name, mp.last_name, mp.gender, mp.age_bracketid,
				mp.congregationid,
				CASE WHEN mp.id = '.$viewerid.' OR (('.$contactMask.') & '.DATA_PERM_VIEWEMAIL.') <> 0 THEN mp.email ELSE \'\' END AS email,
				CASE WHEN mp.id = '.$viewerid.' OR (('.$contactMask.') & '.DATA_PERM_VIEWMOBILE.') <> 0 THEN mp.mobile_tel ELSE \'\' END AS mobile_tel,
				CASE WHEN mp.id = '.$viewerid.' OR (('.$contactMask.') & '.DATA_PERM_VIEWMOBILE.') <> 0 THEN mp.work_tel ELSE \'\' END AS work_tel,
				mp.familyid
				FROM _person mp
				JOIN person_status mps ON mps.id = mp.status
				JOIN person_group_membership pgm ON pgm.personid = mp.id AND pgm.groupid = '.(int)$groupid.'
				LEFT JOIN _person_share_consent psc ON psc.personid = mp.id AND psc.groupid = '.(int)$groupid.'
				LEFT JOIN _person_share_consent pscd ON pscd.personid = mp.id AND pscd.groupid = 0
				WHERE (NOT mps.is_archived)
					AND ((('.$visibleMask.') & 1) <> 0 /* DATA_PERM_VIEWNAME */
						OR mp.id = '.$viewerid.')
				ORDER BY mp.last_name, mp.first_name';
		return $db->queryAll($sql, null, null, true);
	}

}
