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
			// Let's send us a campaign!
			$postData = Array(
				'type' => 'regular',
				'recipients' => Array('list_id' => $this->_report->getValue('mailchimp_list_id')),
				'settings' => Array(
					'subject_line' => $_POST['subject'],
					'title' => $_POST['subject'],
					'from_name' => $this->_from_name,
					'reply_to' => $this->_from_address,
					/* 'template_id' => '' */
				),
			);
			$postRes = $this->_mc->post('/campaigns', $postData);
			if (!$this->_mc->success()) {
				trigger_error("Mailchimp error: ".$this->_mc->getLastError());
			}
			$campaignID = $postRes['id'];
			if (empty($campaignID)) {
				trigger_error("Failed to create campaign");
				exit;
			}
			$putContent = Array(
				'html' => $_POST['message'],
				'template' => Array('id' => (int)$_POST['templateid']),

			);
			$putRes = $this->_mc->put('/campaigns/'.$campaignID.'/content', $putContent);
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
		$templateOptions = Array();
		foreach ($templates['templates'] as $t) {
			$templateOptions[$t['id']] = $t['name'];
			if (preg_match('/^1 Column$/', $t['name']) && !$defaultTemplateID) $defaultTemplateID = $t['id'];
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
					<input type="text" name="subject" size="60" />
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Template</label>
				<div class="controls">
					<?php print_widget('templateid', Array('type' => 'select', 'options' => $templateOptions), $defaultTemplateID); ?>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label">Message</label>
				<div class="controls">
					<?php print_widget('message', Array('type' => 'html'), ''); ?>
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
?>
