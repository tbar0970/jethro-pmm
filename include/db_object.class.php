<?php

class db_object
{

	public $id = NULL;
	public $fields = Array();
	public $values = Array();

	protected $_old_values = Array();
	protected $_held_locks = Array();
	protected $_acquirable_locks = Array();

	protected $_tmp = Array();

	protected $_load_permission_level = 0;
	protected $_save_permission_level = 0;


//--        CREATING, LOADING AND SAVING        --//

	public function __construct($id=0)
	{
		if (!$this->checkPerm($this->_load_permission_level)) {
			trigger_error('Current user has insufficient permission level to load a '.get_class($this).' object', E_USER_ERROR);
		}

		$this->fields = Array();
		$parent_class = get_parent_class($this);
		while ($parent_class != 'db_object') {
			$new_fields = call_user_func(Array($parent_class, '_getFields'));
			foreach ($new_fields as $i => $v) {
				$new_fields[$i]['table_name'] = strtolower($parent_class);
			}
			$this->fields += $new_fields;
			$parent_class = get_parent_class($parent_class);
		}
		$own_fields = call_user_func(Array(get_class($this), '_getFields'));
		foreach ($own_fields as $i => $v) {
			$own_fields[$i]['table_name'] = strtolower(get_class($this));
		}
		$this->fields = $own_fields + $this->fields;
		if ($id) {
			$this->load($id);
		} else {
			$this->loadDefaults();
		}
	}

	public function getInitSQL($table_name=NULL)
	{
		return $this->_getInitSQL($table_name);
	}

	/* This helper allows grandchild classes access to the default getInitSQL function */
	protected function _getInitSQL($table_name=NULL)
	{
		if (is_null($table_name)) $table_name = strtolower(get_class($this));
		$indexes = '';
		foreach ($this->_getUniqueKeys() as $name => $fields) {
			$indexes .= ',
				UNIQUE KEY `'.$name.'` ('.implode(', ', $fields).')';
		}
		foreach ($this->_getIndexes() as $name => $fields) {
			$indexes .= '
				INDEX `'.$name.'` ('.implode(', ', $fields).')';
		}

		$res = "
			CREATE TABLE `".$table_name."` (
			  `id` int(11) NOT NULL auto_increment,
				";
		foreach (call_user_func(Array(get_class($this), '_getFields')) as $name => $details) {
			$type = 'varchar(255)';
			$default = array_get($details, 'default', '');
			$null_exp = array_get($details, 'allow_empty', 0) ? 'NULL' : 'NOT NULL';
			switch ($details['type']) {
				case 'date':
					$type = 'date';
					break;
				case 'datetime':
					$type = 'datetime';
					break;
				case 'timestamp':
					$type = 'timestamp';
					$default = 'CURRENT_TIMESTAMP';
					break;
				case 'html':
					$type = 'text';
					$default = FALSE; // text columns cannot have a default
					break;
				case 'text':
					if (array_get($details, 'height', 1) != 1) {
						$type = 'text';
						$default = FALSE; // text columns cannot have a default
					} else {
						$type = 'varchar(255)';
					}
					break;
				case 'bibleref':
					$type = 'char(19)';
					break;
				case 'int':
					if (!is_null($len = array_get($details, 'fixed_length'))) {
						$type = 'varchar('.$len.')';
					} else {
						$type = 'int(11)';
					}
					$default = array_get($details, 'default', 0);
					break;
				case 'reference':
					$type = 'int(11)';
					$default = array_get($details, 'default', 0);
					break;
				case 'serialise':
					$type = 'text';
					$default = FALSE; // text columns cannot have a default
					break;
				case 'boolean':
				case 'bool':
					$type = 'TINYINT(1) UNSIGNED NOT NULL';
					$default = array_get($details, 'default', 0);
			}

			switch ($default) {
				case 'CURRENT_TIMESTAMP':
				case 'NULL':
				case '0':
					break;
				default:
					$default = $GLOBALS['db']->quote($default);
					break;
			}

			if ($default !== FALSE) $default = ' DEFAULT '.$default;

			$res .= "`".$name."` ".$type." ".$null_exp.$default.",
				";
		}
		$res .= "PRIMARY KEY (`id`)".$indexes."
			) ENGINE=InnoDB DEFAULT CHARSET=utf8";
		return $res;
	}

	protected function _getIndexes()
	{
		return Array();
	}

	protected function _getUniqueKeys()
	{
		return Array();
	}

