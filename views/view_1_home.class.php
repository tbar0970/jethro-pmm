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
		if (ifdef('NEEDS_1086_CHECK')) {
			if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
				require_once 'views/view_0_fix_age_brackets.class.php';
				$x = new View__Fix_Age_Brackets();
				$x->printInvitation();
			}
		}
		if (ifdef('NEEDS_1035_UPGRADE')) {
			require_once 'upgrades/upgradelibs/status_upgrader.class.php';
			Status_Upgrader::runHTML();
		}

		?>
		<div class="homepage">

		<div class="homepage-box search-forms">
			<h3>
				<a class="pull-right hide-phone"
				   href="javascript:if (sp = prompt('Search <?php echo SYSTEM_NAME; ?> for: ')) window.location='<?php echo BASE_URL; ?>?view=_mixed_search&search='+sp"
				   onclick="prompt('To create a search-jethro button in your browser, save the following code as a bookmark/favourite: ', this.href); return false"
				>
					<small class="hidden-phone"><i class="icon-bookmark"></i>Bookmark</small>
				</a>
				<?php echo _('System-Wide Search');?>
			</h3>
			<?php
			require_once 'views/view_0_mixed_search.class.php';
			View__mixed_search::printSearchForm();
			?>
		</div>

		<?php
		if ( $GLOBALS['user_system']->havePerm(PERM_VIEWMYNOTES)) {
			$user = $GLOBALS['system']->getDBObject('staff_member', $GLOBALS['user_system']->getCurrentUser('id'));
			$tasks = $user->getTasks('now');
			?>
			<div class="homepage-box my-notes">
				<h3><?php echo _('Notes '); ?><span><?php echo _('for immediate action');?></span></h3>
				<?php
				if ($tasks) {
					?>
					<table class="table table-condensed table-striped table-hover clickable-rows">
						<thead>
							<tr>
								<th><?php echo _('For');?></th>
								<th><?php echo _('Subject');?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ($tasks as $id => $task) {
								$icon = ($task['type'] == 'person') ? 'user' : 'home';
								$view = ($task['type'] == 'person') ? 'persons' : 'families';
								$url = ifdef('NOTES_LINK_TO_EDIT')
										? '?view=_edit_note&note_type='.ents($task['type']).'&noteid='.(int)$id.'&back_to=home'
										: '?view='.$view.'&'.$task['type'].'id='.$task[$task['type'].'id'].'&note_back_to=home#note_'.$id;
								$nameurl = '?view='.$view.'&'.$task['type'].'id='.$task[$task['type'].'id'];
								?>
								<tr>
									<td class="narrow-gentle"><a href="<?php echo $nameurl; ?>"><i class="icon-<?php echo $icon; ?>"></i><?php echo ents($task['name']); ?></a></td>
									<td><a href="<?php echo $url; ?>"><?php echo ents($task['subject']); ?></a></td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
					<?php
				} else {
					?>
					<p><i>None</i></p>
					<?php
				}
				$later = $user->getTasks('later');
				$count = count($later);
				if ($count) {
					?>
					<p class="align-right"><?php echo _('You have ');?><a href="<?php echo build_url(Array('view' => 'notes__for_future_action', 'assignee' => $user->id)); ?>"><?php echo count($later); ?> note<?php echo ($count > 1) ? 's' : ''; ?> <?php echo _('for future action');?></a></p>
					<?php
				}
				?>
			</div>
			<?php
		}

		if ($GLOBALS['user_system']->havePerm(PERM_VIEWROSTER)) {
			?>
			<div class="homepage-box my-roster">
				<h3>
				<?php
				if (ifdef('ROSTER_FEEDS_ENABLED', 0)) {
					?>
					<a href="?view=_manage_ical" class="pull-right"><small><i class="icon-rss"></i><span class="hidden-phone">Subscribe</span></small></a>
					<?php
				}
				?>
					Upcoming roster
				</h3>
				<?php
				$GLOBALS['system']->includeDBClass('roster_role_assignment');
				$rallocs = Roster_Role_Assignment::getUpcomingAssignments($GLOBALS['user_system']->getCurrentUser('id'));
				if ($rallocs) {
					foreach ($rallocs as $date => $allocs) {
						 ?>
						 <h5><?php echo date('j M', strtotime($date)); ?></h5>
						 <?php
						 foreach ($allocs as $alloc) {
							  echo $alloc['cong'].' '.$alloc['title'].'<br />';
						 }
					}
					?>
					<div class="pull-right"><a href="./?view=persons&personid=<?php echo $GLOBALS['user_system']->getCurrentUser('id'); ?>#rosters">See all</a></div>
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
		?>
		</div>
		<?php
		$reportVals = Array('all');
		if ($GLOBALS['user_system']->havePerm(PERM_RUNREPORT)) {
			$reportVals[] = 'auth';
		}
		$owners = Array(NULL, $GLOBALS['user_system']->getCurrentUser('id'));
		$frontpagereports = $GLOBALS['system']->getDBObjectData('person_query', Array('show_on_homepage' => $reportVals, 'owner' => $owners), 'AND', 'name');
		foreach ($frontpagereports as $reportid => $reportparams) {
			$report = $GLOBALS['system']->getDBObject('person_query', $reportid);
			?>
			<div class="homepage-box">
				<h3><?php echo $reportparams['name']; ?></h3>
				<?php $report->printResults(); ?>
			</div>
			<?php
		 }
	}
}