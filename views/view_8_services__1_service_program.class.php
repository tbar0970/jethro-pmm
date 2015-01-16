<?php
require_once 'include/bible_ref.class.php';
class View_Services__Service_Program extends View
{
	private $_start_date = NULL;
	private $_end_date = NULL;
	private $_congregations = Array();
	private $_grouped_services = Array();
	private $_dummy_service = NULL;
	private $_editing = FALSE;
	private $_saved = FALSE;

	static function getMenuPermissionLevel()
	{
		return PERM_VIEWSERVICE;
	}

	function processView()
	{
		// Get the congregations and make sure they're in order
		if (!empty($_REQUEST['congregations'])) {
			$this->_congregations = array_keys($GLOBALS['system']->getDBObjectData('congregation', Array('!meeting_time' => ''), 'OR', 'meeting_time'));
			foreach ($this->_congregations as $i => $v) {
				if (!in_array($v, $_REQUEST['congregations'])) {
					unset($this->_congregations[$i]);
				}
			}
			$this->_congregations = array_values($this->_congregations); // re-index
			$_SESSION['service_congs'] = $this->_congregations;
		} else if (!empty($_SESSION['service_congs'])) {
			$this->_congregations = $_SESSION['service_congs'];
		}
		$this->_start_date = process_widget('start_date', Array('type' => 'date'), NULL);
		$this->_end_date = process_widget('end_date', Array('type' => 'date'), NULL);
		if (empty($this->_start_date) && empty($this->_end_date)) {
			 if (!empty($_SESSION['service_dates'])) {
				list($this->_start_date, $this->_end_date) = $_SESSION['service_dates'];
			 }
		} else {
			$_SESSION['service_dates'] = Array($this->_start_date, $this->_end_date);
		}
		if (empty($this->_start_date)) $this->_start_date = date('Y-m-d');
		if (empty($this->_end_date)) $this->_end_date = date('Y-m-d', strtotime('+2 months'));

		$GLOBALS['system']->includeDBClass('service');
		$this->_dummy_service = new Service();

		$this->_loadServices();

		$this->_editing = !empty($_REQUEST['editing']) && $GLOBALS['user_system']->havePerm(PERM_BULKSERVICE);

		if ($this->_editing) {
			foreach ($this->_congregations as $id) {
				$cong = $GLOBALS['system']->getDBObject('congregation', $id);
				if (!$cong->haveLock('services') && !($cong->canAcquireLock('services') && $cong->acquireLock('services'))) {
					$this->_failed_congs[] = $id;
					unset($this->_congregations[$id]);
				}
			}
		}

		if (!empty($_REQUEST['program_submitted'])
			&& $GLOBALS['user_system']->havePerm(PERM_BULKSERVICE)
		) {
			$this->_handleProgramSave();
		}

	}

	private function _loadServices()
	{
		$this->_grouped_services = Array();
		if ($this->_congregations && $this->_start_date && $this->_end_date) {
			// Get the relevant services and group by date
			$params = Array(
						'congregationid' => $this->_congregations,
						'>date'			 => date('Y-m-d', strtotime($this->_start_date.'-1 day')),
						'<date'			 => date('Y-m-d', strtotime($this->_end_date.'+1 day')),
					  );
			$services = $GLOBALS['system']->getDBObjectData('service', $params, 'AND', 'date');
			foreach ($services as $id => $details) {
				$details['id'] = $id;
				$this->_grouped_services[$details['date']][$details['congregationid']] = $details;
			}
		}
	}

