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
		$persons = Array();
		foreach ($list as $id => $member) {
			if ($member['familyid'] != $lastFamilyID) {
				if (!empty($persons)) {
					echo '<div class="member-family-members">';
					include 'templates/member_list.template.php';
					echo '</div>';
					echo '</div>'; // member-family-contents
				}
				$persons = Array();

				$dummy->populate($id, $member);
				$showAddress = defined('MEMBERS_SHARE_ADDRESS')
								&& MEMBERS_SHARE_ADDRESS
								&& !empty($member['address_suburb']);

				?>
				<h3><?php echo ents($member['family_name']); ?></h3>
				<?php
				if ($GLOBALS['system']->featureEnabled('PHOTOS')) {
					?>
					<img class="family" src="?call=photo&familyid=<?php echo $member['familyid']; ?>" />
					<?php
				}
				echo '<div class="member-family-contents">';
				if ($showAddress || $member['home_tel']) {
					?>
					<div class="member-family-details">
							<?php
							if ($showAddress) {
								echo nl2br(ents($member['address_street'])).'<br />';
								echo ents($member['address_suburb'].' '.$member['address_state'].' '.$member['address_postcode']);
								echo '<br />';
							}
							if (!empty($member['home_tel'])) {
								$dummy->printFieldValue('home_tel');
							}
							?>
					</div>
					<?php
				}
			}
			$persons[$id] = $member;
			$lastFamilyID = $member['familyid'];
		}
		echo '<div class="member-family-members">';
		include 'templates/member_list.template.php';
		echo '</div>';
		echo '</div>';

	}

}
