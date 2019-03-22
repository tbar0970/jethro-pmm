<?php
class View_Display_Roster extends View
{
	var $_roster_view = null;

	function processView()
	{
		if (!empty($_REQUEST['roster_view'])) {
			if (defined('PUBLIC_ROSTER_SECRET')
					&& strlen(PUBLIC_ROSTER_SECRET)
					&& (array_get($_REQUEST, 'secret') != PUBLIC_ROSTER_SECRET)
			) {
				add_message("Sorry, this roster URL is not valid because it does not contain the secret key.  Please contact your church administrator for assistance.", 'error');
			} else {
				$this->_roster_view = $GLOBALS['system']->getDBObject('roster_view', (int)$_REQUEST['roster_view']);
			}
		}
	}

	function getTitle()
	{
		if ($this->_roster_view) {
			return $this->_roster_view->getValue('name');
		} else {
			return 'Display Roster';
		}
	}

	function printView()
	{
		if ($this->_roster_view) {
			$end_date = NULL;
			if (!empty($_REQUEST['weeks'])) {
				$end_date = date('Y-m-d', strtotime('+'.(((int)$_REQUEST['weeks']*7)+1).' days'));
			}
			$this->_roster_view->printView(NULL, $end_date, FALSE, TRUE);
		} else if (defined('PUBLIC_ROSTER_SECRET')
					&& strlen(PUBLIC_ROSTER_SECRET)
					&& (array_get($_REQUEST, 'secret') != PUBLIC_ROSTER_SECRET)) {
			print_message("Please contact your church administrator to get the private URLs for viewing rosters");
			exit;
		} else {

			?>
			<ul>
			<?php
			$views = $GLOBALS['system']->getDBObjectData('roster_view', Array('!visibility' => ''), 'AND', 'name');
			foreach ($views as $id => $detail) {
				?>
				<li><a href="<?php echo build_url(Array('roster_view' => $id)); ?>"><?php echo ents($detail['name']); ?></a></li>
				<?php
			}
			?>
			</ul>
			<?php
		}

	}

}
