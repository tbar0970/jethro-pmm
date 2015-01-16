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
					* {
						font-family: sans-serif;
					}
					td, th {
						padding: 5px 10px;
						vertical-align: top;
					}
					th {
						background-color: #555;
						color: white;
					}
					th * {
						color: white !important;
					}
					table {
						border-collapse: collapse;
					}
				</style>
			</head>
			<body>
				<h1><?php echo ents($service->toString()); ?></h1>
				<?php $service->printServicePlan(); ?>
			</body>
		</html>
		<?php
	}
}
?>
