<?php
class Call_Service_Plan extends Call
{
	/**
	 * Execute this call
	 *
	 * @return void
	 * @access public
	 */
	function run()
	{
		$service = $GLOBALS['system']->getDBObject('service', (int)$_REQUEST['serviceid']);

		?>
		<html>
			<head>
				<title><?php echo $service->getValue('date').'_'.$service->getCongregation()->getValue('meeting_time'); ?></title>
				<style>
					body {
						margin: 1cm;
					}
					table {
						border-collapse: collapse;
						width: 100%;
					}
					table.table-bordered td, table.table-bordered th {
						border: 1px solid black;
					}
					* {
						font-family: "Arial Narrow", Arial, Sans Serif
					}
					td, th {
						padding: 5px 7px;
						vertical-align: top;
						font-size: 11pt;
					}
					.center {
						text-align: center;
					}
					thead th {
						background-color: #555;
						color: white !important;
						text-align: left;
					}
					tbody th {
						text-align: left;
					}
					tbody th:first-child {
						padding-left: 0px;
					}
					thead th * {
						color: white !important;
					}
					table {
						border-collapse: collapse;
					}
					td.run-sheet-comments {
						font-size: 85%;
					}
					td.run-sheet-comments * {
						line-height: 1em !important;
					}
					td.narrow, th.narrow, table.personnel th {
						width: 1%;
						white-space: nowrap;
					}
					a, a:visited {
						color: #444;
					}
				</style>
				<style media="print">
					html body * {
						color: black;
					}
					thead th * {
						color: white !important;
					}
					body {
						margin: 0px !important;
					}
				</style>

			</head>
			<body>
				<h1><?php echo ents($service->toString(TRUE)); ?></h1>
				<p><?php $service->PrintFieldValue('summary_inline'); ?></p>
				<?php
				$service->printRunSheetPersonnelTable();
				$service->printRunSheet();
				?>
			</body>
		</html>
		<?php
	}
}