<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.3
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

use MillionDollarScript\Classes\Capabilities;

defined( 'ABSPATH' ) or exit;

function mds_check_permission( $permission ): bool {
	$enabled = Capabilities::enabled( $permission );

	// If capability is not enabled show the menu and enable the page.
	if ( ! $enabled ) {
		return true;
	}

	if ( current_user_can( $permission ) ) {
		// User has permission
		return true;
	} else {
		// No permission
		return false;
	}
}

function mds_logged_in(): void {
	if ( ! is_user_logged_in() ) {
		wp_redirect( wp_login_url() );
		die();
	}
}