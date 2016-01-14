<?php
class Service_Component extends db_object
{
	var $_load_permission_level = PERM_VIEWSERVICE;
	var $_save_permission_level = PERM_EDITSERVICE;

	function __construct($id=0)
	{
		parent::__construct($id);

		if (!empty($_REQUEST['categoryid'])) {
			$_SESSION['service_comp_categoryid'] = $_REQUEST['categoryid'];
		}
		if (empty($this->id) && !empty($_SESSION['service_comp_categoryid'])) {
			$this->values['categoryid'] = array_get($_SESSION, 'service_comp_categoryid');
		}
	}

	protected static function _getFields()
	{

		$fields = Array(
			'categoryid'	=> Array(
									'type'				=> 'reference',
									'references'		=> 'service_component_category',
									'label'				=> 'Category',
									'show_id'			=> FALSE,
									'allow_empty'		=> FALSE,
								   ),
			'title'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
									'class' => 'autofocus',
									'allow_empty' => false,
								   ),
			'alt_title'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
								   ),
			'length_mins'		=> Array(
									'type'		=> 'int',
									'label'		=> 'Length (mins)',
									'divider_before' => true,
								   ),

			'runsheet_title_format'	=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
									'placeholder' => '(Optional)',
									'note' => 'How should this component be shown on the run sheet.  Can include replacements such as the component\'s %title%, %SERVICE_TOPIC% or %NAME_OF_SOMEROSTERROLE%.  Leave blank to use the category\'s default.',
								   ),
			'show_in_handout'		=> Array(
									'type'		=> 'select',
									'options'  => Array(
													'0' => 'No',
													'title' => 'Title only',
													'full'  => 'Title and Content',
													),
									'label'    => 'Show on Handout?',
									'editable' => true,
									'show_in_summary' => true,
									'note' => 'Items that are shown on the handout appear with numbers on the run sheet.',
								   ),

			'handout_title_format'	=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
									'placeholder' => '(Optional)',
									'note' => 'How should this component be shown on the handout.  Can include replacements such as the component\'s %title%, %SERVICE_TOPIC% or %NAME_OF_SOMEROSTERROLE%.  Leave blank to use the category\'s default.',
								   ),
			'show_on_slide'		=> Array(
									'type'		=> 'select',
									'options'  => Array('No', 'Yes'),
									'label'    => 'Show on Slide',
									'editable' => false,
									'show_in_summary' => false,
								   ),
			'content_html'		=> Array(
									'divider_before' => true,
									'type'		=> 'html',
									'label'     => 'Content',
									'note' => 'When typing in lyrics, use Ctrl+Enter between lines and normal Enter between verses. Don\'t worry if pasted lyrics contain odd fonts etc; these will be stripped on save.'
								   ),
			'credits'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'height'    => 3,
									'initial_cap'	=> TRUE,
								   ),
			'ccli_number'		=> Array(
									'label' => 'CCLI Number',
									'type'		=> 'int',
									'width'		=> 8,
								   ),
		);
		return $fields;
	}

	function search($keyword, $tagid, $congregationid, $categoryid=NULL)
	{
		$conds = Array();
		if (!empty($keyword)) {
			$conds['keyword'] = $keyword;
		}
		if (!empty($tagid)) {
			$conds['tagid'] = (int)$tagid;
		}
		if (!empty($congregationid)) {
			$conds['congregationid'] = (int)$congregationid;
		}
		if (!empty($categoryid)) {
			$conds['categoryid'] = (int)$categoryid;
		}
		return $GLOBALS['system']->getDBObjectData('service_component', $conds, 'AND', 'title');
	}

	/**
	 * Funny behaviour here:  tagid and congregationid are always ANDed even if $logic=or
	 */
	function getInstancesQueryComps($params, $logic, $order)
	{
		$congid = array_get($params, 'congregationid');
		unset($params['congregationid']);

		$tagid = array_get($params, 'tagid');
		unset($params['tagid']);

		$keyword = array_get($params, 'keyword');
		unset($params['keyword']);

		$res = parent::getInstancesQueryComps($params, $logic, $order);

		foreach ($res['select'] as $k => $v) {
			if (substr($v, -12) == 'content_html') unset($res['select'][$k]);
		}
		$res['select'][] = 'GROUP_CONCAT(DISTINCT cong.name SEPARATOR ", ") as congregations';
		$res['from'] .= ' JOIN service_component_category cat ON cat.id = service_component.categoryid';
		$res['from'] .= ' LEFT JOIN congregation_service_component csc ON csc.componentid = service_component.id ';
		$res['from'] .= ' LEFT JOIN congregation cong ON cong.id = csc.congregationid ';
		$res['from'] .=  ' LEFT JOIN service_item si ON si.componentid = service_component.id ';
		$res['from'] .=  ' LEFT JOIN service svc ON svc.id = si.serviceid AND svc.congregationid = cong.id ';
		$res['from'] .=  ' LEFT JOIN service svc1m ON svc1m.id = svc.id AND svc1m.date > NOW() - INTERVAL 1 MONTH ';
		$res['from'] .=  ' LEFT JOIN service svc12m ON svc12m.id = svc.id AND svc12m.date > NOW() - INTERVAL 12 MONTH ';
		$res['select'][] = 'IF (LENGTH(service_component.runsheet_title_format) = 0, cat.runsheet_title_format, service_component.runsheet_title_format) as runsheet_title_format ';
		$res['select'][] = 'COUNT(DISTINCT svc1m.id) AS usage_1m';
		$res['select'][] = 'COUNT(DISTINCT svc12m.id) AS usage_12m';
		$res['select'][] = 'MAX(svc.date) as lastused';
		$res['group_by'] = 'service_component.id';

		if ($res['where'] == '') $res['where'] = '1=1';
		$res['where']  = '('.$res['where'].') ';
		if ($congid === 0) {
			$res['where'] .=  ' '.$logic.' cong.id IS NULL';
		} else if ($congid !== NULL) {
			$res['where'] .=  ' '.$logic.' cong.id = '.(int)$congid;
		} else {
			$res['where'] .=  ' '.$logic.' cong.id IS NOT NULL';
		}
		if ($tagid) {
			$res['from'] .= ' LEFT JOIN service_component_tagging sct ON sct.componentid = service_component.id AND sct.tagid = '.(int)$tagid;
			$res['where'] .= ' '.$logic.' sct.tagid IS NOT NULL';
		}
		if ($keyword) {
			$qk = $GLOBALS['db']->quote("%{$keyword}%");
			$res['where'] .= ' '.$logic.' (title LIKE '.$qk.' OR alt_title LIKE '.$qk.' OR content_html LIKE '.$qk.')';
		}

		return $res;
	}

	public static function getAllByCCLINumber()
	{
		$SQL = 'SELECT ccli_number, id
				FROM service_component';
		$res = $GLOBALS['db']->queryAll($SQL, null, null, true, false);
		check_db_result($res);
		return $res;
	}
	
	protected function _printSummaryRows()
	{
		$oldFields = $this->fields;
		$this->fields = Array();
		foreach ($oldFields as $k => $v) {
			$this->fields[$k] = $v;
			if ($k == 'categoryid') {
				$this->fields['congregationids'] = Array('label' => 'Congregations');
			}
			if ($k == 'alt_title') {
				$this->fields['tags'] = Array();
			}
		}
		parent::_printSummaryRows();
		unset($this->fields['congregationids']);
		unset($this->fields['tags']);
	}

	public function printFieldValue($name)
	{
		switch ($name) {
			case 'congregationids':
				$congs = $GLOBALS['system']->getDBObjectData('congregation_service_component', Array('componentid' => $this->id));
				$names = Array();
				foreach ($congs as $cong) {
					$names[] = $cong['name'];
				}
				echo ents(implode(', ', $names));
				break;

			case 'tags':
				$tags = $GLOBALS['system']->getDBObjectData('service_component_tagging', Array('componentid' => $this->id));
				$names = Array();
				foreach ($tags as $tag) {
					echo '<span class="label">'.ents($tag['tag']).'</span> ';
				}
				break;

			case 'ccli_number':
				if (defined('CCLI_DETAIL_URL')) {
					// Can't just use class=med-popup because it's loaded in an AJAX frame so the window.onload has already run
					echo '<a href="'.str_replace('__NUMBER__', $this->getValue($name), CCLI_DETAIL_URL).'" onclick="return TBLib.handleMedPopupLinkClick(this)">';
				}
				echo $this->getValue($name);
				if (defined('CCLI_DETAIL_URL')) {
					echo '</a>';
				}
				break;


			default:
				return parent::printFieldValue($name);
		}
	}
	


	function toString()
	{
		return $this->values['title'];
	}

	public function printForm($prefix='', $fields=NULL)
	{
		$oldFields = $this->fields;
		$this->fields = Array();
		foreach ($oldFields as $k => $v) {
			$this->fields[$k] = $v;
			$congs = $GLOBALS['system']->getDBObjectData('congregation', Array('!meeting_time' => ''), 'AND');
			$options = Array();
			foreach ($congs as $id => $detail) {
				$options[$id] = $detail['name'];
			}
			if ($k == 'categoryid') {
				$this->fields['congregationids'] = Array(
					'type' => 'select',
					'label' => 'Used By',
					'options' => $options,
					'order_by'			=> 'meeting_time',
					'allow_empty'		=> TRUE,
					'allow_multiple'	=> TRUE,
					'note'				=> 'If a component is no longer used by any congregation, unselect all options',
				);
				if (empty($this->id)) {
					if (!empty($_REQUEST['congregationid'])) {
						$this->setValue('congregationids', Array($_REQUEST['congregationid']));
					} else {
						$this->values['congregationids'] = array_keys($congs);
					}
				}
			}
			if ($k == 'alt_title') {
				$this->fields['tags'] = Array();
			}
		}
		parent::printForm($prefix, $fields);
		unset($this->fields['congregationids']);
		unset($this->fields['tags']);
	}

	public function printFieldInterface($name, $prefix='')
	{
		if ($name == 'tags') {
			$options = $GLOBALS['system']->getDBObjectData('service_component_tag', Array(), 'OR', 'tag');
			foreach ($options as $k => $v) $options[$k] = $v['tag'];
			$params = Array(
					'type' => 'select',
					'options' => $options,
					'label' => 'Tags',
					'references' => 'service_component_tag',
					'order_by'			=> 'tag',
					'allow_empty'		=> FALSE,
					'allow_multiple'	=> FALSE,
					'class' => 'tag-chooser',
					'empty_text' => '-- Select --',
				);

			?>
			<table class="expandable">
			<?php
			foreach (array_get($this->values, 'tags', Array()) as $tagid) {
				?>
				<tr>
					<td><?php print_widget('tags[]', $params, $tagid); ?></td>
					<td><img src="<?php echo BASE_URL; ?>resources/img/cross_red.png" class="icon delete-row" title="Delete this tag from the list" /></td>
				</tr>
				<?php
			}
			$params['allow_empty'] = TRUE;
			$params['options']['_new_'] = '* Add new tag:';
			?>
				<tr>
					<td>
						<?php print_widget('tags[]', $params, NULL); ?>
						<input style="display: none" placeholder="Type new tag here" type="text" name="new_tags[]" />
					</td>
					<td><img src="<?php echo BASE_URL; ?>resources/img/cross_red.png" class="icon delete-row" title="Delete this tag from the list" /></td>
				</tr>
			</table>
			<p class="help-inline"><a href="?view=_manage_service_component_tags">Manage tag library</a></p>
			<?php
		} else {
			parent::printFieldInterface($name, $prefix);
		}
		if ($name == 'ccli_number') {
			if (defined('CCLI_SEARCH_URL')) {
				?>
				&nbsp; <a class="smallprint ccli-lookup" href="<?php echo CCLI_SEARCH_URL; ?>">Search CCLI</a>
				<?php
	}
		}
	}

	public function processForm($prefix='', $fields=NULL) {
		$res = parent::processForm($prefix, $fields);
		$this->values['congregationids'] = array_get($_REQUEST, $prefix.'congregationids', Array());
		$this->_tmp['tagids'] = Array();
		if (!empty($_REQUEST['tags'])) {
			foreach ($_REQUEST['tags'] as $tagid) {
				if ($tagid && is_numeric($tagid)) $this->_tmp['tagids'][] = $tagid;
			}
		}

		if (!empty($_REQUEST['new_tags'])) {
			$GLOBALS['system']->includeDBClass('service_component_tag');
			foreach ($_REQUEST['new_tags'] as $tag) {
				$tag = trim($tag);
				if (strlen($tag)) {
					$tag = ucfirst($tag);
					$obj = new Service_Component_Tag();
					$obj->setValue('tag', $tag);
					$obj->create();
					$this->_tmp['tagids'][] = $obj->id;
				}
			}
		}
		return $res;
	}

	public function create()
	{
		$res = parent::create();
		if ($res && $this->id) {
			$this->_saveCongregations();
			$this->_saveTags();
		}
		return $res;
	}

	public function load($id)
	{
		$res = parent::load($id);
		if ($this->id) {
			$SQL = 'SELECT congregationid FROM congregation_service_component WHERE componentid = '.(int)$this->id;
			$this->values['congregationids'] = $GLOBALS['db']->queryCol($SQL);
			$SQL = 'SELECT t.id FROM service_component_tagging tt
						JOIN service_component_tag t ON tt.tagid = t.id
						WHERE componentid = '.(int)$this->id.'
						ORDER BY tag';
			$this->values['tags'] = $GLOBALS['db']->queryCol($SQL);
		}
		return $res;
	}

	public function save()
	{
		$res = parent::save();
		if ($res) {
			$this->_saveCongregations(TRUE);
			$this->_saveTags(TRUE);
		}
		return $res;
	}

	private function _saveCongregations($deleteOld=FALSE)
	{
		if ($deleteOld) {
			check_db_result($GLOBALS['db']->exec('DELETE FROM congregation_service_component WHERE componentid = '.(int)$this->id));
		}
		$sets = Array();
		foreach (array_unique(array_get($this->values, 'congregationids', Array())) as $congid) {
			$sets[] = '('.(int)$this->id.', '.(int)$congid.')';
		}
		if (!empty($sets)) {
			$SQL = 'INSERT INTO congregation_service_component
					(componentid, congregationid)
					VALUES
					'.implode(",\n", $sets);
			$x = $GLOBALS['db']->exec($SQL);
			check_db_result($x);
		}
	}

	private function _saveTags($deleteOld=FALSE)
	{
		if ($deleteOld) {
			check_db_result($GLOBALS['db']->exec('DELETE FROM service_component_tagging WHERE componentid = '.(int)$this->id));
		}
		$sets = Array();
		foreach (array_unique(array_get($this->_tmp, 'tagids', Array())) as $tagid) {
			$sets[] = '('.(int)$this->id.', '.(int)$tagid.')';
		}
		if (!empty($sets)) {
			$SQL = 'INSERT INTO service_component_tagging
					(componentid, tagid)
					VALUES
					'.implode(",\n", $sets);
			$x = $GLOBALS['db']->exec($SQL);
			check_db_result($x);
		}

	}

	public function addCongregation($newCong)
	{
		$this->values['congregationids'][] = $newCong;
		$this->values['congregationids'] = array_unique($this->values['congregationids']);
	}

}