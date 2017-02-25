<?php
/* @var $persons */
$GLOBALS['system']->includeDBClass('member');
?>

<table class="table table-hover">
<?php
$dummy = new Member();
foreach ($persons as $id => $member) {
	$dummy->populate($id, $member);
	?>
		<tr>
			<td>
				<?php echo ents($member['first_name'].' '.$member['last_name']); ?>
			</td>
			<td class="hidden-phone">
				<?php $dummy->printFieldValue('congregationid'); ?>
			</td>
			<td class="hidden-phone">
				<?php $dummy->printFieldValue('age_bracketid'); ?>
			</td>
			<td>
				<?php $dummy->printFieldValue('mobile_tel'); ?>
			</td>
			<td>
				<?php $dummy->printFieldValue('email'); ?>
			</td>
		</tr>
	<?php
}
?>
</table>