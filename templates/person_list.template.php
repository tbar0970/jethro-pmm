<?php
/*
@var $persons
@var $special_fields
@var $show_actions	Whether to show the action links for each person
@var $link_Class	Classname to add to all the action links (eg med-popup)
@var $view_tab		Which view-person tab to link to (eg attendance)
@var $callbacks		Functions to call to render each column's value
*/
$link_class = empty($link_class) ? '' : 'class="'.$link_class.'"';
$view_tab = empty($view_tab) ? '' : '#'.$view_tab;

$GLOBALS['system']->includeDBClass('person');
$dummy_person = new Person();

if (!isset($special_fields)) {
	$special_fields = Array();
	if(!isset($include_special_fields) || $include_special_fields) {
		if (!empty($persons)) {
			$first_row = reset($persons);
			foreach ($first_row as $i => $v) {
				if (!isset($dummy_person->fields[$i]) && (strtolower($i) != 'id')) {
					$special_fields[] = $i;
				}
			}
		}
	}
}
if (empty($callbacks)) $callbacks = Array();

if (!isset($show_actions)) $show_actions = TRUE;

if ($show_actions) {
	?>
	<form method="post" enctype="multipart/form-data" action="" class="bulk-person-action">
	<?php
}
?>
<table class="table table-hover table-striped <?php if (empty($callbacks)) echo 'clickable-rows'; ?>">
	<thead>
		<?php include 'person_list_header_footer.template.php'; ?>
	</thead>
	<tbody>
	<?php
	foreach ($persons as $id => $details) {
		$dummy_person->populate($id, $details);
		$tr_class = ($details['status'] === 'archived') ? ' class="archived"' : '';
		?>
		<tr data-personid="<?php echo $id; ?>" <?php echo $tr_class; ?>>
			<td><?php echo $id; ?></td>
			<td class="nowrap"><?php echo $dummy_person->printFieldvalue('name'); ?></td>
		<?php
		foreach ($special_fields as $field) {
			?>
			<td>
				<?php
				if (isset($callbacks[$field])) {
					call_user_func($callbacks[$field], $id, array_get($details, $field, ''));
				} else {
					echo array_get($details, $field, '');
				}
				?>
				</td>
			<?php
		}?>
			<td><?php $dummy_person->printFieldValue('status'); ?></td>
			<td><?php $dummy_person->printFieldValue('age_bracketid'); ?></td>
			<td><?php $dummy_person->printFieldValue('gender'); ?></td>
			<?php
				include_once 'include/size_detector.class.php';
				if (!SizeDetector::isNarrow()) {
			?>			
			<td><?php $dummy_person->printFieldvalue('mobile_tel'); ?></td>
			<?php
			}
				if (defined('PERSON_LIST_SHOW_GROUPS') && PERSON_LIST_SHOW_GROUPS) {
				?>
					<td>  
				  	  <?php 
				  	  	$gstr = '';
				  	  	foreach (Person_Group::getGroups($id) as $gid => $gdetail) {
				  	  		if (strlen($gstr)) $gstr .= ', ';
				  	  		$gstr = $gstr . $gdetail['name'];
				  	  	}
						if (strlen($gstr)) {
							?>
							<a title="<?php echo ents($gstr); ?>" href="?view=persons&personid=<?php echo $id; ?>#groups">	
								<?php echo ents(substr($gstr, 0, 45)) . '...' ; ?>
							</a>
							<?php
						}
						?>
				  	</td>
				 <?php
				 }
				 ?>
		<?php
		if ($show_actions) {

			?>
			<td class="narrow action-cell">
				<a <?php echo $link_class; ?> href="?view=persons&personid=<?php echo $id; echo $view_tab ?>"><i class="icon-user"></i><?php echo _('View')?></a> &nbsp;
			<?php
			if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
				?>
				<a <?php echo $link_class; ?> href="?view=_edit_person&personid=<?php echo $id; ?>"><i class="icon-wrench"></i><?php echo _('Edit')?></a> &nbsp;
				<?php
			}
			if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
				?>
				<a <?php echo $link_class; ?> href="?view=_add_note_to_person&personid=<?php echo $id; ?>"><i class="icon-pencil"></i><?php echo _('Add Note')?></a>
				<?php
			}
			?>
			</td>
			<td class="selector"><input name="personid[]" type="checkbox" value="<?php echo $id; ?>" /></td>
			<?php
		}
		?>
		</tr>
		<?php
	}
	?>
	</tbody>
<?php
// Add a footer when there is enough rows to justify it.
if (count($persons) > 30) {
	?>
	<tfoot>
		<?php include 'person_list_header_footer.template.php'; ?>
	</tfoot>
	<?php
}
?>
</table>
		
<?php
if ($show_actions) {
	include 'templates/bulk_actions.template.php';

	?>
	</form>
	<?php
}
