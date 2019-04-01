<?php
include_once 'include/db_object.class.php';
require_once 'include/bible_ref.class.php';
class service extends db_object
{
	var $_readings = Array(); // bible readings for the service, fetched from the service_bible_reading table
	var $_old_readings = Array();

	protected $_load_permission_level = 0;
	protected $_save_permission_level = PERM_EDITSERVICE;

	public function __construct($id=0)
	{
		if (!$GLOBALS['system']->featureEnabled('SERVICEDETAILS')) {
			// WHen the SERVICEDETAILS feature is not enabled, PERM_EDITSERVICE
			// does not exist, so nobody has access!
			$this->_save_permission_level = PERM_BULKSERVICE;
		}
		return parent::__construct($id);
	}

	protected static function _getFields()
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
			'comments'	=> Array(
									'type'		=> 'html',
									'height'	=> '7em',
									'toolbar'  => 'basic',
									'toolbarLocation'  => 'bottom',
									'enterMode' => 'BR',
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
	}

	function __deleteBibleReadings()
	{
		$sql = 'DELETE FROM service_bible_reading
				WHERE service_id = '.(int)$this->id;
		$res = $GLOBALS['db']->query($sql);
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
		$type = str_replace('to_', '', $type);
		if (!in_array($type, Array('all', 'preach', 'read'))) return Array();

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
			@list($bible, $type, $number) = $bits;
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

	function toString($long=FALSE)
	{
		$cong = $GLOBALS['system']->getDBObject('congregation', $this->getValue('congregationid'));
		$congName = $cong->getValue($long ? 'name' : 'long_name');
		if (!strlen($congName)) $congName = $cong->toString();
		$date = $long ? date('jS F Y', strtotime($this->getValue('date'))) : $this->getFormattedValue('date');
		return $congName.' Service on '.$date;
	}

	static function shiftServices($congids, $after_date, $shift_by)
	{
		$sql = 'UPDATE service
				SET date = DATE_ADD(date, INTERVAL '.(int)$shift_by.' DAY)
				WHERE date >= '.$GLOBALS['db']->quote($after_date).'
				AND congregationid IN ('.implode(', ', array_map(Array($GLOBALS['db'], 'quote'), $congids)).')
				ORDER BY date '.(($shift_by > 0) ? 'DESC' : 'ASC');
		$res = $GLOBALS['db']->query($sql);
	}

	function getFormattedValue($fieldname, $value=null)
	{
		switch ($fieldname) {

			case 'bible_to_read':
			case 'bible_to_preach':
				$type = substr($fieldname, strlen('bible_to_'));
				$readings = $this->getRawBibleReadings('read');
				$res = Array();
				foreach ($readings as $reading) {
					$br = new Bible_Ref($reading['bible_ref']);
					$res[] = $br->toShortString();
				}
				return implode(', ', $res);
				break;

			case 'bible_all':
				$readings = $this->getRawBibleReadings();
				$res = Array();
				foreach ($readings as $reading) {
					$br = new Bible_Ref($reading['bible_ref']);
					$entry = $br->toShortString();
					if (!$reading['to_read']) $entry = '('.$entry.')';
					if ($reading['to_preach']) $entry = '<strong>'.$entry.'</strong>';
					$res[] = $entry;
				}
				return implode(', ', $res);
				break;

			case 'format_title':
			case 'topic_title':
				return $this->values[$fieldname];
				break;

			case 'summary':
				$res = Array();
				if ($this->values['topic_title']) $res[] = $this->values['topic_title'];
				if ($b = $this->getFormattedValue('bible_all')) $res[] = $b;
				if ($this->values['format_title']) $res[] = $this->values['format_title'];
				if ($this->values['notes']) $res[] = $this->values['notes'];
				return implode("\n", $res);
				break;

			default:
				if (strpos($fieldname, 'comps_') === 0) {
					$compCatID = (int)substr($fieldname, 6);
					$res = Array();
					foreach ($this->getItems(FALSE, $compCatID) as $item) {
						$res[] = ents($item['title']);
					}
					return implode("\n", $res);
				} else {
					return parent::getFormattedValue($fieldname);
				}
		}	}

	function printFieldValue($fieldname, $value=NULL)
	{
		// a few special cases
		switch ($fieldname) {
			case 'bible_to_read':
			case 'bible_to_preach':
				$type = substr($fieldname, strlen('bible_to_'));
				$readings = $this->getRawBibleReadings($type);
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
			case 'summary_inline':
				$separator = $fieldname == 'summary' ? '<br />' : '&nbsp; &bull; &nbsp;';
				$bits = Array();
				if (strlen($this->values['topic_title'])) {
					$bits[] = '<i>'.ents($this->values['topic_title']).'</i>';
				}
				if ($this->getRawBibleReadings()) {
					ob_start();
					$this->printFieldValue('bible_all');
					$bits[] = ob_get_clean();
				}
				if (strlen($this->values['format_title'])) {
					$bits[] = ents($this->values['format_title']);
				}
				if (!empty($this->values['notes'])) {
					$x = '<small>';
					if ($fieldname == 'summary_inline') {
						$x .= str_replace("\n", ' / ', ents($this->values['notes']));
					} else {
						$x .= nl2br(ents($this->values['notes']));
					}
					$x .= '</small>';
					$bits[] = $x;
				}
				echo implode($separator, $bits);
				break;
			default:
				if (strpos($fieldname, 'comps_') === 0) {
					$compCatID = (int)substr($fieldname, 6);
					$res = Array();
					foreach ($this->getItems(FALSE, $compCatID) as $item) {
						$res[] = ents($item['title']);
					}
					echo implode('<br />', $res);
				} else {
					parent::printFieldvalue($fieldname);
				}
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
		$res = Array(
				'topic_title' => 'Topic',
				'bible_all'	=> 'Bible texts (all)',
				'bible_to_read' => 'Bible texts to read',
				'bible_to_preach' => 'Bible texts to preach on',
				'format_title'	=> 'Format',
				'notes'			=> 'Notes',
				'summary'	=> 'Summary of topic, texts, format and notes'
			);
		$compCats = $GLOBALS['system']->getDBObjectData('service_component_category');
		foreach ($compCats as $id => $details) {
			$res['comps_'.$id] = 'All '.$details['category_name'];
		}

		return $res;
	}


	static function getDisplayFieldsShort()
	{
		$res = Array(
				'topic_title' => 'Topic',
				'bible_all'	=> 'Bible Texts',
				'bible_to_read'	=> 'Bible Readings',
				'bible_to_preach'	=> 'Sermon Texts',
				'format_title'	=> 'Format',
				'notes' => 'Notes',
				'summary'	=> 'Summary',
			);
		$compCats = $GLOBALS['system']->getDBObjectData('service_component_category');
		foreach ($compCats as $id => $details) {
			$res['comps_'.$id] = $details['category_name'];
		}
		return $res;

	}


	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['select'][] = 'GROUP_CONCAT(CONCAT(sbr.bible_ref, "=", sbr.to_read, "=", sbr.to_preach) ORDER BY sbr.order_num SEPARATOR ";") as readings';
		$res['from'] .= ' LEFT JOIN service_bible_reading sbr ON service.id = sbr.service_id';
		$res['select'][] = 'IF (si.id IS NULL, 0, 1) as has_items';
		$res['from'] .= ' LEFT JOIN service_item si ON si.serviceid = service.id AND si.rank = 0 ';
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

		} else if (substr($keyword, -10) == '_FIRSTNAME') {
			return $this->getPersonnelByRoleTitle(substr($keyword, 0, -10), TRUE);

		} else if (0 === strpos($keyword, 'SERVICE_')) {
			$service_field = strtolower(substr($keyword, strlen('SERVICE_')));
			if (in_array($service_field, Array('topic', 'format'))) {
				$service_field .= '_title';
			}
			if ((substr($service_field, 0, 5) == 'bible') || isset($this->fields[$service_field])) {
				$res = $this->getValue($service_field);
				if ($service_field == 'date') {
					// make a friendly date
					$res = date('j F Y', strtotime($res));
				}
				return $res;
			}

		}

		// look for a role that matches
		return $this->getPersonnelByRoleTitle($keyword);
	}

