<?php

class View_Persons__Reports extends View
{
	var $_query;

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
				$query_creator = $GLOBALS['system']->getDBObject('staff_member', $this->_query->getValue('creator'));
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
			if ($this->_query) {
				if (!empty($_REQUEST['configure'])) {
					return _('Configure Person Report');
				} else {
					if ($this->_query->getValue('name')) {
						return $this->_query->getValue('name');
					} else {
						return _('Person Report Results');
					}
				}
			} else {
				return _('Configure Person Report');
			}
		} else {
			return _('Person Reports');
		}
	}

	function printView()
	{
		if (!empty($_REQUEST['configure'])) {
			// PRINT THE FORM TO CONFIGURE THE REPORT
			?>
			<form method="post" class="form-horizontal" action="<?php echo build_url(Array('configure' => NULL)); ?>">
				<input type="hidden" name="query_submitted" value="1" />
				<?php
				$this->_query->acquireLock();
				$this->_query->printForm();
				?>
				<h3>&nbsp</h3>
				<input type="submit" class="btn" value="<?php echo _('Save and view results');?>" />
				<input type="submit" class="btn" name="return" value="<?php echo _('Save and return to report list');?>" />
				<a class="btn" href="?view=<?php echo ents($_REQUEST['view']); ?>"><?php echo _('Cancel and return to report list');?></a>

			</form>
			<?php

		} else if (!empty($this->_query->id)) {
			// SHOW QUERY RESULTS
			?>
			<?php

			$this->_query->printResults();
			?>
			<hr />
			<div class="row-fluid no-print">
				<div class="span4">
					<a href="?view=<?php echo ents($_REQUEST['view']); ?>"><i class="icon-chevron-left"></i><?php echo _('Back to list of reports');?></a>
				</div>
				<div class="span4 align-centre">
					<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=<?php echo $this->_query->id; ?>&configure=1"><i class="icon-wrench"></i><?php echo _('Reconfigure this report');?></a>
				</div>
				<div class="span4 align-right hidden-phone">
					<a href="?call=report_csv&queryid=<?php echo $this->_query->id; ?>"><i class="icon-download-alt"></i><?php echo _('Download CSV');?></a>
				</div>
			</div>
			<?php
			
		} else if (!empty($_REQUEST['queryid']) && is_numeric($_REQUEST['queryid'])) {
			// THEY ASKED FOR A QUERY BUT THEY CAN'T HAVE IT
			print_message('The requested report does not exist, or you do not have permission to view it', 'error');
			return;

		} else if (!empty($_REQUEST['custom_report'])) {
			$this->_executeCustomReport($_REQUEST['custom_report']);
			
		} else {
			// PRINT THE LIST OF SAVED REPORTS
			?>
			<p>
				<a href="<?php echo build_url(Array('*' => NULL, 'view' => $_REQUEST['view'], 'queryid' => 0, 'configure' => 1)); ?>"><i class="icon-plus-sign"></i><?php echo _('Create a new report');?></a>
			</p>

			<?php
			$saved_reports = $GLOBALS['system']->getDBObjectData('person_query', Array('(owner' => Array(NULL, $GLOBALS['user_system']->getCurrentUser('id'))), 'OR', 'name');
			if (empty($saved_reports) && empty($_SESSION['saved_query'])) {
				?>
				<p><i><?php echo _('There are not yet any reports saved in the system');?></i></p>
				<?php
			} else {
				?>
				<form method="post">
				<table class="table table-striped table-min-width table-hover">
					<thead>
						<tr>
							<th class="hidden-phone">ID</th>
							<th><?php echo _('Report Name');?></th>
							<th><?php echo _('Visible To');?></th>
						<?php
						if (!SizeDetector::isNarrow()) {
							?>
							<th><?php echo _('Actions');?></th>
							<th></th>
							<?php
						}
						?>
						</tr>
					</thead>
					<tbody>

					<?php
					if (!empty($_SESSION['saved_query'])) {
						if (SizeDetector::isNarrow()) {
							?>
							<td class="hidden-phone">-</td>
							<td>
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=TEMP">
									<i><?php echo _('Last ad-hoc report');?></i>
								</a>
							</td>
						} else {
							?>
							<tr>
								<td class="hidden-phone">-</td>
								<td><i><?php echo _('Last ad-hoc report');?></i></td>
								<td>-</td>
								<td class="action-cell">
									<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=TEMP"><i class="icon-list"></i><?php echo _('View');?></a> &nbsp;
									<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=TEMP&configure=1"><i class="icon-wrench"></i><?php echo _('Configure');?></a> &nbsp;
								</td>
								<td></td>
							</tr>
							<?php
						}
					}

					$staff_members = $GLOBALS['system']->getDBObjectData('staff_member');
					$current_user_id = $GLOBALS['user_system']->getCurrentUser('id');
					foreach ($saved_reports as $id => $details) {
						?>
						<tr>
							<td class="narrow hidden-phone"><?php echo (int)$id; ?></td>
							<td>
								<?php
								if (SizeDetector::isNarrow()) echo '<a href="?view='.ents($_REQUEST['view']).'&queryid='.(int)$id.'">';
								echo $details['name'];
								if (SizeDetector::isNarrow()) echo '</a>';
								?>
							</td>
							<td><?php echo ($details['owner'] === NULL) ? _('Everyone') : _('Only Me'); ?></td>
						<?php
						if (!SizeDetector::isNarrow()) {
							?>
							<td class="action-cell narrow">
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=<?php echo $id; ?>"><i class="icon-list"></i><?php echo _('View');?></a> &nbsp;
							<?php
							if (strlen($details['mailchimp_list_id'])) {
								?>
								<a href="?view=_send_mc_campaign&reportid=<?php echo (int)$id; ?>"><i class="icon-email">@</i>Send campaign</a>
								<?php
							} else {
								?>
								<a target="_append" href="?call=email&print_modal=1&queryid=<?php echo $id; ?>"><i class="icon-email">@</i><?php echo _('Email');?></a>
								<?php
							}
							?>
							</td>
							<td class="action-cell narrow">
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=<?php echo $id; ?>&configure=1"><i class="icon-wrench"></i><?php echo _('Configure');?></a> &nbsp;
							<?php
							if ($GLOBALS['user_system']->havePerm(PERM_MANAGEREPORTS)) {
								?>
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=<?php echo $id; ?>&delete=1" data-method="post" class=" double-confirm-title" title="Delete this report"><i class="icon-trash"></i><?php echo _('Delete');?></a>
								<?php
							}
							?>
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
				</form>
				<?php

				$this->_listCustomReports();
			}
		}
	}

	private function _listCustomReports()
	{
		$files = $this->_getCustomReports();
		if (empty($files)) return;
		
		?>
		<h3>Custom Reports</h3>
		<ul>
		<?php
		foreach ($files as $fn => $title) {
			echo '<li><a href="'.build_url(Array('custom_report' => $fn)).'">'.ents($title).'</a></li>';
		}
		?>
		</ul>
		<?php
	}

	private function _executeCustomReport($file)
	{
		$reports = $this->_getCustomReports();
		if (!isset($reports[$file])) {
			trigger_error("Invalid custom report filename");
			return;
		}
		$fn = $this->_getCustomReportsDir().'/'.$file;
		if (!is_readable($fn)) {
				trigger_error("Custom report SQL file is not readable");
				return;
		}

		echo '<h2>'.ents($reports[$file]).'</h2>';
		$fp = fopen($fn, 'r');
		if (!$fp) {
				trigger_error("Could not open custom report SQL file");
				return;
		}
		$sql = fread($fp, 99999);
		fclose($fp);

		$sql = trim($sql);
		if (0 !== strpos(strtoupper($sql), 'SELECT')) {
			trigger_error("Custom report does not seem to start with SELECT; aborting");
			return;
		}

		$res = $GLOBALS['db']->queryAll($sql);
		if (empty($res)) return;

		?>
		<table class="table table-bordered table-condensed table-auto-width">
			<thead>
				<tr>
				<?php
				$headers = array_keys($res[0]);
				foreach ($headers as $h) {
					?>
					<th><?php echo ents($h); ?></th>
					<?php
				}
				?>
			</thead>
			<tbody>
			<?php
			foreach ($res as $row) {
				?>
				<tr>
				<?php
				foreach ($row as $k => $v) {
					echo '<td>'.ents($v).'</td>';
				}
				?>
				</tr>
				<?php
			}
			?>
			</tbody>
			<tfoot>
				<tr>
					<td class="report-summary no-tsv" colspan="<?php echo count($headers); ?>">
						<span class="pull-right no-print">
							Copy:
							<span class="clickable" title="plain table to paste elsewhere" data-action="copy-table">Table</span>
							<span class="clickable" title="tab-separated for spreadsheet" data-action="copy-tsv">TSV</span>
						</span>
					</td>
				</tr>
			</tfoot>
		</table>
		<?php
	}


	private function _getCustomReports()
	{
		$dir = $this->_getCustomReportsDir();
		if (!is_dir($dir)) return Array();

		$files = glob($dir.'/*.sql');
		if (empty($files)) return Array();

		foreach ($files as $f) {
			$f = basename($f);
			$title = substr(ucwords(str_replace('_', ' ', $f)), 0, -4);
			$res[$f] = $title;
		}
		return $res;

	}

	private function _getCustomReportsDir()
	{
		return ifdef('CUSTOM_REPORTS_DIR', JETHRO_ROOT.'/custom_reports');
	}

}

