<?php
use \DrewM\MailChimp\MailChimp;
require_once 'vendor/autoload.php';

class View__Send_MC_Campaign extends View
{
	private $_report = NULL;
	private $_from_name = '';
	private $_from_address = '';
	private $_mc = NULL;
	private $_sent_campaign_id = '';

	static function getMenuPermissionLevel()
	{
		return PERM_SENDSMS;
	}

	function processView()
	{
		$this->_report = new Person_Query((int)$_REQUEST['reportid']);
		if (empty($this->_report)) {
			trigger_error("Report not found");
			exit;
		}
		if (!strlen(ifdef('MAILCHIMP_API_KEY'))) {
			trigger_error("Mailchimp API key needs to be set up in system config first");
			exit;
		}
		$this->_mc = new MailChimp(MAILCHIMP_API_KEY);

		$us = $GLOBALS['user_system'];
		$this->_from_name = $us->getCurrentUser('first_name').' '.$us->getCurrentUser('last_name');
		$this->_from_address = $us->getCurrentUser('email');

		if (!empty($_POST['subject']) && !empty($_POST['message'])) {
			$html = $_POST['message'];

			$attachment_error = FALSE;
			$attachments = Array();
			if (!empty($_FILES['attachment'])) {
				foreach ($_FILES['attachment']['name'] as $i => $name) {
					if (!strlen($name)) continue;
					if ($_FILES['attachment']['size'][$i] == 0) {
						add_message("Attachment ".ents($name)." was empty. Campaign not sent.", 'error');
						$attachment_error = TRUE;
						continue;
					}
					if ($_FILES['attachment']['size'][$i] > 1024*1024*5) {
						add_message("Attachment ".ents($name)." was too large (max 5MB).  Campaign not sent.", 'error');
						$attachment_error = TRUE;
						continue;
					}
					if ($_FILES['attachment']['error'][$i]) {
						add_message("Error (code ".$_FILES['attachment']['error'].") attaching ".ents($name).". Campaign not sent.", 'error');
						$attachment_error = TRUE;
						continue;
					}
					$attachments[$name] = $_FILES['attachment']['tmp_name'][$i];
				}
			}
			if ($attachment_error) return; // Do not send.

			$zip = new ZipArchive();
			$zip_name = str_replace('.tmp', '', tempnam(sys_get_temp_dir(), 'mailchimp_content-zip'));
			if (!$zip->open($zip_name, ZipArchive::CREATE)) {
				trigger_error("Could not create zip archive to submit to mailchimp", E_USER_ERROR);
				exit;
			}
			foreach ($attachments as $name => $tmpname) {
				$name = preg_replace('/[^A-Za-z0-9.+-_]/', '', str_replace(' ', '_', trim($name)));
				$zip->addFile($tmpname, $name);
				$html .= '<br /><b>Attachment: <a href="'.ents($name).'">'.ents($name).'</a>';
			}
			$zip->addFromString('message.html', $html);
			$zip->close();

			// Let's send us a campaign!
			$postData = Array(
				'type' => 'regular',
				'recipients' => Array('list_id' => $this->_report->getValue('mailchimp_list_id')),
				'settings' => Array(
					'subject_line' => $_POST['subject'],
					'title' => $_POST['subject'],
					'from_name' => $this->_from_name,
					'reply_to' => $this->_from_address,
	 				/*'template_id' => (int)$_POST['templateid'],*/
				),
			);
			$postRes = $this->_mc->post('/campaigns', $postData);
			if (!$this->_mc->success()) {
				trigger_error("Mailchimp error: ".$this->_mc->getLastError());
				return;
			}
			$campaignID = $postRes['id'];
			if (empty($campaignID)) {
				trigger_error("Failed to create campaign");
				exit;
			}
			$putContent = Array(
				/*'html' => $html,*/
				/*'template' => Array('id' => (int)$_POST['templateid']),*/
				'archive' => Array(
					'archive_content' => base64_encode(file_get_contents($zip_name)),
					'archive_type' => 'zip'
				)
			);

			$timeout = 30; // sometimes takes a while to handle the ZIP
			$putRes = $this->_mc->put('/campaigns/'.$campaignID.'/content', $putContent, $timeout);
			if (!$this->_mc->success()) {
				trigger_error("Mailchimp error: ".$this->_mc->getLastError());
			}

			$checklistRes = $this->_mc->GET('/campaigns/'.$campaignID.'/send-checklist');
			if (empty($checklistRes['is_ready'])) {
				foreach ($checklistRes['items'] as $r) {
					if ($r['type'] == 'error') {
						add_message('Mailchimp error: '.$r['heading'].': '.$r['details'], 'error');
					}
				}
			} else {
				$sendRes = $this->_mc->POST('/campaigns/'.$campaignID.'/actions/send');
				if (!$this->_mc->success()) {
					trigger_error("Mailchimp error: ".$this->_mc->getLastError());
				} else {
					$this->_sent_campaign_id = $campaignID;
				}
			}

			foreach ($attachments as $name => $tmpname) {
				unlink($tmpname);
			}
			unlink($zip_name);

		}
	}