	/**
	 *
	 * @return Array ([tablename.]columnName => referenceExpression) eg '`tagid`' => '`tagoption`(`id`) ON DELETE CASCADE'
	 */
	public function getForeignKeys()
	{
		return Array();
	}

	public function create()
	{
		if (!$this->checkPerm($this->_save_permission_level)) {
			trigger_error('Current user has insufficient permission level to create a '.get_class($this).' object', E_USER_ERROR);
		}

		$GLOBALS['system']->setFriendlyErrors(TRUE);
		if (!$this->readyToCreate()) {
			return FALSE;
		}
		$GLOBALS['system']->setFriendlyErrors(FALSE);
		if (isset($this->fields['creator']) && empty($this->values['creator'])) {
			$userid = $GLOBALS['user_system']->getCurrentPerson('id');
			if (!is_null($userid)) {
				$this->values['creator'] = $userid;
			}
		}
		if (isset($this->fields['history'])) {
			$this->values['history'] = Array(time() => 'Created');
		}

		$parent_class =  strtolower(get_parent_class($this));
		if ($parent_class != 'db_object') {
			$parent_obj = new $parent_class();
			$parent_obj->populate(0, $this->values);
			if (!$parent_obj->create()) {
				return FALSE;
			}
			$this->id = $parent_obj->id;
		}

		return $this->_createFinal();
	}

	protected function _createFinal()
	{
		$db =& $GLOBALS['db'];
		$flds = Array();
		$vals = Array();
		$our_fields = call_user_func(Array(get_class($this), '_getFields'));
		foreach ($our_fields as $name => $details) {
			if (array_get($details, 'readonly')) continue;
			$flds[] = $name;
			$v = array_get($this->values, $name, '');
			if (($v === '') && (($details['type'] == 'date') || $details['type'] == 'datetime')) {
				// Mysql strict mode doesn't like blank strings being inserted into datetime cols
				$v = NULL;
			}
			if ($details['type'] == 'serialise') {
				$vals[] = $db->quote(serialize($v));
			} else {
				$vals[] = $db->quote($v);
			}
		}

		if ($this->id) {
			// if this class doesn't extend db_object directly then ID is not an auto-increment field
			// so we save it like the rest
			array_unshift($flds, 'id');
			array_unshift($vals, $db->quote((int)$this->id));
		}

		$sql = 'INSERT INTO '.strtolower(get_class($this)).' ('.implode(', ', $flds).')
				 VALUES ('.implode(', ', $vals).')';
		$res = $db->query($sql);
		if (empty($this->id)) $this->id = $db->lastInsertId();
		$this->_old_values = Array();
		return TRUE;
	}


	public function createFromChild(&$child)
	{
		if (!$this->checkPerm($this->_save_permission_level)) {
			trigger_error('Current user has insufficient permission level to create a '.get_class($this).' object', E_USER_ERROR);
		}
		$this->populate($child->id, $child->values);
		return $this->_createFinal();
	}


	protected function _getTableNames()
	{
		$res = strtolower(get_class($this));
		$parent_class = strtolower(get_parent_class($this));
		while ($parent_class != 'db_object') {
			$res  = '('.$res.' JOIN '.$parent_class.' on '.$res.'.id = '.$parent_class.'.id)';
			$parent_class = strtolower(get_parent_class($parent_class));
		}
		return $res;
	}

	/**
	* Get the fields for this class only
	*
	* (Fields for parent classes are automatically added when instanting objects of this class
	*
	* @return array
	* @access protected
	*/
	protected static function _getFields()
	{
		return Array();
	}


	public function load($id)
	{
		$db =& $GLOBALS['db'];
		$sql = 'SELECT *
				FROM '.strtolower($this->_getTableNames()).'
				WHERE '.strtolower(get_class($this)).'.id = '.$db->quote($id) .'
				LIMIT 1';
		$res = $db->queryRow($sql);
		if (!empty($res)) {
			$this->id = $res['id'];
			unset($res['id']);
			$this->values = $res;
		}
		foreach ($this->fields as $name => $details) {
			if (($details['type'] == 'serialise') && isset($this->values[$name])) {
				$this->values[$name] = unserialize($this->values[$name]);
			}
		}
	}


	public function loadDefaults()
	{
		foreach ($this->fields as $id => $details) {
			$this->values[$id] = array_get($details, 'default', '');
			if (($details['type'] == 'reference') && empty($this->values[$id])) {
				$this->values[$id] = NULL;
			}
		}
	}


