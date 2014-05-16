<?php
class View_Groups__List_All extends View
{
	var $_group = NULL;
	var $_group_data = NULL;
	var $_category_data = NULL;

	function getTitle()
	{
		return 'All Groups';
	}

	function processView()
	{
		$conds = empty($_REQUEST['show_archived'])? Array('is_archived' => 0) : Array();
		$this->_group_data = $GLOBALS['system']->getDBObjectData('person_group', $conds, 'OR', 'categoryid, name');
		$this->_category_data = $GLOBALS['system']->getDBObjectData('person_group_category', Array(), 'OR', 'name');
	}

	function _printCats($parentid=0)
	{
		foreach ($this->_category_data as $cid => $cat) {
			if ($cat['parent_category'] != $parentid) continue;
			?>
			<h3><?php echo ents($cat['name']); ?></h3>
			<?php 
			$this->_printGroupsForCategory($cid);
			?>
			<div class="indent-left">
			<?php
			$this->_printCats($cid);
			?>
			</div>
			<?php
		}
	}

	function _printGroupsForCategory($cid)
	{
		$my_groups = Array();
		foreach($this->_group_data as $gid => $group) {
			if ($group['categoryid'] == $cid) $my_groups[$gid] = $group;
		}
		if (!empty($my_groups)) {
			?>
			<table class="table table-striped table-condensed table-hover clickable-rows">
				<thead>
					<tr>
						<th class="narrow">ID</th>
						<th>Name</th>
						<th class="narrow">Members</th>
					<?php
					if ($GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
						?>
						<th></th>
						<?php
					}
					?>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ($my_groups as $gid => $details) {
					$tr_class = $details['is_archived'] ? ' class="archived"' : '';
					?>
					<tr<?php echo $tr_class; ?>>
						<td><?php echo $gid; ?></td>
						<td><a class="block" href="?view=groups&groupid=<?php echo $gid; ?>"><?php echo $details['name']; ?></a></td>
						<td><?php echo $details['member_count']; ?></td>
					<?php
					if ($GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
						?>
						<td class="narrow action-cell">
							<a href="?view=_edit_group&groupid=<?php echo $gid; ?>&back_to=groups__list_all"><i class="icon-wrench"></i>Edit</a>
							<form class="min" method="post" action="?view=_edit_group&groupid=<?php echo $gid; ?>">
								<input type="hidden" name="action" value="delete" />
								<button type="submit" class="btn-link double-confirm-title"title="Delete this group">
									<i class="icon-trash"></i>Delete
								</button>
							</form>
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
	}
			
				
	
	function printView()
	{
		?>
		<div>
			<?php
			if (empty($_REQUEST['show_archived'])) {
				?>
				<a class="pull-right hidden-phone" href="<?php echo build_url(Array('show_archived' => 1)); ?>"><i class="icon-eye-open"></i>Include Archived Groups</a>
				<?php
			} else {
				?>
				<a class="pull-right hidden-phone" href="<?php echo build_url(Array('show_archived' => 0)); ?>"><i class="icon-eye-close"></i>Exclude Archived Groups</a>
				<?php
			}

			if ($GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
				?>
				<a href="?view=groups__add"><i class="icon-plus-sign"></i>Add a new group</a>
				<?php
			}
			?>
		</div>
		<div>
			<?php
			$cats = $this->_category_data; // + Array(0 => Array('name' => 'Uncategorised Groups'));
			$this->_printCats();
			?>
			<h3>Uncategorised Groups</h3>
			<?php $this->_printGroupsForCategory(0); ?>
		</div>
		<?php

	}
}
?>
