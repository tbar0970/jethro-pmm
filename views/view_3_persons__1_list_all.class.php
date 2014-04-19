<?php
include_once 'include/paginator.class.php';
class View_Persons__List_All extends View
{
	var $_person_data;
	var $_paginator;

	function processView()
	{
		$params = Array();
		if (empty($_REQUEST['show_archived'])) {
			$params['!status'] = 'archived';
		}
		if (empty($_SESSION['total_persons'])) {
			$_SESSION['total_persons'] = $GLOBALS['db']->queryOne('SELECT count(*) from person');
		}
		if (!empty($_REQUEST['slice_size'])) {
			$this->_paginator = new Paginator((float)$_REQUEST['slice_size'], (int)$_REQUEST['slice_num']);
			$params['-SUBSTRING(person.last_name, 1, 1)'] = $this->_paginator->getCurrentSliceStartEnd();
		} else if ($_SESSION['total_persons'] > CHUNK_SIZE) {
			$num_chunks = ceil($_SESSION['total_persons'] / CHUNK_SIZE);
			$this->_paginator = new Paginator(26 / $num_chunks, 1);
			$params['-SUBSTRING(person.last_name, 1, 1)'] = $this->_paginator->getCurrentSliceStartEnd();
		}
		$this->_person_data = ($GLOBALS['system']->getDBObjectData('person', $params, 'AND', 'last_name'));
	}

	
	function getTitle()
	{
		$res = 'All Persons';
		return $res;

	}

	
	function printView()
	{
		if ($this->_paginator) {
			echo '<p>';
			$this->_paginator->printPageNav();
			echo '</p>';
		}

		if (empty($_REQUEST['show_archived'])) {
			echo '<p class="pull-right"><a href="'.build_url(Array('show_archived' => 1)).'">Include Archived Persons</a></p>';
		} else {
			echo '<p class="pull-right"><a href="'.build_url(Array('show_archived' => NULL)).'">Exclude Archived Persons</a></p>';
		}

		$GLOBALS['system']->includeDBClass('person');
		$persons =& $this->_person_data;
		if (empty($persons)) {
			if ($this->_paginator) {
				?>
				<p><strong>No persons in this range</strong></p>
				<?php
			} else {
				?>
				<p><strong>No persons were found</strong></p>
				<a href="<?php echo build_url(Array('show_archived' => 1)); ?>">Include Archived Persons</a>
				<?php
			}
		} else {
			if ($this->_paginator) {
				echo '<p><strong>'.count($persons).' persons in this range</strong></p>';
			} else  {
				echo '<p><strong>'.count($persons).' persons in total</strong></p>';
			}
			$special_fields = Array('congregation');
			include dirname(dirname(__FILE__)).'/templates/person_list.template.php';
		}
	}
}
?>