	function getTitle()
	{
		return 'Send Mailchimp Campaign';
	}

	function printView()
	{
		if (!$GLOBALS['user_system']->havePerm(PERM_SENDSMS)) {
			print_message("Sorry, you don't have permission to send MailChimp campaigns. Please ask your system administrator for help.", 'error');
			return;
		}
		if (!empty($this->_sent_campaign_id)) {
			$URL = 'https://us1.admin.mailchimp.com/reports/summary?id='.$this->_sent_campaign_id;
			print_message('Your MailChimp campaign has been sent.  <a target="_mailchimp" href="'.$URL.'">Track in Mailchimp</a>.', 'success', TRUE);
			return;
		}
		$templates = $this->_mc->get('/templates', Array('count' => 100));
		$defaultTemplateID = NULL;
		$templateOptions = Array('' => '(None)');
		foreach ($templates['templates'] as $t) {
			$templateOptions[$t['id']] = $t['name'];
		}
		?>
		<form method="post" class="form-horizontal" enctype="multipart/form-data">
			<div class="control-group">
				<label class="control-label">From</label>
				<div class="controls">
					<?php
					echo ents($this->_from_name);
					echo ents(' <'.$this->_from_address.'>');
					?>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">To</label>
				<div class="controls">
					<a target="_mailchimp" href="https://us1.admin.mailchimp.com/lists/">Mailchimp list</a> #<?php $this->_report->printFieldValue('mailchimp_list_id'); ?> (from
					<a href="?view=persons__reports&execute=1&reportid=<?php echo $this->_report->id; ?>">
						<?php $this->_report->printFieldValue('name'); ?>
					</a>)
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Subject</label>
				<div class="controls">
					<?php $subject = $this->_sent_campaign_id ? '' : array_get($_REQUEST, 'subject', ''); ?>
					<input type="text" name="subject" size="60" value="<?php echo ents($subject); ?>" />
				</div>
			</div>
			<?php
			/*
			<div class="control-group">
				<label class="control-label">Template</label>
				<div class="controls">
					<?php print_widget('templateid', Array('type' => 'select', 'options' => $templateOptions), ''); ?>
				</div>
			</div>
			 */
			?>
			<div class="control-group">
				<label class="control-label">Message</label>
				<div class="controls">
					<?php 
					$content = $this->_sent_campaign_id ? '' : array_get($_REQUEST, 'message', '');
					print_widget('message', Array('type' => 'html'), $content);
					?>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Attachments</label>
				<div class="controls">
					<table class="expandable">
						<tr>
							<td><input name="attachment[]" type="file" />
						</tr>
					</table>
				</div>
			</div>


			<div class="control-group">
				<div class="controls">
					<button class="btn confirm-title" title="send this campaign"  type="submit">Send</button>
					<a class="btn" href="?view=persons__reports">Cancel</a>
				</div>
			</div>


		</form>
		<?php

	}

}