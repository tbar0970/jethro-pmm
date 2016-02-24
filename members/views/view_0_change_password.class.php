<?php
class View__Change_Password extends View
{
	function getTitle()
	{
		return 'Change Password';
	}

	function processView()
	{
			// This is handled by member_user_system
		
	}
	
	function printView()
	{		
		?>
		<form method="post" action="?isreset=1" novalidate>
			<label>New password:</label>		
			<input type="password" name="password1" />
			
			<label>And again, to confirm:</label>
			<input type="password" name="password2" />
			<br />
			
			<input type="submit" class="btn" name="set-password" value="Save" />
			<a class="btn btn-lnk" href="?">Cancel</a>
		</form>
		<?php

	}

}
