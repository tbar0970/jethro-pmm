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
				<?php if ($GLOBALS['system']->featureEnabled('BIBLE_API')) { ?>
				<link rel="preconnect" href="https://fonts.googleapis.com">
				<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
				<link href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
				<link type="text/css" rel="stylesheet" href="https://assets.api.bible/css/scripture-styles.css" />
				<style>
					body {
						font-family: "Noto Serif", serif;
						font-size: 1.125rem;
						line-height: 1.5;
						color: #2a2a2a;
					}
				</style>
				<?php } else { ?>
				    body {
					font-family: sans-serif;
				    }
				<?php } ?>
				<style media="print">
					html body * {
						color: black;
						text-decoration: none;
					}
				</style>
				<style>
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