	function getPersonnelByRoleTitle($role_title, $first_name_only=FALSE, $index=NULL)
	{
		$sql = 'SELECT roster_role_id, first_name, last_name
			FROM person
				JOIN roster_role_assignment rra ON rra.personid = person.id
				JOIN roster_role rr ON rra.roster_role_id = rr.id
			WHERE UPPER(REPLACE(rr.title, \' \', \'_\')) = '.$GLOBALS['db']->quote($role_title).'
				AND (rr.congregationid = '.$GLOBALS['db']->quote($this->getValue('congregationid')).'
					OR (IFNULL(rr.congregationid, 0) = 0))
				AND rra.assignment_date = '.$GLOBALS['db']->quote($this->getValue('date')).'
		';
		if ($index !== NULL) {
			$sql .= 'AND rank = '.($index-1).' ';
		}
		$sql .= '
				ORDER BY roster_role_id, rank';
		$assignments =  $GLOBALS['db']->queryAll($sql, null, null, false);
		$role_ids = Array();
		$names = Array();
		foreach ($assignments as $assignment) {
			$role_id = $assignment['roster_role_id'];
			$role_ids[$role_id] = 1;
			$names[] = $assignment['first_name'].($first_name_only ? '' : (' '.$assignment['last_name']));
		}
		if ((count($role_ids) == 0) && preg_match('/_[0-9]+$/', $role_title)) {
			// Try treating the last bit as rank
			$bits = explode('_', $role_title);
			$index = array_pop($bits);
			$short_title = implode('_', $bits);
			return $this->getPersonnelByRoleTitle($short_title, $first_name_only, $index);
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

	/**
	 * Find all services after a particular date.
	 *
	 * If the congregationid is specified, then only services for this congregation are returned.
	 * @param string $date
	 * @param int $congregationid
	 * @return mixed Returns an array of service objects.
	 */
	public static function findAllAfterDate($date, $congregationid = null)
	{
            $db =& $GLOBALS['db'];
            $sql = '';
            if ($congregationid == null)
            {
                $sql = 'SELECT id FROM service where date >= ' . $db->quote($date);
            }
            else
            {
                $sql = 'SELECT id FROM service where date >= ' . $db->quote($date) .
                    ' and congregationid = ' . $db->quote($congregationid);
            }
            $res = $db->queryAll($sql);
            $services = Array();
            foreach ($res as $row)
            {
                $service = System_Controller::get()->getDBObject('service', $row['id']);
                $services[] = $service;
            }

            return $services;
	}

	public function saveItems($itemList)
	{
		$db = $GLOBALS['db'];
		$res = $db->exec('DELETE FROM service_item WHERE serviceid = '.(int)$this->id);

		$compids = $comps = Array();
		foreach ($itemList as $item) {
			if ($item['componentid']) $compids[] = (int)$item['componentid'];
		}
		if ($compids) {
			$set = implode(', ', array_unique($compids));
			$comps = $GLOBALS['system']->getDBObjectData('service_component', Array('(id' => $set));
		}

		if (!empty($itemList)) {
			$SQL = 'INSERT INTO service_item
					(serviceid, rank, componentid, title, personnel, show_in_handout, length_mins, note, heading_text)
					VALUES
					';
			$sets = Array();
			foreach ($itemList as $rank => $item) {
				if ($item['componentid']) {
					$item['title'] = ''; // title is only saved for ad hoc items

					// only save personnel if it's been changed from the component's default
					// so that if the roster changes, the run sheet will auto updated.
					if ($item['personnel'] == $this->replaceKeywords($comps[$item['componentid']]['personnel'])) {
						$item['personnel'] = '';
					}
				} else {
					$item['componentid'] = NULL;
				}
				$sets[] = '('.(int)$this->id.', '.(int)$rank.', '.$db->quote($item['componentid']).', '.$db->quote($item['title']).', '.$db->quote($item['personnel']).', '.$db->quote($item['show_in_handout']).', '.(int)$item['length_mins'].', '.$db->quote(array_get($item, 'note')).', '.$db->quote(array_get($item, 'heading_text')).')';
			}
			$SQL .= implode(",\n", $sets);
			$res = $db->exec($SQL);;
		}
	}

	/**
	 * This is unusual. The 'comments' field gets saved separately
	 * because it falls under the 'items' lock, not the default lock,
	 * because it is edited with the run sheet, not the overall service program.
	 * @param string$comments
	 */
	public function saveComments($comments)
	{
		if ($this->haveLock('items')) {
			$db = $GLOBALS['db'];
			$SQL = 'UPDATE service
					SET comments = '.$db->quote($comments).'
					WHERE id = '.(int)$this->id;
			$res = $db->exec($SQL);
			$this->values['comments'] = $comments;
			return TRUE;
		}
		return FALSE;
	}

	public function getItems($withContent=FALSE, $ofCategoryID=NULL)
	{
		$SQL = 'SELECT si.*,
					IF (si.componentid IS NULL, si.title, sc.title) AS title,
					sc.alt_title,
					'.($withContent ? 'sc.content_html, sc.credits, ' : '').'
					IFNULL(IF(LENGTH(sc.runsheet_title_format) = 0, scc.runsheet_title_format, sc.runsheet_title_format), "%title%") AS runsheet_title_format,
					IFNULL(IF(LENGTH(sc.handout_title_format) = 0, scc.handout_title_format, sc.handout_title_format), "%title%") AS handout_title_format,
					IF(LENGTH(si.personnel) > 0, si.personnel, IF(LENGTH(sc.personnel) > 0, sc.personnel, scc.personnel_default)) as personnel,
					sc.categoryid
				FROM service_item si
				LEFT JOIN service_component sc ON si.componentid = sc.id
				LEFT JOIN service_component_category scc ON sc.categoryid = scc.id
				WHERE si.serviceid = '.(int)$this->id.'
				';
		if (!empty($ofCategoryID)) $SQL .= ' AND sc.categoryid = '.(int)$ofCategoryID."\n";
		$SQL .= ' ORDER BY rank';
		$res = $GLOBALS['db']->queryAll($SQL);

		foreach ($res as $k => &$item) {
			$item['personnel'] = $this->replaceKeywords($item['personnel']);
		}
		unset($item);
		return $res;
	}

	public function printRunSheet()
	{
		?>
		<table cellspacing="0" cellpadding="5"
			class="table table-bordered table-condensed table-full-width run-sheet no-narrow-magic"
		>
			<thead>
				<tr>
					<th class="narrow">Start</th>
					<th class="narrow">#</th>
					<th>Item</th>
					<th class="narrow">Personnel</th>
				</tr>
			</thead>
			<tbody>
			<?php
			$num = 1;
			$items = $this->getItems();
			$cong = $GLOBALS['system']->getDBObject('congregation', $this->getValue('congregationid'));
			$time = strtotime(preg_replace('/[^0-9]/', '', $cong->getValue('meeting_time')));
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
					<td class="narrow"><?php echo date('H:i', $time); ?></td>
					<td class="narrow center"><?php if ($item['show_in_handout'] != '0') echo $num++; ?></td>
					<td>
						<?php
						$title = $item['runsheet_title_format'];
						$title = str_replace('%title%', $item['title'], $title);
						$title = $this->replaceKeywords($title);
						echo ents($title);
						if ($item['note']) echo '<br /><i><small>'.nl2br(ents($item['note'])).'</small></i>';
						?>
					</td>
					<td class="narrow"><?php echo ents($item['personnel']); ?></td>
				</tr>
				<?php
				$time += $item['length_mins']*60;
			}
			?>
			</tbody>
		<?php
		if ($this->getValue('comments')) {
			?>
			<tfoot>
				<tr>
					<td colspan="4" class="run-sheet-comments"><?php $this->printFieldValue('comments'); ?></td>
				</tr>
			</tfoot>
			<?php
		}
		?>
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
			if ($i['show_in_handout'] == '0') continue;
			?>
			<h4 id="item<?php echo $k; ?>">
				<?php
				echo ($num++).'. ';
				$title = $i['handout_title_format'];
				$title = str_replace('%title%', $i['title'], $title);
				$title = $this->replaceKeywords($title);
				echo ents($title);
				?>
			</h4>
			<?php
			if ($i['show_in_handout'] == 'full') {
				echo $i['content_html'];
				?>
				<small>
					<?php echo nl2br(ents($i['credits'])); ?>
				</small>
				<?php
			}
		}
	}

	public function printRunSheetPersonnelFlexi()
	{
		$rosterViews = Roster_View::getForRunSheet($this->getValue('congregationid'));
		if ($rosterViews) {
			ob_start();
			$emails = $personids = Array();
			foreach ($rosterViews as $view) {
				$asns = $view->printSingleViewFlexi($this);
				foreach ($asns as $role => $roleAsns) {
					foreach ($roleAsns as $asn) {
						if (!empty($asn['personid'])) $personids[] = $asn['personid'];
						if (!empty($asn['email'])) $emails[] = $asn['email'];
					}
				}
			}
			$assignments_output = ob_get_clean();
			$email_href = get_email_href($GLOBALS['user_system']->getCurrentUser('email'), NULL, array_unique($emails), $this->toString());
			if (SMS_Sender::canSend()) {
				SMS_Sender::printModal();
			}

			?>
			<div class="row-fluid">
			<div id="service-personnel" class="span12 clearfix">
				<h3>
					<span class="pull-right"><small>
					<?php
					if (count($rosterViews) == 1) {
						?>
						<a href="?view=rosters__edit_roster_assignments&viewid=<?php echo key($rosterViews); ?>&start_date=<?php echo $this->getValue('date'); ?>&end_date=<?php echo $this->getValue('date'); ?>"><i class="icon-wrench"></i>Edit</a>
						&nbsp;
						<?php
					}
					?>
						<a href="<?php echo $email_href; ?>"><i class="icon-email">@</i>Email</a>
					<?php
					if (SMS_Sender::canSend()) {
						?>
						&nbsp;
						<a href="#send-sms-modal" data-personid="<?php echo implode(',', array_unique($personids)); ?>" data-toggle="sms-modal" data-name="Personnel for <?php echo ents($this->toString());?>"><i class="icon-envelope"></i>SMS</a>
						<?php
					}
					?>
					</small></span>
					Personnel
				</h3>
				<?php
				echo $assignments_output;
				?>
			</div>
			</div>
			<?php
		}
	}

	public function printRunSheetPersonnelTable()
	{
		$rosterViews = Roster_View::getForRunSheet($this->getValue('congregationid'));
		if ($rosterViews) {
			?>
			<?php
			foreach ($rosterViews as $view) {
				$view->printSingleViewTable($this);
			}
			echo '<br />';
		}
	}

	/**
	 * Calculate the meeting date/time.
	 *
	 * @param int $meetingDate The date of the meeting.
	 * @param string $meetingTime The meeting time is like "1000" for 10:00.
	 * @return int The meeting date/time.
	 */
	public static function getMeetingDateTime($meetingDate, $meetingTime) {
		$dateString = date('Y-m-d', $meetingDate);
		if ($meetingTime != NULL && preg_match('/(\\d\\d)(\\d\\d)/', $meetingTime, $matches)) {
			$dateString .= ' '.$matches[1].':'.$matches[2];
		}
		return strtotime($dateString);
	}
}
