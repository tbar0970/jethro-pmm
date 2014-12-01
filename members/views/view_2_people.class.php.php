<?php
class View_People extends View
{
	function getTitle()
	{
		return '';
	}

	function processView()
	{
	}
	
	function printView()
	{
		$GLOBALS['system']->includeDBClass('member');
		?>
		<div class="container" id="member-list">
			<form method="get" class="form-inline">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
			<div class="input-append input-prepend">
				<span class="add-on"><i class="icon-search"></i></span>
				<input name="search" type="text" placeholder="Enter name to search" value="<?php echo ents(array_get($_REQUEST, 'search')); ?>"))">
				<br class="visible-phone" />
				<?php
				print_widget('congregationid', Array('type' => 'select', 'options' => Array('' => 'Any congregation') + Member::getCongregations()), array_get($_REQUEST, 'congregationid')); ?>
				<button data-action="search" class="btn" type="submit">Filter</button>
				<a class="btn" href="?view=<?php echo ents($_REQUEST['view']); ?>">Clear</a>
			</div>
			</form>

		<?php
		$list = Member::getList(array_get($_REQUEST, 'search'), array_get($_REQUEST, 'congregationid'));
		$lastFamilyID = NULL;
		$dummy = new Member();
		foreach ($list as $id => $member) {
			$dummy->populate($id, $member);
			if ($member['familyid'] != $lastFamilyID) {
				?>
				<div class="row-fluid">
					<div class="span12">
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
					</div>
				</div>
				<?php
			}
			?>
				<div class="row-fluid">
					<div class="span3">
						<?php echo ents($member['first_name'].' '.$member['last_name']); ?>
					</div>
					<div class="span3">
						<?php $dummy->printFieldValue('congregationid'); ?>
					</div>
					<div class="span3">
						<?php $dummy->printFieldValue('mobile_tel'); ?>
					</div>
					<div class="span3">
						<?php $dummy->printFieldValue('email'); ?>
					</div>
				</div>
			<?php
			$lastFamilyID = $member['familyid'];
		}
		?>
		</table>
		<?php

	}

}
