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
		<p id="message"></p>
		<script>
			$.ajax('https://api.github.com/repos/tbar0970/jethro-pmm/tags', {
				dataType: 'json'
			}).done(function (data) {
				if (data[0].name == '<?php echo JETHRO_VERSION; ?>') {
					$('#message').html('<i class="icon-ok"></i> Your system is up to date on version '+data[0].name);
				} else {
					$('#message').html('<i class="icon-warning-sign"></i>Your system is running <?php echo JETHRO_VERSION; ?> but <a href="https://github.com/tbar0970/jethro-pmm/releases" target="_blank">'+data[0].name + '</a> is available');
				}
			});
		</script>
		<?php
		if (defined('SYSADMIN_HREF') && strlen(SYSADMIN_HREF)) {
			echo '<p>For help, <a href="'.SYSADMIN_HREF.'">contact your system administrator</a></p>';
		}
	}
}
?>
