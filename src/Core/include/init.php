<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.9
 * @author      Ryan Rhode
 * @copyright   (C) 2023, Ryan Rhode
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *    Million Dollar Script
 *    Pixels to Profit: Ignite Your Revolution
 *    https://milliondollarscript.com/
 *
 */

use MillionDollarScript\Classes\Config;

defined( 'ABSPATH' ) or exit;

require_once MDS_CORE_PATH . 'include/database.php';
require_once MDS_BASE_PATH . 'src/Classes/Config.php';

$MDSConfig = Config::load();

@ini_set( 'memory_limit', $MDSConfig['MEMORY_LIMIT'] );

require_once MDS_CORE_PATH . 'include/wp_functions.php';
mds_load_wp();

require_once MDS_CORE_PATH . 'include/functions2.php';
global $f2;
$f2 = new functions2();

require_once MDS_CORE_PATH . 'include/mail_manager.php';
require_once MDS_CORE_PATH . 'include/price_functions.php';
require_once MDS_CORE_PATH . 'include/functions.php';
require_once MDS_CORE_PATH . 'include/image_functions.php';
require_once MDS_CORE_PATH . 'include/login_functions.php';
