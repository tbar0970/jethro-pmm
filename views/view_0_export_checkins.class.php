<?php
class View__Export_Checkins extends View
{
	static function getMenuPermissionLevel()
	{
		return PERM_VIEWATTENDANCE;
	}

	private $data = NULL;
	private $venue = NULL;

	function processView() {
		$this->venue = new Venue($_REQUEST['venueid']);
		if (!empty($_REQUEST['from_y'])) {
			$from = process_widget('from', Array('type' => 'date')).' 00:00:00';
			$to = process_widget('to', Array('type' => 'date')).' 23:59:59';
			$params['-timestamp'] = Array($from, $to);
			$params['venueid'] = $_REQUEST['venueid'];
			$this->data = $GLOBALS['system']->getDBObjectData('checkin', $params);
			if ($this->data) {
				header("Content-type: text/csv");
				header("Content-Disposition: attachment; filename=checkins.csv");
				$fp = fopen('php://output', 'w');
				$firstRow = reset($this->data);
				fputcsv($fp, array_keys($firstRow));
				foreach ($this->data as $d) {
					$d['venueid'] = $this->venue->getValue('name');
					fputcsv($fp, $d);
				}
				fclose($fp);
				exit;

			}
		}
	}

	function getTitle()
	{
		return "Export check-ins for ".$this->venue->getValue('name');
	}

	public function printView()
	{
		?>
		Please select the date range to export:
		<form method="post" class="form-horizontal well">
		From <?php print_widget('from', Array('type' => 'date'), date('Y-m-d', strtotime('-1 month'))); ?>
		to <?php print_widget('from', Array('type' => 'date'), date('Y-m-d')); ?>
		<?php
		?>
		<input type="submit" class="btn" />
		</form>
		<?php
	}

}