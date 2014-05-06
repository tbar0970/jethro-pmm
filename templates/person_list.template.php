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
		<tr>
			<th class="narrow">ID</th>
			<th>Name</th>
		<?php
		foreach ($special_fields as $field) {
			?>
			<th><?php echo ucwords(str_replace('_', ' ', $field)); ?></th>
			<?php
		}?>
			<th>Status</th>
			<th>Age</th>
			<th>Gender</th>
		<?php
		if ($show_actions) {
			?>
			<th>Actions</th>
			<th class="narrow selector form-inline"><input type="checkbox" class="select-all" title="Select all" /></th>
			<?php
		}
		?>
		</tr>
	</thead>
	<tbody>
	<?php
	foreach ($persons as $id => $details) {
		$dummy_person->populate($id, $details);
		$tr_class = ($details['status'] === 'archived') ? ' class="archived"' : '';
		?>
		<tr<?php echo $tr_class; ?>>
			<td><?php echo $id; ?></td>
			<td class="nowrap"><?php echo $dummy_person->printFieldvalue('name'); ?></td>
		<?php
		foreach ($special_fields as $field) {
			?>
			<td>
				<?php
				if (isset($callbacks[$field])) {
					call_user_func($callbacks[$field], $id, $details[$field]);
				} else {
					echo $details[$field]; 
				}
				?>
				</td>
			<?php
		}?>
			<td><?php $dummy_person->printFieldValue('status'); ?></td>
			<td><?php $dummy_person->printFieldValue('age_bracket'); ?></td>
			<td><?php $dummy_person->printFieldValue('gender'); ?></td>
		<?php
		if ($show_actions) {

			?>
			<td class="narrow action-cell">
				<a <?php echo $link_class; ?> href="?view=persons&personid=<?php echo $id; echo $view_tab ?>"><i class="icon-user"></i>View</a> &nbsp;
			<?php
			if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
				?>
				<a <?php echo $link_class; ?> href="?view=_edit_person&personid=<?php echo $id; ?>"><i class="icon-wrench"></i>Edit</a> &nbsp;
				<?php
			}
			if ($GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
				?>
				<a <?php echo $link_class; ?> href="?view=_add_note_to_person&personid=<?php echo $id; ?>"><i class="icon-pencil"></i>Add Note</a>
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
</table>
<?php
if ($show_actions) {
	include 'templates/bulk_actions.template.php';
}

if ($show_actions) {
	?>
	</form>
	<?php
}