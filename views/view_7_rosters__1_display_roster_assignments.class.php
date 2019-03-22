<?php
class View_Rosters__Display_Roster_Assignments extends View
{
	var $_start_date = '';
	var $_end_date = '';
	var $_view = null;
	var $_editing = FALSE;

	static function getMenuPermissionLevel()
	{
		return PERM_VIEWROSTER;
	}

	function processView()
	{
		if (!empty($_REQUEST['editing']) && $_REQUEST['view'] == 'rosters__display_roster_assignments') {
			redirect('rosters__edit_roster_assignments');
		}
		$this->_start_date = process_widget('start_date', Array('type' => 'date'));
		if (is_null($this->_start_date)) {
			if (!empty($_SESSION['roster_start_date'])) {
				$this->_start_date = $_SESSION['roster_start_date'];
			} else {
				$this->_start_date = date('Y-m-d');
			}
		}
		$this->_end_date = process_widget('end_date', Array('type' => 'date'));
		if (is_null($this->_end_date)) {
			if (!empty($_SESSION['roster_end_date'])) {
				$this->_end_date = $_SESSION['roster_end_date'];
			} else {
				$this->_end_date = date('Y-m-d', strtotime('+'.ROSTER_WEEKS_DEFAULT.' weeks'));
			}

		}
		if (!empty($_REQUEST['viewid'])) {
			$this->_view = $GLOBALS['system']->getDBObject('roster_view', (int)$_REQUEST['viewid']);
		}
		$_SESSION['roster_start_date'] = $this->_start_date;
		$_SESSION['roster_end_date'] = $this->_end_date;
	}


	function printView()
	{
		$this->_printParams();
		if ($this->_view) {
			$this->_view->printView($this->_start_date, $this->_end_date, $this->_editing);
		}
	}

	function _printParams()
	{
			$views = $GLOBALS['system']->getDBObjectData('roster_view', array());
			if (empty($views)) {
				print_message("You need to set up some roster views before you can display or edit roster assignments", 'failure');
				return;
			}
			$viewid = ($this->_view) ? $this->_view->id : null;
			?>
			<form method="get" class="well well-small no-print">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
			<table>
				<tr>
					<th>Roster view</th>
					<td>
						<?php
						print_widget('viewid', Array('type' => 'reference', 'references' => 'roster_view', 'order_by' => 'name'), $viewid);
						?>
					</td>
					<td></td>
				</tr>
				<tr>
					<th class="right">between</th>
					<td><?php print_widget('start_date', Array('type' => 'date'), $this->_start_date); ?></td>
					<td></td>
				</tr>
				<tr>
					<th class="right">and</th>
					<td>
						<?php print_widget('end_date', Array('type' => 'date'), $this->_end_date); ?> &nbsp;
						<button type="submit" name="viewing" value="1" class="btn">View Assignments</button>
					<?php
					if ($GLOBALS['user_system']->havePerm(PERM_EDITROSTER)) {
						?>
						<button type="submit" name="editing" value="1" class="btn">Edit Assignments</button>
						<?php
					}
					?>
					</td>
				</tr>
			</table>
			</form>
			<?php
			if ($viewid) {
				?>
				<p class="no-print">
				<?php
				if (!$this->_editing) {
					echo '<a href="?call=email&roster_view='.$viewid.'&start_date='.$this->_start_date.'&end_date='.$this->_end_date.'" class="hidden-frame"><i class="icon-email">@</i>Email all assignees</a> &nbsp; ';
					echo '<a target="print-roster" class="med-newwin" href="'.BASE_URL.'?call=display_roster&viewid='.$viewid.'&start_date='.$this->_start_date.'&end_date='.$this->_end_date.'"><i class="icon-print"></i>Show printable version</a> &nbsp; ';
					if ($this->_view->getValue('visibility') != '') {
						echo '<a target="_rosterview" href="'.BASE_URL.'members/?view=rosters&roster_view='.$this->_view->id.'"><i class="icon-share"></i>View in members area</a> &nbsp; ';
					}
					if ($this->_view->getValue('visibility') == 'public') {
						$url = BASE_URL.'public/?view=display_roster&roster_view='.$this->_view->id;
						if (PUBLIC_ROSTER_SECRET) $url .= '&secret='.PUBLIC_ROSTER_SECRET;
						echo '<a target="_rosterview" href="'.$url.'"><i class="icon-share"></i>View in public site</a> &nbsp; ';
					}
					echo '<a href="?call=roster_csv&roster_view='.$viewid.'&start_date='.$this->_start_date.'&end_date='.$this->_end_date.'" ><i class="icon-download-alt"></i>Download CSV</a> &nbsp; ';
					echo '<br />';
				}
				?>
				</p>
				<?php
			}

		}

	function getTitle()
	{
		if ($this->_view) {
			return $this->_view->getValue('name');
		} else {
			return 'Display Roster Assignments';
		}

	}

}
?>
