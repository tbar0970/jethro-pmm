<?php
/**
 * JETHRO PMM
 *
 * This file is part of Jethro PMM - https://github.com/tbar0970/jethro-pmm
 *
 * Jethro PMM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jethro PMM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jethro PMM.  If not, see <https://www.gnu.org/licenses/>.
 *
 * index.php - first stop for every request
 *
 * @author Tom Barrett <tom@tombarrett.id.au>
 * @version $Id: index.php,v 1.17 2013/09/25 12:23:22 tbar0970 Exp $
 * @package jethro-pmm
 */

define('JETHRO_ROOT', dirname(__FILE__));
define('TEMPLATE_DIR', JETHRO_ROOT.'/templates/');

// Load configuration
$conf=JETHRO_ROOT.'/conf.php';
if (!file_exists($conf)) {
	$errmsg = "Jethro configuration file not found.  You need to copy $conf.sample to $conf and edit it before Jethro can run";
	echo $errmsg;
	trigger_error($errmsg, E_USER_ERROR);
	exit();
}
if (!is_readable($conf)) {
	$errmsg = "$conf not readable by user ".$_SERVER["USER"].".";
	echo $errmsg;
	trigger_error($errmsg, E_USER_ERROR);
	exit();
}
require_once $conf;

define('DB_MODE', 'PRIVATE');
require_once JETHRO_ROOT.'/include/init.php';

// Set up the user system
require_once JETHRO_ROOT.'/include/user_system.class.php';
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['user_system'] = new User_System();

if ($GLOBALS['user_system']->getCurrentUser() == NULL) {
	System_Controller::checkConfigHealth();
	// Nobody is logged in, so show login screen or installer
	if (!$GLOBALS['db']->hasTables()) {
		require_once JETHRO_ROOT.'/include/installer.class.php';
		$installer = new Installer();
		$installer->run();
	} else {
		$GLOBALS['user_system']->printLogin();
	}
} else {
	// We have a user, so run the system
	$GLOBALS['system'] = System_Controller::get();
	System_Controller::get()->run();
}