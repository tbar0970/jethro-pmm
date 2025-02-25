<?php
/*
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
 * @version $Id: index.php,v 1.3 2013/03/20 11:03:53 tbar0970 Exp $
 * @package jethro-pmm
 */

define('THIS_DIR', str_replace('\\', '/', dirname(__FILE__)));
define('JETHRO_ROOT', preg_replace('#/public$#', '', THIS_DIR));
define('TEMPLATE_DIR', THIS_DIR.'/templates/');
set_include_path(get_include_path().PATH_SEPARATOR.dirname(THIS_DIR));

// Load configuration
require_once dirname(THIS_DIR).'/conf.php';

// Initialise system
define('IS_PUBLIC', true);
define('DB_MODE', 'PUBLIC');
require_once JETHRO_ROOT.'/include/init.php';

// Init user system but don't try to auth anyone
require_once JETHRO_ROOT.'/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setPublic();

require_once 'include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get(THIS_DIR);

// Check if public access is enabled
// But wave through roster_ical requests, which are access-checked later.
if ((ifdef('PUBLIC_AREA_ENABLED', 0) == 0) && (array_get($_GET, 'call') != 'roster_ical')) {
	header("HTTP/1.0 403 Forbidden");
	?>
	<p>The public area is not enabled for this Jethro System.  You may like to view the <a href="<?php echo BASE_URL; ?>members">members area<a>
	<?php
	exit;
}

$GLOBALS['system']->run();
