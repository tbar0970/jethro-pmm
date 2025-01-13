<?php
/* @var $persons */
$GLOBALS['system']->includeDBClass('member');
$dummy = new Member();
foreach ($persons as $personid => $person) {
	$dummy->populate($personid, $person);
	?>
	<div class="family-member">
		<?php
		if ($GLOBALS['system']->featureEnabled('PHOTOS')) {
			?>
			<img src="?call=photo&personid=<?php echo $personid; ?>" />
			<?php
		}
		?>
		<div>
			<strong><?php echo ents($dummy->toString()); ?></strong>
			<br />
			<?php
			if (ifdef('MEMBERS_SEE_AGE_BRACKET', TRUE)) {
				echo ents($dummy->getFormattedValue('age_bracketid'));
				echo ' &bull; ';
			}
			echo ents($dummy->getFormattedValue('gender'));
			if ($dummy->getValue('mobile_tel')) {
				echo ' &bull; ';	
				$dummy->printFieldValue('mobile_tel');
			}
			if ($dummy->getValue('email')) {
				echo '<br />';
				$dummy->printFieldValue('email');
			}
			?>
		</div>

	</div>
	<?php
}
/*

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
 * <?php */