<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.10
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

namespace MillionDollarScript\Classes;

defined( 'ABSPATH' ) or exit;

class Orders {

	/**
	 * Check if an order is owned by a user. If $user_id is omitted or null then it will use the current user id.
	 *
	 * @param int $order_id
	 * @param int|null $user_id
	 * @param bool $return_order_id
	 *
	 * @return bool
	 */
	public static function is_owned_by( int $order_id, int $user_id = null, bool $return_order_id = false ): bool {
		global $wpdb;

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `order_id` FROM " . MDS_DB_PREFIX . "orders WHERE `user_id`=%d AND `order_id`=%d",
				$user_id,
				$order_id
			)
		);

		if ( empty( $result ) ) {
			return false;
		}

		if ( $return_order_id ) {
			return $result;
		}

		return true;
	}

	/**
	 * Check if order is in progress.
	 *
	 * @param $order_id
	 *
	 * @return bool
	 */
	public static function is_order_in_progress( $order_id ): bool {
		global $wpdb;
		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `order_in_progress` FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id` = %d",
				$order_id
			)
		);

		return $row === 'Y';
	}

	/**
	 * Get the current order in progress for the given user.
	 *
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public static function get_current_order_in_progress( $user_id = null ): ?string {
		global $wpdb;

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `order_id` FROM `" . MDS_DB_PREFIX . "orders` WHERE `user_id` = %d AND `order_in_progress` = 'Y' ORDER BY `order_id` DESC LIMIT 1",
				$user_id
			)
		);
	}

	/**
	 * Set order as in progress.
	 *
	 * @param int $order_id
	 * @param bool $in_progress
	 *
	 * @return void
	 */
	public static function set_order_in_progress( int $order_id, bool $in_progress = true ): void {
		global $wpdb;
		$wpdb->update(
			MDS_DB_PREFIX . "orders",
			[ 'order_in_progress' => $in_progress ? 'Y' : 'N' ],
			[ 'order_id' => $order_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Reset the current order in progress for the given user.
	 *
	 * @param int|null $user_id
	 *
	 * @return void
	 */
	public static function reset_progress( int $user_id = null ): void {
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$order_id = self::get_current_order_in_progress( $user_id );
		if ( ! empty( $order_id ) ) {
			self::set_order_in_progress( $order_id, false );
			Steps::set_current_step( $order_id, 0 );
		}
	}

	/**
	 * Reset the given user's order progress. Used when all orders are cleared.
	 *
	 * @param int|null $user_id
	 *
	 * @return void
	 */
	public static function reset_order_progress( int $user_id = null ): void {
		// Check for filter return value to determine if we should reset the order progress.
		if ( apply_filters( 'mds_reset_order_progress', true ) === false ) {
			return;
		}

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( WooCommerceFunctions::is_wc_active() ) {
			WooCommerceFunctions::clear_cart();
			WooCommerceFunctions::reset_session_variables( $user_id );
		} else {
			Orders::reset_progress( $user_id );
			delete_user_meta( $user_id, 'mds_confirm' );
		}
	}

	/**
	 * Get the status of the order.
	 *
	 * @param $order_id
	 *
	 * @return string|null
	 */
	public static function get_order_status( $order_id ): ?string {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `status` FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id` = %d",
				$order_id
			)
		);
	}

	public static function set_current_order_id( $order_id = null ): void {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! empty( $user_id ) ) {
			if ( is_null( $order_id ) ) {
				$order_id = self::create_order();
			} else {
				// Check if order id is owned by the user first
				$order_id = self::is_owned_by( $order_id, $user_id, true );

				if ( empty( $order_id ) ) {
					return;
				}
			}

			// Update order to be in progress
			$wpdb->update(
				MDS_DB_PREFIX . "orders",
				[ 'order_in_progress' => 'Y' ],
				[ 'order_id' => $order_id ],
				[ '%s' ],
				[ '%d' ]
			);
		}
	}

	/**
	 * Get the current order id for the user.
	 *
	 * @return int|null
	 */
	public static function get_current_order_id(): int|null {
		return self::get_current_order_in_progress();
	}

	/**
	 * Set the last modification time of the orders.
	 *
	 * @return void
	 */
	public static function set_last_order_modification_time(): void {
		update_option( 'mds_last_order_modification_time', time() );
	}

	/**
	 * Get the last modification time of the orders.
	 *
	 * @return int
	 */
	public static function get_last_order_modification_time(): int {
		return get_option( 'mds_last_order_modification_time', 0 );
	}

	/**
	 * Create a new order. If $user_id is omitted it will use the current user id.
	 *
	 * @param $user_id
	 *
	 * @return int
	 */
	public static function create_order( $user_id = null ): int {
		global $wpdb;

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		// Add new order to database
		$wpdb->insert( MDS_DB_PREFIX . "orders", [
			'user_id'           => $user_id,
			'status'            => 'new',
			'order_in_progress' => 'Y',
			'current_step'      => 1
		] );

		return $wpdb->insert_id;
	}

	/**
	 * Get the order for the given order id.
	 *
	 * @param $order_id
	 *
	 * @return array|\stdClass|null
	 */
	public static function get_order( $order_id ): array|\stdClass|null {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id` = %d",
				$order_id
			)
		);
	}
	
	public static function get_ad_id_from_order_id( $order_id ): int {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `ad_id` FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id` = %d",
				$order_id
			)
		);
	}
}