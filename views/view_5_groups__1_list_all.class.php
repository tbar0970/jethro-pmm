<?php
class View_Groups__List_All extends View
{
	var $_group = NULL;
	var $_group_data = NULL;
	var $_category_data = NULL;

	function getTitle()
	{
		if (empty($_REQUEST['search'])) {
			return _('All Groups');
		} else {
			return _('Group search results');
		}
	}

	function processView()
	{
		$this->_category_data = $GLOBALS['system']->getDBObjectData('person_group_category', Array(), 'OR', 'name');
		if (!empty($_REQUEST['search'])) {
			$this->_group_data = $GLOBALS['system']->getDBObjectData('person_group', Array('name' => array_get($_REQUEST, 'search', '')), 'OR', 'name');
			if (empty($this->_group_data)) {
				$this->_group_data = $GLOBALS['system']->getDBObjectData('person_group', Array('name' => '%'.array_get($_REQUEST, 'search', '').'%'), 'OR', 'name');
			}
			if (count($this->_group_data) == 1) {
				add_message("One group found");
				redirect('groups', Array('groupid' => key($this->_group_data), 'name' => NULL)); // exits
			}
		} else {
			$conds = empty($_REQUEST['show_archived'])? Array('is_archived' => 0) : Array();
			$this->_group_data = $GLOBALS['system']->getDBObjectData('person_group', $conds, 'OR', 'categoryid, name');
		}
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
						<th><?php echo _('Name');?></th>
						<th class="narrow"><?php echo _('Members');?></th>
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
							<a href="?view=_edit_group&groupid=<?php echo $gid; ?>&back_to=groups__list_all"><i class="icon-wrench"></i><?php echo _('Edit');?></a>
							<form class="min" method="post" action="?view=_edit_group&groupid=<?php echo $gid; ?>">
								<input type="hidden" name="action" value="delete" />
								<button type="submit" class="btn-link double-confirm-title"title="Delete this group">
									<i class="icon-trash"></i><?php echo _('Delete');?>
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
		// Search form - show top right if not yet searching
		$formclass = empty($_REQUEST['search']) ? 'pull-right' : '';
		?>
		<div class="list-all-controls">
		<form method="get" class="<?php echo $formclass; ?> form-horizontal min fullwidth-phone">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>">
			<span class="input-append">
				<input type="text" name="search" enterkeyhint="Search" placeholder="Search groups..." value="<?php echo ents(array_get($_REQUEST, 'search', '')); ?>">
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
			?>
			<?php
			if (empty($_REQUEST['show_archived'])) {
				?>
				<a class="soft pull-right hidden-phone" href="<?php echo build_url(Array('show_archived' => 1)); ?>"><i class="icon-eye-open"></i><?php echo _('Include Archived')?></a>
				<?php
			} else {
				?>
				<a class="soft pull-right hidden-phone" href="<?php echo build_url(Array('show_archived' => 0)); ?>"><i class="icon-eye-close"></i><?php echo _('Exclude Archived')?></a>
				<?php
			}

			if ($GLOBALS['user_system']->havePerm(PERM_EDITGROUP)) {
				?>
				<a href="?view=groups__add"><i class="icon-plus-sign"></i><?php echo _('Add a new group');?></a>
				<?php
			}
			?>
			<?php
		} else if (empty($this->_group_data)) {
			?>
			<p><strong>No matching groups were found</strong></p>
			<?php
		} else {
			?>
			<p><strong><?php echo count($this->_group_data); ?> matching groups found:</strong></p>
			<?php
		}
		?>
		</div>
		<div>
			<?php
			$cats = $this->_category_data; // + Array(0 => Array('name' => 'Uncategorised Groups'));
			$this->_printCats();
			foreach ($this->_group_data as $g) {
				if ($g['categoryid'] == 0) {
					?>
					<h3><?php echo _('Uncategorised Groups');?></h3>
					<?php 
					$this->_printGroupsForCategory(0);
					break;
				}
			}
			?>
		</div>
		<?php

	}
}