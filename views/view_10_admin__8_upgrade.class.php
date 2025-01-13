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
			$.ajax('https://api.github.com/repos/tbar0970/jethro-pmm/releases/latest', {
				dataType: 'json'
			}).done(function (data) {
				if (data.tag_name.replace('v', '') == '<?php echo JETHRO_VERSION; ?>') {
					$('#message').html('<i class="icon-ok"></i> Your system is up to date on version '+data.tag_name);
				} else if ('<?php echo JETHRO_VERSION; ?>' == 'DEV') {
					$('#message').html('<i class="icon-ok"></i> Your system is running Jethro in DEV mode.  The latest release is <a href="https://github.com/tbar0970/jethro-pmm/releases" target="_blank">'+data.tag_name + '</a>');
				} else {
					$('#message').html('<i class="icon-warning-sign"></i>Your system is running <?php echo JETHRO_VERSION; ?> but <a href="https://github.com/tbar0970/jethro-pmm/releases" target="_blank">'+data.tag_name + '</a> is available');
				}
			});
		</script>
		<?php
		if (defined('SYSADMIN_HREF') && strlen(SYSADMIN_HREF)) {
			echo '<p>For help, <a href="'.SYSADMIN_HREF.'">contact your system administrator</a></p>';
		}
	}
}