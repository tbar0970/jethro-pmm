<?php
class View__Edit_Ical extends View
{
	protected $person;

	function getTitle()
	{
		return 'Manage Roster iCal Feed';
	}
	
	function _loadPerson()
	{
		$this->person = $GLOBALS['system']->getDBObject('person', $GLOBALS['user_system']->getCurrentMember('id'));
	}
	
	function processView()
	{
            $this->_loadPerson();
			if (array_get($_POST, 'action')) {
				if (!$this->person->acquireLock()) {
					add_message("Could not adjust iCal feed at this time; please try again later", 'error');
					return;
				}
				if ($_POST['action'] == 'enable') {
					$this->person->setFeedUUID();
					$message = 'iCal feed enabled';
				} else if ($_POST['action'] == 'change') {
					$this->person->setFeedUUID();
					$message = 'iCal URL changed';
				} else if ($_POST['action'] == 'disable') {
					$this->person->setValue('feed_uuid', NULL);
					$message = 'iCal feed disabled';
				} else {
					$message = 'Invalid action';
				}
				if (!$this->person->save(FALSE)) {
					add_message("Could not adjust iCal feed at this time; please try again later", 'error');
					return;
				}
				$this->person->releaseLock();
				add_message($message);
			}
	}
	
	function printView()
	{
		$uuid = $this->person->getValue('feed_uuid');
		if ($uuid) {
			$url = BASE_URL.'public/?call=roster_ical&uuid='.rawurlencode($uuid);
			?>
			<p>Your personalised roster assignments iCal feed is available at <br />
				<span class="input-append"><input id="ical-url" type="text" class="span8" autoselect="autoselect" readonly="readonly" value="<?php echo $url; ?>" />
				<button type="button" class="btn" data-action="copy" data-target="#ical-url">Copy</button>
				</span>
			</p>

			<p>To use the feed, enter this URL into Google Calendar, Microsoft Outlook, Apple iCal or another calendar tool which supports the iCalendar format.</p>

			<p>To regenerate your personalised URL, click below.  Your old feed URL will then stop working.</p>
			<form method="post" class="inline">
				<input type="hidden" name="action" value="change" />
				<input type="submit" class="btn" value="Generate New URL" />
			</form>

			<p>To disable your iCal feed altogether, click below.</p>
			<form method="post" class="inline">
				<input type="hidden" name="action" value="disable" />
				<input type="submit" class="btn" value="Disable iCal feed" />
			</form>
			<?php
		} else {
			?>
			<p>Jethro can publish an iCal feed of your roster assignments.  To enable your personal iCal feed, click below.</p>
			<form method="post" class="form-inline">
				<input type="hidden" name="action" value="enable" />
				<input type="submit" class="btn" value="Enable iCal feed" />
			</form>
			<?php
		}
	}

}
