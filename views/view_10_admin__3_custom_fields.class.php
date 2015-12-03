<?php
class View_Admin__Custom_Fields extends View
{
	private $fields = Array();

	static function getMenuPermissionLevel()
	{
		$features = explode(',', ENABLED_FEATURES);
		if (in_array('DATES', $features) || in_array('CUSTOMFIELDS', $features)) {
			return PERM_SYSADMIN;
		} else {
			return -1;
		}
	}

	function getTitle()
	{
		return 'Configure Custom Fields';
	}

	function processView()
	{
		$fields = $GLOBALS['system']->getDBObjectData('custom_field', Array(), 'OR', 'rank');
		foreach ($fields as $id => $details) {
			$x = new Custom_field($id);
			if (!$x->acquireLock()) {
				$this->fields = NULL;
				add_message("Somebody else is currently editing the custom fields.  Please try again later", 'error');
				return;
			}
			$this->fields[$id] = $x;
		}

		$i = 0;
		$ranks = array_flip(array_get($_REQUEST, 'index', Array()));
		while (array_key_exists('fields_'.$i.'_id', $_REQUEST)) {
			$prefix = 'fields_'.$i.'_';
			if (empty($_REQUEST[$prefix.'id'])) {
				if (!empty($_REQUEST[$prefix.'name'])) {
					// new field
					$field = new Custom_Field();
					$field->processForm($prefix);
					$field->setValue('rank', $ranks[$i]);
					$field->create();
					$field->acquireLock();
					$this->fields[$field->id] = $field;
				}
			} else {
				$fieldID = $_REQUEST[$prefix.'id'];
				// existing field
				if (!empty($_REQUEST[$prefix.'delete'])) {
					$this->fields[$fieldID]->delete();
					unset($this->fields[$fieldID]);
				} else {
					$this->fields[$fieldID]->processForm($prefix);
					$this->fields[$fieldID]->setValue('rank', $ranks[$i]);
					$this->fields[$fieldID]->save();
				}
			}
			$i++;
		}



		uasort($this->fields, create_function('$x,$y', 'return (int)$x->getValue("rank") > (int)$y->getValue("rank");'));

		if ($i > 0) {
			add_message("Custom fields updated", 'success');
		}

	}

	function printView()
	{
		if (is_null($this->fields)) return;
		
		if (empty($this->fields)) {
			?>
			<p><i>No custom fields have been set up in the system yet.</i></p>
			<?php
		}
		$field = new Custom_Field();
		$this->fields['_new_'] = $field;
		?>
		<form method="post">
		<table id="custom-fields-editor" class="table table-auto-width expandable valign-top">
			<thead>
				<tr>
					<th>ID</th>
					<th>Name</th>
					<th>Type</th>
					<th>Multi?</th>
					<th>Parameters</th>
					<th><i class="icon-trash"></i></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$i = 0;
			foreach ($this->fields as $field) {
				$prefix = 'fields_'.$i.'_';
				?>
				<tr>
					<td>
						<?php
						echo $field->id;
						print_hidden_field($prefix.'id', $field->id);
						print_hidden_field('index[]', $i);
						?>
					</td>
					<td>
						<?php $field->printFieldInterface('name', $prefix); ?>
					</td>
					<td>
						<?php
						if ($field->id) {
							$field->printFieldValue('type');
						} else {
							$field->printFieldInterface('type', $prefix);
						}
						?>
					</td>
					<td class="center">
						<?php $field->printFieldInterface('allow_multiple', $prefix); ?>
					</td>
					<td>
						<?php $field->printFieldInterface('params', $prefix); ?>
					</td>
					<td class="center">
						<?php
						if ($field->id) {
							?>
							<input type="checkbox" name="<?php echo $prefix; ?>delete" value="1"
								   data-toggle="strikethrough" data-target="row"
								   title="Click to delete this field" />
							<?php
						}
						?>
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
