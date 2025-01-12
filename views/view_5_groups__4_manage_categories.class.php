<?php
class View_Groups__Manage_Categories extends View
{
	var $_all_categories;

	static function getMenuPermissionLevel()
	{
		return PERM_MANAGEGROUPCATS;
	}

	function processView()
	{
		if (!empty($_POST['delete_category_id'])) {
			$cat = $GLOBALS['system']->getDBObject('person_group_category', (int)$_POST['delete_category_id']);
			$cat->delete();
			add_message(_('Category deleted'));
		}
		$this->_all_categories = $GLOBALS['system']->getDBObjectData('person_group_category', Array(), 'OR', 'name');
	}
	
	function getTitle()
	{
		return _('Person Group Categories');
	}


	function printView()
	{
		?>
		<div class="container row-fluid">
			<p class="span8 text alert alert-info">
				<?php echo _('A person group in Jethro can belong to up to 1 category.  Group categories can contain sub-categories.  Putting groups into categories makes it easier to browse through them, and also allows you to run person queries based on all the groups in a category.');?>
			</p>
			<div class="span4 align-right">
				<a href="?view=_add_group_category"><i class="icon-plus-sign"></i><?php echo _('Add a new group category');?></a>
			</div>
		</div>
		<table class="table table-hover table-full-width">
		<?php
		$this->_printCategories();
		?>
		</table>
		<?php
	}

	function _printCategories($parent=0, $indent=0)
	{
		$this_level = Array();
		foreach ($this->_all_categories as $id => $details) {
			if ($details['parent_category'] == $parent) {
				$this_level[$id] = $details;
			}
		}
		if (!empty($this_level)) {
			foreach ($this_level as $id => $details) {
				?>
				<tr>
					<td style="padding-left: <?php echo 10+$indent*30; ?>px"><a href="?view=groups__list_all#cat<?php echo $id; ?>"><?php echo ents($details['name']); ?></a></td>
					<td class="action-cell narrow">
						<form class="min" style="clear: both" method="post" onsubmit="return confirm('<?php echo _('Are you sure you want to delete this category?');?>')">
							<a href="?view=_edit_group_category&categoryid=<?php echo (int)$id;?>"><i class="icon-wrench"></i><?php echo _('Edit');?></a>
							<input type="hidden" name="delete_category_id" value="<?php echo $id; ?>" />
							<button type="submit" class="btn-link"><i class="icon-trash"></i><?php echo _('Delete');?></button>
						</form>
					</td>
				</tr>
				<?php
				$this->_printCategories($id, $indent+1);
			}
		}
	}

}