<?php
class Call_Service_Comp_Detail extends Call
{
	function run()
	{
		$GLOBALS['system']->initErrorHandler();
		$comp = $GLOBALS['system']->getDBObject('service_component', (int)$_REQUEST['id']);
		if ($comp) {
			if (!empty($_REQUEST['head'])) {
				?>
				<html>
					<head>
						<title>Jethro PMM - Service Component Detail</title>
						<?php include 'templates/head.template.php'; ?>

					</head>
					<body>
						<div id="body">
						<?php
			}
			include 'templates/service_component_detail.template.php';
			if (!empty($_REQUEST['head'])) {
				?>
						</div>
					</body>
				</html>
				<?php
			}
		} else {
			echo 'Component not found';
		}
	}
}