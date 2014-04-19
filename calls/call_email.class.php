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
			$recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!email' => '', '!status' => 'archived'), 'AND');
			$blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'email' => '', '!status' => 'archived'), 'AND');
			$archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'status' => 'archived'), 'AND');
		} else if (!empty($_REQUEST['groupid'])) {
			$group = $GLOBALS['system']->getDBObject('person_group', (int)$_REQUEST['groupid']);
			$personids = array_keys($group->getMembers());
			$recips = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, '!email' => '', '!status' => 'archived'), 'AND');
			$blanks = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'email' => '', '!status' => 'archived'), 'AND');
			$archived = $GLOBALS['system']->getDBObjectData('person', Array('(id' => $personids, 'status' => 'archived'), 'AND');
		} else if (!empty($_REQUEST['roster_view'])) {
			$view = $GLOBALS['system']->getDBObject('roster_view', (int)$_REQUEST['roster_view']);
			$recips = $view->getAssignees($_REQUEST['start_date'], $_REQUEST['end_date']);
			// TODO: find email-less people here?
		} else {
			switch (array_get($_REQUEST, 'email_type')) {
				case 'family':
					$GLOBALS['system']->includeDBClass('family');
					$families = Family::getFamilyDataByMemberIDs($_POST['personid']);
					$recips = $GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), '!email' => '', '!status' => 'archived'), 'AND');
					$blanks =$GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), 'email' => '', '!status' => 'archived'), 'AND');
					$archived = $GLOBALS['system']->getDBObjectData('person', Array('age_bracket' => '0', '(familyid' => array_keys($families), 'status' => 'archived'), 'AND');
					break;
				case 'person':
				default:
					$recips = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid'], '!email' => '', '!status' => 'archived'), 'AND');
					$blanks = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid'], 'email' => '', '!status' => 'archived'), 'AND');
					$archived = $GLOBALS['system']->getDBObjectData('person', Array('id' => $_POST['personid'], 'status' => 'archived'), 'AND');
					$GLOBALS['system']->includeDBClass('person');
					break;
			}
		}
		
		$emails = array();
		foreach ($recips as $recip) {
			$emails[$recip['email']] = 1;
		}
		$emails = array_keys($emails);

		if (!empty($_REQUEST['show_modal'])) {
			$this->printModal($emails, $archived, $blanks);
		} else if (!empty($_REQUEST['print_popup'])) {
			$this->printPopup($emails, $archived, $blanks);
		} else if ((count($emails) > EMAIL_CHUNK_SIZE) || !empty($blanks)) {
			$this->launchPopupFromHiddenIframe($blanks);
		} else if (count($emails) > 0) {
			$my_email = $GLOBALS['user_system']->getCurrentUser('email');
			$public = array_get($_REQUEST, 'method') == 'public';	
			$to = implode(',', $emails);
			if (!$public) $to = $my_email.'?bcc='.$to;
			?>
			<a id="mailto" href="mailto:<?php echo $to; ?>" target="_parent">Send email</a>
			<script>document.getElementById('mailto').click();</script>
			<?php
		} else {
			?>
			<script>alert('None of the selected persons have email addresses in the system');</script>
			<?php
		}
	}

	private function getPrivateTo($emails) {
		$my_email = $GLOBALS['user_system']->getCurrentUser('email');
		return $my_email.'?bcc='.implode(',', array_diff($emails, Array($my_email)));
	}

	private function printModal($emails, $archived, $blanks) {
		$my_email = $GLOBALS['user_system']->getCurrentUser('email');
		$chunks = array_chunk($emails, EMAIL_CHUNK_SIZE);
		$this->printArchivedWarning($archived);
		if (count($chunks) == 1) {
			?>
			<p>
			<a href="mailto:<?php echo $this->getPrivateTo($emails); ?>" class="btn btn-primary">Email privately</a>
			&nbsp;
			<a href="mailto:<?php echo implode(',', $emails); ?>" class="btn btn-danger" title="WARNING: this will let all group membes see each other's addresses, and should be used with care">Email publicly</a>
			</p>
			<?php
		} else {
			?>
			<p style="line-height: 50px">
			<?php
			foreach ($chunks as $i => $chunk) {
				?>
				<a href="mailto:<?php echo $this->getPrivateTo($chunk); ?>" class="btn"  onclick="this.style.textDecoration='line-through'">Email Batch #<?php echo $i; ?></a>
				<?php
			}
			?>
			</p>
			<?php
		}	
		$this->printBlanks($blanks);

	}

	private function printPopup($emails, $archived, $blanks) {
		$public = array_get($_REQUEST, 'method') == 'public';
		?>
		<html>
			<head>
				<title>Jethro PMM - selected emails</title>
				<?php include 'templates/head.template.php'; ?>

			</head>
			<body>
				<div id="body">
				<?php

				$chunks = array_chunk($emails, EMAIL_CHUNK_SIZE);
				if (count($chunks) == 1) {
					$this->printArchivedWarning($archived);
					$to = $public ? implode(',', $emails) : $this->getPrivateTo($emails);
					?>
					<br />
					<div class="align-center"><a class="btn btn-primary" href="mailto:<?php echo $to ?>">Email selected persons now</a></div>
					<?php
				} else {
					?>
					<h1>Send Email</h1>
					<?php $this->printArchivedWarning($archived); ?>
					<p style="line-height: 50px">
					<?php
					foreach ($chunks as $i => $chunk) {
						$to = $public ? implode(',', $chunk) : $this->getPrivateTo($chunk);
						?>
						<a class="btn" href="mailto:<?php echo $to; ?>" onclick="this.style.textDecoration='line-through'">Email Batch #<?php echo ($i+1); ?></a>&nbsp;&nbsp;
						<?php
					}
					?>
					</p>
					<?php
				}
				$this->printBlanks($blanks);
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
						var w = <?php echo empty($blanks) ? '300' : 'Math.round(screen.width * 0.6, 10)'; ?>;
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
			?>
			<br />
			<h4>Note: The following recipients have no email address:</h4>
			<script>
				var targetWin = window.opener.parent;
				$(document).ready(function() {
					$('table.person-list td a').click(function() {
						if (targetWin) {
							targetWin.document.location.href = this.href;
							return false;
						}
					});
				});
			</script>
			<?php
			$persons = $blanks;
			$special_fields = Array();
			include 'templates/person_list.template.php';
		}
	}

	private function printArchivedWarning(&$archived) {
			if (!empty($archived)) {
				print_message("Warning: ".count($archived).' of the intended recipients are archived and will not be sent this email', 'error');
			}
	}
}


?>
