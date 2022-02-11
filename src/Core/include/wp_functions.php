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

function mds_load_wp() {

	$wpdomain = parse_url( WP_URL );

	if ( ! defined( 'COOKIE_DOMAIN' ) ) {
		define( 'COOKIE_DOMAIN', '.' . $wpdomain['host'] );
	}
	if ( ! defined( 'COOKIEPATH' ) ) {
		define( 'COOKIEPATH', '/' );
	}
	if ( ! defined( 'COOKIEHASH' ) ) {
		define( 'COOKIEHASH', md5( $wpdomain['host'] ) );
	}

	require_once WP_PATH . '/wp-load.php';
	require_once WP_PATH . '/wp-includes/pluggable.php';
}

function mds_wp_login_check() {
	if ( WP_ENABLED == "YES" && WP_USERS_ENABLED == "YES" ) {
		// If WP integration is enabled then redirect to WP login page if not logged in

		mds_load_wp();

		$doredirect = false;

		if ( ! is_user_logged_in() ) {

			// Check if MDS WP plugin Options class exists
			if ( class_exists( '\MillionDollarScript\Classes\Options' ) ) {

				// Get the login page option
				$loginpage = \MillionDollarScript\Classes\Options::get_option( 'login-page' );

				// If it doesn't contain the default wp-login.php and is a valid URL
				if ( strpos( $loginpage, 'wp-login.php' ) === false && wp_http_validate_url( $loginpage ) ) {

					// If not in preview or customizer
					if ( ! is_preview() && ! is_customize_preview() ) {

						// escape the URL
						$loginhref = esc_url( $loginpage );

						$woocommerce_enabled = \MillionDollarScript\Classes\Options::get_option( 'woocommerce' );

						if ( $woocommerce_enabled ) {
							$login_redirect_url  = \MillionDollarScript\Classes\Options::get_option( 'login-redirect' );
							$loginhref .= ( strpos( $loginhref, '?' ) !== false ? '&' : '?' ) . 'redirect_to=' . esc_url_raw( $login_redirect_url ) . '&redirect=' . esc_url_raw( $login_redirect_url );
						}

						// do a javascript redirect in the parent frame if it exists, otherwise just redirect in the current window
						echo '
					<script>
					if (parent.frames.length > 0) {
						parent.location.href = "' . $loginhref . '";
					} else {
						window.location.href = "' . $loginhref . '";
					}
					</script>
					';
						exit;
					}
				} else {
					// if the url isn't valid redirect to the default login
					$doredirect = true;
				}
			} else {
				// if the class doesn't exist then the plugin may not be installed/activated so redirect to the default login
				$doredirect = true;
			}

			// redirect to the default login and then back to the users page
			if ( $doredirect ) {
				wp_redirect( wp_login_url( BASE_HTTP_PATH . 'users/index.php' ) );
				exit;
			}
		}
	}
}