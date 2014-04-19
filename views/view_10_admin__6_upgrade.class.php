<?php
class View_Admin__Upgrade extends View
{
	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function getTitle()
	{
		return 'Check for Jethro updates';
	}

	function processView()
	{
	}

	function printView()
	{
		?>
		<iframe frameborder="0" style="width: 100%; height: 400px" src="http://jethro-pmm.sourceforge.net/update_check.php?currentversion=<?php echo JETHRO_VERSION; ?>"></iframe>
		<?php
	}
}
?>
