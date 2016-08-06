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

// Initialise system
define('DSN', MEMBERS_DSN);
define('IS_PUBLIC', true);
require_once JETHRO_ROOT.'/include/init.php';

// Check if member access is enabled
if (!defined('MEMBER_LOGIN_ENABLED') || !MEMBER_LOGIN_ENABLED) {
	?>
	<p>Member Login is not enabled for this Jethro System.  You may like to view the <a href="<?php echo BASE_URL; ?>/public">public site</a>
	<?php
	exit;
}

// Initialise system
// Check for old style DSN - and try to work - but this is messy and horrible to use
if (defined('MEMBERS_DSN')) {
		preg_match('|([a-z]+)://([^:]*)(:(.*))?@([A-Za-z0-9\.-]*)(/([0-9a-zA-Z_/\.]*))|',
     PRIVATE_DSN,$matches);
		 define('DB_TYPE', $matches[1]);
		 define('DB_HOST', $matches[5]);
		 define('DB_DATABASE', $matches[7]);
		 define('DB_MEMBERS_USERNAME', $matches[2]);
		 define('DB_MEMBERS_PASSWORD', $matches[4]);
}
if (!defined('DSN')) {
		define('DSN', DB_TYPE . ':host=' . DB_HOST . (!empty(DB_PORT)? (';port=' . DB_PORT):'') . ';dbname=' . DB_DATABASE . ';charset=utf8');
}
if (!defined('DB_USERNAME')) define('DB_USERNAME', DB_MEMBERS_USERNAME);
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', DB_MEMBERS_PASSWORD);

define('IS_PUBLIC', true);
require_once JETHRO_ROOT.'/include/init.php';

// Set up the user system
require_once THIS_DIR.'/include/member_user_system.class.php';
$GLOBALS['user_system'] = new Member_User_System();
$GLOBALS['user_system']->run();

if ($GLOBALS['user_system']->getCurrentMember() != NULL) {
	require_once 'include/system_controller.class.php';
	$GLOBALS['system'] = System_Controller::get(THIS_DIR);
	System_Controller::get()->run();
}
