<?php
class View_Home extends View
{
	function getTitle()
	{
		return NULL;
	}

	function processView()
	{
		if (!MEMBER_SWAP_ENABLED) {
			return;
		}

		$currentMemberId = $GLOBALS['user_system']->getCurrentMember('id');
		if (!empty($_GET['accept']) && !empty($_GET['on']) && !empty($_GET['from'])) {
			// Verify that the role is currently assigned to the original person on that date
			$where = 'WHERE rra.`roster_role_id` = '.(int)$_GET['accept'].' AND rra.`assignment_date` = '.$GLOBALS['db']->quote($_GET['on']).' AND rra.`personid` = '.(int)$_GET['from'];
			$SQL = 'SELECT 1 FROM roster_role_assignment rra '.$where;
			if ($GLOBALS['db']->queryRow($SQL)) {
				$SQL = 'UPDATE roster_role_assignment rra
						SET rra.`personid` = '.$currentMemberId.' '.$where;
				$GLOBALS['db']->queryRow($SQL);

				add_message('Swap accepted', 'success');
			}
			
		}
		if (!empty($_POST['assignees'])) {
			foreach ($_POST['assignees'] as $roleid => $dates) {
				foreach ($dates as $date => $assignee) {
					if (!$assignee || $assignee == $currentMemberId) {
						// No new person, or new person is the same as the current person
						continue;
					}
					$SQL = 'SELECT 1 FROM roster_role_assignment rra
							WHERE rra.`roster_role_id` = '.(int)$roleid.' AND rra.`assignment_date` = '.$GLOBALS['db']->quote($date).' AND rra.`personid` = '.$currentMemberId;
					if (!$GLOBALS['db']->queryRow($SQL)) {
						// The role isn't actually assigned to the original person on that date
						// This shouldn't happen, unless submission occurred twice
						continue;
					}

					$person = new Person($assignee);
					$personEmail = $person->getValue('email');
					if (!$personEmail) {
						// No e-mail
						// This should have been filtered out by printChooserForMember()
						continue;
					}

					$url = baseurl_absolute().'/members?accept='.(int)$roleid.'&on='.$date.'&from='.$currentMemberId;

					$body = "Hi %s,

%s has asked you to cover %s on %s. To accept, %s";

					$memberName = $GLOBALS['user_system']->getCurrentMember('first_name');
					$formattedDate = date('F jS Y', strtotime($date));
					$role = new Roster_Role($roleid);
					$roleTitle = $role->getValue('title');
					$text = sprintf($body, $person->getValue('first_name'), $memberName, $roleTitle, $formattedDate, 'go to '.$url);
					$html = sprintf(nl2br($body), $person->getValue('first_name'), $memberName, $roleTitle, $formattedDate, '<a href="'.$url.'">click here</a>.');
					$message = Emailer::newMessage()
									  ->setSubject('Cover '.$roleTitle)
									  ->setFrom(['church@hurstvillepresbyterian.org' => 'Hurstville Presbyterian Roster'])
									  ->setTo([$personEmail => $person->getValue('first_name').' '.$person->getValue('last_name')])
									  ->setBody($text)
									  ->addPart($html, 'text/html');
					Emailer::send($message);

					add_message('Swap requested', 'info');
				}
			}
		}
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
		$currentMemberId = $GLOBALS['user_system']->getCurrentMember('id');
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
			$rallocs = Roster_Role_Assignment::getUpcomingAssignments($currentMemberId, NULL);
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
								?><div class="member_roster_role_assignment">
									<div class="info">
										<div><?php
											echo $alloc['cong'].' '.$alloc['title'];
										?></div><?php
										if (MEMBER_SWAP_ENABLED) {
											?><a href="javascript:void(0);">Swap</a><?php
										}
									?></div><?php
									if (MEMBER_SWAP_ENABLED) {
										?><form method="POST" class="swap">
											Assign
											<?php
											$roster_role = new Roster_Role($alloc['id']);
											$roster_role->printChooserForMember($date, $currentMemberId);
											?>
											<button type="submit" class="btn save">Save</button>
											<button type="button" class="btn cancel">Cancel</button>
										</form><?php
									}
								?></div><?php
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
		$groups = Person_Group::getGroups($currentMemberId, FALSE, TRUE);
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