	public function save()
	{
		if (!$this->checkPerm($this->_save_permission_level)) {
			trigger_error('Current user has insufficient permission level to save a '.get_class($this).' object', E_USER_ERROR);
		}
		$GLOBALS['system']->setFriendlyErrors(TRUE);
		if (!$this->validateFields()) {
			return FALSE;
		}
		$GLOBALS['system']->setFriendlyErrors(FALSE);

		$acquiredLock = FALSE;
		if (!$this->haveLock()) {
			if ($this->acquireLock()) {
				$acquiredLock = TRUE;
			}
		}
		if (!$this->haveLock()) {
			trigger_error('Cannot save values for '.get_class($this).' #'.$this->id.' because someone else has the lock', E_USER_NOTICE);
			return FALSE;
		}

		// Add to the history, unless it's been explicly set as a value (see Person::archiveAndClean())
		if (isset($this->fields['history']) && empty($this->_old_values['history'])) {
			$changes = $this->_getChanges();
			if ($changes) {
				$user = $GLOBALS['user_system']->getCurrentPerson();
				$now = time();
				$this->values['history'][$now] = 'Updated by '.$user['first_name'].' '.$user['last_name'].' (#'.$user['id'].")\n".implode("\n", $changes);
				$this->_old_values['history'] = 1;
			}
		}

		if (empty($this->_old_values)) return TRUE;

		// Set any last-changed fields
		foreach ($this->_old_values as $i => $v) {
			if (array_key_exists($i.'_last_changed', $this->fields)) {
				$this->values[$i.'_last_changed'] = date('Y-m-d H:i:s');
				$this->_old_values[$i.'_last_changed'] = 1;
			}
		}

		$parent_class = strtolower(get_parent_class($this));
		if ($parent_class != 'db_object') {
			$parent_obj = new $parent_class($this->id);
			$parent_obj->populate($this->id, $this->values);
			if (!$parent_obj->save()) {
				return FALSE;
			}
		}

		// Update the DB
		$db =& $GLOBALS['db'];
		$sets = Array();
		$our_fields = $this->_getFields();
		foreach ($this->_old_values as $i => $v) {
			if (!isset($our_fields[$i])) continue;
			if (array_get($this->fields[$i], 'readonly')) continue;
			$new_val = $this->values[$i];
			if ($this->fields[$i]['type'] == 'serialise') {
				$new_val = serialize($new_val);
			}
			if (($this->fields[$i]['type'] == 'datetime') && ($new_val == 'CURRENT_TIMESTAMP')) {
				// CURRENT_TIMESTAMP should not be quoted
				$sets[] = ''.$i.' = '.$new_val;
			} else {
				// quote everything else
				$sets[] = ''.$i.' = '.$db->quote($new_val);
			}
		}
		if (!empty($sets)) {
			$sql = 'UPDATE '.strtolower(get_class($this)).'
					SET '.implode("\n, ", $sets).'
					WHERE id = '.$db->quote($this->id);
			$res = $db->query($sql);
		}

		$this->_old_values = Array();

		if ($acquiredLock) $this->releaseLock();

		return TRUE;
	}

	protected function _getChanges()
	{
		$changes = Array();
		foreach ($this->_old_values as $name => $old_val) {
			if ($name == 'history') continue;
			if ($name == 'password') continue;
			if (!array_get($this->fields[$name], 'show_in_summary', TRUE)
					&& !array_get($this->fields[$name], 'editable', TRUE)
			) {
				continue;
			}
			$changes[] = $this->getFieldLabel($name).' changed from "'.ents($this->getFormattedValue($name, $old_val)).'" to "'.ents($this->getFormattedValue($name)).'"';
		}
		return $changes;
	}

	public function reset()
	{
		$this->values = $this->_old_values = Array();
		$this->id = 0;
	}

	public function populate($id, $values)
	{
		$this->_old_values = Array();
		$this->id = $id;
		foreach ($this->fields as $fieldname => $details) {
			if (empty($details['readonly']) && array_key_exists($fieldname, $values)) {
				if (($details['type'] == 'serialise') && is_string($values[$fieldname])) {
					$values[$fieldname] = unserialize($values[$fieldname]);
				}
				$this->setValue($fieldname, $values[$fieldname]);
			}
		}
		$this->_held_locks = Array();
		$this->_acquirable_locks = Array();
	}

	public function delete()
	{
		$GLOBALS['system']->doTransaction('begin');
		$db =& $GLOBALS['db'];
		$table_name = strtolower(get_class($this));
		while ($table_name != 'db_object') {
			$sql = 'DELETE FROM '.$table_name.' WHERE id='.$db->quote($this->id);
			$res = $db->query($sql);
			$table_name = strtolower(get_parent_class($table_name));
		}
		$GLOBALS['system']->doTransaction('commit');
		return TRUE;
	}

