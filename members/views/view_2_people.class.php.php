<?php
class View_People extends View
{
	function getTitle()
	{
		return NULL;
	}

	function processView()
	{
	}
	
	function printView()
	{
		?>
		<table class="table">
			<?php
		$order = 'family_name, age_bracket ASC, gender DESC';
		$list = $GLOBALS['system']->getDBObjectData('member', Array(), 'OR', $order);
		
		
		
		$lastFamilyID = NULL;
		$dummy = new Member();
		foreach ($list as $id => $member) {
			$dummy->populate($id, $member);
			if ($member['familyid'] != $lastFamilyID) {
				?>
				<tr>
					<td colspan="4">
						<h3><?php echo ents($member['family_name']); ?></h3>
						<?php
						if (!empty($member['home_tel'])) {
							$dummy->printFieldValue('home_tel');
						}
						?>
					</td>
				</tr>
				<?php
			}
			?>
				<tr>
					<td>
						<?php echo ents($member['first_name'].' '.$member['last_name']); ?>
					</td>
					<td>
						<?php $dummy->printFieldValue('congregationid'); ?>
					</td>
					<td>
						<?php $dummy->printFieldValue('mobile_tel'); ?>
					</td>
					<td>
						<?php $dummy->printFieldValue('email'); ?>
					</td>
				</tr>
			<?php
			$lastFamilyID = $member['familyid'];
		}
		?>
		</table>
		<?php

	}

}
