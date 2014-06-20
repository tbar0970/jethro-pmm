<?php
/*
 * JETHRO PMM
 *
 * This file is part of Jethro PMM - http://jethro-pmm.sourceforge.net
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
 * along with Jethro PMM.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * index.php - first stop for every request
 *
 * @author Tom Barrett <tom@tombarrett.id.au>
 * @version $Id: index.php,v 1.3 2013/03/20 11:03:53 tbar0970 Exp $
 * @package jethro-pmm
 */

define('THIS_DIR', str_replace('\\', '/', dirname(__FILE__)));
define('JETHRO_ROOT', preg_replace('#/members$#', '', THIS_DIR));
define('TEMPLATE_DIR', THIS_DIR.'/templates/');
set_include_path(get_include_path().PATH_SEPARATOR.dirname(THIS_DIR));

// Load configuration
require_once dirname(THIS_DIR).'/conf.php';
define('DSN', MEMBERS_DSN);
define('IS_PUBLIC', true);

// Initialise system
require_once JETHRO_ROOT.'/include/init.php';

// Set up the user system
require_once JETHRO_ROOT.'/include/user_system.class.php';
require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['user_system'] = new User_System();
if ($GLOBALS['user_system']->getCurrentMember() == NULL) {
	// Nobody is logged in, so show login screen or installer
	$GLOBALS['user_system']->printLogin();
} else {
	require_once 'include/system_controller.class.php';
	$GLOBALS['system'] = new System_Controller(THIS_DIR);
	$GLOBALS['system']->run();
}
