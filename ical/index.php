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
 * @author Ben Kelley <ben.kelley@gmail.com>
 * @version $Id:$
 * @package jethro-pmm
 */

define('THIS_DIR', str_replace('\\', '/', dirname(__FILE__)));
define('JETHRO_ROOT', preg_replace('#/ical$#', '', THIS_DIR));
define('TEMPLATE_DIR', THIS_DIR.'/templates/');
set_include_path(get_include_path().PATH_SEPARATOR.dirname(THIS_DIR));

// Load configuration
require_once dirname(THIS_DIR).'/conf.php';

// Check if member access is enabled
if (!defined('MEMBER_LOGIN_ENABLED') || !MEMBER_LOGIN_ENABLED) {
	?>
	<p>Member Login is not enabled for this Jethro System.  You may like to view the <a href="<?php echo BASE_URL; ?>/public">public site</a>
	<?php
	exit;
}


// Initialise system
define('DSN', MEMBERS_DSN);
define('IS_PUBLIC', true);
require_once JETHRO_ROOT.'/include/init.php';

require_once THIS_DIR.'/include/ical_system.class.php';
if (!Ical_System::get()->featureEnabled('ROSTERS&SERVICES')) {
    	?>
	<p>Rosters are not enabled for this Jethro System.  You may like to view the <a href="<?php echo BASE_URL; ?>/public">public site</a>
	<?php
	exit;
}

// Init user system but don't try to auth anyone
require_once JETHRO_ROOT.'/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setPublic();

require_once 'include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get(THIS_DIR);
System_Controller::get()->run();

Ical_System::get()->run();
