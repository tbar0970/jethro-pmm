<?php

// ------------------------ MODALS --------------------------

// -------ACTION PLAN MODAL --------- //
$GLOBALS['system']->includeDBClass('action_plan');
$plan_chooser = Action_Plan::getMultiChooser('planid', Array());
if ($plan_chooser) {
	?>
	<div id="action-plan-modal" class="modal hide fade" role="dialog" aria-hidden="true">
		<form method="post" action="?view=_execute_plans&personid[]=<?php echo (int)$person->id; ?>">
			<div class="modal-header">
				<h4>Execute Action Plan for <?php $person->printFieldValue('name'); ?></h4>
			</div>
			<div class="modal-body">
				<p><?php echo $plan_chooser; ?></p>
				<p>Reference date for plans: <?php print_widget('plan_reference_date', Array('type' => 'date'), NULL); ?></p>
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
$groups = Person_Group::getGroups($person->id);
if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
	?>
	<div id="add-group-modal" class="modal hide fade" role="dialog" aria-hidden="true">
		<form method="post">
			<input type="hidden" name="view" value="_edit_group" />
			<input type="hidden" name="personid" value="<?php echo $person->id; ?>" />
			<input type="hidden" name="action" value="add_member" />
			<input type="hidden" name="overwrite_membership" value="1" />
			<input type="hidden" name="back_to" value="persons" />

			<div class="modal-header">
				<h4>Add <?php $person->printFieldValue('name'); ?> to a group</h4>
			</div>
			<div class="modal-body">
				<?php
				$GLOBALS['system']->includeDBClass('person_group');
				echo 'Add as a ';
				Person_Group::printMembershipStatusChooser('membership_status');
				echo ' of ';
				$can_add_group = Person_Group::printChooser('groupid', 0, array_keys($groups));
				?>
			</div>
			<div class="modal-footer">
				<input type="submit" class="btn" value="Go" accesskey="s" onclick="if (!$('[name=groupid]').val()) { alert('Choose a group first'); return false; }" />
				<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
			</div>
		</form>
	</div>
	<?php
}

// -------- CHECK PERMISSIONS AND ASSEMBLE DATA ------------- //
include_once 'include/size_detector.class.php';
$accordion = SizeDetector::getWidth() && SizeDetector::isNarrow();

$tabs = Array(
	'basic' => 'Basic Details'
);
if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
	$notes = $person->getNotesHistory();
	$tabs['notes'] = 'Notes ('.count($notes).')';
}
if ($can_add_group || (count($groups) > 1)) {
	$tabs['groups'] = 'Groups ('.count($groups).')';
}
if ($GLOBALS['user_system']->havePerm(PERM_VIEWATTENDANCE)) {
	$tabs['attendance'] = 'Attendance';
}
if ($GLOBALS['user_system']->havePerm(PERM_VIEWROSTER)) {
	$tabs['rosters'] = 'Rosters';
}
if (!$accordion
	&& ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE) || !$GLOBALS['system']->featureEnabled('NOTES'))
) {
	// If the notes feature is enabled but the current user doesn't have access to notes
	// then history is hidden (sponsored by Coast Evangelical Church)
	$tabs['history'] = 'History';
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
		<li <?php if ($current_tab == $id) echo 'class="active"'; ?>><a data-toggle="tab" href="#<?php echo $id; ?>"><?php echo htmlentities($label); ?></a></li>
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
				<a class="accordion-toggle" data-toggle="collapse" href="#%1$s">
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

printf($panel_header, 'basic', 'Basic Details', 'active'); 

if (!$accordion && $GLOBALS['system']->featureEnabled('PHOTOS')) {
	?>
	<img width="<?php echo Person::MAX_PHOTO_WIDTH; ?>" src="?call=person_photo&personid=<?php echo (int)$person->id; ?>" />
	<div class="person-details-withphoto">
	<?php
} else {
	?>
	<div class="person-details-nophoto">
	<?php
}
?>
		<div class="person-details-box">
			<div class="header-link pull-right">
				<?php
				if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
					?>
					<a href="?view=_edit_person&personid=<?php echo $person->id; ?>"><i class="icon-white icon-wrench"></i>Edit</a>
					<?php
				}
				?>
			</div>

			<h3>Person Details</h3>

			<?php 
			
			$person->printSummary(); 

			if ($family->getValue('address_street')) {
				?>
				<a href="?call=envelopes&personid=<?php echo $person->id; ?>" class="pull-right envelope-popup"><i class="icon-envelope"></i>Print Envelope</a>
				<?php
			}
			if ($plan_chooser) {
				?>
				<a href="#action-plan-modal" data-toggle="modal"><i class="icon-forward"></i>Execute Action Plan</a>
				<?php
			}
			?>
			<br class="clearfix" />
		</div>
		
		<div class="person-details-box">
			<div class="header-link pull-right">
				<a href="?view=families&familyid=<?php echo $family->id; ?>"><i class="icon-home icon-white"></i>View</a>
				<?php
				if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
					?>
					&nbsp; <a href="?view=_edit_family&familyid=<?php echo $family->id; ?>"><i class="icon-white icon-wrench"></i>Edit</a>
					<?php
				}
				?>
			</div>
			<h3>Family Details</h3>
			<?php
			$family->printSummary(); 
			?>
		</div>
	</div>
