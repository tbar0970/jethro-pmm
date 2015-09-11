<?php
class View__Edit_Ical extends View
{
        private $person = NULL;
	private $person_uuid = NULL;
	
	function getTitle()
	{
		if ($this->person) return "iCal Enable/Disable";
	}
	
	function processView()
	{
            $this->person = $GLOBALS['member_user_system']->getCurrentMember();
            $GLOBALS['system']->includeDBClass('person_uuid');
            $temp_person_uuid = new Person_UUID();
            $temp_person_uuid->values['personid'] = $this->person['id'];
            $this->person_uuid = $temp_person_uuid->getUUID();

            if (!empty($_POST)) {
                if ($_POST['action'] == 'Disable') {
                    $temp_person_uuid->delete();
                    $message = 'iCal URL Disabled';
                }
                else if ($_POST['action'] == 'Enable') {
                    $temp_person_uuid->generateUUID();
                    $message = 'iCal URL Enabled';
                }
                else if ($_POST['action'] == 'Change') {
                    $temp_person_uuid->generateUUID();                    
                    $message = 'URL Changed';
                }
                $this->person_uuid = $temp_person_uuid->getUUID();
                
		add_message($message);
		redirect('_edit_ical');			
            }
	}
	
	function printView()
	{
            ?>
                <form method="POST" id="ical_form">
<p>Roster iCal Subscription Options</p>
<script>
    function changeAction(newAction)
    {
        document.getElementById("user_action").value = newAction;
        document.getElementById("ical_form").submit();
    }
</script>
<input type="hidden" name="action" id="user_action" value=""/>
            <?php
            if ($this->person_uuid)
            {
                ?>
<p>Subscription to your roster assignments by iCal is currently enabled. Your unique subscription URL is:</p>
                <?php
                $url = BASE_URL . 'ical/?mode=roster&uuid=' . rawurlencode($this->person_uuid);
                echo '<p><a href="' . $url . '" target="_blank">' . $url . '</a></p>';
                ?>
                    <input type="button" value="Disable" onclick="changeAction('Disable');"/>
                    <input type="button" value="Change URL" onclick="changeAction('Change');"/>
                <?php
            }
            else
            {
                ?>
<p>Subscription to your roster assignments by iCal is currently disabled. To enable this feature click the Enable button.</p>
                    <input type="button" value="Enable" onclick="changeAction('Enable');"/>
                <?php                
            }
            ?>
                </form>
            <?php
	}

}
