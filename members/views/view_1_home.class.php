<?php
class View_Home extends View
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
		$GLOBALS['system']->includeDBClass('member');
		?>
		<div class="member-homepage-container">
			
		<div class="member-homepage-smalls">
		<div class="member-homepage-box">
			<h3>Search people</h3>
			<form method="get" class="form-inline input-append fullwidth">
				<input type="hidden" name="view" value="people" />
				<input name="search" type="text" placeholder="Enter name" value="<?php echo ents(array_get($_REQUEST, 'search')); ?>">
				<button data-action="search" class="btn" type="submit">Search</button>
			</form>
		</div>

		<?php
		if ($GLOBALS['system']->featureEnabled('ROSTERS&SERVICES')) {
			?>
			<div class="member-homepage-box">
			<h3>
				<?php
				if (ifdef('ROSTER_FEEDS_ENABLED', 0)) {
					?>
					<a href="?view=_edit_ical" class="pull-right"><small><i class="icon-rss"></i><span class="hidden-phone">Subscribe</span></small></a>
					<?php
				}
				?>
				My Roster &nbsp;
			</h3>
			<?php
			$GLOBALS['system']->includeDBClass('roster_role_assignment');
			$rallocs = Roster_Role_Assignment::getUpcomingAssignments($GLOBALS['user_system']->getCurrentMember('id'), NULL);
			if ($rallocs) {
				?>
				<table class="table table-condensed">
				<?php
				foreach ($rallocs as $date => $allocs) {
					 ?>
					 <tr>
						 <th class="narrow"><?php echo date('j M', strtotime($date)); ?></th>
						 <td>
							<?php
							foreach ($allocs as $alloc) {
								 echo $alloc['cong'].' '.$alloc['title'].'<br />';
							}
							?>
						 </td>
					 </tr>
					 <?php
				}
				?>
				</table>
				<?php
			} else {
				?>
				<p><i>None</i></p>
				<?php
			}
			?>
			</div>
			<?php
		}

		$GLOBALS['system']->includeDBClass('person_group');
		$groups = Person_Group::getGroups($GLOBALS['user_system']->getCurrentMember('id'), FALSE, TRUE);
		echo '<div  class="member-homepage-box" >';
		echo '<h3>My Groups</h3>';
		echo '<ul>';
		foreach ($groups as $id => $details) {
			echo '<li><a href="?view=_groups&groupid='.(int)$id.'">'.ents($details['name']).'</a></li>';
		}
		echo '</ul>';
		echo '</div>';

		?>
		</div>
			
		<div class="member-homepage-box family">
			<h3>
				<a class="pull-right" href="?view=_edit_me"><small><i class="icon-wrench"></i>Edit</small></a>
				My Family
			</h3>
			<?php
			$family = $GLOBALS['system']->getDBObject('family', $GLOBALS['user_system']->getCurrentMember('familyid'));
			$persons = $family->getMemberData();
			unset($family->fields['status']);

			if ((count($persons) > 1) && $GLOBALS['system']->featureEnabled('PHOTOS')) {
				?>
				<img class="family-photo" src="?call=photo&familyid=<?php echo (int)$family->id; ?>" />
				<?php
			}

			$family->printSummary();
			echo '<div class="member-family-members" style="clear: both">';
			include 'templates/member_list.template.php';
			echo '</div>';
			?>
		</div>
		</div>
		<?php

	}

	function printFamilyMembers($persons) {
		include 'templates/member_list.template.php';
	}

}