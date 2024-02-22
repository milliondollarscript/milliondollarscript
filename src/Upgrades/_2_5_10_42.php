<?php

/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

namespace MillionDollarScript\Upgrades;

use MillionDollarScript\Classes\Options;

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_5_10_42 {

	public function upgrade( $version ): void {
		if ( version_compare( $version, '2.5.10.42', '<' ) ) {
			global $wpdb;

			// Add denied to status orders
			$table_name = MDS_DB_PREFIX . 'orders';
			$sql        = "SHOW COLUMNS FROM {$table_name} LIKE 'status';";
			$result     = $wpdb->get_results( $sql );
			if ( empty( $result ) || $result[0]->Type !== "enum('denied','pending','completed','cancelled','confirmed','new','expired','deleted','renew_wait','renew_paid')" ) {
				$sql = "ALTER TABLE {$table_name} CHANGE `status` `status` SET('denied','pending','completed','cancelled','confirmed','new','expired','deleted','renew_wait','renew_paid');";
				$wpdb->query( $sql );
			}

			// Add denied to status blocks
			$table_name = MDS_DB_PREFIX . 'blocks';
			$sql    = "SHOW COLUMNS FROM {$table_name} LIKE 'status';";
			$result = $wpdb->get_results( $sql );
			if ( empty( $result ) || $result[0]->Type !== "enum('denied','cancelled','reserved','sold','free','ordered','nfs')" ) {
				$sql = "ALTER TABLE {$table_name} CHANGE `status` `status` SET('denied','cancelled','reserved','sold','free','ordered','nfs');";
				$wpdb->query( $sql );
			}
		}
	}
}
