<?php
class Task{
	public $view;
	public $icon;
	public $name;
	public $type;
	public $typeId;
	public $id;
	public $subject;
}
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
		$clientSideTasks = Array();
		if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) $num_cols++;
		if ($GLOBALS['user_system']->havePerm(PERM_VIEWROSTER)) $num_cols++;

		if ( $GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
			$user =& $GLOBALS['system']->getDBObject('staff_member', $GLOBALS['user_system']->getCurrentUser('id'));
			$tasks = $user->getTasks('now');
				if ($tasks) {
					foreach ($tasks as $id => $task) {
						$t = new Task();
						$t->view = ($task['type'] == 'person') ? 'persons' : 'families';
						$t->icon = ($task['type'] == 'person') ? 'user' : 'home';
						$t->name = ents($task['name']);
						$t->type = $task['type'];
						$t->typeId = $task[$task['type'].'id'];
						$t->id = $id;
						$t->subject = ents($task['subject']);
						array_push($clientSideTasks,$t);
					}
				} else {
					//Display "None"
				}
				$later = $user->getTasks('later');
				$count = count($later);
				if ($count) {
				}
		}
		?>
		<div ng-controller="Homepage" class="homepage homepage-{{numCols}}-col">

		<div class="homepage-box search-forms">
			<h3>
				<a class="pull-right hide-phone" 
				   href="javascript:if (sp = prompt('Search {{systemName}} for: ')) window.location='{{baseUrl}}?view=_mixed_search&search='+sp"
				   onclick="prompt('To create a search-jethro button in your browser, save the following code as a bookmark/favourite: ', this.href); return false"
				>
					<small>Bookmark</small>
				</a>
				System-Wide Search</h3>
			<label class="msie-only">Enter a person, family or group name, or phone number or email:</label>
			<form method="get">
				<input type="hidden" name="view" value="_mixed_search" />
				<span class="input-prepend input-append">
					<span class="add-on"><i class="icon-search"></i></span>
					<input type="text" name="search" class="" placeholder="Name, Phone or Email" /> 
					<button type="submit" class="btn">Go</button>
				</span>
			</form>
		</div>
			<div class="homepage-box my-notes">
				<h3>Notes <span>for immediate action</span></h3>
					<table name="tasks" class="table table-condensed table-striped table-hover clickable-rows" width="100%">
						<thead>
							<tr>
								<th>For</th>
								<th>Subject</th>
							</tr>
						</thead>
						<tbody>
							<tr ng-repeat="task in tasks">
								<td class="narrow"><i class="icon-{{task.icon}}"></i> {{task.name}}</td>
								<td><a href="?view={{task.view}}&{{task.type}}id={{task.typeId}}#note_{{task.id}}">{{task.subject}}</a></td>
							</tr>
						</tbody>
					</table>
					<p name="noTasks"><i>None</i></p>
					<p class="align-right">You have <a href="{{notesForFutureActionUrl}}">{{notesCount}} for future action</a></p>
			</div>
			<?php
		if ($GLOBALS['user_system']->havePerm(PERM_VIEWROSTER)) {
			?>
			<div class="homepage-box my-roster">
				<h3>
					<a href="?view=_manage_ical" class="pull-right hidden-phone"><small>Subscribe</small></a>
					Upcoming roster<span> allocations</span>
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
					<p><i>None</i></p>
			 </div>
		</div>
		<?php
				}
		}
		?>
		<script>
		/* mainTemplateApp is initialised in main.template.php. */
		mainTemplateApp.controller("Homepage",function($scope){
			$scope.numCols = "<?php echo $num_cols; ?>";
			$scope.systemName = "<?php //echo $SYSTEM_NAME; ?>";
			$scope.baseUrl = "<?php //echo $BASE_URL; ?>";
			$scope.tasks = JSON.parse('{"tasks":<?php echo json_encode($clientSideTasks); ?>}').tasks;
			if ($scope.tasks.length > 0){
					$("[name='noTasks']").addClass("hidden");
			} else {
				$("[name='tasks']").addClass("hidden");
			}
			$scope.notesForFutureActionUrl = "<?php echo build_url(Array('view' => 'notes__for_future_action', 'assignee' => $user->id)); ?>";
			$scope.notesCount = <?php echo count($later); ?>;
			$scope.notesCount += ($scope.notesCount == 1 ? " note" : " notes");
		});
		</script>
		<?php
	}
}
?>
