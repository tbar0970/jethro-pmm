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
		<li class="active"><a data-toggle="tab" href="#basic"><?php echo _('Basic Details')?></a></li>
	<?php
	if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
		$order =(ifdef('NOTES_ORDER', 'ASC') == 'ASC') ? 'ASC' : 'DESC';
		$notes = $GLOBALS['system']->getDBObjectData('family_note', Array('familyid' => $family->id), 'OR', 'created '.$order);
		?>
		<li><a data-toggle="tab" href="#notes"><?php echo _('Notes')?> (<?php echo count($notes); ?>)</a></li>
		<?php
	}
	if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE) || !$GLOBALS['system']->featureEnabled('NOTES')) {
		?>
		<li><a data-toggle="tab" href="#history"><?php echo _('History')?></a></li>
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
	<div class="family-details">

		<div class="details-box">
			<?php
			if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
				?>
				<div class="header-link pull-right">
					<a href="?view=_edit_family&familyid=<?php echo $family->id; ?>">
						<i class="icon-white icon-wrench"></i><?php echo _('Edit'); ?>
					</a>
				</div>
				<?php
			}
			?>

			<h3><?php echo _('Family Details'); ?></h3>

			<?php
			$family->printSummary();

			if ($family->getPostalAddress() != '') {
				echo '<a class="pull-right" href="?call=envelopes&familyid='.$family->id.'" class="envelope-popup"><i class="icon-envelope"></i>'._('Print Envelope').'</a>';
			}
			?>
		</div>

		<div class="details-box">
			<?php
			if (Person::allowedToAdd()) {
				?>
				<div class="header-link pull-right">
					<a href="?view=_add_person_to_family&familyid=<?php echo $family->id; ?>">
						<i class="icon-white icon-plus-sign"></i><?php echo _('Add Member'); ?>
					</a>
				</div>
				<?php
			}
			?>
			<h3><?php echo _('Members'); ?></h3>
			<form method="post" enctype="multipart/form-data" action="" class="bulk-person-action">
			<div class="family-members-container">
			<?php
			$dummy = new Person();
			foreach ($family->getMemberData() as $personid => $person) {
				$dummy->populate($personid, $person);
				$archivedClass = in_array($person['status'], Person_Status::getArchivedIDs()) ? 'archived' : '';
				?>
				<a href="?view=persons&personid=<?php echo (int)$personid; ?>">
				<div class="family-member <?php echo $archivedClass; ?>">
					<?php
					if ($GLOBALS['system']->featureEnabled('PHOTOS')) {
						?>
						<img src="?call=photo&personid=<?php echo $personid; ?>" />
						<?php
					}
					?>
					<label>
						<input name="personid[]" type="checkbox" checked="checked" value="<?php echo (int)$personid; ?>" />
					</label>
					<div>
						<strong><?php echo ents($dummy->toString()); ?></strong>
						<br />
						<?php
						echo ents($dummy->getFormattedValue('age_bracketid'));
						echo ' &bull; ';
						echo ents($dummy->getFormattedValue('gender'));
						echo '<br />';
						echo ents($dummy->getFormattedValue('status'));
						echo ' &bull; ';
						echo ents($dummy->getFormattedValue('congregationid'));
						?>
					</div>

				</div>
				</a>
				<?php
			}
			?>
			</div>
			<?php

			$all_emails = $family->getAllEmailAddrs();
			if (!empty($all_emails)) {
				echo '<a class="pull-right" href="'.get_email_href($all_emails).'" '.email_link_extras().'><i class="icon-email">@</i>Email All</a>';
			}
			?>
			<?php include 'templates/bulk_actions.template.php'; ?>
			</form>

		</div>
	</div>

	<?php
	if ($GLOBALS['system']->featureEnabled('PHOTOS')) {
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
				<a href="?view=_add_note_to_family&familyid=<?php echo $family->id; ?>"><i class="icon-plus-sign"></i><?php echo _('Add Family Note')?></a>
				<?php
			} else if (count($members) == 1) {
				?>
				<a href="?view=_add_note_to_person&personid=<?php $memberarray = array_keys($members); echo reset($memberarray); ?>"><?php echo _('Add Person Note')?></a>
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
	<p><?php echo _('Family Record Created on ')?><?php $family->printFieldValue('created'); ?> by <?php $family->printFieldValue('creator'); ?></p>
	<?php
	$family->printFieldValue('history');
	echo $panel_footer;
}
?>

</div>
