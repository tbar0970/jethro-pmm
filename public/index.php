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
define('JETHRO_ROOT', preg_replace('#/public$#', '', THIS_DIR));
define('TEMPLATE_DIR', THIS_DIR.'/templates/');
set_include_path(get_include_path().PATH_SEPARATOR.dirname(THIS_DIR));

// Load configuration
require_once dirname(THIS_DIR).'/conf.php';
// Check for old style DSN - and try to work - but this is messy and horrible to use
if (defined('PUBLIC_DSN')) {
		preg_match('|([a-z]+)://([^:]*)(:(.*))?@([A-Za-z0-9\.-]*)(/([0-9a-zA-Z_/\.]*))|',
     PRIVATE_DSN,$matches);
		 if (!defined('DB_TYPE')) define('DB_TYPE', $matches[1]);
		 if (!defined('DB_HOST')) define('DB_HOST', $matches[5]);
		 if (!defined('DB_DATABASE')) define('DB_DATABASE', $matches[7]);
		 if (!defined('DB_PUBLIC_USERNAME')) define('DB_PUBLIC_USERNAME', $matches[2]);
		 if (!defined('DB_PUBLIC_PASSWORD')) define('DB_PUBLIC_PASSWORD', $matches[4]);
}
if (!defined('DSN')) {
		define('DSN', DB_TYPE . ':host=' . DB_HOST . (!empty(DB_PORT)? (';port=' . DB_PORT):'') . ';dbname=' . DB_DATABASE . ';charset=utf8');
}
if (!defined('DB_USERNAME')) define('DB_USERNAME', DB_PUBLIC_USERNAME);
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', DB_PUBLIC_PASSWORD);
define('IS_PUBLIC', true);

// Initialise system
require_once JETHRO_ROOT.'/include/init.php';

// Init user system but don't try to auth anyone
require_once JETHRO_ROOT.'/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setPublic();

require_once 'include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get(THIS_DIR);
$GLOBALS['system']->run();