	private function _handleProgramSave()
	{
		// Update and/or create services on existing dates
		$dummy = new Service();
		foreach ($this->_grouped_services as $date => $date_services) {
			foreach ($this->_congregations as $congid) {
				if (isset($date_services[$congid])) {
					// update the existing service
					$dummy->populate($date_services[$congid]['id'], $date_services[$congid]);
					if ($dummy->acquireLock()) {
						$this->_processServiceCell($congid, $date, $dummy);
						$dummy->save();
						$dummy->releaseLock();
					} else {
						trigger_error("Could not acquire lock on individual service for $congid on $date - didn't save");
					}
				} else if (!empty($_POST['topic_title'][$congid][$date]) || !empty($_POST['format_title'][$congid][$date]) || !empty($_POST['bible_ref0'][$congid][$date])) {
					// create a new service
					$service = new Service();
					$service->setValue('date', $date);
					$service->setValue('congregationid', $congid);
					$this->_processServiceCell($congid, $date, $service);
					$service->create();
				}
			}
		}

		// Add services on new dates
		$i = 0;
		while (isset($_POST['new_service_date_d'][$i])) {
			foreach ($this->_congregations as $congid) {
				if (!empty($_POST['topic_title'][$congid]['new_'.$i]) || !empty($_POST['format_title'][$congid]['new_'.$i]) || !empty($_POST['bible_refs'][$congid]['new_'.$i][0]) ||
				!empty($_POST['bible_refs'][$congid]['new_'.$i][1])) {
					// we need to create a service here
					$service = new Service();
					$service->setValue('date', process_widget('new_service_date['.$i.']', Array('type' => 'date')));
					$service->setValue('congregationid', $congid);
					$this->_processServiceCell($congid, 'new_'.$i, $service);
					$service->create();
				}
			}
			$i++;
		}

		$shifted = FALSE;

		// Process the "delete" commands if necessary
		if (!empty($_POST['delete_single'])) {
			$service = $GLOBALS['system']->getDBOBject('service', (int)$_POST['delete_single']);
			if ($service) {
				$service->delete();
				if (!empty($_POST['shift_after_delete'])) {
					Service::shiftServices(Array($service->getValue('congregationid')), $service->getValue('date'), '-7');
					$shifted = TRUE;
				}
			}
		}
		if (!empty($_POST['delete_all_date'])) {
			$services = $GLOBALS['system']->getDBObjectData('service', Array('date' => $_POST['delete_all_date'], 'congregationid' => $this->_congregations), 'AND');
			$dummy = new Service();
			foreach ($services as $id => $details) {
				$dummy->populate($id, $details);
				$dummy->delete();
				$shifted = TRUE;
			}
			if (!empty($_POST['shift_after_delete'])) {
				Service::shiftServices($this->_congregations, $_POST['delete_all_date'], '-7');
				$shifted = TRUE;
			}
		}

		// Process the "insert" commands if necessary
		if (!empty($_POST['insert_all_date'])) {
			Service::shiftServices($this->_congregations, $_POST['insert_all_date'], '7');
			$shifted = TRUE;
		}
		if (!empty($_POST['insert_single_date'])) {
			foreach ($_POST['insert_single_date'] as $congid => $date) {
				Service::shiftServices(Array($congid), $date, '7');
				$shifted = TRUE;
			}
		}

		if (!$shifted) {
			foreach ($this->_congregations as $id) {
				$cong = $GLOBALS['system']->getDBObject('congregation', $id);
				$cong->releaseLock('services');
			}

			add_message("Services saved");
			redirect($_REQUEST['view'], Array('editing' => NULL));
		}

		$this->_loadServices();
	}

	function _processServiceCell($congid, $date, $service)
	{
		if (!isset($_POST['topic_title'][$congid][$date])) return;
		$service->setValue('topic_title', $_POST['topic_title'][$congid][$date]);
		$service->setValue('format_title', $_POST['format_title'][$congid][$date]);
		$service->setValue('notes', $_POST['notes'][$congid][$date]);
		$service->clearReadings();

		foreach ($_POST['bible_refs'][$congid][$date] as $i => $bible_ref) {
			if (!empty($bible_ref)) {
				$to_read = $_POST['bible_to_read'][$congid][$date][$i];
				$to_preach = $_POST['bible_to_preach'][$congid][$date][$i];
				$service->addReading($bible_ref, $to_read, $to_preach);
			}
		}
	}

	function getTitle()
	{
		return ($this->_editing ? 'Edit' : 'View').' Service Program';

	}


	function printView()
	{
		if (!empty($this->_failed_congs)) {
			if (empty($_REQUEST['program_submitted'])) {
				if (empty($this->_congregations)) {
					print_message('Services for the selected congregations cannot be edited currently because another user has the lock.  Wait for them to finish and then try again', 'failure');
				} else {
					print_message('Another user is currently editing services for congregation(s)   #'.implode(', #', $this->_failed_congs).'.  To edit services for those congregations, wait for the other user to finish and then try again');
				}
			} else {
				print_message('ERROR: Could not save details for congregations "'.implode(', ', $this->_failed_congs).'" because the lock had expired and could not be re-acquired', 'failure');
			}
		}
		if ($this->_saved) {
			print_message('Services saved', 'success');
		}


		$this->_printParamsForm();

		if ($this->_editing) {
			$this->_printServiceProgramEditor();
		} else {
			$this->_printServiceProgram();
		}
	}

