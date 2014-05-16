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
		$num_cols = 1;
		if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) $num_cols++;
		if ($GLOBALS['user_system']->havePerm(PERM_VIEWROSTER)) $num_cols++;

		?>
		<div class="homepage homepage-<?php echo $num_cols; ?>-col">

		<div class="homepage-box search-forms">
			<h3>Search</h3>

			<label class="msie-only">Persons</label>
			<form method="get">
				<input type="hidden" name="view" value="persons__search" />
				<div class="input-prepend input-append">
					<span class="add-on"><i class="icon-user"></i></span>
					<input type="text" name="name" id="search-name" class="" placeholder="Person" /> 
					<button type="submit" class="btn">Go</button>
				</div>
			</form>

			<label class="msie-only">Families</label>
			<form method="get">
				<input type="hidden" name="view" value="families__search" />
				<span class="input-prepend input-append">
					<span class="add-on"><i class="icon-home"></i></span>
					<input type="text" name="name" id="search-family" class="" placeholder="Family" /> 
					<button type="submit" class="btn">Go</button>
				</span>
			</form>

			<label class="msie-only">Groups</label>
			<form method="get">
				<input type="hidden" name="view" value="groups__search" />
				<span class="input-prepend input-append">
					<span class="add-on"><i class="icon-th"></i></span>
					<input type="text" name="name" class="" placeholder="Group" /> 
					<button type="submit" class="btn">Go</button>
				</span>
			</form>

			<label class="msie-only">Look up phone number</label>
			<form method="get">
				<input type="hidden" name="view" value="_mixed_search" />
				<span class="input-prepend input-append">
					<span class="add-on"><i class="icon-signal"></i></span>
					<input type="text" name="tel" class="" placeholder="Phone #" /> 
					<button type="submit" class="btn">Go</button>
				</span>
			</form>

		</div>

		<?php
		if ( $GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
			$user =& $GLOBALS['system']->getDBObject('staff_member', $GLOBALS['user_system']->getCurrentUser('id'));
			$tasks = $user->getTasks('now');
			?>
			<div class="homepage-box my-notes">
				<h3>Notes <span>for immediate action</span></h3>
				<?php
				if ($tasks) {
					?>
					<table class="table table-condensed table-striped table-hover clickable-rows" width="100%">
						<thead>
							<tr>
								<th>For</th>
								<th>Subject</th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ($tasks as $id => $task) {
								$view = ($task['type'] == 'person') ? 'persons' : 'families';
								$icon = ($task['type'] == 'person') ? 'user' : 'home';
								?>
								<tr>
									<td class="narrow"><i class="icon-<?php echo $icon; ?>"></i> <?php echo ents($task['name']); ?></td>
									<td><a href="?view=<?php echo $view; ?>&<?php echo $task['type']; ?>id=<?php echo $task[$task['type'].'id']; ?>#note_<?php echo $id; ?>"><?php echo ents($task['subject']); ?></a></td>
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
					<p class="align-right">You have <a href="<?php echo build_url(Array('view' => 'notes__for_future_action', 'assignee' => $user->id)); ?>"><?php echo count($later); ?> note<?php echo ($count > 1) ? 's' : ''; ?> for future action</a></p>
					<?php
				}
				?>
			</div>
			<?php
		}

		if ($GLOBALS['user_system']->havePerm(PERM_VIEWROSTER)) {
			?>
			<div class="homepage-box my-roster">
				<h3>Upcoming roster<span> allocations</span></h3>
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

	}
}
?>
