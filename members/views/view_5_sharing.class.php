<?php
class View_sharing extends View
{
	function getTitle()
	{
		return 'Sharing';
	}

	function processView()
	{
		if (array_get($_POST, 'share_consent_submitted')) {
			$GLOBALS['system']->includeDBClass('person_group');
			$memberid = (int)$GLOBALS['user_system']->getCurrentMember('id');
			Person_Group::setShareConsent($memberid, 0, !empty($_POST['share_visible_default']), !empty($_POST['share_contact_default']));
			Person_Group::applyShareConsentCheckboxes(
				$memberid,
				Person_Group::getGroups($memberid, FALSE, TRUE),
				(array)array_get($_POST, 'share_visible', Array()),
				(array)array_get($_POST, 'share_contact', Array()),
				(array)array_get($_POST, 'share_inherit_visible', Array()),
				(array)array_get($_POST, 'share_inherit_contact', Array()),
				(array)array_get($_POST, 'share_rendered', Array())
			);
			add_message("Sharing settings saved");
			redirect('sharing');
		}
	}

	function printView()
	{
		$GLOBALS['system']->includeDBClass('person_group');
		$memberid = (int)$GLOBALS['user_system']->getCurrentMember('id');
		$shareGroups = Person_Group::getGroups($memberid, FALSE, TRUE);
		$shareDefault = Person_Group::getDefaultShareConsent($memberid);
		$shareCanEdit = TRUE;
		$shareDefaultEditable = TRUE;
		$shareGroupUrl = '?view=_groups&groupid=%d';
		?>
		<p>Tick &quot;Share my Name&quot; to let fellow members of a group see your name in the members area, and &quot;Share my Contact Info&quot; to also show your email and phone. The default row applies to sharing-enabled groups where you haven't chosen individually.</p>
		<form method="post" class="share-consent-form">
		<input type="hidden" name="share_consent_submitted" value="1" />
		<?php include JETHRO_ROOT.'/templates/share_consent.template.php'; ?>
		<?php if (!$shareGroups) { ?>
			<p><em>You are not currently in any groups that support sharing.</em></p>
		<?php } ?>
		<button class="btn" type="submit">Save</button>
		<p class="help-block"><i>Tick to share; untick to hide this group's listing even when your default is on. Click &quot;Use default&quot; to make the row follow your default again, or &quot;Set individually&quot; to override it for this group.</i></p>
		</form>
		<?php
	}
}