	function _printServiceProgram()
	{
		if (empty($this->_congregations)) return;
		?>
		<p class="pull-right"><a href="<?php echo build_url(Array('editing' => 1)); ?>"><i class="icon-wrench"></i>Edit</a></p>
		<table class="table roster service-program table-auto-width">
			<thead>
				<tr>
					<th>Date</th>
				<?php
				foreach ($this->_congregations as $congid) {
					$cong = $GLOBALS['system']->getDBObject('congregation', (int)$congid);
					?>
					<th><?php echo ents($cong->getValue('name')); ?></th>
					<?php
				}
				?>
				</tr>
			</thead>
			<tbody>
				<?php
				// Print rows for existing services
				if (empty($this->_grouped_services)) {
					$last_date = date('Y-m-d', strtotime($this->_start_date.' -8 days'));
				} else {
					$last_date = key(array_reverse($this->_grouped_services));
				}
				$last_cong = count($this->_congregations) -1;
				foreach ($this->_grouped_services as $date => $services) {
					?>
					<tr<?php if ($date == date('Y-m-d', strtotime('Sunday'))) echo ' class="tblib-hover"'; ?>>
						<td class="nowrap center narrow"><strong><?php echo date('j M y', strtotime($date)); ?></strong></td>
					<?php
					foreach ($this->_congregations as $i => $congid) {
						?>
						<td class="service-details">
							<?php $this->_printServiceViewCell($congid, $date, array_get($services, $congid, Array())); ?>
						</td>
						<?php
					}
					?>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	function _formatBible($raw, $linked=FALSE)
	{
		if ($raw) {
			$br = new Bible_Ref($raw);
			return $linked ? $br->getLinkedShortString() : $br->toShortString();
		}
		return '';
	}

	function _printParamsForm()
	{
		$congs = $GLOBALS['system']->getDBObjectData('congregation', Array('!meeting_time' => ''), 'OR', 'meeting_time');
		if (empty($congs)) {
			print_message("To edit services you must first go to admin > congregations and set the 'code name' for the relevant congregations", 'failure');
			return;
		}
		?>
		<form method="get" class="well well-small">
		<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
		<?php
		if ($GLOBALS['user_system']->havePerm(PERM_BULKSERVICE)) {
			?>
			<select name="editing">
				<option value="0">View</option>
				<option value="1" <?php if ($this->_editing) echo 'selected="selected"'; ?>>Edit</option>
			</select>
			<b>the service program</b>
			<?php
		}
		?>
		<table>
			<tr>
				<td rowspan="3" class="nowrap" style="padding-right: 2ex">
					<b>For congregations</b><br />
					<?php
					
					foreach ($congs as $id => $details) {
						?>
						<label class="checkbox">
							<input type="checkbox" name="congregations[]" 
								<?php if (in_array($id, $this->_congregations)) echo 'checked="checked" '; ?>
								value="<?php echo $id; ?>" id="congregations_<?php echo $id; ?>" />
							<?php echo ents($details['name']); ?>
						</label>
						<?php
					}
					?>
				</td>
				<td><b>from</b>&nbsp;</td>
				<td class="nowrap"><?php print_widget('start_date', Array('type' => 'date'), $this->_start_date); ?></td>
			</tr>
			<tr>
				<td><b>to</b></td>
				<td class="nowrap">
					<?php print_widget('end_date', Array('type' => 'date'), $this->_end_date); ?>
					&nbsp;
					<button type="submit" class="btn">Go</button>
				</td>
			</tr>
		</table>
		</form>
		<?php
	}

	function _printServiceViewCell($congid, $date, $data)
	{
		if (empty($data)) return;
		if ($data['has_items']) {
			?>
			<a class="pull-right" title="View service run sheet" href="?view=services__service_details&date=<?php echo $date; ?>&congregationid=<?php echo $congid; ?>"><i class="icon-list"></i></a>
			<?php
		} else {
			?>
			<a class="pull-right" title="Create service run sheet" href="?view=services__service_details&editing=1&date=<?php echo $date; ?>&congregationid=<?php echo $congid; ?>"><i class="icon-plus-sign"></i></a>
			<?php
		}
		$this->_dummy_service->populate($data['id'], $data);
		$this->_dummy_service->printFieldValue('summary');

	}


	function _printServiceProgramEditor()
	{
		if ($this->_saved) return; // just saved, have released locks
		if (empty($_REQUEST['congregations'])) return;

		?>
		<script>
			setTimeout('showLockExpiryWarning()', <?php echo (strtotime('+'.LOCK_LENGTH, 0)-60)*1000; ?>);
			setTimeout('showLockExpiredWarning()', <?php echo (strtotime('+'.LOCK_LENGTH, 0))*1000; ?>);
		</script>

		<form method="post" class="warn-unsaved">
		<input type="hidden" name="program_submitted" value="1" />
		<!-- the following hidden fields preserve the value of an image input whose click
		     is intercepted by a confirm-shift popup -->
		<input type="hidden" name="delete_single" value="" id="delete_single" />
		<input type="hidden" name="delete_all_date" value="" id="delete_all_date" />

		<table id="service-program-editor" class="table roster service-program table-auto-width" style="table-layout: fixed">

			<thead>
				<tr>
					<th>Date</th>
				<?php
				foreach ($this->_congregations as $congid) {
					$cong = $GLOBALS['system']->getDBObject('congregation', (int)$congid);
					?>
					<th colspan="3"><?php echo ents($cong->getValue('name')); ?></th>
					<?php
				}
				?>
				</tr>
			</thead>
			<tbody>
			<?php
			// Print rows for existing services
			if (empty($this->_grouped_services)) {
				$last_date = date('Y-m-d', strtotime($this->_start_date.' -8 days'));
			} else {
				$last_date = key(array_reverse($this->_grouped_services));
			}
			$last_cong = count($this->_congregations) -1;
			$this_sunday = date('Y-m-d', strtotime('Sunday'));
			if ($this->_grouped_services) {
				$last_date = key($this->_grouped_services);
			}
			$new_service_i = 0;
			foreach ($this->_grouped_services as $date => $services) {
				// first, print a blank one if necessary
				$last_date_plus_week = date('Y-m-d', strtotime($last_date.' +1 week'));
				while ($last_date_plus_week < $date) {
					// it's been more than a week since the last service
					// so print a blank one in between
					$this->_printNewServiceRow($new_service_i++, $last_date_plus_week);
					$last_date_plus_week = date('Y-m-d', strtotime($last_date_plus_week.' +1 week'));
				}

				// Now print the service we actually have
				$class_clause = ($date == $this_sunday) ? 'class="hovered"' : '';
				?>
				<tr class="copy-details">
					<td>
						<button type="submit" name="insert_all_date" value="<?php echo $date; ?>" class="confirm-title" title="Create a blank week here for ALL CONGREGATIONS by moving all the following services down"></button>
					</td>
				<?php
				foreach ($this->_congregations as $congid) {
					?>
					<td colspan="3">
						<button type="submit" name="insert_single_date[<?php echo $congid; ?>]" value="<?php echo $date; ?>" class="confirm-title" title="Create a blank week here for this congregation by moving all the following services down"></button>
					</td>
					<?php
				}
				?>
				</tr>

				<tr <?php echo $class_clause; ?>>
					<td class="service-date"><strong><?php echo date('j M y', strtotime($date)); ?></strong><br />
					<input type="image" name="delete_all_date" value="<?php echo $date; ?>" src="<?php echo BASE_URL; ?>/resources/img/cross_red.png" class="confirm-shift" title="Delete all services on this date" /></td>
				<?php
				foreach ($this->_congregations as $i => $congid) {
					?>
					<td class="left-tools">
						<?php if ($i != 0) echo '<img src="'.BASE_URL.'/resources/img/arrow_left_heavy_blue.png" class="clickable copy-left" title="Click to copy this service\'s details to the previous congregation" />'; ?>
					</td>
					<td class="service">
						<?php $this->_printServiceEditCell($congid, $date, array_get($services, $congid, Array())); ?>
					</td>
					<td class="right-tools">
						<?php
						if ($i != $last_cong) {
							echo '<img src="'.BASE_URL.'/resources/img/arrow_right_heavy_blue.png" class="clickable copy-right" title="Click to copy this service\'s details to the next congregation" />';
						}
						if (isset($services[$congid])) {
							?>
							<input type="image" name="delete_single" value="<?php echo $services[$congid]['id']; ?>" src="<?php echo BASE_URL; ?>/resources/img/cross_red.png" class="delete-single confirm-shift" title="Delete this service" />
							<?php
						}
						?>
					</td>
					<?php
				}
				?>
				</tr>
				<?php
				$last_date = $date;
			}

			// Print rows for new services
			$running_date = date('Y-m-d', strtotime('Sunday', strtotime($last_date.' +1 week')));
			while ($running_date < $this->_end_date) {
				$this->_printNewServiceRow($new_service_i, $running_date);
				$running_date = date('Y-m-d', strtotime($running_date.' +1 week'));
				$new_service_i++;
			}
			?>
			</tbody>
		</table>
		<input type="submit" class="btn" value="Save" accesskey="s" />

		<div id="shift-confirm-popup" class="modal hide" role="dialog" aria-hidden="true">
			<div class="modal-header">
				<h4>Delete service</h4>
			</div>
			<div class="modal-body">
				After deleting, would you like to move the following services up a week to close the gap?
			</div>
			<div class="modal-footer">
				<input type="submit" class="btn" name="shift_after_delete" value="Yes" />
				<input type="submit" class="btn" value="No" />
				<input type="button" class="btn" value="Cancel" data-dismiss="modal" aria-hidden="true" />
			</div>
		</div>

		</form>
		<?php
	}


	function _printNewServiceRow($i, $running_date)
	{
			?>
			<tr>
				<td class="service-date"><?php print_widget('new_service_date['.$i.']', Array('type' => 'date', 'month_format' => 'M'), $running_date); ?></td>
			<?php
			$j = 0;
			foreach ($this->_congregations as $congid) {
				?>
				<td class="left-tools">
					<?php if ($j != 0) echo '<img src="'.BASE_URL.'/resources/img/arrow_left_heavy_blue.png" class="clickable copy-left" title="Click to copy this service\'s details to the previous congregation" />'; ?>
				</td>
				<td class="service">
					<?php $this->_printServiceEditCell($congid, 'new_'.$i, Array()); ?>
				</td>
				<td class="right-tools">
					<?php if ($j != count($this->_congregations) -1) echo '<img src="'.BASE_URL.'/resources/img/arrow_right_heavy_blue.png" class="clickable copy-right" title="Click to copy this service\'s details to the next congregation" />'; ?>
				</td>
				<?php
				$j++;
			}
			?>
			</tr>
			<?php
		}


	function _printServiceEditCell($congid, $date, $data)
	{
		?>
		<table class="service-details">
			<tr>
				<th>Topic</th>
				<td class="topic">
					<input type="text" name="topic_title[<?php echo $congid; ?>][<?php echo $date; ?>]" value="<?php echo ents(array_get($data, 'topic_title')); ?>" />
				</td>
			</tr>
			<tr>
				<th>Texts</th>
				<td>
					<table class="expandable table-hover">
					<?php
					$readings = array_get($data, 'readings');
					if (empty($readings)) {
						$readings = Array(Array('to_read' => 1));
					}
					foreach ($readings as $reading) {
						?>
						<tr>
							<td>
								<input type="text" name="bible_refs[<?php echo $congid; ?>][<?php echo $date; ?>][]" class="bible-ref" value="<?php echo ents($this->_formatBible(array_get($reading, 'bible_ref', ''), FALSE)); ?>" />
							</td>
							<td class="bible-options">

								<label title="to be read" class="preserve-value">
									R
									<?php
									/*  because checkboxes themselves don't get don't get submitted if not checked, and
									we need the "to_read" etc fields to match up with the actual bible refs,
									we use a hidden field for submission, and rely on JS in tb_lib to adjust
									the hidden field when the checkbox is clicked */
									?>
									<input type="checkbox"  class="toggle-next-hidden" />
									<input type="hidden" name="bible_to_read[<?php echo $congid; ?>][<?php echo $date; ?>][]" value="<?php echo (int)array_get($reading, 'to_read'); ?>" />
								</label>

								<label title="to be preached on">
									P
									<input type="checkbox" class="toggle-next-hidden bible-to-preach" />
									<input type="hidden" name="bible_to_preach[<?php echo $congid; ?>][<?php echo $date; ?>][]" value="<?php echo (int)array_get($reading, 'to_preach'); ?>" />
								</label>

								<img src="<?php echo BASE_URL; ?>/resources/img/arrow_up_thin_black.png" class="icon move-row-up" title="Move up" />
								<img src="<?php echo BASE_URL; ?>/resources/img/arrow_down_thin_black.png" class="icon move-row-down" title="Move down" />
								&nbsp;
							</td>
						</tr>
						<?php
					}
					?>
					</table>
				</td>
			</tr>
			<tr>
				<th>Format</th>
				<td class="format">
					<input type="text" name="format_title[<?php echo $congid; ?>][<?php echo $date; ?>]" value="<?php echo array_get($data, 'format_title'); ?>" />
					<i class="icon-chevron-down clickable toggle-next-tr <?php if (!empty($data['notes'])) echo 'got-notes'; ?>" title="Show notes" ></i>
				</td>
			</tr>
			<tr class="hide">
				<th>Notes</th>
				<td><textarea class="full-width-input" name="notes[<?php echo $congid; ?>][<?php echo $date; ?>]"><?php echo ents(array_get($data, 'notes')); ?></textarea></td>
			</tr>
		</table>
		<?php
	}

}
?>
