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
				<style media="print">
					html body * {
						color: black;
						text-decoration: none;
					}
				</style>
				<style>
					body {
						margin: 1cm;
					}
					* {
						font-family: "Arial Narrow", Arial, Sans Serif
					}
					td, th {
						padding: 5px 10px;
						vertical-align: top;
						font-size: 11pt;
					}
					thead th {
						background-color: #555;
						color: white;
					}
					tbody th {
						text-align: left;
					}
					tbody th:first-child {
						padding-left: 0px;
					}
					th * {
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
					td.narrow {
						width: 1%;
						white-space: nowrap;
					}
				</style>
			</head>
			<body>
				<h1><?php echo ents($service->toString()); ?></h1>
				<?php
				$service->printRunSheetPersonnelTable();
				$service->printRunSheet();
				?>
			</body>
		</html>
		<?php
	}
}
?>