<?php 
echo $panel_footer;

/************** NOTES TAB **************/

if (isset($tabs['notes'])) {

	printf($panel_header, 'notes', 'Notes ('.count($notes).')', ''); 

	if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
		?>
		<div class="pull-right"><a href="?view=_add_note_to_person&personid=<?php echo $person->id; ?>"><i class="icon-plus-sign"></i>Add Note</a></div>
		<?php
	}
	if (empty($notes)) {
		?>
		<p><i>There are no person or family notes for <?php $person->printFieldValue('name'); ?></i></p>
		<?php
	} else {
		?>
		<p><i>Person and Family Notes for <?php $person->printFieldValue('name'); ?>:</i></p>
		<?php
	}
	$show_edit_link = true;
	include 'list_notes.template.php';

	echo $panel_footer;
}


/************** GROUPS TAB *****************/
if (isset($tabs['groups'])) {
	printf($panel_header, 'groups', 'Groups ('.count($groups).')', ''); 

	if ($can_add_group) {
		?>
		<div class="pull-right"><a href="#add-group-modal" data-toggle="modal"><i class="icon-plus-sign"></i>Add to a group</a></div>
		<?php
	}

	if (empty($groups)) {
		?>
		<p><i><?php $person->printFieldValue('name'); ?> is not a member of any active groups</i></p>
		<?php
	} else {
		?>
		<p><i><?php $person->printFieldValue('name'); ?> is a member of:</i></p>
		<table class="table table-condensed table-auto-width table-striped table-hover clickable-rows">
			<thead>
				<tr>
					<th>ID</th>
					<th>Group Name</th>
					<th>Membership Status</th>
					<th>Joined Group</th>
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
					<td><a href="?view=groups&groupid=<?php echo $id; ?>"><?php echo htmlentities($details['name']); ?></a></td>
					<td><?php echo htmlentities($details['membership_status']); ?></td>
					<td><?php echo format_datetime($details['created']); ?></td>
					<td><form class="min" method="post" action="?view=_edit_group&action=remove_member&groupid=<?php echo $id; ?>&back_to=persons" ><input type="hidden" name="personid" value="<?php echo $person->id; ?>"><label class="clickable submit confirm-title" title="Remove <?php $person->printFieldValue('name'); ?> from <?php echo htmlentities($details['name']); ?>"><i class="icon-remove-sign"></i>Remove</label></form></td>
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

/************************ HISTORY TAB ********************/

if (isset($tabs['history'])) {
	printf($panel_header, 'history', 'History', ''); 
	$person->printFieldValue('history');
	echo $panel_footer;
}

if (isset($tabs['attendance'])) {

	/********************** ATTENDANCE TAB **************/

	printf($panel_header, 'attendance', 'Attendance', ''); 

	$num_weeks = 12;
	$attendances = $person->getRecentAttendance($num_weeks);
	if (empty($attendances)) {
		?>
		<p><i>No attendance has been recorded for <?php $person->printFieldValue('name'); ?></i></p>
		<?php
	} else {

		$colours = Array(
					'0'	=> 'Red',
					'1'	=> 'Green',
					'?' => 'Yellow'
				   );
		$labels = Array(
					'0'	=> 'A',
					'1'	=> 'P',
					'?' => '?'
				   );
		$width = floor(100 / $num_weeks);
		foreach ($attendances as $group_name => $group_attendances) {
			if (empty($group_name)) {
				?>
				<p><i>Congregational Attendance:</i></p>
				<?php
			} else {
				?>
				<p><i>Attendance at <?php echo htmlentities($group_name); ?>:</i></p>
				<?php
			}
			?>
			<table class="table table-bordered table-auto-width">
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
						<td style="background-color: <?php echo $colours[$att['present']]; ?>;">
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

	printf($panel_header, 'rosters', 'Rosters', ''); 

	$GLOBALS['system']->includeDBClass('roster_role_assignment');
	$assignments = Roster_Role_Assignment::getUpcomingAssignments($person->id, NULL);
	if (empty($assignments)) {
		?>
		<p><i><?php $person->printFieldValue('name'); ?> has no upcoming roster assignments</i></p>
		<?php
	} else {
		?>
		<p><i>Upcoming roster assignments for <?php $person->printFieldValue('name'); ?>:</i></p>
		<?php
		foreach ($assignments as $date => $allocs) {
			?>
			<h5><?php echo date('j M', strtotime($date)); ?></h5>
			<?php
			foreach ($allocs as $alloc) {
				echo htmlentities($alloc['cong'].' '.$alloc['title']).'<br />';
			}
		}
	}

	echo $panel_footer;

}

?>
</div>
<?php



