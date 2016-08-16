<?php
include_once 'include/paginator.class.php';
class View_Families__List_All extends View
{
	var $_family_data;
	var $_paginator;

	function processView()
	{
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


	function getTitle()
	{
		$res = _('All Families');
		return $res;

	}


	function printView()
	{
		if ($this->_paginator) {
			echo '<p>';
			$this->_paginator->printPageNav();
			echo '</p>';
		}

		$GLOBALS['system']->includeDBClass('family');
		$families =& $this->_family_data;
		if (empty($families)) {
			if ($this->_paginator) {
				?>
				<div class="row">
					<div class="col-xs-12">
						<span class="result count"><?php echo _('No families in this range')?></span>
					</div>
				</div>
				<?php
			} else {
				?>
				<div class="row">
					<div class="col-xs-7">
						<span class="result count"><?php echo _('No families were found')?></span>
					</div>
					<div class="col-xs-5">
						<form class="switchForm">
							<div class="switch">
								<label>
									<input data-url="<?php echo build_url(Array('show_archived' => NULL)); ?>" name="show_archived" id="switch_includeArchived" type="checkbox" <?php echo $checked; ?>>
									<?php echo _('Archived'); ?>
								</label>
							</div>
						</form>
					</div>
				</div>
				<?php
			}
		} else {
			?>
			<div class="row">
				<div class="col-xs-7">
			<?php
			if ($this->_paginator) {
				echo '<span class="result count">'.count($families).' '._('families in this range').'</span>';
			} else  {
				echo '<span class="result count">'.count($families).' '._('families in total').'</span>';
			}
			$checked = empty($_REQUEST['show_archived']) ? '': 'checked';
			?>
				</div>
				<div class="col-xs-5">
					<form class="switchForm">
						<div class="switch">
							<label>
								<input data-url="<?php echo build_url(Array('show_archived' => NULL)); ?>" name="show_archived" id="switch_includeArchived" type="checkbox" <?php echo $checked; ?>>
								<?php echo _('Archived'); ?>
							</label>
						</div>
					</form>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-12">
				<?php
			include dirname(dirname(__FILE__)).'/templates/family_list.template.php';
			?>
				</div>
			</div>
			<?php
		}
	}
}
?>
