<?php
include_once 'include/db_object.class.php';
class Person_Group_Category extends db_object
{
	protected $_save_permission_level = PERM_MANAGEGROUPCATS;

	public function __construct($id=NULL) {
		parent::__construct($id);
		if (!empty($_REQUEST['parent_category'])) {
			$_SESSION['group_categoryid'] = $_REQUEST['parent_category'];
		} else if (!empty($_SESSION['group_categoryid'])) {
			$this->values['parent_category'] = array_get($_SESSION, 'group_categoryid');
		}
	}

	protected static function _getFields()
	{
		return Array(
			'name'				=> Array(
									'type'		=> 'text',
									'width'		=> 40,
									'maxlength'	=> 128,
									'allow_empty'	=> FALSE,
									'initial_cap'	=> TRUE,
								),
			'parent_category'	=> Array(
									'type'	=> 'reference',
									'references'	=> 'person_group_category',
									'editable'		=> true,
									'note'			=> 'To make this a top-level category, leave this blank',
									'allow_empty'	=> true,
								),
		);
	}

	function delete()
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		parent::delete();
		$sql = 'UPDATE person_group SET categoryid = 0 WHERE categoryid = '.(int)$this->id;
		$res = $GLOBALS['db']->query($sql);
		$GLOBALS['system']->doTransaction('COMMIT');
	}

	function printFieldInterface($fieldname, $prefix='')
	{
		if ($fieldname == 'parent_category') {
			self::printChooser($prefix.$fieldname, $this->getValue('parent_category'), $this->id);
		} else {
			return parent::printFieldInterface($fieldname, $prefix);
		}
	}

	static function printChooser($fieldname, $value=NULL, $exclude_categoryid=NULL)
	{
		$params = empty($exclude_categoryid) ? Array() : Array('!id' => $exclude_categoryid);
		$all_cats = $GLOBALS['system']->getDBObjectData('person_group_category', $params, 'AND', 'parent_category ASC');
		?>
		<select name="<?php echo $fieldname; ?>">
		<option value="0">(None)</option>
		<?php
		self::_printChooserOptions($all_cats, $value);
		?>
		</select>
		<?php
	}

	static function _printChooserOptions($all_cats, $value, $parent=0, $indent='')
	{
		foreach ($all_cats as $id => $cat) {
			if ($cat['parent_category'] != $parent) continue;
			$sel = ($id == $value) ? ' selected="selected"' : '';
			?>
			<option value="<?php echo $id; ?>"<?php echo $sel; ?>><?php echo $indent.ents($cat['name']); ?></option>
			<?php
			self::_printChooserOptions($all_cats, $value, $id, $indent.'&nbsp;&nbsp;&nbsp;&nbsp;');
		}
	}

}