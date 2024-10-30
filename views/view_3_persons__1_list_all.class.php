<?php
include_once 'include/paginator.class.php';
class View_Persons__List_All extends View
{
	var $_person_data;
	var $_paginator;

	function processView()
	{
		if (!empty($_REQUEST['search'])) {
			$this->_person_data = Person::getPersonsBySearch($_REQUEST['search']);

			if ((count($this->_person_data) == 1)) {
				add_message('One matching person found');
				redirect('persons', Array('name' => NULL, 'personid' => key($this->_person_data)));
			}

			// Put all the archived ones last
			$archiveds = Array();
			foreach ($this->_person_data as $k => $v) {
				if (in_array($v['status'], Person_Status::getArchivedIDs())) {
					$archiveds[$k] = $v;
					unset($this->_person_data[$k]);
				}
			}
			foreach ($archiveds as $k => $v) {
				$this->_person_data[$k] = $v;
			}
		} else {

			$params = Array();
			if (empty($_REQUEST['show_archived'])) {
				$params['!(status'] = Person_Status::getArchivedIDs();
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
	}

	
	function getTitle()
	{
		if (!empty($_REQUEST['search'])) {
			return _('Person search results');
		} else {
			return _('All Persons');
		}
	}

	
	function printView()
	{
		?>
		<div class="list-all-controls">
		<?php
		// Search form - show top right if not yet searching
		$formclass = empty($_REQUEST['search']) ? 'pull-right' : '';
		?>
		<form method="get" class="<?php echo $formclass; ?> form-horizontal min fullwidth-phone">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>">
			<span class="input-append">
				<input type="text" name="search" enterkeyhint="Search" placeholder="Search persons..." value="<?php echo ents(array_get($_REQUEST, 'search', '')); ?>">
				<button type="submit" class="btn"><i class="icon-search"></i></button>
			<?php
			if (!empty($_REQUEST['search'])) {
				?>
				<a class="btn" href="<?php echo build_url(Array('search'=>NULL));?>"><i class="icon-remove"></i></a>
				<?php
			}
			?>
			</span>
		</form>
		<?php
		if (empty($_REQUEST['search'])) {
			if ($this->_paginator) {
				$this->_paginator->printPageNav();
			}
			if (empty($_REQUEST['show_archived'])) {
				echo '<p class="pull-right"><i class="icon-eye-open"></i> <a class="soft" href="'.build_url(Array('show_archived' => 1)).'">Include Archived</a></p>';
			} else {
				echo '<p class="pull-right"><i class="icon-eye-close"></i> <a class="soft" href="'.build_url(Array('show_archived' => NULL)).'">Exclude Archived</a></p>';
			}
		}

		$GLOBALS['system']->includeDBClass('person');
		$persons =& $this->_person_data;
		if (empty($persons)) {
			if ($this->_paginator) {
				?>
				<p><strong><?php echo _('No persons in this range');?></strong></p>
				<?php
			} else if (!empty($_REQUEST['search'])) {
				?>
				<p><strong><?php echo _('No matching families were found')?></strong></p>			
				<?php
			} else {
				?>
				<p><strong><?php echo _('No persons were found');?></strong></p>
				<a href="<?php echo build_url(Array('show_archived' => 1)); ?>"><?php echo _('Include Archived Persons');?></a>
				<?php
			}
			 
		} else {
			if ($this->_paginator) {
				echo '<p><strong>'.count($persons)._(' persons in this range').'</strong></p>';
			} else if (!empty($_REQUEST['search'])) {
				?>
				<p><strong><?php echo count($persons).' '._('matching persons found').':';?></strong></p>			
				<?php
			} else  {
				echo '<p><strong>'.count($persons)._(' persons in total').'</strong></p>';
			}
		}
		?>
		</div>
		<?php
		if (!empty($persons)) {
			$special_fields = Array('congregation');
			include dirname(dirname(__FILE__)).'/templates/person_list.template.php';
		}
	}
}