<?php
/**
 * LEVI CPM
 * 
 *
 * @author Tom Barrett <tom@tombarrett.id.au>
 * @version $Id: call_display_roster.class.php,v 1.2 2013/03/19 09:47:51 tbar0970 Exp $
 * @package jethro-pmm
 */
class Call_Display_Roster extends Call
{
	/**
	 * Execute this call
	 *
	 * @return void
	 * @access public
	 */
	function run()
	{
		$roster_id = (int)array_get($_REQUEST, 'viewid');
		if (empty($roster_id)) return;
		$view = $GLOBALS['system']->getDBObject('roster_view', $roster_id);

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
						padding: 3px 1ex;
						font-size: 0.8em;
						vertical-align: top;
					}
					thead th {
						background-color: #555;
						color: white;
					}
					thead th * {
						color: white !important;
					}
					table {
						border-collapse: collapse;
					}
					.smallprint {
						margin-top: 1ex;
						font-size: 75%;
					}
					tbody .roster-date {
						text-align: right;
					}
					#body table.roster p {
						line-height: 1.0;
						margin: 1.5px 0px;
						padding: 1px;
						max-width: 20em;
					}
					#body table.roster p.title {
						font-style: italic;
						font-size: 105%;
					}
					#body table.roster p.bible {
						color: @jethroLinkColor;
						font-weight: 400;
					}
					#body table.roster p.bible strong {
						color: @jethroLinkColor;
						font-weight: 800;
					}
					#body table.roster p.notes {
						font-size: 80%;
						color: #666;
						font-style: italic;
					}
				</style>
			</head>
			<body id="body">
				<h1>Roster: <?php $view->printFieldValue('name'); ?></h1>
				<?php

				$start_date = substr(array_get($_REQUEST, 'start_date', ''), 0, 10);
				$end_date = substr(array_get($_REQUEST, 'end_date', ''), 0, 10);
				$view->printView($start_date, $end_date, FALSE, TRUE, TRUE);
				?>
			</body>
		</html>
		<?php
	}
}