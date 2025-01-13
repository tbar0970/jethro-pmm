<?php
class Call_email extends Call
{
	function run()
	{
		if (!empty($_REQUEST['print_popup'])) {
			$GLOBALS['system']->initErrorHandler();
		}
		$blanks = $archived = Array();

		if (!empty($_REQUEST['queryid'])) {
			$query = $GLOBALS['system']->getDBObject('person_query', (int)$_REQUEST['queryid']);
			$personids = $query->getResultPersonIDs();
			$recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!email' => '', '!(status' => Person_Status::getArchivedIDs()), 'AND');
			$blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'email' => '', '!(status' => Person_Status::getArchivedIDs()), 'AND');
			$archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '(status' => Person_Status::getArchivedIDs()), 'AND');
		} else if (!empty($_REQUEST['groupid'])) {
			$group = $GLOBALS['system']->getDBObject('person_group', (int)$_REQUEST['groupid']);
			$personids = array_keys($group->getMembers());
			$recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!email' => '', '!(status' => Person_Status::getArchivedIDs()), 'AND');
			$blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'email' => '', '!(status' => Person_Status::getArchivedIDs()), 'AND');
			$archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '(status' => Person_Status::getArchivedIDs()), 'AND');
		} else if (!empty($_REQUEST['roster_view'])) {
			$recips = Array();
			foreach ((array)$_REQUEST['roster_view'] as $viewid) {
				$view = $GLOBALS['system']->getDBObject('roster_view', (int)$viewid);
				$recips += $view->getAssignees($_REQUEST['start_date'], $_REQUEST['end_date']);
			}
		} else {
			if (empty($_REQUEST['personid'])) {
				$recips = $emails = $blanks = $archived = Array();
			} else {
				switch (array_get($_REQUEST, 'email_type')) {
					case 'family':
						$GLOBALS['system']->includeDBClass('family');
						$families = Family::getFamilyDataByMemberIDs($_POST['personid']);
						$recips = $GLOBALS['system']->getDBObjectData('person', Array('(age_bracketid' => Age_Bracket::getAdults(), '(familyid' => array_keys($families), '!email' => '', '!(status' => Person_Status::getArchivedIDs()), 'AND');
						$blanks =$GLOBALS['system']->getDBObjectData('person', Array('(age_bracketid' => Age_Bracket::getAdults(), '(familyid' => array_keys($families), 'email' => '', '!(status' => Person_Status::getArchivedIDs()), 'AND');
						$archived = $GLOBALS['system']->getDBObjectData('person', Array('(age_bracketid' => Age_Bracket::getAdults(), '(familyid' => array_keys($families), '(status' => Person_Status::getArchivedIDs()), 'AND');
						break;
					case 'person':
					default:
						$recips = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid'], '!email' => '', '!(status' => Person_Status::getArchivedIDs()), 'AND');
						$blanks = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid'], 'email' => '', '!(status' => Person_Status::getArchivedIDs()), 'AND');
						$archived = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid'], '(status' => Person_Status::getArchivedIDs()), 'AND');
						$GLOBALS['system']->includeDBClass('person');
						break;
				}
			}
		}

		$emails = array();
		foreach ($recips as $recip) {
			$emails[$recip['email']] = 1;
		}
		$emails = array_keys($emails);

		if (!empty($_REQUEST['print_modal'])) {
			$this->printWholeModal($emails, $archived, $blanks);
		} else if (!empty($_REQUEST['print_popup'])) {
			$this->printPopup($emails, $archived, $blanks);
		} else if ((count($emails) > EMAIL_CHUNK_SIZE) || !empty($blanks)) {
			// We are inside the hidden frame but can't do a direct single link.
			$this->launchPopupFromHiddenIframe($blanks);
		} else if (count($emails) > 0) {
			// We are inside the hidden frame. Open mail client/gmail directly.
			$public = array_get($_REQUEST, 'method') == 'public'; // ie, not BCC
			include 'templates/head.template.php';
			?>
			<a id="mailto" href="<?php echo $this->getHref($emails, $public); ?>" target="_parent" <?php echo email_link_extras(); ?>>Send email</a>
			<script>TBLib.handleMailtoClick.apply(document.getElementById('mailto'));</script>
			<?php
		} else {
			?>
			<script>alert('None of the selected persons have email addresses in the system');</script>
			<?php
		}
	}

	private function getHref($emails, $public) {
		if ($public) {
			$href = get_email_href($emails);
		} else {
			$my_email = $GLOBALS['user_system']->getCurrentUser('email');
			$href = get_email_href($my_email, NULL, array_diff($emails, Array($my_email)));
		}
		return $href;
	}

	private function printWholeModal($emails, $archived, $blanks)
	{
		?>
		<div class="modal fade modal-wide" data-show="true" id="email-modal" role="dialog">
			<div class="modal-header">
				<h4>Email <?php echo count($emails); ?> persons
					<?php //echo _('Email members of '); echo ents($this->_group->getValue('name'));
				?>
			</h4>
			</div>
			<div class="modal-body">
				<?php
				$this->printModalContent($emails, $archived, $blanks);
				?>
			</div>
			<div class="modal-footer">
				<input class="btn" type="button" value="<?php echo _('Close'); ?>" data-dismiss="modal" aria-hidden="true" />
			</div>
		</div>
		<script>
			$('#email-modal').modal('show').attr('id', '');
		</script>
		<?php
	}

	private function printModalContent($emails, $archived, $blanks)
	{
		if (count($emails) == 0) {
			print_message("There are no persons to email", 'error');
		} else if (count($emails) < EMAIL_CHUNK_SIZE) {
			?>
			<p>
			<a href="<?php echo $this->getHref($emails, FALSE); ?>" class="btn btn-primary" <?php echo email_link_extras(); ?>>Email privately</a>
			&nbsp;
			<a href="<?php echo $this->getHref($emails, TRUE); ?>" class="btn btn-danger" <?php echo email_link_extras(); ?> title="WARNING: this will let all group members see each other's addresses, and should be used with care">Email publicly</a>
			</p>
			<?php
		} else {
			$sep = defined('MULTI_EMAIL_SEPARATOR') ? MULTI_EMAIL_SEPARATOR : ',';
			$set = implode($sep, $emails);
			?>
			<form><p>Copy the addresses below and paste into your email client. (There are too many for a link.)<br />Remember it's wise to use BCC for large group emails.</p>
			<p class="input-append">
				<input type="text" id="emails" readonly="readonly" autoselect="autoselect" style="width: 60ex" value="<?php echo ents($set); ?>" />
				<button input class="btn" type="button" data-action="copy" data-target="#emails">Copy</button>
			</p>
			</form>
			<?php
		}
		if (count($archived) || count($blanks)) echo '<hr />';
		$this->printArchivedWarning($archived);
		$this->printBlanks($blanks);
	}

	private function printPopup($emails, $archived, $blanks)
	{
		$public = array_get($_REQUEST, 'method') == 'public';
		?><!DOCTYPE html>
		<html lang="en">
			<head>
				<?php include 'templates/head.template.php'; ?>

			</head>
			<body>
				<div id="body">
					<h1>Send Email</h1>
					<?php
					$this->printModalContent($emails, $archived, $blanks);
					?>
				</div>
			</body>
		</html>
		<?php
	}

	private function launchPopupFromHiddenIframe($blanks) {
			?>
			<html>
				<body>
					<form id="emailpopupform" method="post" action="<?php echo build_url(Array('print_popup'=>1)); ?>" target="emailpopup">
						<?php print_hidden_fields($_POST); ?>
					</form>

					<script>
						var w = Math.round(screen.width * 0.6, 10);
						var h = <?php echo empty($blanks) ? '300' : '450'; ?>;
						var left = Math.round(screen.width - w);
						var top = Math.round((screen.height/2)-(h/2), 10);
						medLinkPopupWindow = window.open('', 'emailpopup', 'height='+h+',width='+w+',top='+top+',left='+left+',resizable=yes,scrollbars=yes');
						if (medLinkPopupWindow && (medLinkPopupWindow.outerHeight)) {
							document.getElementById('emailpopupform').submit();
							try { medLinkPopupWindow.focus(); } catch (e) {}
						} else {
							alert('Jethro tried but could not open a popup window - you probably have a popup blocker enabled.  Please disable your popup blocker for this site, reload the page and try again.');
						}
					</script>
				</body>
			</html>
			<?php
	}

	private function printBlanks($blanks) {
		if (!empty($blanks)) {
			echo "<i>The following ".count($blanks)." persons have no email address, and will not be emailed:</i>";
			$persons = $blanks;
			$special_fields = Array();
			include 'templates/person_list.template.php';
		}
	}

	private function printArchivedWarning(&$archived) {
			if (!empty($archived)) {
				print_message("Note: ".count($archived).' of the intended recipients are archived and will not be emailed.', 'warning');
			}
	}
}