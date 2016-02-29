<?php
include_once 'include/size_detector.class.php';
  function _compareCreatedDates($a, $b)
  {
      $aDate = strtotime($a['created']);
      $bDate = strtotime($b['created']);
      // comments are always in chronological order, so grab the last comment
      if (count($a['comments']) > 0) {
        $cDate = strtotime(end($a['comments'])['created']);
        reset($a['comments']);
        if ($cDate > $aDate) { $aDate = $cDate;}
      }
      if (count($b['comments']) > 0) {
        $cDate = strtotime(end($b['comments'])['created']);
        reset($b['comments']);
        if ($cDate > $bDate) { $bDate = $cDate;}
      }
      return $aDate < $bDate;
    }

$accordion = SizeDetector::getWidth() && SizeDetector::isNarrow();
if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
	$notes = $GLOBALS['system']->getDBObjectData('family_note', Array('familyid' => $family->id), 'OR', 'created');
  	uasort($notes, '_compareCreatedDates');
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
$links = Array();
if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
	$links[] = '<a href="?view=_edit_family&familyid='.$family->id.'"><i class="icon-wrench"></i>Edit</a>';
	if (Person::allowedToAdd()) {
		// users with group or cong restrictions are not allowed to add persons
		$links[] = '<a href="?view=_add_person_to_family&familyid='.$family->id.'"><i class="icon-plus-sign"></i>Add Member</a>';
	}
}
if (!empty($links)) {
	?>
	<div class="align-right">
		<?php echo implode(' &nbsp; ', $links); ?>
	</div>
	<?php
}

$family->printSummary($accordion ? TRUE : FALSE);

?>
<div class="align-right">
	<?php
	$links = Array();
	if ($family->getPostalAddress() != '') {
		$links[] = '<a href="?call=envelopes&familyid='.$family->id.'" class="envelope-popup"><i class="icon-envelope"></i>Print Envelope</a>';
	}
	$all_emails = $family->getAllEmailAddrs();
	if (!empty($all_emails)) {
		$links[] = '<a href="'.get_email_href($all_emails).'" '.email_link_extras().'><i class="icon-email">@</i>Email All</a>';
	}
	echo implode(' &nbsp; ', $links);
	?>
</div>
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
