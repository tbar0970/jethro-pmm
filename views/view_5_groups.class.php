<?php
class View_Groups extends View
{
	var $_group = NULL;

	function getTitle()
	{
		if ($this->_group) {
			return 'Viewing Group: '.$this->_group->getValue('name');
		}
	}

	function processView()
	{
		if (!empty($_REQUEST['person_groupid'])) {
			$_REQUEST['groupid'] = $_REQUEST['person_groupid'];
		}
		if (!empty($_REQUEST['groupid'])) {
			$this->_group =& $GLOBALS['system']->getDBObject('person_group', (int)$_REQUEST['groupid']);
		}
		if (isset($_REQUEST['show_archived'])) {
			$_SESSION['show_archived_group_members'] = (int)$_REQUEST['show_archived'];
		}

		if (!empty($_POST['membership_status'])) {
			if ($this->_group->updateMembershipStatuses($_POST['membership_status'])) {
				add_message('Membership statuses updated', 'success');
			}
		}
	}

	function printView()
	{
		if ($this->_group) {
			?>
			<h3>Group Details</h3>
			<table class="table-full-width">
				<tr>
					<th class="narrow" style="padding-bottom: 10px">Category</th>
					<td><?php $this->_group->printFieldValue('categoryid'); ?>&nbsp;&nbsp;</td>
					<th class="narrow hidden-phone">Record Attendance?</th>
					<td class="hidden-phone"><?php $this->_group->printFieldValue('attendance_recording_days'); ?></td>
					<td class="align-right">
						<a class="link-collapse" href="?view=_edit_group&groupid=<?php echo $this->_group->id; ?>"><i class="icon-wrench"></i>Edit group details</a>
					</td>
				</tr>
				<tr>
					<th class="narrow">Status</th>
					<td><?php $this->_group->printFieldValue('is_archived'); ?></td>
					<th class="narrow hidden-phone">Share Member Details?</th>
					<td class="hidden-phone"><?php $this->_group->printFieldValue('share_member_details'); ?></td>
					<td class="align-right">
						<form class="min" method="post" action="?view=_edit_group&groupid=<?php echo $this->_group->id; ?>">
							<input type="hidden" name="action" value="delete" />
							<button class="btn-link double-confirm-title link-collapse" type="submit" title="Delete this group">
								<i class="icon-trash"></i>Delete group
							</button>
						</form>
					</td>
				</tr>
			</table>

			<div class="modal hide fade" id="action-plan-modal" role="dialog" aria-hidden="true">
				<form method="post" action="?view=_edit_group&action=add_member&groupid=<?php echo $this->_group->id; ?>">
					<div class="modal-header">
						<h4>Add Members by Name Search</h4>
					</div>
					<div class="modal-body">
						<table>
							<tr>
								<td>Select persons:</td>
								<td>
									<?php
									$GLOBALS['system']->includeDBClass('person');
									Person::printMultipleFinder('personid');
									?>
								</td>
							</tr>
							<tr>
								<td>Membership status:</td>
								<td>
									<?php
									$GLOBALS['system']->includeDBClass('person_group');
									Person_Group::printMembershipStatusChooser('membership_status');
									?>
								</td>
							</tr>
						</table>
					</div>
					<div class="modal-footer">
						<input class="btn" type="submit" value="Add Members" id="add-member-button" />
						<input class="btn" type="button" value="Cancel" data-dismiss="modal" aria-hidden="true" />
					</div>
				</form>
			</div>

			<div class="modal hide fade autosize" id="email-modal" role="dialog" aria-hidden="true">
				<div class="modal-header">
					<h4>Email members of <?php echo ents($this->_group->getValue('name')); ?></h4>
				</div>
				<div class="modal-body">
					<!-- to be populated with ajax -->
				</div>
				<div class="modal-footer">
					<input class="btn" type="button" value="Cancel" data-dismiss="modal" aria-hidden="true" />
				</div>
			</div>


			<?php
			$mParams = empty($_SESSION['show_archived_group_members']) ? Array() : Array('!status' => 'archived');
			$persons = $this->_group->getMembers($mParams);
			list ($status_options, $default_status) = Person_Group::getMembershipStatusOptionsAndDefault();
			?>
			<h3>Group Members (<?php echo count($persons); ?>)</h3>

			<?php
			if (empty($_REQUEST['edit_statuses'])) {
				?>
				<div class="group-members-links">
					<div class="archived-link">
						<?php
						if (empty($_SESSION['show_archived_group_members'])) {
							?>
							<a class="hidden-phone" href="<?php echo build_url(Array('show_archived' => 1)); ?>"><i class="icon-eye-open"></i>Show archived members</a>
							<?php
						} else {
							?>
							<a class="hidden-phone" href="<?php echo build_url(Array('show_archived' => 0)); ?>"><i class="icon-eye-close"></i>Hide archived members</a>
							<?php
						}
						?>
					</div>
				<?php 
				if (!empty($persons)) {
					?>
					<div class="email-link">
						<a href="<?php echo build_url(Array('view' => NULL, 'call' => 'email', 'groupid' => $this->_group->id, 'show_modal' => 1)); ?>" data-target="#email-modal" data-toggle="modal"><i class="icon-email">@</i>Email members</a>
					</div>
					<?php
				}
				if (!empty($persons) && $GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
					if (count($status_options) > 1) {
						?>
						<div class="edit-status-link">
							<a href="<?php echo build_url(Array('edit_statuses' => 1)); ?>"><i class="icon-wrench"></i>Edit membership statuses</a>
						</div>
						<?php
					}
				}
				?>
					<div class="add-link">
						<a href="#action-plan-modal" data-toggle="modal"><i class="icon-plus-sign"></i>Add members</a>
					</div>
				</div>
				<?php
			}

			if (!empty($persons)) {
				foreach ($persons as $k => &$v) {
					$v['joined_group'] = format_date($v['joined_group']);
				}
				$special_fields = Array('joined_group', 'congregation');
				if (count($status_options) > 1) {
					array_unshift($special_fields, 'membership_status');
				}
				if (!empty($_REQUEST['edit_statuses'])) {
					?>
					<form method="post" action="<?php echo build_url(Array('edit_statuses' => NULL)); ?>">
					<?php
					$callbacks = Array(
						'membership_status' => Array($this, 'printMembershipStatusChooser')
					);
					$show_actions = FALSE;
					// This is a bit ugly, but the 'membership status' column needs to show the membership status chooser,
					// which needs the ID rather than the label
					foreach ($persons as &$person) {
						$person['membership_status'] = $person['membership_status_id'];
					}
				}
				include_once 'templates/person_list.template.php';
				if (!empty($_REQUEST['edit_statuses'])) {
					?>
					<div class="align-right">
						<input type="submit" class="btn" value="Save membership statuses" />
						<a class="btn" href="<?php echo build_url(Array('edit_statuses' => NULL)); ?>">Cancel</a>
					</div>
					</form>
					<?php
				}

			} else {
				?>
				<p><em>This group does not currently have any members</em></p>
				<?php
			}
		}
	}

	public static function printMembershipStatusChooser($personid, $value) {
		Person_Group::printMembershipStatusChooser('membership_status['.(int)$personid.']', $value);
	}
}
?>
