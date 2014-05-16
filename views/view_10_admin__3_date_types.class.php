<?php
class View_Admin__Date_Types extends View
{
	static function getMenuPermissionLevel()
	{
		$features = explode(',', ENABLED_FEATURES);
		if (in_array('DATES', $features)) {
			return PERM_SYSADMIN;
		} else {
			return -1;
		}
	}

	function getTitle()
	{
		return 'Configure Date Types';
	}

	function processView()
	{
		if (!empty($_POST['datetypename'])) {
			$to_add = $to_delete = $to_update = Array();
			foreach ($_POST['datetypename'] as $id => $name) {
				if ($id == '_new_') {
					foreach ($name as $n) {
						if ($n) $to_add[] = $n;
					}
				} else if ($name) {
					$to_update[$id] = $name;
				}
			}
			foreach ($to_update as $id => $name) {
				$SQL = 'UPDATE date_type
						SET name = '.$GLOBALS['db']->quote($name).'
						WHERE id = '.(int)$id;
				$res = $GLOBALS['db']->query($SQL);
				check_db_result($res);
			}
			$res = $GLOBALS['db']->query('DELETE FROM date_type WHERE id NOT IN ('.implode(',', array_merge(array_keys($to_update))).')');
			foreach ($to_add as $name) {
				$SQL = 'INSERT INTO date_type (name)
						VALUES ('.$GLOBALS['db']->quote($name).')';
				$res = $GLOBALS['db']->query($SQL);
				check_db_result($res);
			}
			add_message("Date types updated");
		}
	}

	function printView()
	{
		$GLOBALS['system']->includeDBClass('person');
		$types = Person::getDateTypes();
		if (empty($types)) {
			?>
			<p><i>No date types have been set up in the system yet.</i></p>
			<?php
		}
		$types += Array('' => '');
		?>
		<form method="post">
		<table class="expandable valign-middle">
			<thead>
			</thead>
			<tbody>
			<?php
			$i = 0;
			foreach ($types as $id => $name) {
				?>
				<tr>
					<td><?php echo $id; ?></td>
					<td>
						<input name="datetypename[<?php echo $id ? $id : '_new_]['; ?>]" value="<?php echo ents($name); ?>" />
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
?>
