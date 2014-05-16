<?php
class init_htpasswd extends jethro_hook
{
	function run($params)
	{
		$passwordfile = dirname(dirname(JETHRO_ROOT)).'/staff.htaccess';
		$username = $params->getValue('username');
		$password = $params->getValue('raw_password');
		if ($params->getValue('active')) {
			// add them to the htpasswd file
			if ($password) {
				system('/usr/sbin/htpasswd -b -m '.$passwordfile.' '.$username.' '.$password);
			}
		} else {
			// remove them from the htpasswd file
			$lines = file($passwordfile);
			foreach ($lines as $i => $v) {
				if (0 === strpos($v, $username.':')) {
					unset($lines[$i]);
				}
			}
			file_put_contents($passwordfile, implode("\n", $lines));
		}
	}
}
