<?php

class View_Admin__Action_Plans extends View
{
	var $_plan;

	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function processView()
	{
		$GLOBALS['system']->includeDBClass('action_plan');
		if (isset($_REQUEST['planid'])) {
			$this->_plan = new Action_Plan((int)$_REQUEST['planid']);
		}
		if ($this->_plan && !empty($_REQUEST['delete'])) {
			if ($this->_plan->acquireLock()) {
				$this->_plan->delete();
				add_message('Action plan deleted');
				$this->_plan->releaseLock();
				$this->_plan  = NULL;
			} else {
				add_message('The plan could not be deleted because another user currently holds the lock.  Wait for them to finish editing then try again.', 'failure');
			}
		} else if (!empty($_POST['plan_submitted'])) {
			$this->_plan->processForm();
			if ($this->_plan->id) {
				$this->_plan->save();
				$this->_plan->releaseLock();
				add_message("Action plan updated");
			} else {
				$this->_plan->create();
				add_message("Action plan created");
			}
			$this->_plan = NULL;
		} else if ($this->_plan && $this->_plan->id) {
			if (!$this->_plan->acquireLock()) {
				add_message("This plan cannot be edited because another user holds the lock.  Please wait for them to finish editing and try again.", 'failure');
			}
		}
	}
	
	function getTitle()
	{
		if ($this->_plan) {
			if ($this->_plan->id) {
				return 'Edit action plan: '.$this->_plan->getValue('name');
			} else {
				return 'Add action plan';
			}
		} else {
			return 'Action plans';
		}
	}
	
	function printView()
	{
		if (!empty($this->_plan)) {
			if (empty($this->_plan->id) || $this->_plan->haveLock()) {
				?>
				<form method="post">
					<input type="hidden" name="plan_submitted" value="1" />
					<?php
					$this->_plan->printForm();
					?>
					<input class="btn" type="submit" value="Save Action Plan" />
					<a class="btn" href="<?php echo build_url(Array('planid' => NULL)); ?>">Cancel</a>
				</form>
				<?php
			} else {
				$this->_plan->printSummary();
			}
		} else {
			?>
			<p class="text alert alert-info">
				<?php echo _("An action plan is a set of pre-defined actions to be performed on a person or family, for example adding a note or adding them to a group. You can trigger an action plan when creating a family, when viewing a person, or using the bulk-actions tool.  Action plans can be useful for automating a workflow such as your newcomer integration process."); ?>
			</p>
			<p><a href="<?php echo build_url(Array('planid' => 0, 'delete' => NULL)); ?>"><i class="icon-plus-sign"></i>Add new plan</a></p>
			<?php
			$saved_plans = $GLOBALS['system']->getDBObjectData('action_plan', Array(), '', 'name');
			if (empty($saved_plans)) {
				?>
				<i>There are not yet any action plans saved in the system</i>
				<?php
			} else {
				?>
				<table class="table table-hover table-striped table-min-width">
					<thead>
						<tr>
							<th>ID</th>
							<th>Name</th>
							<th>Last modified</th>
							<th>&nbsp;</th>
						</tr>
					</thead>
					<tbody>
					<?php
					$dummy_plan = new Action_Plan();
					foreach ($saved_plans as $id => $details) {
						$dummy_plan->populate($id, $details);
						?>
						<tr>
							<td><?php echo (int)$id; ?></td>
							<td class="nowrap"><?php echo ents($details['name']); ?></td>
							<td class="nowrap"><?php $dummy_plan->printFieldValue('modified'); ?> by <?php echo $dummy_plan->printFieldValue('modifier'); ?></td>
							<td class="narrow action-cell">
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&planid=<?php echo $id; ?>"><i class="icon-wrench"></i>Edit</a> &nbsp;
								<a href="?view=<?php echo ents($_REQUEST['view']); ?>&planid=<?php echo $id; ?>&delete=1" class="confirm-title" title="Delete this action plan"><i class="icon-trash"></i>Delete</a>
							</td>
						</tr>
						<?php
					}
					?>
					</tbody>
				</table>
				<?php
			}
		}
	}
}