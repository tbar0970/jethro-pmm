<?php
include_once 'include/size_detector.class.php';
$accordion = SizeDetector::getWidth() && SizeDetector::isNarrow();
if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
	$notes = $GLOBALS['system']->getDBObjectData('family_note', Array('familyid' => $family->id), 'OR', 'created');
}

if (!$accordion) {
	$panel_header = '<div class="tab-pane %3$s" id="%1$s">';
	$panel_footer = '</div>';
	?>
	<!---------- TAB HEADERS --------------->
	<ul class="nav nav-tabs">
		<li class="active"><a data-toggle="tab" href="#basic">Basic Details</a></li>
	<?php
	if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
		$notes = $GLOBALS['system']->getDBObjectData('family_note', Array('familyid' => $family->id), 'OR', 'created');
		?>
		<li><a data-toggle="tab" href="#notes">Notes (<?php echo count($notes); ?>)</a></li>
		<?php
	}
	if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE) || !$GLOBALS['system']->featureEnabled('NOTES')) {
		?>
		<li><a data-toggle="tab" href="#history">History</a></li>
		<?php
	}
	?>
	</ul>

	<div class="tab-content view-family">
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

printf($panel_header, 'basic', 'Basic Details & Members', 'active'); 
	?>
	<div class="person-details">

		<div class="person-details-box match-height">
			<?php
			if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
				?>
				<div class="header-link pull-right">
					<a href="?view=_edit_family&familyid=<?php echo $family->id; ?>"><i class="icon-white icon-wrench"></i>Edit</a>
				</div>
				<?php
			}
			?>

			<h3>Family Details</h3>

			<?php
			$family->printSummary();

			if ($family->getPostalAddress() != '') {
				echo '<a class="pull-right" href="?call=envelopes&familyid='.$family->id.'" class="envelope-popup"><i class="icon-envelope"></i>Print Envelope</a>';
			}
			?>
		</div>

		<div class="person-details-box match-height">
			<?php
			if (Person::allowedToAdd()) {
				?>
				<div class="header-link pull-right">
					<a href="?view=_add_person_to_family&familyid=<?php echo $family->id; ?>"><i class="icon-white icon-plus-sign"></i>Add Member</a>
				</div>
				<?php
			}
			?>
			<h3>Members</h3>
			<?php
			$family->printMemberList();

			$all_emails = $family->getAllEmailAddrs();
			if (!empty($all_emails)) {
				echo '<a class="pull-right" href="'.get_email_href($all_emails).'" '.email_link_extras().'><i class="icon-email">@</i>Email All</a>';
			}
			?>
			<br class="clearfix" />

		</div>
	</div>

	<?php
	if (!$accordion && $GLOBALS['system']->featureEnabled('PHOTOS')) {
		?>
		<img class="person-photo" width="<?php echo Photo_Handler::MAX_PHOTO_WIDTH; ?>" src="?call=photo&familyid=<?php echo (int)$family->id; ?>" />
		<?php
	}
	?>
	<br class="clearfix" />

<?php
echo $panel_footer;

/**************** NOTES TAB ****************/

if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
	printf($panel_header, 'notes', 'Notes ('.count($notes).')', '');
	$show_edit_link = FALSE;
	if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
		?>
		<div class="align-right">
			<?php
			$members = $family->getMemberData();
			if (count($members) > 1) {
				?>
				<a href="?view=_add_note_to_family&familyid=<?php echo $family->id; ?>"><i class="icon-plus-sign"></i>Add Family Note</a>
				<?php
			} else {
				?>
				<a href="?view=_add_note_to_person&personid=<?php echo reset(array_keys($members)); ?>">Add Person Note</a>
				<?php
			}
			?>
		</div>
		<?php
		$show_edit_link = TRUE;
	}
	include 'list_notes.template.php';

	echo $panel_footer;
}

/************** HISTORY TAB *******************/
if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE) || !$GLOBALS['system']->featureEnabled('NOTES')) {
	printf($panel_header, 'history', 'History', '');
	?>
	<p>Family Record Created on <?php $family->printFieldValue('created'); ?> by <?php $family->printFieldValue('creator'); ?></p>
	<?php 
	$family->printFieldValue('history');
	echo $panel_footer;
}
?>

</div>
