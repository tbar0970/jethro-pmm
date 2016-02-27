<?php
/* This class is a holder for the data that gets sent to the client. */
class PersonStatistic{
	public $statusName;
	public $count;
	public function __construct(){
		$this->count = 0;
		$this->statusName = "";
	}
}
class View_Persons__Statistics extends View
{
	static function getMenuPermissionLevel()
	{
		return PERM_RUNREPORT;
	}

	function getTitle()
	{
		return 'System-wide Person Statistics';
	}
	
	function printView()
	{
		$GLOBALS['system']->includeDBClass('person');
		$stats = Person::getStatusStats();
		$personStats = Array();
		
		foreach ($stats as $status_name => $count) {
			$p = new PersonStatistic();
			$p->statusName = ents($status_name);
			$p->count = (int)$count;
			array_push($personStats,$p);
		}
		$personStatsJson = json_encode($personStats);
		?>
		<table ng-controller="ViewPersonsStatistics" class="table table-auto table-striped table-bordered">
			<tr ng-repeat="p in personStats">
				<th>{{p.statusName}}</th>
				<td>{{p.count}}</td>
			</tr>
		</table>
		<script>
			/* mainTemplateApp is initialised in main.template.php. */
			mainTemplateApp.controller("ViewPersonsStatistics",function($scope){
				$scope.personStats = <?php echo $personStatsJson ?>;
			});
		</script>
		<?php
	}


}
?>
