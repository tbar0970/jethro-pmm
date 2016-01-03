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
		<div class="member-homepage-box visible-phone">
			<h3>Search people</h3>
			<form method="get" class="form-inline">
				<input type="hidden" name="view" value="people" />
				<input name="search" type="text" placeholder="Enter name to search" value="<?php echo ents(array_get($_REQUEST, 'search')); ?>">
				<button data-action="search" class="btn" type="submit">Search</button>
			</form>
		</div>

		<div class="member-homepage-box">
			<h3>
				<a class="pull-right" href="?view=_edit_me"><small>Edit</small></a>
				My Family
			</h3>
			<?php
			$family = $GLOBALS['system']->getDBObject('family', $GLOBALS['member_user_system']->getCurrentMember('familyid'));
			unset($family->fields['status']);
			$family->printCustomSummary(Array($this, 'printFamilyMembers'));
			?>
		</div>

		<?php
		if ($GLOBALS['system']->featureEnabled('ROSTERS&SERVICES')) {
			?>
			<div class="member-homepage-box">
			<h3>
				<a class="pull-right" href="?view=_edit_ical"><small>Subscribe</small></a>
				My Roster Allocations &nbsp;
			</h3>
			<?php
			$GLOBALS['system']->includeDBClass('roster_role_assignment');
			$rallocs = Roster_Role_Assignment::getUpcomingAssignments($GLOBALS['member_user_system']->getCurrentMember('id'), NULL);
			if ($rallocs) {
				?>
				<table class="table table-auto-width">
				<?php
				foreach ($rallocs as $date => $allocs) {
					 ?>
					 <tr>
						 <th><?php echo date('j M', strtotime($date)); ?></th>
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
		$groups = Person_Group::getGroups($GLOBALS['member_user_system']->getCurrentMember('id'), FALSE, TRUE);
		if (count($groups) > 1) {
			echo '<div  class="member-homepage-box" >';
			echo '<h3>My Groups</h3>';
			echo '<ul>';
			foreach ($groups as $id => $details) {
				echo '<li><a href="?view=_groups&groupid='.(int)$id.'">'.ents($details['name']).'</a></li>';
			}
			echo '</ul>';
			echo '</div>';
		}
		
		?>
		<div class="member-homepage-box hidden-phone">
			<h3>Search people</h3>
			<form method="get" class="form-inline">
				<input type="hidden" name="view" value="people" />
				<input name="search" type="text" placeholder="Enter name to search" value="<?php echo ents(array_get($_REQUEST, 'search')); ?>">
				<button data-action="search" class="btn" type="submit">Search</button>
			</form>
		</div>
		<?php

		

	}
	
	function printFamilyMembers($persons) {
		include 'templates/member_list.template.php';
	}

}

?>
