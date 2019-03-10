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
			if ($this->_query->id) {
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
			?>
			<form method="post" class="form-horizontal" action="<?php echo build_url(Array('configure' => NULL)); ?>">
				<input type="hidden" name="query_submitted" value="1" />
				<?php
				$this->_query->acquireLock();
				$this->_query->printForm();
				?>
				<h3>&nbsp</h3>
				<input type="submit" class="btn" value=<?php echo _('"Save and view results"');?> />
				<input type="submit" class="btn" name="return" value=<?php echo _('"Save and return to report list"');?> />
				<a class="btn" href="?view=<?php echo ents($_REQUEST['view']); ?>"><?php echo _('Cancel and return to report list');?></a>

			</form>
			<?php

		} else if (!empty($this->_query)) {
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
				<div class="span4 align-right">
					<a href="?call=report_csv&queryid=<?php echo $this->_query->id; ?>"><i class="icon-download-alt"></i><?php echo _('Download CSV');?></a>
				</div>
			</div>
			<?php

		} else {
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
							<th>ID</th>
							<th><?php echo _('Report Name');?></th>
							<th><?php echo _('Visible To');?></th>
							<th><?php echo _('Actions');?></th>
							<th></th>
							<th></th>
						</tr>
					</thead>
					<tbody>

					<?php
					if (!empty($_SESSION['saved_query'])) {
						?>
						<tr>
							<td>-</td>
							<td><i><?php echo _('Last ad-hoc report');?></i></td>
							<td>-</td>
							<td class="action-cell">
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=TEMP&configure=1"><i class="icon-wrench"></i><?php echo _('Configure');?></a> &nbsp;
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=TEMP"><i class="icon-list"></i><?php echo _('View');?></a> &nbsp;
							</td>
							<td>&nbsp;</td>
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
							<td><?php echo ($details['owner'] === NULL) ? _('Everyone') : _('Only Me'); ?></td>
							<td class="action-cell narrow">
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=<?php echo $id; ?>"><i class="icon-list"></i><?php echo _('View');?></a> &nbsp;
							<?php
							if (strlen($details['mailchimp_list_id'])) {
								?>
								<a href="?view=_send_mc_campaign&reportid=<?php echo (int)$id; ?>"><i class="icon-email">@</i>Send campaign</a>
								<?php
							} else {
								?>
								<a href="?call=email&queryid=<?php echo $id; ?>" class="hidden-frame"><i class="icon-email">@</i><?php echo _('Email');?></a>
								<?php
							}
							?>
							</td>
							<td>
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=<?php echo $id; ?>&configure=1"><i class="icon-wrench"></i><?php echo _('Configure');?></a> &nbsp;
							<?php
							if ($GLOBALS['user_system']->havePerm(PERM_MANAGEREPORTS)) {
								?>
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&queryid=<?php echo $id; ?>&delete=1" data-method="post" class="double-confirm-title" title="Delete this report"><i class="icon-trash"></i><?php echo _('Delete');?></a>
								<?php
							}
							?>
							</td>
						</tr>
						<?php
					}
					?>
					</tbody>
				</table>
				</form>
				<?php
			}
		}
	}


}
?>
