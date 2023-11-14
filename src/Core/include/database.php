<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.5
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

use MillionDollarScript\Classes\Database;

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( 'wpdb' ) ) {

	// Try to load WP first if necessary
	$scheme   = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . '://';
	$wpdomain = parse_url( $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

	if ( ! defined( 'COOKIE_DOMAIN' ) ) {
		define( 'COOKIE_DOMAIN', '.' . $wpdomain['host'] );
	}
	if ( ! defined( 'COOKIEPATH' ) ) {
		define( 'COOKIEPATH', '/' );
	}
	if ( ! defined( 'COOKIEHASH' ) ) {
		define( 'COOKIEHASH', md5( $wpdomain['host'] ) );
	}

	require_once ABSPATH . '/wp-load.php';
	require_once ABSPATH . '/wp-includes/pluggable.php';

	require_once 'wp_functions.php';
}

global $mdsdb;
if ( $mdsdb == null ) {
	$mdsdb = new Database();
}

/**
 * Returns SQL error output for debug purposes.
 *
 * @param $sql
 *
 * @return string
 */
function mds_sql_error( $sql ): string {
	error_log( $sql );
	error_log( mysqli_error( $GLOBALS['connection'] ) );

	return "<br />SQL:[" . htmlspecialchars( $sql, ENT_QUOTES ) . "]<br />ERROR:[" . htmlspecialchars( mysqli_error( $GLOBALS['connection'] ), ENT_QUOTES ) . "]<br />";
}

/**
 * Log SQL error to debug log and optionally exit.
 *
 * @param $sql
 * @param bool $exit
 */
function mds_sql_log_die( $sql, $exit = true ) {
	global $f2;
	$f2->write_log( 'SQL error: ' . mysqli_error( $GLOBALS['connection'] ) );
	$f2->write_log( '$sql: ' . $sql );

	if ( $exit ) {
		exit;
	}
}
