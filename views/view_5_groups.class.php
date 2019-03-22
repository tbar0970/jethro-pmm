<?php
class View_Groups extends View
{
	var $_group = NULL;

	function getTitle()
	{
		if ($this->_group) {
			return _('Viewing Group: ').$this->_group->getValue('name');
		}
	}

	function processView()
	{
		if (!empty($_REQUEST['person_groupid'])) {
			$_REQUEST['groupid'] = $_REQUEST['person_groupid'];
		}
		if (!empty($_REQUEST['groupid'])) {
			$this->_group = $GLOBALS['system']->getDBObject('person_group', (int)$_REQUEST['groupid']);
		}
		if (empty($this->_group)) {
			add_message("The specified group does not exist or you do not have permission to view it", 'error');
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
		if (!$this->_group) return;

		require_once 'include/size_detector.class.php';
		if (!SizeDetector::isNarrow()) {
			?>
			<h3><?php echo _('Group Details');?></h3>
			<table class="table table-full-width no-borders">
				<tr>
					<th class="narrow"><?php echo _('Category');?></th>
					<td><?php $this->_group->printFieldValue('categoryid'); ?>&nbsp;&nbsp;</td>
					<th class="narrow hidden-phone"><?php echo _('Record Attendance?');?></th>
					<td class="hidden-phone"><?php $this->_group->printFieldValue('attendance_recording_days'); ?></td>
					<td class="align-right">
					<?php
					if ($GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
						?>
						<a class="link-collapse" href="?view=_edit_group&groupid=<?php echo $this->_group->id; ?>"><i class="icon-wrench"></i><?php echo _('Edit group details');?></a>
						<?php
					}
					?>
					</td>
				</tr>
				<tr>
					<th class="narrow"><?php echo _('Status');?></th>
					<td><?php $this->_group->printFieldValue('is_archived'); ?></td>
					<th class="narrow hidden-phone"><?php echo _('Share Member Details?');?></th>
					<td class="hidden-phone"><?php $this->_group->printFieldValue('share_member_details'); ?></td>
					<td class="align-right">
						<?php
						if ($GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
							?>
							<a data-method="post" class="double-confirm-title link-collapse" title="<?php echo _('Delete group');?>"
							   href="?view=_edit_group&action=delete&groupid=<?php echo $this->_group->id; ?>">
								<i class="icon-trash"></i><?php echo _('Delete group');?>
							</a>
							<?php
						}
						?>
					</td>
				</tr>
				<tr>
					<th class="narrow"><?php echo _('Visibility');?></th>
					<td><?php $this->_group->printFieldValue('owner'); ?></td>
					<th class="narrow hidden-phone"><?php echo _('Show on add-family page?');?></th>
					<td class="hidden-phone"><?php $this->_group->printFieldValue('show_add_family'); ?></td>
					<td></td>
				</tr>
			</table>
			<?php
		}

		if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
			?>
			<div class="modal hide fade" id="action-plan-modal" role="dialog" aria-hidden="true">
				<form method="post" action="?view=_edit_group&action=add_member&groupid=<?php echo $this->_group->id; ?>">
					<div class="modal-header">
						<h4><?php echo _('Add Members by Name Search');?></h4>
					</div>
					<div class="modal-body">
						<table>
							<tr>
								<td><?php echo _('Select persons:');?></td>
								<td>
									<?php
									$GLOBALS['system']->includeDBClass('person');
									Person::printMultipleFinder('personid');
									?>
								</td>
							</tr>
							<tr>
								<td><?php echo _('Membership status:');?></td>
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
			<?php
		}
		?>

		<div class="modal hide fade autosize" id="email-modal" role="dialog" aria-hidden="true">
			<div class="modal-header">
				<h4><?php echo _('Email members of ');?><?php echo ents($this->_group->getValue('name')); ?></h4>
			</div>
			<div class="modal-body">
				<!-- to be populated with ajax -->
			</div>
			<div class="modal-footer">
				<input class="btn" type="button" value="Cancel" data-dismiss="modal" aria-hidden="true" />
			</div>
		</div>


		<?php
		$mParams = Array();
		if (!array_get($_SESSION, 'show_archived_group_members', FALSE)) {
			$mParams['!status'] = 'archived';
		}
		$persons = $this->_group->getMembers($mParams);
		list ($status_options, $default_status) = Person_Group::getMembershipStatusOptionsAndDefault();
		?>
		<h3 class="hidden-phone"><?php echo _('Group Members');?> (<?php echo count($persons); ?>)</h3>

		<?php
		if (empty($_REQUEST['edit_statuses'])) {
			?>
			<div class="group-members-links">
				<div class="archived-link">
					<?php
					if (empty($_SESSION['show_archived_group_members'])) {
						?>
						<a class="hidden-phone" href="<?php echo build_url(Array('show_archived' => 1)); ?>"><i class="icon-eye-open"></i><?php echo _('Show archived members');?></a>
						<?php
					} else {
						?>
						<a class="hidden-phone" href="<?php echo build_url(Array('show_archived' => 0)); ?>"><i class="icon-eye-close"></i><?php echo _('Hide archived members');?></a>
						<?php
					}
					?>
				</div>
			<?php
			if (SizeDetector::isNarrow()) {
				?>
				<a href="?view=_edit_group&groupid=<?php echo $this->_group->id; ?>"><i class="icon-wrench"></i><?php echo _('Edit group details');?></a>
				<?php
			}
			if (!empty($persons)) {
				?>
				<div class="email-link">
					<a href="<?php echo build_url(Array('view' => NULL, 'call' => 'email', 'groupid' => $this->_group->id, 'show_modal' => 1)); ?>" data-target="#email-modal" data-toggle="modal"><i class="icon-email">@</i><?php echo _('Email members');?></a>
				</div>
				<?php
			}
			if (!empty($persons) && $GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
				if (count($status_options) > 1) {
					?>
					<div class="edit-status-link">
						<a href="<?php echo build_url(Array('edit_statuses' => 1)); ?>"><i class="icon-wrench"></i><?php echo _('Edit membership statuses');?></a>
					</div>
					<?php
				}
			}
			if ($GLOBALS['user_system']->havePerm(PERM_EDITPERSON)) {
				?>
				<div class="add-link">
					<a href="#action-plan-modal" data-toggle="modal"><i class="icon-plus-sign"></i><?php echo _('Add members');?></a>
				</div>
				<?php
			}
			?>
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
			} else {
				?>
				<form method="post" enctype="multipart/form-data" action="" class="bulk-person-action">
				<?php
			}
			$tclasses = 'table table-hover table-striped table-auto-width';
			if (empty($_REQUEST['edit_statuses'])) $tclasses .= ' clickable-rows';
			?>
			<table class="<?php echo $tclasses; ?>">
				<thead>
					<tr>
						<th><?php echo _('Name'); ?></th>
					<?php
					if (count($status_options) <= 1) {
						?>
						<th><?php echo _('Congregation'); ?></th>
						<th><?php echo _('Status'); ?></th>
						<?php
					} else {
						?>
						<th><?php echo _('Membership status'); ?></th>
						<?php
					}
					if (SizeDetector::isWide()) {
						?>
						<th><?php echo _('Age'); ?></th>
						<th><?php echo _('Gender'); ?></th>
						<?php
					}
					if (!SizeDetector::isNarrow()) {
						?>
						<th><?php echo _('Joined Group'); ?></th>
						<?php
					}
					?>
						<th>&nbsp;</th>
						<th class="narrow selector form-inline"><input type="checkbox" class="select-all" title=<?php echo _('"Select all"')?> /></th>
					</tr>

				</thead>
				<tbody>
			<?php
			$dummy_person = new Person();
			foreach ($persons as $id => $details) {
				$dummy_person->populate($id, $details);
				$tr_class = ($details['status'] === 'archived') ? ' class="archived"' : '';
				?>
				<tr data-personid="<?php echo $id; ?>" <?php echo $tr_class; ?>>
					<td class="nowrap"><?php echo $dummy_person->printFieldvalue('name'); ?></td>
				<?php
				if (count($status_options) <= 1) {
					?>
					<td class="nowrap"><?php echo $dummy_person->printFieldvalue('congregationid'); ?></td>
					<td class="nowrap"><?php echo $dummy_person->printFieldvalue('status'); ?></td>
					<?php
				} else if (!empty($_REQUEST['edit_statuses'])){
					?>
					<td><?php $this->printMembershipStatusChooser($id, $details['membership_status_id']); ?></td>
					<?php
				} else {
					?>
					<td><?php echo ents($details['membership_status']); ?></td>
					<?php
				}
				if (SizeDetector::isWide()) {
					?>
					<td><?php $dummy_person->printFieldValue('age_bracketid'); ?></td>
					<td><?php $dummy_person->printFieldValue('gender'); ?></td>
					<?php
				}

				if (!SizeDetector::isNarrow()) {
					?>
					<td><?php echo format_date($details['joined_group']); ?></td>
					<?php
				}
				?>
					<td class="narrow"><a href="?view=persons&personid=<?php echo $id; ?>"><i class="icon-user"></i><?php echo _('View'); ?></td>
					<td class="selector narrow"><input name="personid[]" type="checkbox" value="<?php echo $id; ?>" /></td>

				</tr>
				<?php
			}
			?>
				</tbody>
			</table>
			<?php

			if (!empty($_REQUEST['edit_statuses'])) {
				?>
				<div class="align-right">
					<input type="submit" class="btn" value="<?php echo _('Save membership statuses');?>" />
					<a class="btn" href="<?php echo build_url(Array('edit_statuses' => NULL)); ?>"><?php echo _('Cancel');?></a>
				</div>
				<?php
			} else {
				include 'templates/bulk_actions.template.php';
			}
			?>
			</form>
			<?php


		} else {
			?>
			<p><em><?php echo _('This group does not currently have any members');?></em></p>
			<?php
		}

	}

	public static function printMembershipStatusChooser($personid, $value) {
		Person_Group::printMembershipStatusChooser('membership_status['.(int)$personid.']', $value);
	}
}
?>
