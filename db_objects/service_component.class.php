<?php
class Service_Component extends db_object
{
	var $_load_permission_level = 0; // want PERM_VIEWSERVICE | PERM_VIEWROSTER
	var $_save_permission_level = 0; // FUTURE: PERM_EDITSERVICE;

	function _getFields()
	{

		$fields = Array(
			'categoryid'	=> Array(
									'type'				=> 'reference',
									'references'		=> 'service_component_category',
									'label'				=> 'Category',
									'show_id'			=> FALSE,
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
			'is_numbered'		=> Array(
									'type'		=> 'select',
									'options'  => Array('No', 'Yes'),
									'label'    => 'Numbered?'
								   ),
			'runsheet_title_format'	=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
									'note' => 'Leave this blank to use the category default',
								   ),
			'handout_title_format'	=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'initial_cap'	=> TRUE,
									'note' => 'Leave this blank to use the category default',
									'editable' => false,
									'show_in_summary' => false,
								   ),
			'show_in_handout'		=> Array(
									'type'		=> 'select',
									'options'  => Array('No', 'Yes'),
									'label'    => 'Show on Handout?',
									'editable' => false,
									'show_in_summary' => false,
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
									'label'     => 'Content'
								   ),
			'credits'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'height'    => 3,
									'initial_cap'	=> TRUE,
								   ),
			'ccli_number'		=> Array(
									'type'		=> 'int',
								   ),
		);
		return $fields;
	}
	
	function getInstancesQueryComps($params, $logic, $order)
	{
		$congid = array_get($params, 'congregationid');
		unset($params['congregationid']);
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
		$res['select'][] = 'IF (LENGTH(service_component.runsheet_title_format) = 0, cat.runsheet_title_format, service_component.runsheet_title_format) as runsheet_title_format ';
//		$res['select'][] = 'SUM(..) as usagescore';
		$res['select'][] = 'MAX(svc.date) as lastused';
		$res['group_by'] = 'service_component.id';
		if ($congid === 0) {
			$res['where'] .= ' AND cong.id IS NULL';
		} else if ($congid !== NULL) {
			$res['where'] .= ' AND cong.id = '.(int)$congid;
		} else {
			$res['where'] .= ' AND cong.id IS NOT NULL';
		}
		return $res;
	}
	
	protected function _printSummaryRows()
	{
		parent::_printSummaryRows();
		?>
		<tr>
			<th>Used by</th>
			<td>
				<?php
				$congs = $GLOBALS['system']->getDBObjectData('congregation_service_component', Array('componentid' => $this->id));
				$names = Array();
				foreach ($congs as $cong) {
					$names[] = $cong['name'];
				}
				echo ents(implode(', ', $names));
				?>
			</td>
		</tr>
		<?php
	
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
			if ($k == 'categoryid') {
				$this->fields['congregationids'] = Array(
					'type' => 'reference',
					'label' => 'Used By',
					'references' => 'congregation',
					'order_by'			=> 'meeting_time',
					'allow_empty'		=> FALSE,
					'allow_multiple'	=> TRUE,
					'filter'			=> create_function('$x', '$y = $x->getValue("meeting_time"); return !empty($y);'),
				);
				if (empty($this->id) && !empty($_REQUEST['congregationid'])) {
					$this->setValue('congregationids', Array($_REQUEST['congregationid']));
				}
			}
		}
		parent::printForm($prefix, $fields);
	}

	public function processForm($prefix='', $fields=NULL) {
		$res = parent::processForm($prefix, $fields);
		$this->_tmp['congregationids'] = array_get($_REQUEST, $prefix.'congregationids', Array());
		return $res;
	}

	public function create()
	{
		$res = parent::create();
		if ($res && $this->id) {
			$this->_saveCongregations();
		}
		return $res;
	}

	public function load($id)
	{
		$res = parent::load($id);
		if ($this->id) {
			$SQL = 'SELECT congregationid FROM congregation_service_component WHERE componentid = '.(int)$this->id;
			$this->values['congregationids'] = $GLOBALS['db']->queryCol($SQL);
		}
		return $res;
	}

	public function save()
	{
		$res = parent::save();
		if ($res) {
			check_db_result($GLOBALS['db']->exec('DELETE FROM congregation_service_component WHERE componentid = '.(int)$this->id));
			$this->_saveCongregations();
		}
		return $res;
	}

	private function _saveCongregations()
	{
		$sets = Array();
		foreach (array_unique(array_get($this->_tmp, 'congregationids', Array())) as $congid) {
			$sets[] = '('.(int)$this->id.', '.(int)$congid.')';
		}
		bam($sets);
		if (!empty($sets)) {
			$SQL = 'INSERT INTO congregation_service_component
					(componentid, congregationid)
					VALUES
					'.implode(",\n", $sets);
			$x = $GLOBALS['db']->exec($SQL);
			check_db_result($x);
		}
	}

}