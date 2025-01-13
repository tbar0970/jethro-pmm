<?php
class Call_Service_Content extends Call
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
					h3 {
						text-transform: uppercase;
						text-align: center;
						color: #888;
					}
					small {
						font-style: italic;
					}
					p, small {
						margin: 10px 0;
					}
					h4 {
						margin: 20px 0 10px 0;
					}
				</style>
			</head>
			<body>
				<h1><?php echo ents($service->toString(TRUE)); ?></h1>
				<?php $service->printServiceContent(); ?>
			</body>
		</html>
		<?php
	}
}