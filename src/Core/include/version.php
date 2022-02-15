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

function get_mds_build_date() {
	$lines = file( __FILE__ );

	$version = '';
	foreach ( $lines as $line ) {
		if ( strpos( $line, '@version' ) !== false ) {
			$version = trim( str_replace( '* @version', '', $line ) );
			break;
		}
	}

	return $version;
}

function get_mds_version() {
	global $wpdb;

	$sql     = "SELECT `val` FROM `" . MDS_DB_PREFIX . "config` WHERE `key`='VERSION_INFO'";
	$version = $wpdb->get_var( $sql );
	if ( $wpdb->num_rows == 0 || empty( $version ) ) {
		$version = MDS_VERSION;
	}

	return $version;
}
