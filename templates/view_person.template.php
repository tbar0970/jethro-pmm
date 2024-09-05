<?php
include_once 'size_detector.class.php';
// ------------------------ MODALS --------------------------

// -------ACTION PLAN MODAL --------- //
$GLOBALS['system']->includeDBClass('action_plan');
$plan_chooser = Action_Plan::getMultiChooser('planid', Array());
if ($plan_chooser) {
	?>
	<div id="action-plan-modal" class="modal hide fade" role="dialog" aria-hidden="true">
		<form method="post" action="?view=_execute_plans&personid[]=<?php echo (int)$person->id; ?>">
			<div class="modal-header">
				<h4><?php echo _('Execute Action Plan for ')?><?php $person->printFieldValue('name'); ?></h4>
			</div>
			<div class="modal-body">
				<p><?php echo $plan_chooser; ?></p>
				<p><?php echo _('Reference date for plans: ')?><?php print_widget('plan_reference_date', Array('type' => 'date'), NULL); ?></p>
			</div>
			<div class="modal-footer">
				<button type="submit" class="btn" accesskey="s">Go</button>
				<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
			</div>
		</form>
	</div>
	<?php
}

// -------ADD TO GROUP MODAL --------- //
$can_add_group = FALSE;
$GLOBALS['system']->includeDBClass('person_group');
$groups = Person_Group::getGroups($person->id, TRUE);
if ($GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
	?>
	<div id="add-group-modal" class="modal hide fade" role="dialog" aria-hidden="true">
		<form method="post">
			<input type="hidden" name="view" value="_edit_group" />
			<input type="hidden" name="personid" value="<?php echo $person->id; ?>" />
			<input type="hidden" name="action" value="add_member" />
			<input type="hidden" name="overwrite_membership" value="1" />
			<input type="hidden" name="back_to" value="persons" />

			<div class="modal-header">
				<h4><?php echo _('Add ')?> <?php $person->printFieldValue('name'); ?><?php echo _(' to a group');?></h4>
			</div>
			<div class="modal-body">
				<?php
				$GLOBALS['system']->includeDBClass('person_group');
				echo _('Add as a ');
				Person_Group::printMembershipStatusChooser('membership_status');
				echo _(' of ');
				$can_add_group = Person_Group::printChooser('groupid', 0, array_keys($groups));
				?>
			</div>
			<div class="modal-footer">
				<input type="submit" class="btn" value="Go" accesskey="s" onclick="if (!$('[name=groupid]').val()) { alert('<?php echo _('Choose a group first'); ?>'); return false; }" />
				<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
			</div>
		</form>
	</div>
	<?php
}

// -------- CHECK PERMISSIONS AND ASSEMBLE DATA ------------- //

$accordion = SizeDetector::getWidth() && SizeDetector::isNarrow();

$tabs = Array(
	'basic' => _('Basic Details')
);
if ($GLOBALS['user_system']->havePerm(PERM_VIEWMYNOTES)) {
	$notes = $person->getNotesHistory();
	$tabs['notes'] = _('Notes').' ('.count($notes).')';
}
if ($can_add_group || (count($groups) > 1)) {
	$tabs['groups'] = _('Groups').' ('.count($groups).')';
}
if ($GLOBALS['user_system']->havePerm(PERM_VIEWATTENDANCE)) {
	$tabs['attendance'] = _('Attendance');
}
if ($GLOBALS['user_system']->havePerm(PERM_VIEWROSTER)) {
	$tabs['rosters'] = _('Rosters');
}
if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
	$accountCount = 0;
	$sm = $GLOBALS['system']->getDBOBject('staff_member', $person->id);
	if ($sm) $accountCount++;
	if ($person->hasMemberAccount()) $accountCount++;
	$tabs['accounts'] = _('Accounts').' ('.$accountCount.')';
}
if (!$accordion
	&& ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE) || !$GLOBALS['system']->featureEnabled('NOTES'))
) {
	// If the notes feature is enabled but the current user doesn't have access to notes
	// then history is hidden (sponsored by Coast Evangelical Church)
	$tabs['history'] = _('History');
}




// ------------------- SET UP HEADERS AND DIVIDERS ------------------