	public function hasField($fieldname)
	{
		return isset($this->fields[$fieldname]);
	}




//--        GETTING AND SETTING FIELD VALUES        --//


	public function setValue($name, $value)
	{
		if (!isset($this->fields[$name])) {
			trigger_error('Cannot set value for field '.ents($name).' - field does not exist', E_USER_WARNING);
			return FALSE;
		}
		if (array_get($this->fields[$name], 'readonly')) {
			trigger_error('Cannot set value for readonly field "'.$name.'"', E_USER_WARNING);
			return;
		}
		if (array_get($this->fields[$name], 'initial_cap')) {
			$value = ucfirst($value);
		}
		if (array_get($this->fields[$name], 'trim')) {
			$value = hard_trim($value);
		}
		if ($this->fields[$name]['type'] == 'select') {
			if (!isset($this->fields[$name]['options'][$value]) && !(array_get($this->fields[$name], 'allow_empty', 1) && empty($value))) {
				trigger_error(ents($value).' is not a valid value for field "'.$name.'", and has not been set', E_USER_NOTICE);
				return;
			}
		}
		if (($this->fields[$name]['type'] == 'phone') && ($value != '')) {
			if (!is_valid_phone_number($value, $this->fields[$name]['formats'])) {
				trigger_error(ents($value).' is not a valid phone number for field "'.$name.'", and has not been set', E_USER_NOTICE);
				return;
			}
			$value = clean_phone_number($value);
		}
		if (!empty($this->fields[$name]['maxlength']) && (strlen($value) > $this->fields[$name]['maxlength'])) {
			$value = substr($value, 0, $this->fields[$name]['maxlength']);
		}
		if (($this->fields[$name]['type'] == 'email') && ($value != '')) {
			if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
				trigger_error(ents($value).' is not a valid value for email field "'.$name.'" and has not been set', E_USER_NOTICE);
				return;
			}
		}
		if ($this->fields[$name]['type'] == 'int') {
			if (!array_get($this->fields[$name], 'allow_empty', true) || ($value !== '')) {
				$strval = (string)$value;
				for ($i=0; $i < strlen($strval); $i++) {
					$char = $strval[$i];
					if ((int)$char != $char) {
						trigger_error(ents($value).' is not a valid value for integer field "'.$name.'" and has not been set', E_USER_NOTICE);
						return;
					}
				}
			}
		}
		if (array_key_exists($name, $this->values) && ($this->values[$name] != $value) && !array_key_exists($name, $this->_old_values)) {
			$this->_old_values[$name] = $this->values[$name];
		}
		$this->values[$name] = $value;
	}

	public function getValue($name)
	{
		return array_get($this->values, $name);
	}

	public function validateFields()
	{
		$res = TRUE;
		foreach ($this->fields as $id => $details) {
			$val = array_get($this->values, $id);
			if (!array_get($details, 'allow_empty', true) && (is_null($val) || ($val === ''))) {
				trigger_error($this->getFieldLabel($id).' is a required field for '.get_class($this).' and cannot be left empty', E_USER_NOTICE);
				$res = FALSE;
			}

			if (isset($details['max_length']) && (strlen($val) > $details['max_length'])) {
				trigger_error('The value for '.array_get($details, 'label', $id).' is too long (maximum is '.$details['max_length'].' characters)', E_USER_NOTICE);
				$res = FALSE;
			}

			if (isset($details['fixed_length']) && !empty($val) && (strlen($val) != $details['fixed_length'])) {
				trigger_error('The value for '.array_get($details, 'label', $id).' is not the correct length (must be exactly '.$details['fixed_length'].' characters)', E_USER_NOTICE);
				$res = FALSE;
			}
		}
		return $res;
	}


	public function readyToCreate()
	{
		return $this->validateFields();

	}


