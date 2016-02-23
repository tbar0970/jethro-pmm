<?php
/* Class for serialising Tasks and sending to the client side. */
class Task{
	public $view;
	public $icon;
	public $name;
	public $type;
	public $typeId;
	public $id;
	public $subject;
}
/* Class for serialising Roster Allocations and sending to the client side. */
class RosterAllocation{
	public function __construct(){
		$this->allocs = Array();
	}
	public $date;
	public $allocs;
}
/* Class for encapsulating and serialising all variables to send to the client side. */
class ViewHomeResponseObject{
	public function __construct(){
		$this->num_cols = 1;
		$this->tasks = Array();
		$this->rallocs = Array();
		$this->systemName = SYSTEM_NAME;
		$this->baseUrl = BASE_URL;
	}
	public $num_cols;
	public $tasks;
	public $rallocs;
	public $systemName;
	public $baseUrl;
	public $notesForFutureActionUrl;
	public $notesCount;
	public $currentUserId;
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
		$response = new ViewHomeResponseObject();
		$response->currentUserId = $GLOBALS['user_system']->getCurrentUser('id');
		if ($GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) $response->num_cols++;
		if ($GLOBALS['user_system']->havePerm(PERM_VIEWROSTER)) $response->num_cols++;

		if ( $GLOBALS['user_system']->havePerm(PERM_VIEWNOTE)) {
			$user =& $GLOBALS['system']->getDBObject('staff_member', $GLOBALS['user_system']->getCurrentUser('id'));
			$response->notesForFutureActionUrl = build_url(Array('view' => 'notes__for_future_action', 'assignee' => $user->id));
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
					array_push($response->$tasks,$t);
				}
			}
			$later = $user->getTasks('later');
			$response->notesCount = count($later);
		}
		if ($GLOBALS['user_system']->havePerm(PERM_VIEWROSTER)) {
			$GLOBALS['system']->includeDBClass('roster_role_assignment');
			$rallocs = Roster_Role_Assignment::getUpcomingAssignments($GLOBALS['user_system']->getCurrentUser('id'));
			if ($rallocs) {
				foreach ($rallocs as $date => $allocs) {
					 $ra = new RosterAllocation();
					 $ra->date = date('j M', strtotime($date));
					 foreach ($allocs as $alloc) {
						  array_push($ra->allocs, $alloc['cong'].' '.$alloc['title']);
					 }
					 array_push($response->rallocs,$ra);
				}
			}
		}
		$responseJson = json_encode($response);
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
			<div class="homepage-box my-roster">
				<h3>
					<a href="?view=_manage_ical" class="pull-right hidden-phone"><small>Subscribe</small></a>
					Upcoming roster<span> allocations</span>
				</h3>
				<div name="rosterAllocations" ng-repeat="ralloc in rallocs">
					<h5>{{ralloc.date}}</h5>
					<p ng-repeat="alloc in ralloc.allocs">{{alloc}}</p>
				</div>
					<div class="pull-right"><a href="./?view=persons&personid={{currentUserId}}#rosters">See all</a></div>
					<p name="noRosterAllocations"><i>None</i></p>
			 </div>
		</div>
		<script>
		/* mainTemplateApp is initialised in main.template.php. */
		mainTemplateApp.controller("Homepage",function($scope){
			var response = JSON.parse('{"response":<?php echo $responseJson; ?>}').response;
			$scope.numCols = response.num_cols;
			$scope.systemName = response.systemName;
			$scope.baseUrl = response.baseUrl;
			$scope.tasks = response.tasks;
			if ($scope.tasks.length > 0){
					$("[name='noTasks']").addClass("hidden");
			} else {
				$("[name='tasks']").addClass("hidden");
			}
			$scope.notesForFutureActionUrl = response.notesForFutureActionUrl;
			$scope.notesCount = response.notesCount;
			$scope.notesCount += ($scope.notesCount == 1 ? " note" : " notes");
			$scope.rallocs = response.rallocs;
			if ($scope.rallocs.length > 0){
				$("[name='noRosterAllocations']").addClass("hidden");
			} else {
				$("[name='rosterAllocations']").addClass("hidden");
			}
			$scope.currentUserId = response.currentUserId;
		});
		</script>
		<?php
	}
}
?>
