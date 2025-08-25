<?php
/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
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

use MillionDollarScript\Classes\Data\Database;
use MillionDollarScript\Classes\System\Logs;

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
 * Handles SQL errors securely - logs details but only shows generic message to users.
 *
 * @param $sql
 *
 * @return string
 */
function mds_sql_error( $sql ): string {
	global $wpdb;
	
	// Log detailed error information for administrators (secure)
	Logs::log( 'MDS SQL Error - Query: ' . $sql );
	Logs::log( 'MDS SQL Error - MySQL Error: ' . $wpdb->last_error );
	Logs::log( 'MDS SQL Error - User: ' . get_current_user_id() . ' - IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );

	// Return generic error message to prevent information disclosure
	// Only show detailed errors to administrators in debug mode
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) {
		return "<br />Database error occurred. Check the error logs for details.<br /><em>Debug Info (Admin Only): " . htmlspecialchars( $wpdb->last_error, ENT_QUOTES ) . "</em><br />";
	}
	
	return "<br />A database error occurred. Please try again later or contact support if the problem persists.<br />";
}

/**
 * Secure error handling for database operations - logs details but shows generic error to users.
 * Use this instead of die(mds_sql_error()) or die(mysqli_error()) patterns.
 *
 * @param string $sql The SQL query that failed
 * @param string $context Additional context for logging (optional)
 * @param bool $wp_die Whether to use wp_die() instead of regular die()
 */
function mds_secure_sql_die( string $sql, string $context = '', bool $wp_die = false ): void {
	global $wpdb;
	
	// Log detailed error information for administrators (secure)
	$error_message = $wpdb->last_error;
	Logs::log( 'MDS SQL Error - Query: ' . $sql );
	Logs::log( 'MDS SQL Error - MySQL Error: ' . $error_message );
	Logs::log( 'MDS SQL Error - Context: ' . $context );
	Logs::log( 'MDS SQL Error - User: ' . get_current_user_id() . ' - IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
	
	// Show generic error message to prevent information disclosure
	$user_message = 'A database error occurred. Please try again later or contact support if the problem persists.';
	
	// Only show detailed errors to administrators in debug mode
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) {
		$user_message .= ' <em>Debug Info (Admin Only): ' . htmlspecialchars( $error_message, ENT_QUOTES ) . '</em>';
	}
	
	if ( $wp_die ) {
		wp_die( esc_html( $user_message ), esc_html( 'Database Error' ), [ 'response' => 500 ] );
	} else {
		die( $user_message );
	}
}

/**
 * Log SQL error to debug log and optionally exit.
 *
 * @param $sql
 * @param bool $exit
 */
function mds_sql_log_die( $sql, $exit = true ) {
	global $f2, $wpdb;
	$f2->write_log( 'SQL error: ' . $wpdb->last_error );
	$f2->write_log( '$sql: ' . $sql );

	if ( $exit ) {
		exit;
	}
}