//--        INTERFACE PAINTING AND PROCESSING        --//


	public function printSummary()
	{
		?>
		<table class="table object-summary">
		<?php
		$this->_printSummaryRows();
		?>
		</table>
		<?php
	}


	protected function _printSummaryRows()
	{
		foreach ($this->fields as $name => $details) {
			if (!array_get($details, 'show_in_summary', true)) continue;
			$c = '';
			if (array_get($details, 'divider_before')) {
				$c = ' class="divider-before"';
			}
			?>
			<tr<?php echo $c; ?>>
				<th>
					<?php echo array_get($details, 'label', _(ucwords(str_replace('_', ' ', $name)))); ?>
				</th>
				<td>
					<?php $this->printFieldValue($name); ?>
				</td>
			</tr>
			<?php
		}
	}

	/**
	* Get the formatted value of a field
	*
	* This is used for HTML and non-HTML output so HTML should not be added
	* - see printFieldValue below for that.
	*/
	public function getFormattedValue($name, $value=null)
	{
		if (!isset($this->fields[$name])) {
			trigger_error('Cannot get value for field '.ents($name).' - field does not exist', E_USER_WARNING);
			return NULL;
		}
		if (is_null($value)) $value = array_get($this->values, $name, NULL);
		$field = $this->fields[$name];

		return format_value($value, $field);
	}


	/**
	* Print the value of a field to the HTML interface
	*
	* Subclasses should add links and other HTML markup by overriding this
	*/
	public function printFieldValue($name, $value=NULL)
	{
		if (!isset($this->fields[$name])) {
			trigger_error('Cannot get value for field '.ents($name).' - field does not exist', E_USER_WARNING);
			return NULL;
		}
		if (is_null($value)) $value = $this->getValue($name);
		if (($name == 'history')) {
			?>
			<table class="history table table-full-width table-striped">
			<?php
			foreach ($value as $time => $detail) {
				?>
				<tr>
					<th class="narrow"><?php echo format_datetime($time); ?></th>
					<td><?php echo nl2br(ents($detail)); ?></td>
				</tr>
				<?php
			}
			?>
			</table>
			<?php
		} else if ($this->fields[$name]['type'] == 'bitmask') {
			$percol = false;
			if (!empty($this->fields[$name]['cols']) && (int)$this->fields[$name]['cols'] > 1) {
				$percol = ceil(count($this->fields[$name]['options']) / $this->fields[$name]['cols']);
			}
			$i = 0;
			foreach ($this->fields[$name]['options'] as $k => $v) {
				$checked_exp = (($value & (int)$k) == $k) ? 'checked="checked"' : '';
				if (!array_get($this->fields[$name], 'show_unselected', TRUE) && empty($checked_exp)) continue;
				?>
				<label class="checkbox">
					<input type="checkbox" disabled="disabled" name="<?php echo ents($name); ?>[]" value="<?php echo ents($k); ?>" id="<?php echo ents($name.'_'.$k); ?>" <?php echo $checked_exp; ?>>
					<?php echo nbsp(ents($v)); ?>
				</label>
				<?php
			}
		} else if (($this->fields[$name]['type'] == 'text')
					&& (array_get($this->fields[$name], 'height', 1) > 1)) {
			echo nl2br(ents($this->getFormattedValue($name, $value)));
		} else if ($this->fields[$name]['type'] == 'phone') {
			echo '<a href="tel:'.$value.'">'.ents($this->getFormattedValue($name, $value)).'</a>';
		} else if (($this->fields[$name]['type'] == 'email')) {
			$personName = ($this->values[$name] == $value) ? $this->values['first_name'].' '.$this->values['last_name'] : '';
			echo '<a href="'.get_email_href($value, $personName).'" '.email_link_extras().'>'.ents($value).'</a>';
		} else if (($this->fields[$name]['type'] == 'html')) {
			echo $this->getFormattedValue($name, $value);
		} else {
			echo ents($this->getFormattedValue($name, $value));
		}
	}


	public function printForm($prefix='', $fields=NULL)
	{
		?>
		<div class="form-horizontal">
		<?php
		foreach ($this->fields as $name => $details) {
			if (empty($fields) && array_get($details, 'divider_before')) {
				?>
				<hr />
				<?php
			}

			if (!is_null($fields) && !in_array($name, $fields)) continue;
			if (array_get($details, 'readonly')) continue;
			if (!array_get($details, 'editable', true)) continue;
			?>
<div class="control-group">
	<label class="control-label" for="<?php echo $name; ?>"><?php echo _($this->getFieldLabel($name)); ?></label>
	<div class="controls">
		<?php
			$this->printFieldInterface($name, $prefix);
			if (!empty($this->fields[$name]['note'])) {
				echo '<p class="help-inline">'.$this->fields[$name]['note'].'</p>';
			}
		?>
	</div>
</div>
			<?php
		}
		?>
		</div>
		<?php

	}


	public function getFieldLabel($id)
	{
		if (empty($id)) return;
		if (!isset($this->fields[$id])) {
			return ucwords($id);
			//trigger_error('No such field '.$id);
			//return;
		}
		return array_get($this->fields[$id], 'label', _(ucwords(str_replace('_', ' ', $id))));

	}

	public function processForm($prefix='', $fields=NULL)
	{
		$GLOBALS['system']->setFriendlyErrors(TRUE);
		foreach ($this->fields as $name => $details) {
			if (!is_null($fields) && !in_array($name, $fields)) continue;
			if (array_get($details, 'readonly')) continue;
			if (!array_get($details, 'editable', true)) continue;
			$this->processFieldInterface($name, $prefix);
		}
		$GLOBALS['system']->setFriendlyErrors(FALSE);
	}

	public function printFieldInterface($name, $prefix='')
	{
		$value = array_get($this->values, $name);
		if ($this->id && !$this->haveLock()) {
			echo $value;
		} else {
			print_widget($prefix.$name, $this->fields[$name], $value);
		}
	}

	public function processFieldInterface($name, $prefix='')
	{
		if (!$this->id || $this->haveLock()) {
			$value = process_widget($prefix.$name, $this->fields[$name]);
			if ($value !== NULL) {
				if (($this->fields[$name]['type'] == 'reference') && ($value === 0)) {
					// process_widget returns 0 when the user selects the 'empty' option
					// but we want to save NULL to the db.
					$value = NULL;
				}
				$this->setValue($name, $value);
			}
		} else {
			trigger_error("Could not save value for object #".$this->id." because we do not hold the lock");
		}
	}


