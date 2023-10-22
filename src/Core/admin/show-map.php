<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.4
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

defined( 'ABSPATH' ) or exit;

require_once MDS_CORE_PATH . "include/output_grid.php";

global $f2;
$BID = $f2->bid();

$types = array(
//	'background',
//	'orders',
//	'grid',
//	'reserved',
//	'selected',

	'background',
	'orders',
	'grid',
	'nfs',
	'ordered',
	'reserved',
	'selected',
	'sold',
	'price_zones',
	'price_zones_text',
);

$user_id = 0;
if ( isset( $_REQUEST['user_id'] ) ) {
	$types[] = 'user';
	$user_id = intval( $_REQUEST['user_id'] );
}

output_grid( true, "", $BID, $types, $user_id );
