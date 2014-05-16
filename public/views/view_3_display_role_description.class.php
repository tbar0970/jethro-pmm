<?php
class View_Display_Role_Description extends View
{
	var $_role = null;

	function processView()
	{
		if (!empty($_REQUEST['role'])) {
			$this->_role =& $GLOBALS['system']->getDBObject('roster_role', (int)$_REQUEST['role']);
		}
	}
	
	function getTitle()
	{
		if ($this->_role) {
			return 'Roster Role: '.$this->_role->getFormattedValue('congregationid').' '.$this->_role->getValue('title');
		} else {
			return 'Display Role Description';
		}
	}

	function printView()
	{
		if ($this->_role) {
			echo $this->_role->getValue('details');
		} else {
			foreach ($GLOBALS['system']->getDBObjectdata('congregation', Array('!meeting_time' => ''), 'AND', 'meeting_time') as $congid => $cong_details) {
				?>
				<h3><?php echo ents($cong_details['name']); ?></h3>
				<ul>
				<?php
				$roles = $GLOBALS['system']->getDBObjectData('roster_role', Array('!details' => '', 'congregationid' => $congid), 'AND', 'title');
				foreach ($roles as $id => $detail) {
					?>
					<li><a href="<?php echo build_url(Array('role' => $id)); ?>"><?php echo ents($detail['title']); ?></a></li>
					<?php
				}
				?>
				</ul>
			<?php
		}
		?>
		<h3>Non-Congregational</h3>
                <ul>
                        <?php
                        $roles = $GLOBALS['system']->getDBObjectData('roster_role', Array('!details' => '', 'congregationid' => 0), 'AND', 'title');
                        foreach ($roles as $id => $detail) {
                                ?>
                                <li><a href="<?php echo build_url(Array('role' => $id)); ?>"><?php echo ents($detail['title']); ?></a></li>
                                <?php
                        }
                        ?>
                </ul>
		<?php	


	}



}

}

?>