//--        PERMISSIONS AND LOCKING        --//

	protected function checkPerm($perm)
	{
		if ($perm == 0) return TRUE;
		if ($GLOBALS['user_system']->getCurrentUser('id')) {
			return $GLOBALS['user_system']->havePerm($perm);
		} else {
			return TRUE;
		}
	}

	public static function getLockLength()
	{
		// this is to work around older config that had the word "minutes" in the config.
		$lockLength = LOCK_LENGTH;
		if (FALSE === strpos($lockLength, ' ')) $lockLength .= ' minutes';
		return $lockLength;
	}

	public function haveLock($type='')
	{
		if (!empty($GLOBALS['JETHRO_INSTALLING'])) return TRUE;
		if (!isset($this->_held_locks[$type])) {
			$db =& $GLOBALS['db'];
			$sql = 'SELECT COUNT(*)
					FROM  db_object_lock
					WHERE object_type = '.$db->quote(strtolower(get_class($this))).'
						AND objectid = '.$db->quote($this->id).'
						AND lock_type = '.$db->quote($type).'
						AND userid = '.$GLOBALS['user_system']->getCurrentPerson('id').'
						AND expires > NOW()';
			$this->_held_locks[$type] = $db->queryOne($sql);
		}
		return $this->_held_locks[$type];
	}

	public function canAcquireLock($type='')
	{
		if (!empty($GLOBALS['JETHRO_INSTALLING'])) return TRUE;
		if (!isset($this->_acquirable_locks[$type])) {
			$db =& $GLOBALS['db'];
			$sql = 'SELECT userid
					FROM  db_object_lock
					WHERE object_type = '.$db->quote(strtolower(get_class($this))).'
						AND lock_type = '.$db->quote($type).'
						AND objectid = '.$db->quote($this->id).'
						AND expires > NOW()';
			$res = $db->queryOne($sql);
			if ($res == $GLOBALS['user_system']->getCurrentPerson('id')) {
				$this->_acquirable_locks[$type] = TRUE; // already got it, what the heck
				$this->_held_locks[$type] = TRUE;
			} else {
				$this->_acquirable_locks[$type] = empty($res); // if nobody else has it, we can get it
			}
		}
		return $this->_acquirable_locks[$type];
	}

	public function acquireLock($type='')
	{
		if (!$this->id) return TRUE;
		if ($this->haveLock($type)) return TRUE;
		if (!$this->canAcquireLock($type)) return FALSE;
		$bits = explode(' ', self::getLockLength());
		$mins = reset($bits);
		$db =& $GLOBALS['db'];
		$sql = 'INSERT INTO db_object_lock (objectid, object_type, lock_type, userid, expires)
				VALUES (
					'.$db->quote($this->id).',
					'.$db->quote(strtolower(get_class($this))).',
					'.$db->quote($type).',
					'.$db->quote($GLOBALS['user_system']->getCurrentPerson('id')).',
					NOW() + INTERVAL '.$db->quote($mins).' MINUTE)';
		$res = $db->query($sql);
		$this->_held_locks[$type] = TRUE;
		$this->_acquirable_locks[$type] = TRUE;

		if (rand(10, 100) == 100) {
			$sql = 'DELETE FROM db_object_lock
					WHERE expires < NOW()';
			$res = $db->query($sql);
		}

		return TRUE;
	}

	/**
	 * Release all locks held by the specified user.
	 * (Called at logout)
	 * @param int $userid	ID of user whose locks are to be released
	 */
	public static function releaseAllLocks($userid)
	{
		$db = $GLOBALS['db'];
		$SQL = 'DELETE FROM db_object_lock
				WHERE userid = '.$db->quote($userid);
		try {
			$res = $db->query($SQL);
		} catch (PDOException $e) {
			// We actually don't care if this fails - it shouldn't hold up the logout.
		}
	}


	public function releaseLock($type='')
	{
		$db =& $GLOBALS['db'];
		$sql = 'DELETE FROM db_object_lock
				WHERE userid = '.$db->quote($GLOBALS['user_system']->getCurrentPerson('id')).'
					AND objectid = '.$db->quote($this->id).'
					AND lock_type = '.$db->quote($type).'
					AND object_type = '.$db->quote(strtolower(get_class($this)));
		$res = $db->query($sql);
		$this->_held_locks[$type] = FALSE;
		$this->_acquirable_locks[$type] = NULL;
	}


