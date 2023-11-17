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
			redirect('rosters__edit_roster_assignments', Array('editing' => NULL));
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
		} else if (!empty($_SESSION['roster_view_id'])) {
			$this->_view = $GLOBALS['system']->getDBObject('roster_view', (int)$_SESSION['roster_view_id']);
		}
		if (empty($_REQUEST['goback'])) {
			$_SESSION['roster_start_date'] = $this->_start_date;
			$_SESSION['roster_end_date'] = $this->_end_date;
			if ($this->_view) $_SESSION['roster_view_id'] = $this->_view->id;
		}
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
			<form method="get" class="form-horizontal well well-small no-print">
			<input type="hidden" name="view" value="<?php echo ents($_REQUEST['view']); ?>" />
			<table>
				<tr>
					<th class="valign-middle">Roster view</th>
					<td>
						<?php
						print_widget('viewid', Array('type' => 'reference', 'references' => 'roster_view', 'order_by' => 'name'), $viewid);
						?>
					</td>
					<td></td>
				</tr>
				<tr>
					<th class="valign-middle right">between</th>
					<td><?php print_widget('start_date', Array('type' => 'date'), $this->_start_date); ?></td>
					<td></td>
				</tr>
				<tr>
					<th class="valign-middle right">and</th>
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
				if (!$this->_editing) {
					echo '<div class="no-print margin-below">';
					echo '<a target="print-roster" class="med-newwin nowrap" href="'.BASE_URL.'?call=display_roster&viewid='.$viewid.'&start_date='.$this->_start_date.'&end_date='.$this->_end_date.'"><i class="icon-print"></i>Show printable version</a> &nbsp; ';
					echo '<a href="?call=email&print_modal=1&roster_view='.$viewid.'&start_date='.$this->_start_date.'&end_date='.$this->_end_date.'" target="_append" class="nowrap"><i class="icon-email">@</i>Email all assignees</a> &nbsp; ';

					if (SMS_Sender::canSend()) {
						$assignees = $this->_view->getAssignees($this->_start_date, $this->_end_date);
						echo '<a  class="nowrap" href="#send-sms-modal" data-personid="'.implode(',', array_keys($assignees)).'" data-toggle="sms-modal" data-name="'.count($assignees).' assignees in '.ents($this->_view->getValue('name')).'"><i class="icon-envelope"></i>SMS all assignees</a> &nbsp; ' ;

					}
					if ($this->_view->getValue('visibility') != '') {
						echo '<a target="_rosterview"  class="nowrap" href="'.BASE_URL.'members/?view=rosters&roster_view='.$this->_view->id.'"><i class="icon-share"></i>View in members area</a> &nbsp; ';
					}
					if ($this->_view->getValue('visibility') == 'public') {
						$url = BASE_URL.'public/?view=display_roster&roster_view='.$this->_view->id;
						if (PUBLIC_ROSTER_SECRET) $url .= '&secret='.PUBLIC_ROSTER_SECRET;
						echo '<a  class="nowrap" target="_rosterview" href="'.$url.'"><i class="icon-share"></i>View in public site</a> &nbsp; ';
					}
					echo '<a href="?call=roster_csv&roster_view='.$viewid.'&start_date='.$this->_start_date.'&end_date='.$this->_end_date.'" ><i class="icon-download-alt"></i>Download CSV</a> &nbsp; ';

					?>
					<a href="#merge-modal" data-toggle="modal" data-target="#merge-modal" ><i class="icon-download-alt"></i>Merge a document...</a>

					<div id="merge-modal" class="modal sms-modal hide fade" role="dialog" aria-hidden="true">
						<form onsubmit="$('#merge-modal').modal('hide')" action="?call=document_merge_rosters" method="post" enctype="multipart/form-data">
						<div class="modal-header">
							<h4>Mail merge a document from this roster</h4>
						</div>
						<div class="modal-body">
							<?php
							include_once 'calls/call_document_merge_rosters.class.php';
							$templates = @glob(Call_Document_Merge_Rosters::getSavedTemplatesDir().'/*.*');
							?>
							<div class="control-group">
							<label class="control-label"><?php echo _('Template Document')?></label>
							<div class="controls">
							<?php
							if (!empty($templates)) {
								$tOptions = Array('' => '', '__NEW__' => 'Upload a new file...');
								foreach ($templates as $t) $tOptions[basename($t)] = basename($t);
								print_widget('source_doc_select', Array('type' => 'select', 'options' => $tOptions, 'class' => 'merge-template'), '');
							?>
							<div id="merge-template-upload" class="indent-left" style="display:none">
								<input type="file" name="source_document" />
								<label class="checkbox"><input type="checkbox" name="save_template" value="1" />Save template for next time</label>
							</div>
							<?php
						} else {
							?>
							<input class="compulsory" type="file" name="source_document" />
							<label class="checkbox"><input type="checkbox" name="save_template" value="1" />Save template for next time</label>
							<?php
						}
						?>
						</div>
					</div>
					<div class="control-group">
						<div class="controls">
							<p class="help-inline">
							<a target="roster-merge-help" class="med-newwin" href="<?php echo BASE_URL; ?>index.php?call=document_merge_help"><i class="icon-help"></i>Help and examples</a>
						&nbsp;
							<button type="submit" class="btn btn-mini muted" name="preview_keywords" onclick="$('input[name=source_document]').removeClass('compulsory')" data-set-form-target="_blank" data-set-form-action="<?php echo BASE_URL; ?>index.php?call=document_merge_rosters">Preview all tags</button>
							</p>
							
						</div>
					</div>
					<div class="modal-footer">
						<input type="hidden" name="roster_view" value="<?php echo $viewid; ?>" />
						<?php
							print_hidden_field('roster_view_name', $this->_view->getValue('name'));
							print_hidden_field('start_date', $this->_start_date);
							print_hidden_field('end_date', $this->_end_date);
							?>
							<input type="submit" class="btn" value="Go" />
							<input type="button" class="btn" data-dismiss="modal" value="Cancel" />
						</div>
						</form>
					</div>
					</div>
				<?php
			}
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
