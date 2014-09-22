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
		$order = 'family_name, family_id, age_bracket ASC, gender DESC';
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
						if (defined('MEMBERS_SHARE_ADDRESS')
							&& MEMBERS_SHARE_ADDRESS
							&& !empty($member['address_suburb'])
						) {
							echo '<br />'.nl2br(ents($member['address_street'])).'<br />';
							echo ents($member['address_suburb'].' '.$member['address_state'].' '.$member['address_postcode']);
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
					<td class="hidden-phone">
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
