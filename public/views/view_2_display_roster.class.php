<?php
class View_Display_Roster extends View
{
	var $_roster_view = null;

	function processView()
	{
		if (!empty($_REQUEST['roster_view'])) {
			$this->_roster_view =& $GLOBALS['system']->getDBObject('roster_view', (int)$_REQUEST['roster_view']);
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
			$this->_roster_view->printView(NULL, NULL, FALSE, TRUE);
		} else {
			?>
			<ul>
			<?php
			$views = $GLOBALS['system']->getDBObjectData('roster_view', Array('is_public' => TRUE), 'AND', 'name');
			foreach ($views as $id => $detail) {
				?>
				<li><a href="<?php echo build_url(Array('roster_view' => $id)); ?>"><?php echo htmlentities($detail['name']); ?></a></li>
				<?php
			}
			?>
			</ul>
			<?php
		}



	}

}

?>
