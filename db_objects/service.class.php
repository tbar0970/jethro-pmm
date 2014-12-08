<?php
include_once 'include/db_object.class.php';
require_once 'include/bible_ref.class.php';
class service extends db_object
{
	var $_readings = Array(); // bible readings for the service, fetched from the service_bible_reading table
	var $_old_readings = Array();

	var $_load_permission_level = 0; // want PERM_VIEWSERVICE | PERM_VIEWROSTER
	var $_save_permission_level = 0; // FUTURE: PERM_EDITSERVICE;

	function _getFields()
	{

		$fields = Array(
			'date'				=> Array(
									'type'		=> 'date',
									'allow_empty'	=> FALSE,
									'default' => '0000-00-00',
								   ),
			'congregationid'	=> Array(
									'type'				=> 'reference',
									'references'		=> 'congregation',
									'label'				=> 'Congregation',
									'show_id'			=> FALSE,
									'class'				=> 'person-congregation',
								   ),
			'format_title'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'maxlength'	=> 128,
									'initial_cap'	=> TRUE,
								   ),
			'topic_title'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'maxlength'	=> 128,
									'initial_cap'	=> TRUE,
								   ),
			'notes'		=> Array(
									'type'		=> 'text',
									'width'		=> 80,
									'height'	=> 4,
									'initial_cap'	=> TRUE,
								   ),
		);
		return $fields;
	}

	function _getUniqueKeys()
	{
		return Array(
				'datecong' => Array('date', 'congregationid'),
			   );
	}
	
	function _createFinal()
	{
		if (parent::_createFinal()) {
			$this->__insertBibleReadings();
			return true;
		} else {
			return false;
		}
	}

	function load($id)
	{
		$res = parent::load($id);
		$this->__loadBibleReadings();
		return $res;
	}

	function populate($id, $values)
	{
		parent::populate($id, $values);
		if (isset($values['readings'])) {
			$this->_readings = $values['readings'];
			$this->_old_readings = $this->_readings;
		} else {
			$this->__loadBibleReadings();
		}
	}

	function save()
	{
		$res = parent::save();
		if ($this->_readings != $this->_old_readings) {
			$this->__deleteBibleReadings();
			$this->__insertBibleReadings();
		}
		return $res;
	}

	function delete()
	{
		$res = parent::delete();
		$this->__deleteBibleReadings();
	}

	function __loadBibleReadings()
	{
		$sql = 'SELECT order_num, bible_ref, to_read, to_preach
				FROM service_bible_reading
				WHERE service_id = '.(int)$this->id.'
				ORDER BY order_num ASC';
		$this->_readings = $GLOBALS['db']->queryAll($sql, null, null, true);
		$this->_old_readings = $this->_readings;
		check_db_result($this->_readings);
	}
	
	function __deleteBibleReadings()
	{
		$sql = 'DELETE FROM service_bible_reading
				WHERE service_id = '.(int)$this->id;
		$res = $GLOBALS['db']->query($sql);
		check_db_result($res);
	}

	function __insertBibleReadings()
	{
		$i = 0;
		$values = Array();
		foreach ($this->_readings as $order_num => $reading) {
			$values[] = '('.(int)$this->id.', '.(int)$order_num.', '.$GLOBALS['db']->quote($reading['bible_ref']).', '.(int)$reading['to_read'].', '.(int)$reading['to_preach'].')';
			$i++;
		}
		if (!empty($values)) {
			$sql = 'INSERT INTO service_bible_reading (service_id, order_num, bible_ref, to_read, to_preach)
				VALUES '.implode(', ', $values);
			$res = $GLOBALS['db']->query($sql);
			check_db_result($res);
		}
		$this->_old_readings = $this->_readings;
	}

	function addReading($ref, $to_read, $to_preach)
	{
		$this->_readings[] = Array('bible_ref' => $ref, 'to_read' => $to_read, 'to_preach' => $to_preach);
	}
	
	function clearReadings()
	{
		$this->_readings = Array();
	}

	function getRawBibleReadings($type='all')
	{
		$candidate_readings = Array();
		foreach ($this->_readings as $reading) {
			if (($type == 'all') || ($reading['to_'.$type])) {
				$candidate_readings[] = $reading;
			}
		}
		return $candidate_readings;
	}

	function getValue($field)
	{
		if (0 === strpos($field, 'bible_')) {
			// eg bible_read_1  or bible_preach_all
			$bits = explode('_', $field);
			list($bible, $type, $number) = $bits;
			$short = (array_get($bits, 3) == 'short');
			$candidate_readings = $this->getRawBibleReadings($type);
			if ($number == 'all') {
				$res = Array();
				foreach ($candidate_readings as $reading) {
					$br = new Bible_Ref($reading['bible_ref']);
					$res[] = $br->toString($short);
				}
				return implode(', ', $res);
			} else {
				$bc = array_get($candidate_readings, $number-1);
				if ($bc) {
					$br = new Bible_Ref($bc['bible_ref']);
					return $br->toString();
				} else {
					return '';
				}
			}
		} else {
			return parent::getValue($field);
		}
	}

	function toString()
	{
		return $this->getFormattedValue('congregationid').' Service on '.$this->getFormattedValue('date');
	}

	static function shiftServices($congids, $after_date, $shift_by)
	{
		$sql = 'UPDATE service
				SET date = DATE_ADD(date, INTERVAL '.(int)$shift_by.' DAY)
				WHERE date >= '.$GLOBALS['db']->quote($after_date).'
				AND congregationid IN ('.implode(', ', array_map(Array($GLOBALS['db'], 'quote'), $congids)).')
				ORDER BY date '.(($shift_by > 0) ? 'DESC' : 'ASC');
		$res = $GLOBALS['db']->query($sql);
		check_db_result($res);
	}

	function printFieldValue($fieldname)
	{
		// a few special cases
		switch ($fieldname) {
			case 'bible_to_read':
			case 'bible_to_preach':
				$type = substr($fieldname, strlen('bible_to_'));
				$readings = $this->getRawBibleReadings('read');
				$res = Array();
				foreach ($readings as $reading) {
					$br = new Bible_Ref($reading['bible_ref']);
					$res[] = $br->getLinkedShortString();
				}
				echo implode(', ', $res);
				break;

			case 'bible_all':
				$readings = $this->getRawBibleReadings();
				$res = Array();
				foreach ($readings as $reading) {
					$br = new Bible_Ref($reading['bible_ref']);
					$entry = $br->getLinkedShortString();
					if (!$reading['to_read']) $entry = '('.$entry.')';
					if ($reading['to_preach']) $entry = '<strong>'.$entry.'</strong>';
					$res[] = $entry;
				}
				echo implode(', ', $res);
				break;
				
			case 'format_title':
			case 'topic_title':
				echo ents($this->values[$fieldname]);
				break;
				
			case 'summary':
				?>
				<i><?php echo ents($this->values['topic_title']); ?></i><br />
				<?php $this->printFieldValue('bible_all'); ?><br />
				<?php
				echo ents($this->values['format_title']); 
				if (!empty($this->values['notes'])) {
					?>
					&nbsp;<span class="clickable" onclick="$(this).next('div.hide').toggle()"><i class="icon-chevron-down"></i></span>
					<div class="smallprint hide"><?php echo nl2br(ents($this->values['notes'])); ?></div>
					<?php
				}
				break;
			default:
				parent::printFieldvalue($fieldname);
		}

	}

	function getFieldLabel($id, $short=FALSE)
	{
		$display_fields = $short ? $this->getDisplayFieldsShort() : $this->getDisplayFields();
		if (isset($display_fields[$id])) {
			return $display_fields[$id];
		}
		return parent::getFieldLabel($id);

	}

	static function getDisplayFields()
	{
		return Array(
				'topic_title' => 'Topic',
				'bible_all'	=> 'Bible texts (all)',
				'bible_to_read' => 'Bible texts to read',
				'bible_to_preach' => 'Bible texts to preach on',
				'format_title'	=> 'Format',
				'notes'			=> 'Notes',
				'summary'	=> 'Summary of topic, texts, format and notes'
			);
	}


	static function getDisplayFieldsShort()
	{
		return Array(
				'topic_title' => 'Topic',
				'bible_all'	=> 'Bible Texts',
				'bible_to_read'	=> 'Bible Readings',
				'bible_to_preach'	=> 'Sermon Texts',
				'format_title'	=> 'Format',
				'notes' => 'Notes',
				'summary'	=> 'Summary',
			);
	}


	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'GROUP_CONCAT(CONCAT(sbr.bible_ref, "=", sbr.to_read, "=", sbr.to_preach) ORDER BY sbr.order_num SEPARATOR ";") as readings';
		$res['from'] .= ' LEFT OUTER JOIN service_bible_reading sbr ON service.id = sbr.service_id';
		$res['group_by'] = 'service.id';
		return $res;
	}

	function getInstancesData($params, $logic='OR', $order='')
	{
		$res = parent::getInstancesData($params, $logic, $order);
		foreach ($res as $i => $v) {
			$res[$i]['readings'] = Array();
			if (!empty($v['readings'])) {
				$readings = explode(';', $v['readings']);
				foreach ($readings as $r) {
					list($ref, $to_read, $to_preach) = explode('=', $r);
					$res[$i]['readings'][] = Array('bible_ref' => $ref, 'to_read' => $to_read, 'to_preach' => $to_preach);
				}
			}
		}
		return $res;
	}

	public function replaceKeywords($text)
	{
		$matches = Array();
		preg_match_all('/%([a-zA-Z0-9_]*)%/', $text, $matches);
		foreach ($matches[1] as $keyword) {
			$text = str_replace('%'.$keyword.'%', $this->getKeywordReplacement($keyword), $text);
		}
		return $text;
	}

	public function getKeywordReplacement($keyword)
	{
		if (0 === strpos($keyword, 'NAME_OF_')) {
			$role_title = substr($keyword, strlen('NAME_OF_'));
			return $this->getPersonnelByRoleTitle($role_title);
		} else if (0 === strpos($keyword, 'SERVICE_')) {
			$service_field = strtolower(substr($keyword, strlen('SERVICE_')));
			$res = $this->getValue($service_field);
			if ($service_field == 'date') {
				// make a friendly date
				$res = date('j F Y', strtotime($res));
			}
			return $res;
		} else {
			return '';
		}
	}

	function getPersonnelByRoleTitle($role_title)
	{
		$sql = 'SELECT *
			FROM person
				JOIN roster_role_assignment rra ON rra.personid = person.id
				JOIN roster_role rr ON rra.roster_role_id = rr.id
			WHERE LOWER(REPLACE(rr.title, \' \', \'_\')) = '.$GLOBALS['db']->quote($role_title).'
				AND rr.congregationid = '.$GLOBALS['db']->quote($this->getValue('congregationid')).'
				AND rra.assignment_date = '.$GLOBALS['db']->quote($this->getValue('date'));
		$assignments =  $GLOBALS['db']->queryAll($sql, null, null, false);
		$role_ids = Array();
		$names = Array();
		foreach ($assignments as $assignment) {
			$role_id = $assignment['roster_role_id'];
			$role_ids[$role_id] = 1;
			$names[] = $assignment['first_name'].' '.$assignment['last_name'];
		}
		if (count($role_ids) != 1) return ''; // either no role found or ambigious role title
		return implode(', ', $names);
	}

	public static function findByDateAndCong($date, $congregationid)
	{
		$serviceid = key($GLOBALS['system']->getDBObjectData('service', Array('date' => $date, 'congregationid' => $congregationid), 'AND'));
		if (empty($serviceid)) {
			return null;
		} else {
			return $GLOBALS['system']->getDBObject('service', $serviceid);
		}
	}

	public function saveItems($itemList)
	{
		$db = $GLOBALS['db'];
		$res = $db->exec('DELETE FROM service_item WHERE serviceid = '.(int)$this->id);
		check_db_result($res);

		if (!empty($itemList)) {
			$SQL = 'INSERT INTO service_item
					(serviceid, rank, componentid, length_mins, note, heading_text)
					VALUES
					';
			$sets = Array();
			foreach ($itemList as $rank => $item) {
				$sets[] = '('.(int)$this->id.', '.(int)$rank.', '.(int)$item['componentid'].', '.(int)$item['length_mins'].', '.$db->quote(array_get($item, 'note')).', '.$db->quote(array_get($item, 'heading_text')).')';
			}
			$SQL .= implode(",\n", $sets);
			$res = $db->exec($SQL);;
			check_db_result($res);
		}
	}

	public function getItems($withContent=FALSE)
	{
		$SQL = 'SELECT si.*, sc.title, sc.alt_title, sc.is_numbered, '.($withContent ? 'sc.content_html, ' : '').'
					IF(LENGTH(sc.runsheet_title_format) = 0, scc.runsheet_title_format, sc.runsheet_title_format) AS runsheet_title_format
				FROM service_item si
				LEFT JOIN service_component sc ON si.componentid = sc.id
				LEFT JOIN service_component_category scc ON sc.categoryid = scc.id
				WHERE si.serviceid = '.(int)$this->id.' ORDER BY rank';
		$res = $GLOBALS['db']->queryAll($SQL);
		check_db_result($res);
		return $res;
	}

	public function printServicePlan()
	{
		?>
		<table
			<?php if (empty($_REQUEST['view'])) echo 'border="1"'; ?>
			class="table table-bordered"
		>
			<thead>
				<tr>
					<th class="narrow">Start</th>
					<th class="narrow">#</th>
					<th>Item</th>
				</tr>
			</thead>
			<tbody>
			<?php
			$num = 1;
			$items = $this->getItems();
			$cong = $GLOBALS['system']->getDBObject('congregation', $this->getValue('congregationid'));
			$time = strtotime(preg_replace('[^0-9]', '', $cong->getValue('meeting_time')));
			foreach ($items as $item) {
				if ($item['heading_text']) {
					?>
					<tr>
						<td colspan="4"><b><?php echo ents($item['heading_text']); ?></b></td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td><?php echo date('Hi', $time); ?></td>
					<td><?php if ($item['is_numbered']) echo $num++; ?></td>
					<td>
						<?php
						$title = $item['runsheet_title_format'];
						$title = str_replace('%title%', $item['title'], $title);
						$title = $this->replaceKeywords($title);
						echo ents($title);
						if ($item['note']) echo '<br /><i><small>'.nl2br(ents($item['note'])).'</small></i>';
						?>
					</td>
				</tr>
				<?php
				$time += $item['length_mins']*60;
			}
			?>
			</tbody>
		</table>
		<?php
	}

	public function printServiceContent()
	{
		$items = $this->getItems(TRUE);
		$num = 1;
		foreach ($items as $k => $i) {
			if ($i['heading_text']) {
				?>
				<h3><?php echo ents($i['heading_text']); ?></h3>
				<?php
			}
			if (!$i['is_numbered']) continue;
			?>
			<h4 id="item<?php echo $k; ?>">
				<?php
				echo ($num++).'. '.ents($i['title']);
				?>
			</h4>
			<?php echo $i['content_html']; ?>
			<?php
		}
	}
}
?>
