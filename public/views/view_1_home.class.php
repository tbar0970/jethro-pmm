<?php
class View_Home extends View
{
	function getTitle()
	{
		return NULL;
	}

	function processView()
	{
	}
	
	function printView()
	{
		?>
		<p></p>
		<p><i>Use the menu above to view rosters and role descriptions for <?php echo SYSTEM_NAME; ?></i><p>
		<?php



	}

}