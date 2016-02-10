<?php

class View_Persons__Reports extends View
{
	var $_query;
	var $_have_results = FALSE;
	var $_result_counts = Array();

	static function getMenuPermissionLevel()
	{
		return PERM_RUNREPORT;
	}

	function processView()
	{
		$GLOBALS['system']->includeDBClass('person_query');
		if (isset($_REQUEST['queryid'])) {
			$this->_query = new Person_Query($_REQUEST['queryid']);
		}
		if ($this->_query && !empty($_REQUEST['delete'])) {
			$can_delete = FALSE;
			if (($this->_query->getValue('creator') == $GLOBALS['user_system']->getCurrentUser('id')) || $GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) {
				$can_delete = true;
			} else {
				$query_creator =& $GLOBALS['system']->getDBObject('staff_member', $this->_query->getValue('creator'));
				if (!$query_creator->getValue('active')) {
					$can_delete = true;
				}
			}
			if ($can_delete) {
				$this->_query->delete();
				add_message('Report deleted');
				redirect($_REQUEST['view']);
			}
		}
		if (!empty($_POST['show_result_count_queryids'])) {
			foreach($_POST['show_result_count_queryids'] as $queryid) {
				$query = new Person_Query($queryid);
				$this->_result_counts[$queryid] = $query->getResultCount();
			}
		}
		if (!empty($_POST['query_submitted'])) {
			$this->_query->processForm();
			if ($this->_query->id) {
				$this->_query->save();
			} else {
				$this->_query->create();
			}
			redirect($_REQUEST['view'], Array('queryid' => !empty($_REQUEST['return']) ? NULL : $this->_query->id));
		}
	}
	
	function getTitle()
	{
		if ($this->_query) {
			if ($this->_query->id) {
				if (!empty($_REQUEST['configure'])) {
					return 'Configure Person Report';
				} else {
					if ($this->_query->getValue('name')) {
						return $this->_query->getValue('name');
					} else {
						return 'Person Report Results';
					}
				}
			} else {
				return 'Configure Person Report';
			}
		} else {
			return 'Person Reports';
		}
	}
	
	function printView()
	{
		if (!empty($_REQUEST['configure'])) {
			?>
			<form method="post" class="form-horizontal" action="<?php echo build_url(Array('configure' => NULL)); ?>">
				<input type="hidden" name="query_submitted" value="1" />
				<?php
				$this->_query->acquireLock();
				$this->_query->printForm();
				?>
				<h3>&nbsp</h3>
				<input type="submit" class="btn" value="Save and view results" />
				<input type="submit" class="btn" name="return" value="Save and return to report list" />
				<a class="btn" href="?view=<?php echo ents($_REQUEST['view']); ?>">Cancel and return to report list</a>

			</form>
			<?php
			
		} else if (!empty($this->_query)) {
			$this->_query->printResults();
			?>
			<hr />
			<div class="row-fluid no-print">
				<div class="span4">
					<a href="?view=<?php echo ents($_REQUEST['view']); ?>"><i class="icon-chevron-left"></i>Back to list of reports</a>
				</div>
				<div class="span4 align-centre">
					<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=<?php echo $this->_query->id; ?>&configure=1"><i class="icon-wrench"></i>Reconfigure this report</a>
				</div>
				<div class="span4 align-right">
					<a href="?call=report_csv&queryid=<?php echo $this->_query->id; ?>"><i class="icon-download-alt"></i>Download CSV</a>
				</div>
			</div>
			<?php
			
		} else {
			?>
			<p>
				<a href="<?php echo build_url(Array('*' => NULL, 'view' => $_REQUEST['view'], 'queryid' => 0, 'configure' => 1)); ?>"><i class="icon-plus-sign"></i>Create a new report</a>
			</p>

			<?php
			$saved_reports = $GLOBALS['system']->getDBObjectData('person_query', Array('(owner' => Array(NULL, $GLOBALS['user_system']->getCurrentUser('id'))), 'OR', 'name');
			if (empty($saved_reports) && empty($_SESSION['saved_query'])) {
				?>
				<p><i>There are not yet any reports saved in the system</i></p>
				<?php
			} else {
				?>
				<form method="post">
				<table class="table table-striped table-min-width table-hover">
					<thead>
						<tr>
							<th>ID</th>
							<th>Report Name</th>
							<th>Visible To</th>
							<th>Actions</th>
							<th></th>
						<?php
						if (!empty($this->_result_counts)) {
							?>
							<th>Results</th>
							<?php
						}
						?>
						</tr>
					</thead>
					<tbody>

					<?php
					if (!empty($_SESSION['saved_query'])) {
						?>
						<tr>
							<td>-</td>
							<td><i>Last ad-hoc query</i></td>
							<td class="action-cell">
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=TEMP&configure=1"><i class="icon-wrench"></i>Configure</a> &nbsp;
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=TEMP"><i class="icon-list"></i>View Results</a> &nbsp;
							</td>
							<td>&nbsp;</td>
						<?php
						if (!empty($this->_result_counts)) {
							?>
							<td></td>
							<?php
						}
						?>
						</tr>
						<?php
					}

					$staff_members = $GLOBALS['system']->getDBObjectData('staff_member');
					$current_user_id = $GLOBALS['user_system']->getCurrentUser('id');
					foreach ($saved_reports as $id => $details) {
						?>
						<tr>
							<td class="narrow"><?php echo (int)$id; ?></td>
							<td><?php echo $details['name']; ?></td>
							<td><?php echo ($details['owner'] === NULL) ? 'Everyone' : 'Only Me'; ?></td>
							<td class="action-cell narrow">
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=<?php echo $id; ?>&configure=1"><i class="icon-wrench"></i>Configure</a> &nbsp;
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=<?php echo $id; ?>"><i class="icon-list"></i>View</a> &nbsp;
								<a href="?call=email&queryid=<?php echo $id; ?>" class="hidden-frame"><i class="icon-email">@</i>Email</a>
							<?php
							if ($GLOBALS['user_system']->havePerm(PERM_MANAGEREPORTS)) {
								?>
								&nbsp;
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=<?php echo $id; ?>&delete=1" data-method="post" class="double-confirm-title" title="Delete this report"><i class="icon-trash"></i>Delete</a>
								<?php
							}
							?>
							</td>
							<td class="narrow"><input type="checkbox" name="show_result_count_queryids[]" value="<?php echo (int)$id; ?>" <?php if (isset($this->_result_counts[$id])) echo 'checked="checked"'; ?> /></td>
						<?php
						if (!empty($this->_result_counts)) {
							?>
							<td class="narrow"><b><?php if (isset($this->_result_counts[$id])) echo (int)$this->_result_counts[$id]; ?></b></td>
							<?php
						}
						?>
						</tr>
						<?php
					}
					?>
					</tbody>
				</table>
				<input type="submit" class="btn-link pull-right" name="show_result_count" value="Show result counts for selected reports" />
				</form>
				<?php
			}
		}
	}


}
?>
