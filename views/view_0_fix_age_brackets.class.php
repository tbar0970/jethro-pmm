<?php
require_once JETHRO_ROOT.'/upgrades/upgradefixes/2.5.1_fix_agebrackets/AgeBracketChangesFixer.php';

/**
 * Shows bulk edits that incorrectly set 'Age Bracket' (https://github.com/tbar0970/jethro-pmm/issues/108), and fixes the changes that the user indicates are incorrect.
 */
class View__Fix_Age_Brackets extends View
{
	private $_stage = 'begin';
	/**
	 * @var BadChangeGroup[]
	 */
	private array $_affectedpersons;
	/**
	 * @var BadChangeFixInfo[]
	 */
	private $_fixresults;

	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function getTitle()
	{
		return 'Fix Broken Age Brackets';
	}

	function printView()
	{
		switch ($this->_stage) {
			case 'begin':
				$this->_printBeginView();
				break;

			case 'done':
				$this->_printDoneView();
				break;
		}
	}

	function processView()
	{
		if (!empty($_REQUEST['done'])) {
			$this->_stage = 'done';
		} else if (!empty($_POST['confirm_fix'])) {
			$this->_processFix();
		} else {
			$this->_findAffectedPersons();
		}
	}


	private function _printBeginView()
	{
		$text = 'A <a href="https://github.com/tbar0970/jethro-pmm/issues/1086">bug in Jethro 2.35.1</a> meant that bulk-updating persons sometimes resulted in their age bracket being reset to '.AgeBracketChangesFixer::getDefaultAgeBracketLabel()."'. This page lets you check for affected records and reset 'Age Bracket' to the corrected value for affected persons.
		Note:  If a change affected just one person, we can't tell if it was a bulk edit, or a regular edit where Age Bracket was deliberately changed. Please review the single-person changes carefully. The multi-person changes are more likely to be incorrect, and these ticked by default.";
		$text = '<p class="text">'.str_replace("\n", '</p><p class="text">', $text);
		print_message($text, 'info', true);
		?>
        <form method="post" enctype="multipart/form-data">
            <table>
				<?php
				/** @var BadChangeGroup $changegroup */
				foreach ($this->_affectedpersons as $changegroup) {
					?>
                    <tr>
                        <td></td>
                        <td colspan="6"><?= $changegroup->toString() ?>
							<?= ($changegroup->isBulkEdit() ? "" : "<br><small>Warning: this may have been deliberate, and not be from a bulk change!</small>") ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="vertical-align: center; min-width: 2em">
                            <input type="checkbox" name='time[]'
                                   value="<?= $changegroup->getId() ?>"
							<?= ($changegroup->isBulkEdit()  ? "checked" : "unchecked") ?>
                        </td>
                        <td>
                            <table class="table table-striped table-condensed table-hover table-min-width clickable-rows query-results">
                                <thead>
                                <tr>
                                    <th>Person</th>
                                    <th>Previous Age Bracket</th>
                                    <th>Current Age Bracket</th>
                                    <th>Related Changes</th>
                                    <th>Proposed Fix</th>
                                </tr>
                                </thead>
                                <tbody>
								<?php
								foreach ($changegroup->getBadChanges() as $change) {
									?>
                                    <tr>
                                        <td>
                                            <a href="/?view=persons&personid=<?= $change->getPersonid() ?>"><?= $change->getPersonName() ?></a>
                                        </td>
                                        <td><?= $change->getOldagebracket() ?></td>
                                        <td><?= $change->getNewagebracket() ?></td>
                                        <td><?= array_reduce($change->getHistlines(), fn($s, $line) => $s .= $line.'</br>', '') ?></td>
                                        <td><?= $change->getNewagebracket() ?> → <?= $change->getOldagebracket() ?></td>
                                    </tr>
									<?php
								}
								?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
				<?php }
				if (count($this->_affectedpersons) > 0) { ?>
                    <tr>
                        <td>&nbsp;</td>
                        <td class="compulsory"><input type="submit" name="confirm_fix" class="btn"
                                                      value="Fix all ticked items"/></td>
                    </tr>
				<?php } else { ?>
                    <tr>
                        <td>No affected persons!</td>
                    </tr>
				<?php } ?>
            </table>
        </form>
		<?php
	}

	private
	function _printDoneView()
	{
		?><h3>Fix completed</h3>
		<?php
		if (!is_null($this->_fixresults)) {
			echo '<table class="table table-striped table-condensed table-hover table-min-width clickable-rows query-results">';
			echo '<tr><th>Person</th><th>Age Bracket</th></tr>';
			foreach ($this->_fixresults as $fixresult) {
				echo "<tr>";
				echo "<td><a href='/?view=persons&personid=".$fixresult->getPersonid()."'>".$fixresult->getPersonName()."</a></td>";
				echo "<td>".$fixresult->getAgebracket();
				echo "</td>";
				echo "</tr>";
			}
			echo "</table>";
		} else {
			echo "No fix results??";
		}
	}

	function _processFix()
	{
		$GLOBALS['system']->doTransaction('BEGIN');
		$changes = [];
		if (array_key_exists('time', $_REQUEST)) { // 'time' is an array of timestamps of changes deemed to be in error. Null if none were picked.
			foreach (AgeBracketChangesFixer::getBadChangeGroups() as $id => $change) {
				if (in_array($id, $_REQUEST['time'])) {
					$changes[$id] = $change;
				}
			}
	    }
		$this->_fixresults = AgeBracketChangesFixer::fix($changes);
		Config_Manager::deleteSetting('NEEDS_1086_CHECK');
		$GLOBALS['system']->doTransaction('COMMIT');
		$this->_stage = 'done';
	}

	/**
	 * @return stdClass
	 */

	private
	function _getAffectedPersons()
	{
	}

	private
	function _findAffectedPersons()
	{
		$this->_affectedpersons = AgeBracketChangesFixer::getBadChangeGroups();
	}

	function printInvitation()
	{
		$this->_findAffectedPersons();
		if ($this->_affectedpersons) {
			print_message('Some records in your system need review for accidental changes. <a href="?view=_fix_age_brackets">Click here for details.</a>', 'error', TRUE);
		} else {
			Config_Manager::deleteSetting('NEEDS_1086_CHECK');
		}
	}
}