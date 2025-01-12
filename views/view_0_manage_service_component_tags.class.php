<?php
class View__Manage_Service_Component_Tags extends View
{
	static function getMenuPermissionLevel()
	{
		return PERM_SERVICECOMPS;
	}

	function getTitle()
	{
		return 'Configure Service Component Tags';
	}

	function processView()
	{
		if (!empty($_POST['tagname'])) {
			$to_add = $to_delete = $to_update = Array();
			foreach ($_POST['tagname'] as $id => $name) {
				if ($id == '_new_') {
					foreach ($name as $n) {
						if ($n) $to_add[] = $n;
					}
				} else if ($name) {
					$to_update[$id] = $name;
				}
			}
			foreach ($to_update as $id => $name) {
				$SQL = 'UPDATE service_component_tag
						SET tag = '.$GLOBALS['db']->quote($name).'
						WHERE id = '.(int)$id;
				$res = $GLOBALS['db']->query($SQL);
			}
			if ($to_update) {
				$delwhere = 'id NOT IN ('.implode(',', array_merge(array_keys($to_update))).')';
			} else {
				$delwhere = 1;
			}
			$res = $GLOBALS['db']->query('DELETE FROM service_component_tag WHERE '.$delwhere);
			foreach ($to_add as $name) {
				$SQL = 'INSERT INTO service_component_tag (tag)
						VALUES ('.$GLOBALS['db']->quote($name).')';
				$res = $GLOBALS['db']->query($SQL);
			}
			add_message("Tags updated");
		}
	}

	function printView()
	{
		$tags = $GLOBALS['system']->getDBObjectData('service_component_tag');
		if (empty($tags)) {
			?>
			<p><i>No tags have been set up in the system yet.</i></p>
			<?php
		}
		$tags += Array('' => Array('tag' => ''));
		?>
		<form method="post">
		<table class="expandable valign-middle">
			<thead>
			</thead>
			<tbody>
			<?php
			$i = 0;
			foreach ($tags as $id => $tagdata) {
				?>
				<tr>
					<td><?php echo $id; ?></td>
					<td>
						<input name="tagname[<?php echo $id ? $id : '_new_]['; ?>]" value="<?php echo ents($tagdata['tag']); ?>" />
					</td>
					<td>
						<i class="icon-trash clickable delete-row"></i>
					</td>
				</tr>
				<?php
				$i++;
			}
			?>
			</tbody>
		</table>
		<input type="submit" class="btn" value="Save" />
		</form>
		<?php
	}
}