if (!$accordion) {
	$panel_header = '<div class="tab-pane %3$s" id="%1$s">';
	$panel_footer = '</div>';
	?>

	<!-- tab headers -->
	<ul class="nav nav-tabs">
	<?php
	$current_tab = 'basic';
	foreach ($tabs as $id => $label) {
		?>
		<li <?php if ($current_tab == $id) echo 'class="active"'; ?>><a data-toggle="tab" href="#<?php echo $id; ?>"><?php echo ents($label); ?></a></li>
		<?php
	}
	?>
	</ul>

	<!-- main container -->
	<div class="tab-content view-person" id="view-person">

	<?php
} else {
	$panel_header = '
		<div class="accordion-group">
			<div class="accordion-heading">
				<a class="accordion-toggle" data-toggle="collapse" href="#%1$s"  onclick="void(0)">
					<i class="icon-chevron-down icon-white pull-right"></i>
					%2$s
				</a>
			</div>
			<div class="accordion-body collapse" id="%1$s">
				<div class="accordion-inner">
	';
	$panel_footer = '
				</div>
			</div>
		</div>
	';
	?>

	<!-- main container -->
	<div class="accordion view-person" id="view-person">
	<?php
}


/**************** BASIC DETAILS TAB *************/

printf($panel_header, 'basic', _('Basic Details'), 'active');
?>
	<div class="person-details">

		<div class="details-box match-height">
			<div class="header-link pull-right">
				<?php
				if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
					?>
					<a href="?view=_edit_person&personid=<?php echo $person->id; ?>"><i class="icon-white icon-wrench"></i><?php echo _('Edit');?></a>
					<?php
				}
				?>
			</div>

			<h3><?php echo _('Person Details')?></h3>

			<?php

			$person->printSummary();

			if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
				?>
				<a class="pull-right hidden-phone indent-left" href="?view=_delete_person&personid=<?php echo $person->id; ?>"><i class="icon-trash"></i><?php echo _('Delete'); ?></a>
				<?php
			}
			if ($family->getValue('address_street')) {
				?>
				<a href="?call=envelopes&personid=<?php echo $person->id; ?>" class="pull-right envelope-popup hidden-phone"><i class="icon-envelope"></i><?php echo _('Print Envelope')?></a>
				<?php
			}
			if ($plan_chooser) {
				?>
				<a href="#action-plan-modal" data-toggle="modal"><i class="icon-forward"></i><?php echo _('Execute Action Plan')?></a>
				<?php
			}
			?>
		</div>

		<div class="details-box match-height">
			<div class="header-link pull-right">
				<a href="?view=families&familyid=<?php echo $family->id; ?>"><i class="icon-home icon-white"></i><?php echo _('View')?></a>
				<?php
				if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
					?>
					&nbsp; <a href="?view=_edit_family&familyid=<?php echo $family->id; ?>"><i class="icon-white icon-wrench"></i><?php echo _('Edit')?></a>
					<?php
				}
				?>
			</div>
			<h3><?php echo _('Family Details')?></h3>
			<?php
			$family->printSummaryWithMembers();
			?>
		</div>

		<?php
		if ($accordion && $GLOBALS['system']->featureEnabled('PHOTOS')) {
			?>
			<img class="person-photo" src="?call=photo&personid=<?php echo (int)$person->id; ?>" />
			<?php
		}
		?>
	</div>

	<?php
	if (!$accordion && $GLOBALS['system']->featureEnabled('PHOTOS')) {
		?>
		<img class="person-photo" width="<?php echo Photo_Handler::MAX_PHOTO_WIDTH; ?>" src="?call=photo&personid=<?php echo (int)$person->id; ?>" />
		<?php
	}
	?>
	<br class="clearfix" />

<?php
echo $panel_footer;

/************** NOTES TAB **************/

if (isset($tabs['notes'])) {

	printf($panel_header, 'notes', _('Notes').' ('.count($notes).')', '');

	if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
		?>
		<div class="pull-right"><a href="?view=_add_note_to_person&personid=<?php echo $person->id; ?>"><i class="icon-plus-sign"></i><?php echo _('Add Note')?></a></div>
		<?php
	}
	if (empty($notes)) {
		?>
		<p><i><?php echo _('There are no person or family notes to show for ')?><?php $person->printFieldValue('name'); ?></i></p>
		<?php
	} else {
		?>
		<p><i><?php echo _('Person and Family Notes for ')?><?php $person->printFieldValue('name'); ?>:</i></p>
		<?php
	}
	$show_edit_link = true;
	include 'list_notes.template.php';

	echo $panel_footer;
}


