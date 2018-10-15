<?php
include_once 'include/db_object.class.php';
class Abstract_Note extends DB_Object
{
	protected $_load_permission_level = PERM_VIEWNOTE;
	protected $_save_permission_level = PERM_EDITNOTE;

	protected static function _getFields()
	{
		$fields = Array(
			'subject'		=> Array(
								'type'		=> 'text',
								'width'		=> 40,
								'maxlength'	=> 256,
								'initial_cap'	=> true,
								'allow_empty'	=> false,
							   ),
			'details'		=> Array(
								'type'		=> 'text',
								'width'		=> 50,
								'height'	=> 5,
								'initial_cap'	=> true,
							   ),
			'status'		=> Array(
								'type'		=> 'select',
								'options'	=> Array(
												'no_action'	=> 'No Action Required',
												'pending'	=> 'Requires Action',
												'failed'	=> 'Failed',
												'complete'	=> 'Complete',
											   ),
								'default'	=> 'no_action',
								'class'		=> 'note-status',
								'allow_empty'	=> false,
								'label'		=> 'Status',
							   ),
			'status_last_changed' => Array(
									'type'				=> 'datetime',
									'show_in_summary'	=> false,
									'allow_empty'		=> TRUE,
									'editable'			=> false,
									'default'			=> NULL,
								   ),
			'assignee'		=> Array(
								'type'			=> 'reference',
								'references'	=> 'staff_member',
								'default'		=> $GLOBALS['user_system']->getCurrentUser('id'),
								'note'			=> 'Choose the user responsible for acting on this note',
								'allow_empty'	=> true,
								'filter'		=> function($x) {return $x->getValue("active") && (($x->getValue("permissions") & PERM_EDITNOTE) == PERM_EDITNOTE);},
							   ),
			'assignee_last_changed' => Array(
									'type'				=> 'datetime',
									'show_in_summary'	=> false,
									'allow_empty'		=> TRUE,
									'editable'			=> false,
									'default'			=> NULL,
								   ),
			'action_date'	=> Array(
								'type'			=> 'date',
								'note'			=> 'This note will appear in the assignee\'s "to-do" list from this date onwards',
								'allow_empty'	=> false,
								'default'		=> date('Y-m-d'),
							   ),
			'creator'		=> Array(
								'type'			=> 'int',
								'editable'		=> false,
								'references'	=> 'person',
							   ),
			'created'		=> Array(
								'type'			=> 'timestamp',
								'readonly'		=> true,
							   ),
			'editor'		=> Array(
								'type'			=> 'int',
								'editable'		=> false,
								'references'	=> 'person',
								'visible'		=> false,
								'default'		=> NULL,
								'allow_empty'   => TRUE,
							   ),
			'edited'		=> Array(
								'type'			=> 'datetime',
								'visible'		=> false,
								'editable'		=> false,
								'allow_empty'	=> true,
								'default'		=> NULL,
							   ),
			'history'		=> Array(
								'type'			=> 'serialise',
								'editable'		=> false,
								'show_in_summary'	=> false,
							   ),
		);
		return $fields;
	}


	function toString()
	{
		$creator = $GLOBALS['system']->getDBObject('person', $this->values['creator']);
		return $this->values['subject'].' ('.$creator->toString().', '.format_date( strtotime($this->values['created'])).')';
	}

	function printFieldInterface($name, $prefix='')
	{
		if ($this->id && in_array($name, Array('subject', 'details'))) {
			if ($GLOBALS['user_system']->getCurrentUser('id') != $this->values['creator']) {
				$this->printFieldValue($name, $prefix);
				return;
			}
		}
		if ($name == 'status') echo '<div class="note-status">';
		parent::printFieldInterface($name, $prefix);
		if ($name == 'status') echo '</div>';
		if ($name == 'action_date') {
			?>
			<span class="nowrap smallprint">
			<button style="font-size: 90%" type="button" class="btn btn-mini" onclick="setDateField('<?php echo $prefix; ?>action_date', '<?php echo date('Y-m-d', strtotime('+1 week')); ?>')">1 week from now</button>
			<button style="font-size: 90%" type="button" class="btn btn-mini" onclick="setDateField('<?php echo $prefix; ?>action_date', '<?php echo date('Y-m-d', strtotime('+1 month')); ?>')">1 month from now</button>
			</span >
			<?php
		}
	}

	function printFieldValue($name, $value=NULL)
	{
		if (is_null($value)) $value = $this->values[$name];
		if (in_array($name, Array('assignee', 'action_date'))) {
			if ($value == 'no_action') {
				echo 'N/A';
				return;
			}
		}
		if ($name == 'subject') {
			echo '<strong>'.$value.'</strong>';
			return;
		}
		return parent::printFieldValue($name, $value);
	}

