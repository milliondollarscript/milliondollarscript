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

// @link https://stackoverflow.com/a/13087678
function full_url() {
	$ssl      = ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on';
	$sp       = strtolower( $_SERVER['SERVER_PROTOCOL'] );
	$protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
	$port     = $_SERVER['SERVER_PORT'];
	$port     = ( ( ! $ssl && $port == '80' ) || ( $ssl && $port == '443' ) ) ? '' : ':' . $port;
	$host     = isset( $_SERVER['HTTP_X_FORWARDED_HOST'] ) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : null );
	$host     = isset( $host ) ? $host : $_SERVER['SERVER_NAME'] . $port;
	$uri      = $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
	$segments = explode( '?', $uri, 2 );

	return $segments[0];
}

// @link https://www.php.net/manual/en/function.parse-url.php#106731
function unparse_url( $parsed_url, $trim_admin = false ) {
	$scheme = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
	$host   = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
	$port   = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
	$user   = isset( $parsed_url['user'] ) ? $parsed_url['user'] : '';
	$pass   = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
	$pass   = ( $user || $pass ) ? "$pass@" : '';
	$path   = isset( $parsed_url['path'] ) ? get_url_path( $parsed_url['path'], $trim_admin ) : '';

	return $scheme . $user . $pass . $host . $port . $path;
}

/**
 * Convert a given URL or string to the proper path where MDS is installed.
 *
 * @param $input
 *
 * @return string
 */
function get_url_path( $input, $trim_admin = false ) {

	// Remove anything before the first "/"
	$output = substr( $input, 0, strrpos( $input, '/' ) + 1 );

	// Get the pathinfo array of it
	$pathinfo = pathinfo( $output );

	// Trim off the admin folder
	if ( $trim_admin ) {
		$output = rtrim( $pathinfo['dirname'], '/admin' ) . '/';
	}

	return $output;
}