/************** GROUPS TAB *****************/
if (isset($tabs['groups'])) {
	printf($panel_header, 'groups', _('Groups').' ('.count($groups).')', '');

	if ($can_add_group) {
		?>
		<div class="pull-right"><a href="#add-group-modal" data-toggle="modal"><i class="icon-plus-sign"></i><?php echo _('Add to a group')?></a></div>
		<?php
	}

	if (empty($groups)) {
		?>
		<p><i><?php $person->printFieldValue('name'); ?><?php echo _(' is not a member of any active groups')?></i></p>
		<?php
	} else {
		?>
		<p><i><?php $person->printFieldValue('name'); ?><?php echo _(' is a member of:')?></i></p>
		<table class="table table-condensed table-auto-width table-striped table-hover clickable-rows">
			<thead>
				<tr>
					<th>ID</th>
					<th><?php echo _('Group Name')?></th>
					<th><?php echo _('Membership Status')?></th>
				<?php
				if (!SizeDetector::isNarrow()) {
					?>
					<th><?php echo _('Joined Group')?></th>
					<?php
				}
				?>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ($groups as $id => $details) {
				$trclass = $details['is_archived'] ? ' class="archived"' : '';
				?>
				<tr<?php echo $trclass; ?>>
					<td><?php echo $id; ?></td>
					<td><a href="?view=groups&groupid=<?php echo $id; ?>"><?php echo ents($details['name']); ?></a></td>
					<td><?php echo ents($details['membership_status']); ?></td>
				<?php
				if (!SizeDetector::isNarrow()) {
					?>
					<td><?php echo format_datetime($details['created']); ?></td>
					<?php
				}
				?>
					<td>
                        <?php if ($can_add_group) { ?>
						<a data-method="post" class="link-collapse confirm-title"
						   href="?view=_edit_group&action=remove_member&groupid=<?php echo $id; ?>&back_to=persons&personid=<?php echo $person->id; ?>"
						   title="Remove <?php $person->printFieldValue('name'); ?> from <?php echo ents($details['name']); ?>">
							<i class="icon-remove-sign"></i><?php echo _('Remove')?>
						</a>
						<?php } ?>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php
	}

	echo $panel_footer;
}

if (isset($tabs['accounts'])) {
	printf($panel_header, 'accounts', _('Accounts'), '');

	echo '<h4>';
	if ($sm) echo '<a class="pull-right" href="?view=_edit_user_account&staff_member_id='.$person->id.'"><i class="icon-wrench"></i>Edit</a>';
	echo _('Control Centre Account');
	echo '</h4>';

	if ($sm) {
		?>
		<table class="table no-borders table-condensed table-auto-width indent-left">
			<tr>
				<th>Username</th>
				<td><?php $sm->printFieldValue('username'); ?></td>
			</tr>
			<tr>
				<th>Permissions</th>
				<td><?php $sm->printFieldValue('permissions'); ?></td>
			</tr>
			<tr>
				<th>Restrictions</th>
				<td><?php $sm->printFieldValue('restrictions'); ?></td>
			</tr>
		</table>
		<?php
	} else {
		echo '<i>'.ents($person->toString()).' '._('does not have a user account for the control centre.').'</i>';
		echo '<p><a href="?view=_add_user_account&personid='.$person->id.'">Create account</a></p>';
	}

	echo '<h4>'._('Members Area Account').'</h4>';
	$ma = $person->hasMemberAccount();
	if ($ma) {
		if ($ma === TRUE) {
			echo '<i class="icon-check"></i>'.ents($person->toString()).' has a members area account. &nbsp;';
		} else {
			echo '<i>'.ents($person->toString()).' has been sent the registration email but not yet completed the rego process. &nbsp;</i>';
		}
		?>
		<form method="post" action="?view=_activate_member_account">
			<input type="hidden" name="personid" value="<?php echo $person->id; ?>" />
			<input type="submit" class="btn" value="Re-send activation email" />
		</form>
		<?php
	} else {
		if ($sm) {
			echo '<i>'.$person->toString().' has not registered a members area account, but can log into the members area using their control centre password.</i>';
		} else if (in_array($person->getValue('status'), Person_Status::getArchivedIDs())) {
			echo '<i>'.$person->toString().' has not yet registered a members area account, and cannot register because they are archived. </i>';
		} else if (!strlen($person->getValue('email'))) {
			echo '<i>'.$person->toString().' must have an email address recorded to register for a member account. </i>';
		} else {
			echo '<i>'.$person->toString().' has not yet registered a members area account. </i>';
			?>
			<form method="post" action="?view=_activate_member_account">
				<input type="hidden" name="personid" value="<?php echo $person->id; ?>" />
				<input type="submit" class="btn" value="Send activation email" />
			</form>
			<?php
		}
	}
	echo $panel_footer;
}

/************************ HISTORY TAB ********************/

if (isset($tabs['history'])) {
	printf($panel_header, 'history', _('History'), '');
	$person->printFieldValue('history');
	echo $panel_footer;
}

if (isset($tabs['attendance'])) {

	/********************** ATTENDANCE TAB **************/

	printf($panel_header, 'attendance', _('Attendance'), '');

	$weeks = SizeDetector::isNarrow() ? 8 : 13;
	$attendances = $person->getAttendance(date('Y-m-d', strtotime('-'.$weeks.' weeks')));
	if (empty($attendances)) {
		?>
		<p><i><?php echo _('No attendance has been recorded for ')?><?php $person->printFieldValue('name'); ?></i></p>
		<?php
	} else {
		$groups = Array();
		$groupids = array_keys($attendances);
		if (count($groupids) > 1 || reset($attendances) != '') {
			$groups = $GLOBALS['system']->getDBObjectData('person_group', Array('id' => $groupids));
		}
		$classes = Array(
					'0'	=> 'absent',
					'1'	=> 'present',
					NULL => 'unknown'
				   );
		$labels = Array(
					'0'	=> 'A',
					'1'	=> 'P',
					NULL => '?'
				   );
		foreach ($attendances as $groupid => $group_attendances) {
			$start = reset($group_attendances);
			$end = end($group_attendances);
			echo '<h4>';
			echo '<a class="pull-right" href="?view=_edit_attendance&personid='.$person->id.'&groupid='.$groupid.'&startdate='.$start['date'].'&enddate='.$end['date'].'"><i class="icon-wrench"></i>'._('Edit').'</a>';
			if (empty($groupid)) {
				echo 'Congregational Attendance';
			} else {
				echo 'Attendance at '.ents($groups[$groupid]['name']);
			}
			echo '</h4>';
			?>
			<table class="table table-bordered table-condensed table-auto-width">
				<thead>
					<tr>
					<?php
					foreach ($group_attendances as $att) {
						?>
						<th><?php echo date('j M', strtotime($att['date'])); ?></th>
						<?php
					}
					?>
					</tr>
				</thead>
				<tbody>
					<tr>
					<?php
					foreach ($group_attendances as $att) {
						?>
						<td class="<?php echo $classes[$att['present']]; ?>">
							<?php echo $labels[$att['present']]; ?>
						</td>
						<?php
					}
					?>
					</tr>
				</tbody>
			</table>
			<?php
		}
	}

	echo $panel_footer;

}

if (isset($tabs['rosters'])) {
	/*************** ROSTERS TAB ****************/

	printf($panel_header, 'rosters', _('Rosters'), '');

	$GLOBALS['system']->includeDBClass('roster_role_assignment');
	$assignments = Roster_Role_Assignment::getUpcomingAssignments($person->id, '99 weeks');
	$absences = $GLOBALS['system']->getDBObjectData(
											'planned_absence', 
											Array('personid' => $person->id, '>=end_date' => date('Y-m-d')),
											'start_date'
									);
	
	?>
	<h4>Upcoming roster assignments</h4>
	<?php
	if (empty($assignments)) {
		?>
		<p><i><?php $person->printFieldValue('name'); ?> has no upcoming roster assignments</i></p>
		<?php
	} else {
		?>
		<table class="table table-bordered table-condensed table-auto-width">
			<thead>
				<tr>
					<th>Date</th>
					<th>Roles</th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ($assignments as $date => $allocs) {
				$warning = '';
				foreach ($absences as $ab) {
					if (($ab['start_date'] <= $date) && ($date <= $ab['end_date'])) {
						$warning = '<br /><span class="label label-important">! Planned absence</span>';
					}
				}
				?>
				<tr>
					<td class="nowrap"><?php echo format_date($date).'&nbsp;'.$warning; ?></td>
					<td>
						<?php
						foreach ($allocs as $alloc) {
							echo ents($alloc['cong'].' '.$alloc['title']).'<br />';
						}
						?>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php
	}
	
	?>
	<h4>
		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_EDITROSTER)) {
			?>
			<a class="pull-right" href="?view=_add_planned_absence&personid=<?php echo $person->id; ?>"><i class="icon-plus-sign"></i>Add</a>
			<?php
		}
		?>
		Planned absences</h4>
	<?php
	if ($absences) {
		?>
		<table class="table table-condensed table-bordered table-auto-width">
			<thead>
				<tr>
					<th>From</th>
					<th>To</th>
					<th>Comment</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ($absences as $id => $row) {
				$tooltip = 'Saved by '.$row['creator_name'].' on '.format_datetime($row['created']);
				?>
				<tr>
					<td><?php echo format_date($row['start_date']); ?></td>
					<td><?php echo format_date($row['end_date']); ?></td>
					<td><?php echo ents($row['comment']); ?></td>
					<td>
						<i class="icon-info-sign" title="<?php echo ents($tooltip); ?>"></i>
						<a class="confirm-title" href="?view=_delete_planned_absence&id=<?php echo $id; ?>" title="Delete this planned absence" data-method="post"><i class="icon-trash"></i></a>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>	
		</table>
		<?php
	} else {
		?>
		<p><i><?php $person->printFieldValue('name'); ?> has no upcoming planned absences</i></p>
		<?php	
	}

	echo $panel_footer;

}

?>
</div>