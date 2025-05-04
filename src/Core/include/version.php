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

defined( 'ABSPATH' ) or exit;

function get_mds_build_date( $format = false ): bool|int|string {
	global $MDSUpdateChecker;

	$timestamp = false;

	if ( $MDSUpdateChecker ) {
		$update = $MDSUpdateChecker->getUpdate();
		if ( $update && ! empty( $update->last_updated ) ) {
			// The last_updated field is usually a string like '2023-10-27 15:30:00'
			$timestamp = strtotime( $update->last_updated );
		}
	}

	// Fallback to file modification time if update checker data isn't available
	if ( ! $timestamp ) {
		$timestamp = filemtime( __FILE__ );
	}

	if ( $format && $timestamp ) {
		return date( 'Y-m-d H:i:s', $timestamp );
	} elseif ( $timestamp ) {
		return $timestamp;
	}

	return false; // Return false if we couldn't get a date
}
