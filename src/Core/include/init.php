<?php
/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-01-30 17:07:25 EST
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */

require_once __DIR__ . '/../include/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../include/MDSConfig.php';

$MDSCONFIG = MDSConfig::load();
foreach ( $MDSCONFIG as $key => $value ) {
	if ( ! defined( $key ) ) {
		define( $key, $value );
	}
}

@ini_set( 'memory_limit', $MDSCONFIG['MEMORY_LIMIT'] );

if ( $MDSCONFIG['WP_ENABLED'] == 'YES' ) {
	require_once $MDSCONFIG['BASE_PATH'] . '/include/wp_functions.php';
	mds_load_wp();
}

global $purifier;
$purifier = new HTMLPurifier();

require_once $MDSCONFIG['BASE_PATH'] . '/include/functions2.php';
global $f2;
$f2 = new functions2();

global $label;
require_once $MDSCONFIG['BASE_PATH'] . '/lang/lang.php';
require_once $MDSCONFIG['BASE_PATH'] . '/include/mail_manager.php';
require_once $MDSCONFIG['BASE_PATH'] . '/include/currency_functions.php';
require_once $MDSCONFIG['BASE_PATH'] . '/include/price_functions.php';
require_once $MDSCONFIG['BASE_PATH'] . '/include/functions.php';
require_once $MDSCONFIG['BASE_PATH'] . '/include/image_functions.php';