	function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] = '('.$res['from'].') LEFT OUTER JOIN person creator ON abstract_note.creator = creator.id';
		$res['from'] = '('.$res['from'].') LEFT OUTER JOIN person assignee ON abstract_note.assignee = assignee.id';
		$res['select'][] = 'creator.first_name as creator_fn';
		$res['select'][] = 'creator.last_name as creator_ln';
		$res['select'][] = 'assignee.first_name as assignee_fn';
		$res['select'][] = 'assignee.last_name as assignee_ln';
		return $res;

	}



	function getInstancesData($params, $logic='OR', $order='')
	{
		$res = parent::getInstancesData($params, $logic, $order);

		// Get the comments to go with them
		if (!empty($res)) {
			$sql = 'SELECT c.noteid, c.*, p.first_name as creator_fn, p.last_name as creator_ln
					FROM note_comment c JOIN person p on c.creator = p.id
					WHERE noteid IN ('.implode(', ', array_keys($res)).')
					ORDER BY noteid, created';
			$db =& $GLOBALS['db'];
			$comments = $db->queryAll($sql, null, null, true, false, true);
			foreach ($res as $i => $v) {
				$res[$i]['comments'] = array_get($comments, $i, Array());
			}
		}

		return $res;

	}

	function printStatusSummary()
	{
		if ($this->values['status'] == 'pending') {
			if ($this->values['action_date'] <= date('Y-m-d')) {
				echo '<strong>';
				$this->printFieldValue('status');
				echo '</strong>';
			} else {
				echo 'Scheduled for action on '.str_replace(' ', '&nbsp;', format_date($this->values['action_date']));
			}
		} else {
			$this->printFieldValue('status');
		}
	}

	/**
	 * @return boolean	True if the current user is allowed to delete this note
	 */
	public function canBeDeleted() {
		return ($this->getValue('status') !== 'pending')
			&& ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN));
	}

	/**
	 * @return boolean	True if the current user is allowed to edit the original content of this note
	 */
	public function canEditOriginal() {
		return $GLOBALS['user_system']->havePerm(PERM_SYSADMIN)
				|| ($GLOBALS['user_system']->getCurrentUser('id') == $this->getValue('creator'));
	}

	function delete()
	{
		if (!$this->canBeDeleted()) {
			trigger_error("This note can not be deleted", E_USER_WARNING);
			return FALSE;
		}
		if (!parent::delete()) return FALSE;
		$db =& $GLOBALS['db'];
		$sql = 'DELETE FROM note_comment WHERE noteid = '.$db->quote($this->id);
		$res = $db->query($sql);
		return TRUE;
	}

	function save()
	{
		// If the subject or details is updated, set the 'editor' and 'edited' fields
		if (isset($this->_old_values['subject'])
			|| isset($this->_old_values['details'])
		) {
			$this->setValue('edited', 'CURRENT_TIMESTAMP');
			$this->setValue('editor', $GLOBALS['user_system']->getCurrentUser('id'));
		}
		return parent::save();
	}


	function printUpdateForm()
	{
		?>
		<form method="post" id="update-note" class="form-horizontal"  data-lock-length="<?php echo db_object::getLockLength() ?>">
			<input type="hidden" name="update_note_submitted" value="1" />
			<div class="control-group">
				<label class="control-label">Comment</label>
				<div class="controls">
					<?php
					$GLOBALS['system']->includeDBClass('note_comment');
					$comment = new Note_Comment();
					$comment->printFieldInterface('contents');
					?>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Status</label>
				<div class="controls">
					<?php $this->printFieldInterface('status'); ?>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Assignee</label>
				<div class="controls">
					<?php $this->printFieldInterface('assignee'); ?>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Action Date</label>
				<div class="controls">
					<?php echo $this->printFieldInterface('action_date'); ?>
					<div class="help-inline"><?php echo ents($this->fields['action_date']['note']); ?></div>
				</div>
			</div>
			<div class="control-group">
				<div class="controls">
					<input type="submit" class="btn" value="Save" />
					<input type="button" value="Cancel" class="btn back" />
				<?php
				if ($this->canBeDeleted()) {
					?>
					<input type="submit" name="delete_note" data-confirm="Notes are designed to accumulate as a historical record, and should usually only be deleted to correct a mistake.  Are you sure you want to delete this note?" class="pull-right btn" value="Delete this note" />
					<?php
				}
				?>
				</div>
			</div>
		</form>
		<?php
	}

	/**
	 * Get notifications that should be sent by email advising people about recently-assigned notes
	 * @param int	 $minutes	Number of minutes in the past to look.
	 * @return array
	 */
	public static function getNotifications($minutes)
	{
		// get notes recently marked for action, notes recently assigned to a new person
		// and notes which have just reached their action date in the last $minutes minutes
		// We operate 10 seconds in the past to allow time for things to setting down
		// We ignore notes that a user has assigned to themselves
		$between = 'BETWEEN (NOW() - INTERVAL '.(int)(($minutes*60)+10).' SECOND) AND (NOW() - INTERVAL 10 SECOND)';
		$SQL = 'SELECT p.first_name, p.last_name, p.email,
					(SELECT count(*)
						FROM abstract_note an
						WHERE status = "pending"
						AND action_date <= DATE(NOW())
						AND assignee = p.id) AS total_notes,
					COUNT(DISTINCT nn.id) as new_notes,
					GROUP_CONCAT(nn.id) as new_note_ids
				FROM person p
				JOIN abstract_note nn ON nn.assignee = p.id
										AND nn.status = "pending"
										AND nn.action_date <= DATE(NOW())
										AND ((
											/* recently assigned by others */
											COALESCE(nn.editor, nn.creator) <> nn.assignee
											AND ((nn.status_last_changed '.$between.')
												OR (nn.assignee_last_changed '.$between.')
												OR (nn.created '.$between.'))
										) OR (
											/* recently reached their action date, regardless of assigner */
											DATE(NOW()) = nn.action_date AND DATE(NOW() - INTERVAL '.(int)$minutes.' MINUTE) <> nn.action_date
										))
				WHERE email <> ""
				GROUP BY p.id';
		return $GLOBALS['db']->queryAll($SQL);
	}

	/**
	 * Clean up any orphaned records that are not references by a person or family note
	 * @return boolean
	 */
	public static function cleanupInstances()
	{
		$SQL = 'DELETE FROM abstract_note WHERE id NOT IN (
					SELECT id FROM person_note
					UNION
					SELECT id from family_note
				)';
		return $GLOBALS['db']->exec($SQL);
	}
}
