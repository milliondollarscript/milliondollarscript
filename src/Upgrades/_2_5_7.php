<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.9
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

namespace MillionDollarScript\Upgrades;

use MillionDollarScript\Classes\Options;

defined( 'ABSPATH' ) or exit;

/** @noinspection PhpUnused */

class _2_5_7 {

	public function upgrade( $version ): void {
		if ( version_compare( $version, '2.5.7', '<' ) ) {

			global $wpdb;

			// Loop through each WooCommerce product
			$products = wc_get_products( array( 'status' => 'publish' ) );
			foreach ( $products as $product ) {
				$meta_values = get_post_custom( $product->get_id() );

				// Check for _milliondollarscript meta values set to yes
				if ( isset( $meta_values['_milliondollarscript'] ) && $meta_values['_milliondollarscript'][0] === 'yes' ) {

					// Loop through each variation and mark them as downloadable
					$variations = $product->get_available_variations();
					foreach ( $variations as $variation ) {
						$variation_id      = $variation['variation_id'];
						$variation_product = wc_get_product( $variation_id );
						$variation_product->set_downloadable( true );
						$variation_product->save();
					}
				}
			}

			// Delete checkout page
			$page_id = Options::get_option( 'users-checkout-page' );
			if ( $page_id !== false ) {

				// Delete the post from WP if it's modified time is older or equal to the stored time option.
				$modified_time          = strtotime( get_post_modified_time( 'Y-m-d H:i:s', false, $page_id ) );
				$original_modified_time = strtotime( get_post_meta( $page_id, '_modified_time', true ) );

				if ( $modified_time == $original_modified_time ) {
					wp_delete_post( $page_id );
				}

				// Delete the options for this page.
				delete_option( '_' . MDS_PREFIX . 'users-checkout-page' );
			}

			// Add order_in_progress column to orders table
			$sql    = "SHOW COLUMNS FROM `" . MDS_DB_PREFIX . "orders` LIKE 'order_in_progress';";
			$result = $wpdb->get_results( $sql );
			if ( ! $result ) {
				$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD COLUMN `order_in_progress` SET('Y', 'N') NOT NULL default 'N';";
				$wpdb->query( $sql );
			}

			// Add current_step column to orders table
			$sql    = "SHOW COLUMNS FROM `" . MDS_DB_PREFIX . "orders` LIKE 'current_step';";
			$result = $wpdb->get_results( $sql );
			if ( ! $result ) {
				$sql = "ALTER TABLE `" . MDS_DB_PREFIX . "orders` ADD COLUMN `current_step` INT NOT NULL default '0';";
				$wpdb->query( $sql );
			}

			// Update any MDS_PREFIX . current_order_id meta for all users to be in progress in the orders table.
			// This is to fix any orders that were created before the order_in_progress and current_step columns were added.
			$users = get_users();
			foreach ( $users as $user ) {
				$meta_key = MDS_PREFIX . 'current_order_id';
				$order_id = get_user_meta( $user->ID, $meta_key, true );
				if ( ! empty( $order_id ) ) {
					$sql = "UPDATE `" . MDS_DB_PREFIX . "orders` SET `order_in_progress`='Y', `current_step`=1 WHERE `order_id`=" . $order_id;
					$wpdb->query( $sql );
				}
			}
		}
	}
}
