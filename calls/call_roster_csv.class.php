<?php
/**
 * LEVI CPM
 * 
 *
 * @author Tom Barrett <tom@tombarrett.id.au>
 * @version $Id: call_display_roster.class.php,v 1.2 2013/03/19 09:47:51 tbar0970 Exp $
 * @package jethro-pmm
 */
class Call_Roster_CSV extends Call
{
	/**
	 * Execute this call
	 *
	 * @return void
	 * @access public
	 */
	function run()
	{
		$roster_id = (int)array_get($_REQUEST, 'roster_view');
		if (empty($roster_id)) return;
		$view = $GLOBALS['system']->getDBObject('roster_view', $roster_id);
		$start_date = substr(array_get($_REQUEST, 'start_date', ''), 0, 10);
		$end_date = substr(array_get($_REQUEST, 'end_date', ''), 0, 10);
		header('Content-type: text/csv');
		header('Content-disposition: attachment; filename="'.preg_replace('[^a-zA-Z0-9]', '_', $view->getValue('name')).'.csv"');
		$view->printCSV($start_date, $end_date);
		
	}
}