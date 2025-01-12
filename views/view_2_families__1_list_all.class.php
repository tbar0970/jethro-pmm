<?php
include_once 'include/paginator.class.php';
class View_Families__List_All extends View
{
	var $_family_data;
	var $_paginator;

	function processView()
	{
		if (!empty($_REQUEST['search'])) {
			
			$search_params = Array('family_name' => $_REQUEST['search']);
			if (!empty($search_params)) {
				$this->_family_data = ($GLOBALS['system']->getDBObjectData('family', $search_params, 'AND', 'family_name'));
			}
			if (empty($this->_family_data)) {
				$search_params['family_name'] = $search_params['family_name'].'%';
				$this->_family_data = ($GLOBALS['system']->getDBObjectData('family', $search_params, 'AND', 'family_name'));
			}
			if (empty($this->_family_data)) {
				$search_params['family_name'] = '%'.$search_params['family_name'].'%';
				$this->_family_data = ($GLOBALS['system']->getDBObjectData('family', $search_params, 'AND', 'family_name'));
			}

			if (count($this->_family_data) == 1) {
				add_message('One matching family found');
				redirect('families', Array('familyid' => key($this->_family_data), 'name' => NULL)); //exits
			}

			// Put all the archived ones last
			$archiveds = Array();
			if (!empty($this->_family_data)) {
				foreach ($this->_family_data as $k => $v) {
					if ($v['status'] == 'archived') {
						$archiveds[$k] = $v;
						unset($this->_family_data[$k]);
					}
				}
			}
			foreach ($archiveds as $k => $v) {
				$this->_family_data[$k] = $v;
			}
		} else {
			$params = Array();
			if (empty($_REQUEST['show_archived'])) {
				$params['!status'] = 'archived';
			}
			if (empty($_SESSION['total_families'])) {
				$_SESSION['total_families'] = $GLOBALS['db']->queryOne('SELECT count(familyid) from family f join person p on p.familyid = f.id');
			}
			if (!empty($_REQUEST['slice_size'])) {
				$this->_paginator = new Paginator((float)$_REQUEST['slice_size'], (int)$_REQUEST['slice_num']);
				$params['-SUBSTRING(family.family_name, 1, 1)'] = $this->_paginator->getCurrentSliceStartEnd();
			} else if ($_SESSION['total_families'] > CHUNK_SIZE) {
				$num_chunks = ceil($_SESSION['total_families'] / CHUNK_SIZE);
				$this->_paginator = new Paginator(26 / $num_chunks, 1);
				$params['-SUBSTRING(family.family_name, 1, 1)'] = $this->_paginator->getCurrentSliceStartEnd();
			}
			$this->_family_data = ($GLOBALS['system']->getDBObjectData('family', $params, 'AND', 'family_name'));
		}
	}

	
	function getTitle()
	{
		if (!empty($_REQUEST['search'])) {
			return _('Family search results');
		} else {
			return _('All Families');
		}

	}

	
	function printView()
	{
		// Search form - show top right if not yet searching
		$formclass = empty($_REQUEST['search']) ? 'pull-right' : '';
		?>
		<div class="list-all-controls">
		<form method="get" class="<?php echo $formclass; ?> form-horizontal min fullwidth-phone">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>">
			<span class="input-append">
				<input type="text" name="search" enterkeyhint="Search" placeholder="Search families..." value="<?php echo ents(array_get($_REQUEST, 'search', '')); ?>">
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
			// pagination - show top left
			if ($this->_paginator) {
				$this->_paginator->printPageNav();
			}
			// show/hide archived - show on right under search box
			if (empty($_REQUEST['show_archived'])) {
				echo '<a class="soft pull-right hidden-phone"href="'.build_url(Array('show_archived' => 1)).'"><i class="icon-eye-open"></i>'._('Include Archived').'</a>';
			} else {
				echo '<a class="soft pull-right hidden-phone" href="'.build_url(Array('show_archived' => NULL)).'"><i class="icon-eye-close"></i>'._('Exclude Archived').'</a>';
			}
		}

		// count - show on left under pagination
		$GLOBALS['system']->includeDBClass('family');
		$families =& $this->_family_data;
		if (empty($families)) {
			if ($this->_paginator) {
				?>
				<p><strong><?php echo _('No families in this range')?></strong></p>
				<?php
			} else if (!empty($_REQUEST['search'])) {
				?>
				<p><strong><?php echo _('No matching families were found')?></strong></p>			
				<?php
			} else {
				?>
				<p><strong><?php echo _('No families were found')?></strong></p>
				<a href="<?php echo build_url(Array('show_archived' => 1)); ?>"><?php echo _('Include Archived families')?></a>
				<?php
			}
		} else {
			if ($this->_paginator) {
				echo '<p class="nowrap"><strong>'.count($families).' '._('families in this range').'</strong></p>';
			} else if (!empty($_REQUEST['search'])) {
				?>
				<p><strong><?php echo count($families).' '._('matching families found').':';?></strong></p>			
				<?php
			} else  {
				echo '<p class="strong"><strong>'.count($families).' '._('families in total').'</strong></p>';
			}
		}
		?>
		</div>
		<?php
		if ($families) {
			include dirname(dirname(__FILE__)).'/templates/family_list.template.php';
		}
	}
}