//--        GLOBAL        --//


	public function getInstancesQueryComps($params, $logic, $order)
	{
		$db =& $GLOBALS['db'];
		if ($logic != 'OR') $logic = 'AND';
		$res = Array();
		$res['select'] = Array(strtolower(get_class($this)).'.id');
		foreach ($this->fields as $fieldname => $details) {
			if ($details['type'] == 'serialise') continue;
			$fieldname = $details['table_name'].'.'.$fieldname;
			$res['select'][] = $fieldname;
		}
		$res['from'] = $this->_getTableNames();
		$wheres = Array();
		foreach ($params as $field => $val) {
			$operator = is_array($val) ? 'IN' : ((FALSE === strpos($val, '%')) && (FALSE === strpos($val, '?'))) ? '=' : 'LIKE';
				$prefix = '';
				$suffix = '';
			if ($field[0] == '!') {
				$prefix = 'NOT (';
				$field = substr($field, 1);
				$suffix = ')';
			} else if ($field[0] == '<') {
				$operator = '<';
				$field = substr($field, 1);
				if ($field[0] == '=') {
					$operator .= '=';
					$field = substr($field, 1);
				}
			} else if ($field[0] == '>') {
				$operator = '>';
				$field = substr($field, 1);
				if ($field[0] == '=') {
					$operator .= '=';
					$field = substr($field, 1);
				}
			} else if ($field[0] == '-') {
				$operator = 'BETWEEN';
				$field = substr($field, 1);
			} else if ($field[0] == '(') {
				if ($val === Array()) {
					// We're checking if the value is a member of an empty set.
					$prefix = '/* empty set check for '.$field.' */';
					$field = '1';
					$operator = '=';
					$val = '2';
				} else {
					$operator = 'IN';
					$field = substr($field, 1);
				}
			} else if ($field[0] == '_') {
				// beginning-of-word match
				$operator = 'WORDBEGIN';
				$field = substr($field, 1);
			} else if ($val === NULL) {
				$operator = 'IS';
			}
			$raw_field = $field;
			if ($field == 'id') {
				$field = strtolower(get_class($this)).'.'.$field;
			} else if (isset($this->fields[$field])) {
				$field = $this->fields[$field]['table_name'].'.'.$field;
			}
			if (isset($this->fields[$raw_field]) && $this->fields[$raw_field]['type'] == 'text') {
				$field = 'LOWER('.$field.')';
			}
			if ($operator == 'BETWEEN') {
				if ($val[1] === NULL) {
					$operator = '>=';
					$val = $val[0];
				} else if ($val[0] === NULL) {
					$operator = '<=';
					$val = $val[1];
				}
			}
			if ($operator == 'IN') {
				if (is_array($val)) {
					if (in_array(NULL, $val)) {
						$prefix  .= '((';
						$suffix = ') OR ('.$field.' IS NULL))';
					}
					$val = implode(',', array_map(Array($GLOBALS['db'], 'quote'), $val));
				}
				$val = '('.$val.')'; // If val wasn't an array we dont quote it coz it's a subquery
				$wheres[] = '('.$prefix.$field.' '.$operator.' '.$val.$suffix.')';
			} else if ($operator == 'WORDBEGIN') {
				$wheres[] = '(('.$field.' LIKE '.$GLOBALS['db']->quote($val.'%').') OR ('.$field.' LIKE '.$GLOBALS['db']->quote('% '.$val.'%').'))';
			} else if ((is_array($val) && !empty($val))) {
				if ($operator == 'BETWEEN') {
					$field_details = array_get($this->fields, $field);
					if ($field_details && ($field_details['type'] == 'datetime') && (strlen($val[0]) == 10)) {
						// we're searching on a datetime field using date values
						// so extend them to prevent boundary errors
						$val[0] .= ' 00:00';
						$val[1] .= '23:59';
					}
					$wheres[] = '('.$field.' '.$operator.' '.$db->quote($val[0]).' AND '.$db->quote($val[1]).')';
				} else {
					$sub_wheres = Array();
					foreach ($val as $v) {
						$operator = ((FALSE === strpos($v, '%')) && (FALSE === strpos($v, '?'))) ? '=' : 'LIKE';
						if (isset($this->fields[$raw_field]) && $this->fields[$raw_field]['type'] == 'text') {
							$v = strtolower($v);
						}
						$sub_wheres[] = '('.$field.' '.$operator.' '.$db->quote($v).')';
					}
					$wheres[] = '('.$prefix.implode(' OR ', $sub_wheres).$suffix.')';
				}
			} else {
				if (isset($this->fields[$raw_field]) && $this->fields[$raw_field]['type'] == 'text') {
					$val = strtolower($val);
				}
				if ((is_array($val) && empty($val))) {
					$val = '';
				}
				$wheres[] = '('.$prefix.$field.' '.$operator.' '.$db->quote($val).$suffix.')';
			}
		}

		$res['where'] = implode("\n\t".$logic.' ', $wheres);

		if (!empty($order)) {
			if (isset($this->fields[$order])) {
				$res['order_by'] = $this->fields[$order]['table_name'].'.'.$order;
			} else {
				$res['order_by'] = $order; // good luck...
			}
		}
		return $res;

	}

	public function getInstancesData($params, $logic='OR', $order='')
	{
		$query_bits = $this->getInstancesQueryComps($params, $logic, $order);
		return $this->_getInstancesData($query_bits);
	}

	protected function _getInstancesData($query_bits)
	{
		$db = $GLOBALS['db'];
		$sql = 'SELECT '.implode(', ', $query_bits['select']).'
				FROM '.$query_bits['from'];
		if (!empty($query_bits['where'])) {
				$sql .= '
					WHERE '.$query_bits['where'];
		}
		if (!empty($query_bits['group_by'])) {
			$sql .= '
					GROUP BY '.$query_bits['group_by'];
		}
		if (!empty($query_bits['order_by'])) {
			$sql .= '
					ORDER BY '.$query_bits['order_by'];
		}

		$res = $db->queryAll($sql, null, null, true, true); // 5th param forces array even if one col
		return $res;

	}//end getInstances()

	public function toString()
	{
		if (array_key_exists('name', $this->fields)) {
			return $this->getValue('name');
		} else if (array_key_exists('title', $this->fields)) {
			return $this->getvalue('title');
		} else {
			return get_class($this).' #'.$this->id;
		}
	}

	public function findMatchingValue($field, $val)
	{
		$val = strtolower($val);
		if ($this->fields[$field]['type'] != 'select') return null;
		foreach ($this->fields[$field]['options'] as $k => $v) {
			if ($val == strtolower($k) || $val == strtolower($v)) return $k;
		}
		return null;
	}

	public function fromCsvRow($row, $overwriteExistingValues=TRUE)
	{
		foreach ($this->fields as $fieldname => $field) {
			if (isset($row[$fieldname])) {
				$val = $row[$fieldname];
				if ($field['type'] == 'select') {
					if ($val) {
						$newval = $this->findMatchingValue($fieldname, $val);
						if (is_null($newval)) {
							trigger_error("\"$val\" is not a valid option for $fieldname");
							continue;
						} else {
							$val = $newval;
						}
					} else {
						$val = array_get($field, 'default', key($field['options']));
					}
				}
				if (($overwriteExistingValues && ($val !== ''))
						|| ($this->getValue($fieldname) == '')
						|| !$this->id
				) {
					$this->setValue($fieldname, $val);
				}
			}
		}
		$this->validateFields();
	}


}//end class
