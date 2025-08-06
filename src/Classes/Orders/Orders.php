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

namespace MillionDollarScript\Classes\Orders;

use MillionDollarScript\Classes\Ajax\Ajax;
use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Email\Emails;
use MillionDollarScript\Classes\Email\Mail;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Payment\Currency;
use MillionDollarScript\Classes\System\Debug;
use MillionDollarScript\Classes\System\Functions;
use MillionDollarScript\Classes\System\Logs;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\WooCommerce\WooCommerceFunctions;

defined( 'ABSPATH' ) or exit;

class Orders {

	/**
	 * Get the order approved status.
	 *
	 * @param $post_id int The post id of the order.
	 *
	 * @return string The approved status of the order.
	 */
	public static function get_order_approved_status( int $post_id ): string {
		global $wpdb;
		$order_id = carbon_get_post_meta( $post_id, MDS_PREFIX . 'order' );
		$order    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `approved` FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id` = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return '';
		}

		return $order->approved;
	}

	/**
	 * Get the published status of an order.
	 *
	 * @param int $post_id The post ID of the order.
	 *
	 * @return string The published status of the order.
	 */
	public static function get_order_published_status( int $post_id ): string {
		global $wpdb;
		$order_id = carbon_get_post_meta( $post_id, MDS_PREFIX . 'order' );
		$order    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `published` FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id` = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return '';
		}

		return $order->published;
	}

	/**
	 * Get the expiration date of an order.
	 *
	 * @param int $order_id The ID of the order.
	 *
	 * @return string The expiration date of the order in the format specified by the WordPress date format setting.
	 */
	public static function get_order_expiration_date( int $order_id ): string {
		global $wpdb;

		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `banner_id`, `date_published` FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id` = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return '';
		}

		$days_expire = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `days_expire` FROM `" . MDS_DB_PREFIX . "banners` WHERE `banner_id` = %d",
				$order->banner_id
			)
		);

		if ( $days_expire == 0 ) {
			return $days_expire;
		}

		try {
			$timezone = get_option( 'timezone_string' ) ?: 'UTC';

			$date_published = $order->date_published;
			if ( empty( $date_published ) ) {
				return '';
			}

			$date = new \DateTime( $date_published, new \DateTimeZone( $timezone ) );
			$date->modify( '+' . $days_expire . ' days' );

			$date_format = get_option( 'date_format' ) ?: 'Y-m-d';
			$time_format = get_option( 'time_format' ) ?: 'H:i:s';

			return $date->format( $date_format . ' ' . $time_format );
		} catch ( \Exception $e ) {
			Debug::output( $e->getMessage() );
			Debug::log_trace();
		}

		return '';
	}

	/**
	 * Check if an order is owned by a user. If $user_id is omitted or null then it will use the current user id.
	 *
	 * @param int $order_id
	 * @param int|null $user_id
	 * @param bool $return_order_id
	 *
	 * @return bool|int
	 */
	public static function is_owned_by( int $order_id, int $user_id = null, bool $return_order_id = false ): bool|int {
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
			return (int) $result;
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
				"SELECT `order_id` FROM `" . MDS_DB_PREFIX . "orders` WHERE `user_id` = %d AND `order_in_progress` = 'Y' AND `status` = 'new' ORDER BY `order_id` DESC LIMIT 1",
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

		// Get the current order in progress before resetting
		$current_order_id = self::get_current_order_in_progress( $user_id );
		
		// Clear data ONLY from 'new' orders to preserve confirmed orders waiting for payment
		global $wpdb;
		
		// Only get 'new' status orders - preserve confirmed orders for payment
		$new_user_orders = $wpdb->get_col( $wpdb->prepare(
			"SELECT order_id FROM " . MDS_DB_PREFIX . "orders WHERE user_id = %d AND status = 'new'",
			$user_id
		));
		
		if ( ! empty( $new_user_orders ) ) {
			$order_ids_placeholder = implode( ',', array_fill( 0, count( $new_user_orders ), '%d' ) );
			
			// Clear block reservations ONLY for new orders
			$wpdb->query( $wpdb->prepare(
				"UPDATE " . MDS_DB_PREFIX . "blocks SET status = 'pending', order_id = NULL WHERE order_id IN ($order_ids_placeholder)",
				...$new_user_orders
			));
			
			// Get ad_ids from NEW orders only
			$ad_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT ad_id FROM " . MDS_DB_PREFIX . "orders WHERE order_id IN ($order_ids_placeholder) AND ad_id IS NOT NULL",
				...$new_user_orders
			));
			
			// Clear block selections ONLY from new orders
			$wpdb->update(
				MDS_DB_PREFIX . 'orders',
				array( 'blocks' => '', 'block_info' => '', 'order_in_progress' => 'N', 'ad_id' => null ),
				array( 'user_id' => $user_id, 'status' => 'new' ),
				array( '%s', '%s', '%s', null ),
				array( '%d', '%s' )
			);
			
			// Delete mds-pixel posts ONLY associated with NEW orders
			if ( ! empty( $ad_ids ) ) {
				foreach ( $ad_ids as $ad_id ) {
					if ( $ad_id && get_post( $ad_id ) ) {
						wp_delete_post( $ad_id, true ); // Force delete
					}
				}
			}
		}
		
		// Set any confirmed orders to not in progress, but preserve their data
		$wpdb->update(
			MDS_DB_PREFIX . 'orders',
			array( 'order_in_progress' => 'N' ),
			array( 'user_id' => $user_id, 'status' => 'confirmed' ),
			array( '%s' ),
			array( '%d', '%s' )
		);
		
		// Also delete any other "new" status mds-pixel posts for this user that might not be associated with orders
		$user_new_posts = get_posts( array(
			'author'      => $user_id,
			'post_status' => 'new',
			'post_type'   => 'mds-pixel',
			'numberposts' => -1
		) );
		
		foreach ( $user_new_posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		if ( WooCommerceFunctions::is_wc_active() ) {
			WooCommerceFunctions::remove_item_from_cart( $user_id, self::get_current_order_id() );
			WooCommerceFunctions::reset_session_variables( $user_id );
		} else {
			self::reset_progress( $user_id );
			delete_user_meta( $user_id, 'mds_confirm' );
		}
		
		// Clear the current order ID from session to force fresh start
		if ( WooCommerceFunctions::is_wc_active() ) {
			// Clear the order ID from WooCommerce session
			if ( WC()->session && is_object( WC()->session ) ) {
				WC()->session->__unset( 'mds_order_id' );
				WC()->session->save_data();
			}
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

			// Reset other orders to not be in progress
			$sql = $wpdb->prepare( "UPDATE " . MDS_DB_PREFIX . "orders SET order_in_progress = %s WHERE user_id = %d AND order_id != %d", 'N', $user_id, $order_id );
			$wpdb->query( $sql );
		}
	}

	/**
	 * Get the current order id for the user.
	 *
	 * @return int|null
	 */
	public static function get_current_order_id(): int|null {
		$order_id = self::get_current_order_in_progress();

		return ! empty( $order_id ) ? (int) $order_id : null;
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
	 * @param int|null $user_id
	 * @param int|null $banner_id
	 *
	 * @return int
	 */
	public static function create_order( int $user_id = null, int $banner_id = null ): int {
		global $wpdb;

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( is_null( $banner_id ) ) {
			global $f2;
			$banner_id = $f2->bid();
		}

		// Only reset if there's no current order in progress
		// This prevents clearing valid order data during the normal flow
		$existing_order = self::get_current_order_in_progress( $user_id );
		if ( empty( $existing_order ) ) {
			self::reset_order_progress( $user_id );
		}

		$now = current_time( 'mysql' );

		// TODO: Change default date_published to NULL in the database and set it to null below.

		// Add new order to database
		$wpdb->insert( MDS_DB_PREFIX . "orders", [
			'user_id'           => $user_id,
			'status'            => 'new',
			'order_in_progress' => 'Y',
			'current_step'      => 1,
			'order_date'        => $now,
			'date_published'    => $now,
			'date_stamp'        => $now,
			'banner_id'         => $banner_id,
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

	/**
	 * Get ad id from order id.
	 *
	 * @param int $order_id
	 *
	 * @return int|null
	 */
	public static function get_ad_id_from_order_id( int $order_id ): int|null {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `ad_id` FROM `" . MDS_DB_PREFIX . "orders` WHERE `order_id` = %d",
				$order_id
			)
		);
	}

	/**
	 * Get order id from ad id.
	 *
	 * @param int $ad_id
	 *
	 * @return int
	 */
	public static function get_order_id_from_ad_id( int $ad_id ): int {
		if ( function_exists( 'carbon_get_post_meta' ) ) {
			$order_id = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'order' );
		} else {
			global $wpdb;
			$table_name = MDS_DB_PREFIX . "orders";
			$query      = $wpdb->prepare( "SELECT order_id FROM $table_name WHERE ad_id = %d", $ad_id );
			$order_id   = $wpdb->get_var( $query );
		}

		if ( ! $order_id ) {
			return 0;
		}

		return (int) $order_id;
	}

	/**
	 * Get WC order ID from MDS order ID
	 *
	 * @param int $mds_order_id
	 *
	 * @return int|null
	 */
	public static function get_wc_order_id_from_mds_order_id( int $mds_order_id ): ?int {
		$args = array(
			'limit'      => 1,
			'meta_query' => array(
				array(
					'key'     => 'mds_order_id',
					'value'   => $mds_order_id,
					'compare' => '=',
				),
			),
			'status'     => 'any',
		);

		// Get order IDs
		$orders = wc_get_orders( $args );

		// Check if any order ID is found
		if ( ! empty( $orders ) ) {
			return $orders[0]->get_id();
		}

		// Return null if no order is found
		return null;
	}

	/**
	 * Get the valid statuses of an order.
	 *
	 * @return string[]
	 */
	public static function valid_order_statuses(): array {
		return [
			'pending',
			'completed',
			'cancelled',
			'confirmed',
			'new',
			'expired',
			'deleted',
			'renew_wait',
			'renew_paid',
			'denied',
			'paid',
		];
	}

	/**
	 * Update order status.
	 *
	 * TODO: Finish and implement into core functions.php in various places.
	 * TODO: Add additional parameters to update other fields, perhaps one array for each orders and blocks containing all keys and values to update.
	 * TODO: Separate blocks to their own class.
	 *
	 * @param int $order_id The ID of the order to update.
	 * @param string $status The new status to set for the order.
	 * @param bool $update_date
	 * @param bool $update_blocks
	 * @param string|null $block_status
	 *
	 * @return void
	 */
	public static function update( int $order_id, string $status, bool $update_date = false, bool $update_blocks = false, ?string $block_status = null ): void {
		global $wpdb;

		if ( in_array( $status, self::valid_order_statuses() ) ) {
			if ( $update_date ) {
				$wpdb->update(
					MDS_DB_PREFIX . "orders",
					[
						'status'     => $status,
						'date_stamp' => current_time( 'mysql' )
					],
					[ 'order_id' => $order_id ],
					[
						'%s',
						'%s'
					],
					[ '%d' ]
				);
			} else {
				$wpdb->update(
					MDS_DB_PREFIX . "orders",
					[ 'status' => $status ],
					[ 'order_id' => $order_id ],
					[ '%s' ],
					[ '%d' ]
				);
			}

		} else {
			Debug::output( 'Invalid status: ' . $status );
			Debug::log_trace();
		}

		if ( $update_blocks ) {
			if ( in_array( $block_status, Blocks::valid_block_statuses() ) ) {
				$wpdb->update(
					MDS_DB_PREFIX . "blocks",
					[ 'status' => $block_status ],
					[ 'order_id' => $order_id ],
					[ '%s' ],
					[ '%d' ]
				);
			} else {
				Debug::output( 'Invalid block status: ' . $block_status );
				Debug::log_trace();
			}
		}
	}

	/**
	 * Renew an order.
	 *
	 * @param int $order_id The ID of the order to be renewed.
	 *
	 * @return void
	 */
	public static function renew( int $order_id ): void {
		global $wpdb;

		$wpdb->update(
			MDS_DB_PREFIX . "orders",
			[
				'status'     => 'renew_paid',
				'date_stamp' => current_time( 'mysql' )
			],
			[ 'order_id' => $order_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Get an orders completion status.
	 *
	 * @param mixed $order_id
	 * @param int $user_id
	 *
	 * @return bool Returns true if the order is in a completed state otherwise false.
	 */
	public static function get_completion_status( mixed $order_id, int $user_id ): bool {
		global $wpdb;
		$table_name = MDS_DB_PREFIX . "orders";
		$sql        = $wpdb->prepare(
			"SELECT COUNT(1) FROM {$table_name} WHERE order_id = %d AND user_id = %d AND (status NOT IN ('new', 'pending') OR approved = 'Y')",
			$order_id,
			$user_id
		);

		$result = $wpdb->get_var( $sql );

		return $result !== false && $result > 0;
	}

	/**
	 * Find a new order for the current user.
	 *
	 * Retrieves the first order with status 'new' from the database
	 * that belongs to the current user.
	 *
	 * @return array|null The order row as an associative array or null if no new order is found.
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 */
	public static function find_new_order(): array|null {
		global $wpdb;
		
		// First check for any new orders in progress - these take priority
		$sql       = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE status='new' AND user_id=%d ORDER BY order_id DESC LIMIT 1", get_current_user_id() );
		$order_row = $wpdb->get_row( $sql, ARRAY_A );
		if ( $wpdb->num_rows > 0 ) {

			// Found an order so set it as the current order.
			self::set_current_order_id( $order_row['order_id'] );

			return $order_row;
		}

		return null;
	}

	/**
	 * Get all orders for the current user.
	 *
	 * @return array
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 */
	public static function get_manage_orders(): array {
		global $wpdb;
		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders AS t1, " . $wpdb->prefix . "users AS t2 WHERE t1.user_id=t2.ID AND t1.user_id=%d ORDER BY t1.order_date DESC ";
		$sql = $wpdb->prepare( $sql, get_current_user_id() );

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Sort by order_date from both orders and temp_orders.
	 *
	 * @param $a
	 * @param $b
	 *
	 * @return int
	 */
	public static function date_sort( $a, $b ): int {
		$dateA = strtotime( $a['order_date'] );
		$dateB = strtotime( $b['order_date'] );

		if ( $dateA == $dateB ) {
			return 0;
		} elseif ( $dateA < $dateB ) {
			return - 1;
		} else {
			return 1;
		}
	}

	/**
	 * Cancel an order.
	 *
	 * @param int $order_id
	 * @param int $user_id
	 *
	 * @return void
	 */
	public static function cancel( int $order_id, int $user_id ): void {

		if ( $order_id == self::get_current_order_id() ) {

			global $wpdb;

			$sql = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id=%d AND order_id=%d", $user_id, $order_id );
			$row = $wpdb->get_row( $sql );

			if ( $row ) {

				// If the cancelled order is in progress then reset the progress.
				if ( self::is_order_in_progress( $order_id ) ) {
					self::reset_order_progress();
				}

				// delete associated ad
				wp_delete_post( intval( $row->ad_id ) );

				// delete blocks
				$sql = $wpdb->prepare( "DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE order_id=%d", $order_id );
				$wpdb->query( $sql );

				// delete associated order
				$sql = $wpdb->prepare( "DELETE FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%d", $order_id );
				$wpdb->query( $sql );

				// delete associated uploaded image
				$imagefile = self::get_tmp_img_name();
				if ( file_exists( $imagefile ) ) {
					unlink( $imagefile );
				}
			}

		} else {
			global $wpdb;
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id = %d AND order_id = %d",
					get_current_user_id(),
					$order_id
				)
			);
			
			if ( $result ) {
				// If the cancelled order is in progress then reset the progress.
				if ( self::is_order_in_progress( $order_id ) ) {
					self::reset_progress();
				}

				self::delete_order( $order_id );
			}
		}
	}

	/**
	 * @param $AUTO_PUBLISH
	 * @param float|int|string $BID
	 *
	 * @return void
	 */
	public static function complete( $AUTO_PUBLISH, float|int|string $BID ): void {
		global $wpdb;

		if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] == self::get_current_order_id() ) {
			// convert the temp order to an order.

			$order_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d",
					intval( self::get_current_order_id() )
				),
				ARRAY_A
			);

			if ( ! $order_row ) {
				// no order id found...
				if ( wp_doing_ajax() ) {
					self::no_orders();
					wp_die();
				}

				Utility::redirect( Utility::get_page_url( 'no-orders' ) );
			} else {
				$_REQUEST['order_id'] = self::reserve_pixels_for_temp_order( $order_row );
			}
		}

		$sql = $wpdb->prepare(
			"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d AND user_id = %d",
			intval( $_REQUEST['order_id'] ),
			get_current_user_id()
		);

		$row = $wpdb->get_row( $sql, ARRAY_A );

		$privileged = carbon_get_user_meta( get_current_user_id(), MDS_PREFIX . 'privileged' );

		if ( ( $row['price'] == 0 ) || ( $privileged == '1' ) ) {
			self::complete_order( $row['user_id'], $row['order_id'] );
			// no transaction for this order
			Language::out( '<h3>Your order was completed.</h3>' );
		}

		// publish
		if ( $AUTO_PUBLISH == 'Y' ) {
			process_image( $BID );
			publish_image( $BID );
			process_map( $BID );
		}
	}

	public static function order_details( $order ): void {
		global $wpdb;

		?>
        <div>
            <b><?php Language::out( "Order Date" ); ?>:</b>
			<?php echo get_date_from_gmt( $order['order_date'] ); ?>
        </div>
        <div>
            <b><?php Language::out( 'Order ID' ); ?>:</b>
			<?php echo isset( $order['order_id'] ) ? '#' . $order['order_id'] : Language::get( 'In progress' ); ?>
        </div>
        <div>
            <b><?php Language::out( "Quantity" ); ?>:</b>
			<?php echo $order['quantity']; ?>
        </div>
        <div>
            <b><?php Language::out( "Grid" ); ?>:</b>
			<?php
			$b_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM " . MDS_DB_PREFIX . "banners WHERE banner_id = %d",
					intval( $order['banner_id'] )
				),
				ARRAY_A
			);

			if ( $b_row ) {
				echo $b_row['name'];
			}

			?>
        </div>
        <div>
            <b><?php Language::out( "Price" ); ?>:</b>
			<?php echo Currency::convert_to_default_currency_formatted( $order['currency'], $order['price'] ); ?>
        </div>
        <div>
            <b><?php Language::out( "Time" ); ?>:</b><br/>
			<?php

			if ( $order['days_expire'] > 0 ) {

				if ( $order['published'] != 'Y' ) {
					$time_start = current_time( 'timestamp' );
				} else {
					$time_start = strtotime( $order['date_published'] );
				}

				$elapsed_time = current_time( 'timestamp' ) - $time_start;

				$exp_time = ( $order['days_expire'] * 24 * 60 * 60 );

				$exp_time_to_go = $exp_time - $elapsed_time;

				$to_go = Utility::elapsedtime( $exp_time_to_go );

				$elapsed = Utility::elapsedtime( $elapsed_time );

				if ( $order['status'] == 'expired' ) {
					$days = "<a href='" . esc_url( Utility::get_page_url( 'payment', [ 'order_id' => $order['order_id'], 'BID' => $order['banner_id'], 'renew' => 'true' ] ) ) . "'>" . Language::get( 'Expired!' ) . "</a>";
				} else if ( $order['date_published'] == '' ) {
					$days = Language::get( 'Not Yet Published' );
				} else {
					$days = Language::get_replace(
						'%ELAPSED% elapsed<br> %TO_GO% to go',
						[ '%ELAPSED%', '%TO_GO%' ],
						[ $elapsed, $to_go ]
					);
				}
			} else {

				$days = Language::get( 'Never' );
			}
			echo $days;
			?>
        </div>
        <div>
			<?php
			if ( isset( $order['status'] ) ) {
				$temp_var = '';
				if ( Options::get_option( 'use-ajax' ) == 'SIMPLE' ) {
					$temp_var = '&order_id=' . $order['order_id'];
				}

				$steps    = Steps::get_steps();
				$USE_AJAX = Options::get_option( 'use-ajax' );

				switch ( $order['status'] ) {
					case "new":
						// Action buttons are now shown in the accordion header
						// Only show status and cancel button in the content area
						$current_step = Steps::get_current_step( $order['order_id'] );
						if ( $current_step != 0 ) {
							echo Language::get( 'In progress' ) . '<br>';
						}
						$cancel_args = [ 'cancel' => 'yes', 'order_id' => $order['order_id'], 'mds_cancel_nonce' => wp_create_nonce( 'mds_cancel_order_' . $order['order_id'] ) ];
						$cancel_url = esc_url( Utility::get_page_url( 'manage', $cancel_args ) );
						echo "<input class='mds-button mds-cancel' type='button' value='" . esc_attr( Language::get( 'Cancel' ) ) . "' data-link='" . $cancel_url . "' onclick='confirmLink(this, \"" . Language::get( 'Cancel, are you sure?' ) . "\")'>";
						break;
					case "confirmed":
						if ( !self::has_payment( $order['order_id'] ) && ( self::is_order_in_progress( $order['order_id'] ) || ( WooCommerceFunctions::is_wc_active() ) ) ) {
							update_user_meta( get_current_user_id(), 'mds_confirm', true );
							// Pay Now button - only show if payment hasn't been made yet
							$pay_args = [ 'order_id' => $order['order_id'], 'BID' => $order['banner_id'] ];
							$pay_url = esc_url( Utility::get_page_url( 'payment', $pay_args ) );
							echo "<input class='mds-button mds-pay' type='button' value='" . esc_attr( Language::get( 'Pay Now' ) ) . "' onclick='window.location=\"" . $pay_url . "\"' />";
							// Cancel button
							$cancel_args = [ 'cancel' => 'yes', 'order_id' => $order['order_id'], 'mds_cancel_nonce' => wp_create_nonce( 'mds_cancel_order_' . $order['order_id'] ) ];
							$cancel_url = esc_url( Utility::get_page_url( 'manage', $cancel_args ) );
							echo "<br><input class='mds-button mds-cancel' type='button' value='" . esc_attr( Language::get( 'Cancel' ) ) . "' data-link='" . $cancel_url . "' onclick='confirmLink(this, \"" . Language::get( 'Cancel, are you sure?' ) . "\")'>";
						} else if ( self::has_payment( $order['order_id'] ) ) {
							// Payment already made - show payment processing status
							echo Language::get( 'Payment submitted and processing...' );
						}
						break;
					case "completed":

						if ( $order['published'] == 'N' ) {
							Language::out( 'Not Yet Published' );
						} else if ( $order['days_expire'] > 0 ) {

							if ( $order['date_published'] != '' ) {
								$time_start = strtotime( $order['date_published'] );
							} else {
								$time_start = current_time( 'timestamp' );
								echo $time_start;
							}

							$elapsed_time   = current_time( 'timestamp' ) - $time_start;
							$exp_time       = $order['days_expire'] * 24 * 60 * 60;
							$exp_time_to_go = $exp_time - $elapsed_time;

							if ( $exp_time_to_go < 0 ) {
								$to_go = "Expired";
							} else {
								$to_go = Utility::elapsedtime( $exp_time_to_go );
							}

							if ( $order['date_published'] != '' ) {
								echo Language::get_replace( 'Expires in: %TIME%', '%TIME%', $to_go );
								echo "<br />";
							}

							if ( Options::get_option( 'order-locking' ) == 'no' ) {
								echo "<a class='mds-button mds-manage' href='" . Utility::get_page_url( 'manage' ) . "?mds-action=manage&aid=" . $order['ad_id'] . "'>" . Language::get( 'Manage' ) . "</a>";
							}
						}

						break;
					case 'renew_wait':
					case "expired":
						$time_expired = strtotime( $order['date_stamp'] );

						$time_when_cancel = $time_expired + ( Options::get_option( 'minutes-renew' ) * 60 );

						$total_minutes = ( $time_when_cancel - time() ) / 60;

						$days              = floor( $total_minutes / 1440 );
						$remaining_minutes = round( $total_minutes ) % 1440;

						$hours   = floor( $remaining_minutes / 60 );
						$minutes = $remaining_minutes % 60;

						// check to see if there is a renew_wait or renew_paid order
						$sql   = $wpdb->prepare( "SELECT order_id FROM " . MDS_DB_PREFIX . "orders WHERE (status = 'renew_paid' OR status = 'renew_wait') AND original_order_id=%d", intval( $order['original_order_id'] ) );
						$res_c = $wpdb->get_results( $sql );

						if ( count( $res_c ) == 0 ) {
							$searchArray  = [ '%DAYS_TO_RENEW%', '%HOURS_TO_RENEW%', '%MINUTES_TO_RENEW%' ];
							$replaceArray = [ $days, $hours, $minutes ];

							echo "<a class='mds-button mds-order-renew' href='" . esc_url( Utility::get_page_url( 'payment' ) ) . "?order_id=" . esc_attr( $order['order_id'] ) . "&BID=" . esc_attr( $order['banner_id'] ) . "&renew=true'>" . esc_html( Language::get_replace( 'Renew Now! %DAYS_TO_RENEW% days, %HOURS_TO_RENEW% hours, and %MINUTES_TO_RENEW% minutes left to renew', $searchArray, $replaceArray ) ) . "</a>";
						}
						break;
					case "pending":
					case "cancelled":
					case "deleted":
						break;
					case "denied":
						echo "<a class='mds-button mds-manage' href='" . Utility::get_page_url( 'manage' ) . "?mds-action=manage&aid=" . $order['ad_id'] . "'>" . Language::get( 'Manage' ) . "</a>";
						break;
					case "paid":
						Language::out( "Thank you for your payment! Your order is awaiting approval." );
						break;
					default:
				}
			} else {
				$temp_var = '&order_id=' . $order['order_id'];
				echo Language::get( 'In progress' ) . '<br>';
				echo "<a href='" . Utility::get_page_url( 'order' ) . "?BID={$order['banner_id']}{$temp_var}'>" . Language::get( 'Confirm now' ) . "</a>";
				$cancel_nonce = wp_create_nonce( 'mds_cancel_order_' . $order['order_id'] );
				echo "<br><input class='mds-button mds-cancel' type='button' value='" . esc_attr( Language::get( 'Cancel' ) ) . "' data-link='" . esc_url( Utility::get_page_url( 'manage' ) . "?cancel=yes{$temp_var}&mds_cancel_nonce=" . $cancel_nonce ) . "' onclick='confirmLink(this, \"" . Language::get( 'Cancel, are you sure?' ) . "\")'>";
			}
			?>
        </div>
		<?php
	}

	public static function no_orders(): void {
		require_once MDS_CORE_PATH . "html/header.php";

		Language::out( '<h1>No new orders in progress</h1>' );
		Language::out_replace(
			'<p>You don\'t have any orders in progress. Please go <a href="%ORDER_URL%">here to order pixels</a>.</p>',
			'%ORDER_URL%',
			Utility::get_page_url( 'order' )
		);

		require_once MDS_CORE_PATH . "html/footer.php";
	}

	public static function order_screen(): void {
		// TODO: handle packages
		if ( ! isset( $_REQUEST['banner_change'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && ! isset( $_POST['type'] ) ) {
			Functions::verify_nonce( 'mds-form' );

			if ( isset( $_POST['mds_dest'] ) ) {
				if ( $_POST['mds_dest'] == 'order' ) {
					// Simple pixel selection
					require_once MDS_CORE_PATH . 'users/order-pixels.php';
				}
			}
		} else {
			require_once MDS_CORE_PATH . 'users/' . ( Options::get_option( 'use-ajax' ) == 'SIMPLE' ? 'order-pixels.php' : 'select.php' );
		}
	}

	public static function get_order_data(): array {

		global $f2, $wpdb;
		$BID = $f2->bid();

		if ( ! is_numeric( $BID ) ) {
			die();
		}

		$banner_data = load_banner_constants( $BID );

		$table_name = MDS_DB_PREFIX . 'orders';
		$user_id    = get_current_user_id();
		$status     = 'new';

		// Only use orders with 'new' status and order_in_progress = 'Y' to ensure clean slate
		$sql = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d AND status = 'new' AND order_in_progress = 'Y' ORDER BY order_id DESC LIMIT 1",
			$user_id
		);
		$order_result = $wpdb->get_row( $sql );
		$order_row    = $order_result ? (array) $order_result : [];

		// load any existing blocks for this order
		$order_row_blocks = ! empty( $order_row['blocks'] ) || isset( $order_row['blocks'] ) && $order_row['blocks'] == '0' ? $order_row['blocks'] : '';
		$block_ids        = $order_row_blocks !== '' ? array_map( 'intval', explode( ',', $order_row_blocks ) ) : [];
		$block_str        = $order_row_blocks !== '' ? implode( ',', $block_ids ) : "";

		$low_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
		$low_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

		$sql    = $wpdb->prepare( "SELECT block_info FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%s", self::get_current_order_id() );
		$result = $wpdb->get_results( $sql );

		if ( ! empty( $result ) ) {
			$block_info = unserialize( $result[0]->block_info );
		}

		$init = false;
		if ( isset( $block_info ) && is_array( $block_info ) ) {

			foreach ( $block_info as $block ) {

				if ( $low_x >= $block['map_x'] ) {
					$low_x = $block['map_x'];
					$init  = true;
				}

				if ( $low_y >= $block['map_y'] ) {
					$low_y = $block['map_y'];
					$init  = true;
				}
			}
		}

		$reinit = false;
		if ( isset( $_REQUEST['banner_change'] ) && $_REQUEST['banner_change'] != '' ) {
			$reinit = true;
		}

		if ( ! $init || $reinit === true ) {
			$low_x     = 0;
			$low_y     = 0;
			$is_moving = true;
		} else {
			$is_moving = false;
		}

		return [
			'ajaxurl'         => admin_url( 'admin-ajax.php' ),
			'mds_nonce'       => esc_js( wp_create_nonce( 'mds_nonce' ) ),
			'NONCE'           => esc_js( wp_create_nonce( 'mds-order' ) ),
			'UPDATE_ORDER'    => esc_url( Utility::get_page_url( 'update-order' ) ),
			'CHECK_SELECTION' => esc_url( Utility::get_page_url( 'check-selection' ) ),
			'MAKE_SELECTION'  => esc_url( Utility::get_page_url( 'make-selection' ) ),
			'low_x'           => intval( $low_x ),
			'low_y'           => intval( $low_y ),
			'is_moving'       => $is_moving,
			'block_str'       => $block_str,
			'grid_width'      => intval( $banner_data['G_WIDTH'] ),
			'grid_height'     => intval( $banner_data['G_HEIGHT'] ),
			'BLK_WIDTH'       => intval( $banner_data['BLK_WIDTH'] ),
			'BLK_HEIGHT'      => intval( $banner_data['BLK_HEIGHT'] ),
			'user_id'         => get_current_user_id(),
			'BID'             => intval( $BID ),
			'time'            => time(),
			'WAIT'            => Language::get( 'Please Wait! Reserving Pixels...' ),
			'WRITE'           => Language::get( 'Write Your Ad' ),
		];
	}

	public static function expire_orders(): void {
		global $wpdb;

		$now       = current_time( 'mysql' );
		$unix_time = time();

		// Get the time of last run from WordPress options
		$last_expire_run = (int) get_option( 'mds_last_expire_run', 0 );

		// Prevent running too frequently (less than 30 seconds apart)
		// This acts as a simple locking mechanism
		if ( $unix_time < $last_expire_run + 30 ) {
			// Function ran recently, exit to prevent overlap
			return;
		}

		// Update the timestamp immediately to act as a lock
		update_option( 'mds_last_expire_run', $unix_time );

		// Delete New Orders that have been around too long
		// $session_duration = intval( ini_get( "session.gc_maxlifetime" ) );
		//
		// $sql = "SELECT order_id, order_date FROM `" . MDS_DB_PREFIX . "orders` WHERE `status`='new' AND DATE_SUB('$now', INTERVAL $session_duration SECOND) >= order_date";
		// $result = $wpdb->get_results( $sql, ARRAY_A );
		// foreach ( $result as $row ) {
		// 	delete_temp_order( $row['order_id'] );
		// }

		$affected_BIDs = array();

		// COMPLETED Orders
		$sql    = $wpdb->prepare(
			"SELECT *, " . MDS_DB_PREFIX . "banners.banner_id as BID 
    FROM " . MDS_DB_PREFIX . "orders, " . MDS_DB_PREFIX . "banners 
    WHERE `status` = 'completed' 
    AND " . MDS_DB_PREFIX . "orders.banner_id = " . MDS_DB_PREFIX . "banners.banner_id 
    AND " . MDS_DB_PREFIX . "orders.days_expire <> 0 
    AND DATE_SUB(%s, INTERVAL " . MDS_DB_PREFIX . "orders.days_expire DAY) >= " . MDS_DB_PREFIX . "orders.date_published 
    AND " . MDS_DB_PREFIX . "orders.date_published IS NOT NULL",
			$now
		);
		$result = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $result as $row ) {
			$affected_BIDs[] = $row['BID'];
			// $expire_days     = intval( $row['days_expire'] );
			// $order_date      = $row['order_date'];
			// $date_published  = $row['date_published'];
			//
			// $order_date_timestamp     = strtotime( $order_date );
			// $date_published_timestamp = strtotime( $date_published );
			//
			// // Calculate the difference in days
			// $diff_days = floor( ( $order_date_timestamp - $date_published_timestamp ) / DAY_IN_SECONDS );
			//
			// if ( $diff_days > $expire_days ) {
			self::expire_order( $row['order_id'] );
			// }
		}

		if ( sizeof( $affected_BIDs ) > 0 ) {
			foreach ( $affected_BIDs as $myBID ) {
				$b_row = load_banner_row( $myBID );
				if ( $b_row['auto_publish'] == 'Y' ) {
					process_image( $myBID );
					publish_image( $myBID );
					process_map( $myBID );
				}
			}
		}
		self::process_paid_renew_orders();
		unset( $affected_BIDs );

		// unconfirmed Orders
		$MINUTES_UNCONFIRMED = Options::get_option( 'minutes-unconfirmed' );
		if ( $MINUTES_UNCONFIRMED != 0 ) {

			$sql = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE `status`=%s", 'new' );
			if ( $MINUTES_UNCONFIRMED != - 1 ) {
				$sql .= $wpdb->prepare( " AND DATE_SUB(%s, INTERVAL %d MINUTE) >= date_stamp AND date_stamp IS NOT NULL", $now, $MINUTES_UNCONFIRMED );
			}

			// Debug: SELECT * FROM wp_mds_orders WHERE `status`='new' AND DATE_SUB('2024-02-07 11:28:31', INTERVAL 1 MINUTE) >= date_stamp AND date_stamp IS NOT NULL

			$results = $wpdb->get_results( $sql, ARRAY_A );

			foreach ( $results as $row ) {

				// Double-check the date before deleting
				// $order_date = strtotime( $row['order_date'] );
				// $order_date = date( 'Y-m-d H:i:s', $order_date );
				// $diff       = strtotime( $order_date ) - strtotime( $now );
				// if ( $diff > intval( $MINUTES_UNCONFIRMED ) ) {
				self::delete_order( $row['order_id'] );

				// Now really delete the order.
				$sql = $wpdb->prepare( "DELETE FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d", intval( $row['order_id'] ) );
				$wpdb->query( $sql );
				global $f2;
				$f2->debug( "Deleted unconfirmed order - " . $sql );
				// }
			}
		}

		// unpaid Orders
		$MINUTES_CONFIRMED = Options::get_option( 'minutes-confirmed' );
		if ( $MINUTES_CONFIRMED != 0 ) {

			$additional_condition = "";
			if ( $MINUTES_CONFIRMED != - 1 ) {
				$additional_condition = $wpdb->prepare( " AND DATE_SUB(%s, INTERVAL %d MINUTE) >= date_stamp AND date_stamp IS NOT NULL", $now, $MINUTES_CONFIRMED );
			}

			$sql     = sprintf( "SELECT * FROM %sorders WHERE `status` = 'confirmed' %s", MDS_DB_PREFIX, $additional_condition );
			$results = $wpdb->get_results( $sql, ARRAY_A );

			foreach ( $results as $row ) {

				// $order_date = strtotime( $row['order_date'] );
				// $order_date = date( 'Y-m-d H:i:s', $order_date );
				// $diff       = strtotime( $order_date ) - strtotime( $now );
				// if ( $diff > intval( $MINUTES_CONFIRMED ) ) {
				self::delete_order( $row['order_id'] );
				// }
			}
		}

		// EXPIRED Orders -> Cancel
		$MINUTES_RENEW = intval( Options::get_option( 'minutes-renew' ) );
		if ( $MINUTES_RENEW != 0 ) {

			$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE `status`='expired'";
			if ( $MINUTES_RENEW != - 1 ) {
				$sql .= " AND DATE_SUB('$now',INTERVAL " . $MINUTES_RENEW . " MINUTE) >= date_stamp AND date_stamp IS NOT NULL";
			}

			$results = $wpdb->get_results( $sql, ARRAY_A );

			if ( $results ) {
				foreach ( $results as $row ) {
					// $order_date = strtotime( $row['order_date'] );
					// $order_date = date( 'Y-m-d H:i:s', $order_date );
					// $diff       = strtotime( $order_date ) - strtotime( $now );
					// if ( $diff > intval( $MINUTES_RENEW ) ) {
					self::cancel_order( $row['order_id'] );
					// }
				}
			}
		}

		// Cancelled Orders -> Delete
		$MINUTES_CANCEL = intval( Options::get_option( 'minutes-cancel' ) );
		if ( $MINUTES_CANCEL != 0 ) {

			$MINUTES_CANCEL = intval( $MINUTES_CANCEL );
			$sql            = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE `status`='cancelled' AND DATE_SUB(%s, INTERVAL %d MINUTE) >= date_stamp AND date_stamp IS NOT NULL", $now, $MINUTES_CANCEL );
			$results        = $wpdb->get_results( $sql, ARRAY_A );
			foreach ( $results as $row ) {
				// $order_date = strtotime( $row['order_date'] );
				// $order_date = date( 'Y-m-d H:i:s', $order_date );
				// $diff       = strtotime( $order_date ) - strtotime( $now );
				// if ( $diff > $MINUTES_CANCEL ) {
				self::delete_order( $row['order_id'] );
				// }
			}
		}

		// Update last run time stamp in WordPress options
		update_option( 'mds_last_expire_run', $unix_time );
	}

	public static function delete_temp_order( $sid, $delete_ad = true ) {
		global $wpdb;

		$order_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %s",
				$sid
			),
			ARRAY_A
		);

		//$sql = "DELETE FROM blocks WHERE session_id='".$sid."' ";
		//$wpdb->query($sql) ;

		$wpdb->delete(
			MDS_DB_PREFIX . "orders",
			array( 'order_id' => $sid ),
			array( '%s' )
		);

		if ( $delete_ad && isset( $order_row['ad_id'] ) ) {
			wp_delete_post( $order_row['ad_id'] );
		}

		// delete the temp order image... and block info...

		$f = self::get_tmp_img_name();
		if ( file_exists( $f ) ) {
			unlink( $f );
		}
	}

	/**
	 * Completes an order.
	 *
	 * @param int $user_id The ID of the user.
	 * @param int $order_id The ID of the order.
	 * @param bool $send_email Whether to send an email or not. Default is true.
	 *
	 * @return void
	 */
	public static function complete_order( int $user_id, int $order_id, bool $send_email = true ): void {
		self::confirm_order( $user_id, $order_id, false );

		global $wpdb;
		$order_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d",
				intval( $order_id )
			),
			ARRAY_A
		);

		if ( $order_row['status'] != 'completed' ) {
			$now = current_time( 'mysql' );

			global $wpdb;

			$steps        = Steps::get_steps( false );
			$current_step = $steps['complete'];

			$table_name   = MDS_DB_PREFIX . "orders";
			$data         = array(
				'status'            => 'completed',
				'date_published'    => null,
				'date_stamp'        => $now,
				'order_in_progress' => 'N',
				'current_step'      => $current_step
			);
			$data_format  = array(
				'%s',
				null,
				'%s',
				'%s',
				'%d'
			);
			$where        = array(
				'order_id' => intval( $order_id )
			);
			$where_format = array( '%d' );
			$updated = $wpdb->update( $table_name, $data, $where, $data_format, $where_format );

			// Fetch WC Order ID from the associated Ad post meta
			$wc_order_id = null;
			if ( ! empty( $order_row['ad_id'] ) ) {
				$wc_order_id = get_post_meta( $order_row['ad_id'], '_wc_order_id', true );
			}

			// Update WooCommerce order status if applicable and MDS update was successful
			// Use the $wc_order_id fetched from post meta
			if ( $updated !== false && class_exists( 'WooCommerce' ) && ! empty( $wc_order_id ) ) {
				$wc_order_id_int = intval( $wc_order_id ); // Ensure it's an integer
				$wc_order    = wc_get_order( $wc_order_id_int );
				if ( $wc_order ) {
					if ( $wc_order->get_status() !== 'completed' ) {
						$wc_order->update_status( 'completed', __( 'MDS order marked as completed.', 'milliondollarscript-two' ) );
					} else {
						Logs::log( '[MDS] WC order ' . $wc_order_id_int . ' already completed.' );
					}
				} else {
					Logs::log( '[MDS] Could not load WC order with ID ' . $wc_order_id_int );
				}
			}

			// Update mds-pixel post status
			wp_update_post( [
				'ID'          => $order_row['ad_id'],
				'post_status' => 'completed',
			] );

			// insert a transaction

			// mark pixels as sold.

			$order_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d",
					intval( $order_id )
				),
				ARRAY_A
			);

			if ( strpos( $order_row['blocks'], "," ) !== false ) {
				$blocks = explode( ",", $order_row['blocks'] );
			} else {
				$blocks = array( 0 => $order_row['blocks'] );
			}
			foreach ( $blocks as $key => $val ) {
				$wpdb->update(
					MDS_DB_PREFIX . "blocks",
					array( 'status' => 'sold' ),
					array( 'block_id' => intval( $val ), 'banner_id' => intval( $order_row['banner_id'] ) ),
					array( '%s' ),
					array( '%d', '%d' )
				);
			}

			$user_info = get_userdata( intval( $user_id ) );

			if ( $order_row['days_expire'] == 0 ) {
				$order_row['days_expire'] = Language::get( 'Never' );
			}

			$banner_data = load_banner_constants( $order_row['banner_id'] );
			$block_count = $order_row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

			$price = Currency::convert_to_default_currency_formatted( $order_row['currency'], $order_row['price'] );

			do_action( 'mds_order_completed', $order_id );

			if ( $send_email ) {

				$search = [
					'%SITE_NAME%',
					'%FIRST_NAME%',
					'%LAST_NAME%',
					'%USER_LOGIN%',
					'%ORDER_ID%',
					'%PIXEL_COUNT%',
					'%BLOCK_COUNT%',
					'%PIXEL_DAYS%',
					'%PRICE%',
					'%SITE_CONTACT_EMAIL%',
					'%SITE_URL%',
				];

				$replace = [
					get_bloginfo( 'name' ),
					$user_info->first_name,
					$user_info->last_name,
					$user_info->user_login,
					$order_row['order_id'],
					$order_row['quantity'],
					$block_count,
					$order_row['days_expire'],
					$price,
					get_bloginfo( 'admin_email' ),
					get_site_url(),
				];

				$subject = Emails::get_email_replace(
					$search,
					$replace,
					'order-completed-subject'
				);

				$message = Emails::get_email_replace(
					$search,
					$replace,
					'order-completed-content'
				);

				// Send email to user if enabled
				if ( Emails::get_email( 'email-user-order-completed' ) === 'yes' ) {
					Mail::send( $user_info->user_email, $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 1 );
				}

				// Send a copy to admin if enabled
				if ( Emails::get_email( 'email-admin-order-completed' ) === 'yes' ) {
					Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 1 );
				}
			}

			// process the grid, if auto_publish is on

			$b_row = load_banner_row( $order_row['banner_id'] );

			if ( $b_row['auto_publish'] == 'Y' ) {
				process_image( $order_row['banner_id'] );
				publish_image( $order_row['banner_id'] );
				process_map( $order_row['banner_id'] );
			}
		}
	}

	/**
	 * Generate a confirmation for the order.
	 *
	 * @param int $user_id User ID
	 * @param int $order_id Order ID
	 * @param bool $send_email Whether to send the email or not
	 *
	 * @throws void
	 */
	public static function confirm_order( int $user_id, int $order_id, bool $send_email = true ): void {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *, t1.blocks as BLK, t1.ad_id as AID FROM " . MDS_DB_PREFIX . "orders as t1, " . $wpdb->prefix . "users as t2 WHERE t1.user_id=t2.ID AND order_id = %d",
				intval( $order_id )
			),
			ARRAY_A
		);

		if ( $row['status'] != 'confirmed' && $row['status'] != 'completed' ) {
			$user_info = get_userdata( $row['ID'] );

			// Update mds-pixel post status
			wp_update_post( [
				'ID'          => $row['AID'],
				'post_status' => 'confirmed',
			] );

			$now = current_time( 'mysql' );

			$wpdb->update(
				MDS_DB_PREFIX . "orders",
				array( 'status' => 'confirmed', 'date_stamp' => $now ),
				array( 'order_id' => intval( $order_id ) ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$wpdb->update(
				MDS_DB_PREFIX . "blocks",
				array( 'status' => 'ordered' ),
				array( 'order_id' => intval( $order_id ), 'banner_id' => intval( $row['banner_id'] ) ),
				array( '%s' ),
				array( '%d', '%d' )
			);

			if ( $row['days_expire'] == 0 ) {
				$row['days_expire'] = Language::get( 'Never' );
			}

			$banner_data = load_banner_constants( $row['banner_id'] );
			$block_count = $row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

			$price = Currency::convert_to_default_currency_formatted( $row['currency'], $row['price'] );

			if ( $send_email ) {
				$search = [
					'%SITE_NAME%',
					'%FIRST_NAME%',
					'%LAST_NAME%',
					'%USER_LOGIN%',
					'%ORDER_ID%',
					'%PIXEL_COUNT%',
					'%BLOCK_COUNT%',
					'%PIXEL_DAYS%',
					'%DEADLINE%',
					'%PRICE%',
					'%SITE_CONTACT_EMAIL%',
					'%SITE_URL%',
				];

				$replace = [
					get_bloginfo( 'name' ),
					$user_info->first_name,
					$user_info->last_name,
					$user_info->user_login,
					$row['order_id'],
					$row['quantity'],
					$block_count,
					$row['days_expire'],
					Options::get_option( 'minutes-confirmed' ),
					$price,
					get_bloginfo( 'admin_email' ),
					get_site_url(),
				];

				$subject = Emails::get_email_replace(
					$search,
					$replace,
					'order-confirmed-subject'
				);

				$message = Emails::get_email_replace(
					$search,
					$replace,
					'order-confirmed-content'
				);

				// Send email to user if enabled
				if ( Emails::get_email( 'email-user-order-confirmed' ) === 'yes' ) {
					Mail::send( $user_info->user_email, $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 2 );
				}

				// Send a copy to admin if enabled
				if ( Emails::get_email( 'email-admin-order-confirmed' ) === 'yes' ) {
					Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 2 );
				}
			}
		} else if ( $row['status'] == 'confirmed' ) {
			$now = current_time( 'mysql' );
			$sql = $wpdb->prepare( "UPDATE " . MDS_DB_PREFIX . "orders set status='paid', date_stamp='%s' WHERE order_id=%d ", $now, intval( $order_id ) );
			$wpdb->query( $sql );
		}
	}

	public static function pend_order( $order_id ) {
		global $wpdb;
		
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *, t1.blocks as BLK, t1.ad_id as AID FROM " . MDS_DB_PREFIX . "orders as t1, " . $wpdb->prefix . "users as t2 WHERE t1.user_id=t2.ID AND order_id = %d",
				intval( $order_id )
			),
			ARRAY_A
		);

		if ( $row && $row['status'] != 'pending' && $row['status'] != 'completed' ) {
			$user_info = get_userdata( $row['ID'] );

			// Update mds-pixel post status
			wp_update_post( [
				'ID'          => $row['AID'],
				'post_status' => 'pending',
			] );

			$now = current_time( 'mysql' );

			$wpdb->update(
				MDS_DB_PREFIX . "orders",
				array( 'status' => 'pending', 'date_stamp' => $now ),
				array( 'order_id' => intval( $order_id ) ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$wpdb->update(
				MDS_DB_PREFIX . "blocks",
				array( 'status' => 'ordered' ),
				array( 'order_id' => intval( $order_id ), 'banner_id' => intval( $row['banner_id'] ) ),
				array( '%s' ),
				array( '%d', '%d' )
			);

			if ( $row['days_expire'] == 0 ) {
				$row['days_expire'] = Language::get( 'Never' );
			}

			$banner_data = load_banner_constants( $row['banner_id'] );
			$block_count = $row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

			$price = Currency::convert_to_default_currency_formatted( $row['currency'], $row['price'] );

			// TODO: Add a database update to convert the FNAME and LNAME to FIRST_NAME and LAST_NAME in the option for this email.
			$search = [
				'%SITE_NAME%',
				'%FIRST_NAME%',
				'%LAST_NAME%',
				'%FNAME%',
				'%LNAME%',
				'%USER_LOGIN%',
				'%ORDER_ID%',
				'%PIXEL_COUNT%',
				'%BLOCK_COUNT%',
				'%PIXEL_DAYS%',
				'%PRICE%',
				'%SITE_CONTACT_EMAIL%',
				'%SITE_URL%',
			];

			$replace = [
				get_bloginfo( 'name' ),
				$user_info->first_name,
				$user_info->last_name,
				$user_info->first_name,
				$user_info->last_name,
				$user_info->user_login,
				$row['order_id'],
				$row['quantity'],
				$block_count,
				$row['days_expire'],
				$price,
				get_bloginfo( 'admin_email' ),
				get_site_url(),
			];

			$subject = Emails::get_email_replace(
				$search,
				$replace,
				'order-pending-subject'
			);

			$message = Emails::get_email_replace(
				$search,
				$replace,
				'order-pending-content'
			);

			// Send email to user if enabled
			if ( Emails::get_email( 'email-user-order-pended' ) === 'yes' ) {
				Mail::send( $user_info->user_email, $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 3 );
			}

			// Send a copy to admin if enabled
			if ( Emails::get_email( 'email-admin-order-pended' ) === 'yes' ) {
				Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 3 );
			}
		}
	}

	public static function expire_order( $order_id ) {
		global $wpdb;
		
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *, t1.banner_id as BID, t1.user_id as UID, t1.ad_id as AID FROM " . MDS_DB_PREFIX . "orders as t1, " . $wpdb->prefix . "users as t2 WHERE t1.user_id=t2.ID AND order_id = %d",
				intval( $order_id )
			),
			ARRAY_A
		);

		if ( $row && ( $row['status'] != 'expired' ) || ( $row['status'] != 'pending' ) ) {
			$user_info = get_userdata( $row['ID'] );

			// Update mds-pixel post status
			wp_update_post( [
				'ID'          => $row['AID'],
				'post_status' => 'expired',
			] );

			$now = current_time( 'mysql' );

			$wpdb->update(
				MDS_DB_PREFIX . "orders",
				array( 'status' => 'expired', 'date_stamp' => $now, 'approved' => 'N', 'order_in_progress' => 'N' ),
				array( 'order_id' => intval( $order_id ) ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			$wpdb->update(
				MDS_DB_PREFIX . "blocks",
				array( 'status' => 'ordered', 'approved' => 'N' ),
				array( 'order_id' => intval( $order_id ), 'banner_id' => intval( $row['BID'] ) ),
				array( '%s', '%s' ),
				array( '%d', '%d' )
			);

			if ( $row['status'] == 'new' ) {
				// do not send email
				return;
			}

			if ( $row['days_expire'] == 0 ) {
				$row['days_expire'] = Language::get( 'Never' );
			}

			$banner_data = load_banner_constants( $row['banner_id'] );
			$block_count = $row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

			$price = Currency::convert_to_default_currency_formatted( $row['currency'], $row['price'] );

			// Clear the order from the users cart.
			WooCommerceFunctions::remove_item_from_cart( $row['user_id'], $order_id );
			self::reset_progress( $row['user_id'] );

			$search = [
				'%SITE_NAME%',
				'%FIRST_NAME%',
				'%LAST_NAME%',
				'%USER_LOGIN%',
				'%ORDER_ID%',
				'%PIXEL_COUNT%',
				'%BLOCK_COUNT%',
				'%PIXEL_DAYS%',
				'%PRICE%',
				'%SITE_CONTACT_EMAIL%',
				'%SITE_URL%',
			];

			$replace = [
				get_bloginfo( 'name' ),
				$user_info->first_name,
				$user_info->last_name,
				$user_info->user_login,
				$row['order_id'],
				$row['quantity'],
				$block_count,
				$row['days_expire'],
				$price,
				get_bloginfo( 'admin_email' ),
				get_site_url(),
			];

			$message = Emails::get_email_replace(
				$search,
				$replace,
				'order-expired-content'
			);

			$subject = Emails::get_email_replace(
				$search,
				$replace,
				'order-expired-subject'
			);

			// Send email to user if enabled
			if ( Emails::get_email( 'email-user-order-expired' ) === 'yes' ) {
				Mail::send( $user_info->user_email, $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 4 );
			}

			// Send a copy to admin if enabled
			if ( Emails::get_email( 'email-admin-order-expired' ) === 'yes' ) {
				Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 4 );
			}
		}
	}

	public static function delete_order( $order_id ): void {
		global $wpdb;

		$order_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d",
				intval( $order_id )
			),
			ARRAY_A
		);

		if ( $order_row && $order_row['status'] != 'deleted' ) {

			// Update mds-pixel post status
			wp_update_post( [
				'ID'          => $order_row['ad_id'],
				'post_status' => 'deleted',
			] );

			$now = current_time( 'mysql' );

			$wpdb->update(
				MDS_DB_PREFIX . "orders",
				array( 'status' => 'deleted', 'date_stamp' => $now, 'order_in_progress' => 'N' ),
				array( 'order_id' => intval( $order_id ) ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			// DELETE BLOCKS

			if ( $order_row['blocks'] != '' ) {
				$wpdb->delete(
					MDS_DB_PREFIX . "blocks",
					array( 'order_id' => intval( $order_id ), 'banner_id' => intval( $order_row['banner_id'] ) ),
					array( '%d', '%d' )
				);
			}

			// DELETE ADS
			wp_delete_post( $order_row['ad_id'] );
		}
	}

	public static function cancel_order( $order_id, $bypass_auth = false ) {
		// Input validation
		$order_id = intval( $order_id );
		if ( $order_id <= 0 ) {
			\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: Invalid order ID in cancel_order - Order ID: ' . $order_id . ' - User: ' . get_current_user_id() );
			return false;
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d",
				$order_id
			),
			ARRAY_A
		);
		
		if ( ! $row ) {
			\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: Order not found in cancel_order - Order ID: ' . $order_id . ' - User: ' . get_current_user_id() );
			return false;
		}

		// Authorization checks (unless bypassed by admin operations)
		if ( ! $bypass_auth ) {
			$current_user_id = get_current_user_id();
			
			// Check if user is logged in
			if ( ! $current_user_id ) {
				\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: Unauthorized cancel_order attempt by non-logged user - Order ID: ' . $order_id );
				return false;
			}
			
			// Check if user owns the order or is admin
			if ( $row['user_id'] != $current_user_id && ! current_user_can( 'manage_options' ) ) {
				\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: Unauthorized cancel_order attempt - Order ID: ' . $order_id . ' - Order Owner: ' . $row['user_id'] . ' - Current User: ' . $current_user_id );
				return false;
			}
		}

		if ( $row['status'] != 'cancelled' ) {

			// Update mds-pixel post status
			wp_update_post( [
				'ID'          => $row['ad_id'],
				'post_status' => 'cancelled',
			] );

			$now = current_time( 'mysql' );

			$wpdb->update(
				MDS_DB_PREFIX . "orders",
				array( 'status' => 'cancelled', 'date_stamp' => $now, 'approved' => 'N', 'order_in_progress' => 'N' ),
				array( 'order_id' => intval( $order_id ) ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			$wpdb->update(
				MDS_DB_PREFIX . "blocks",
				array( 'status' => 'cancelled', 'approved' => 'N' ),
				array( 'order_id' => intval( $order_id ), 'banner_id' => intval( $row['banner_id'] ) ),
				array( '%s', '%s' ),
				array( '%d', '%d' )
			);
		}

		// process the grid, if auto_publish is on
		$b_row = load_banner_row( $row['banner_id'] );
		if ( $b_row['auto_publish'] == 'Y' ) {
			process_image( $row['banner_id'] );
			publish_image( $row['banner_id'] );
			process_map( $row['banner_id'] );
		}
		
		// Log successful cancellation
		$context = $bypass_auth ? 'admin bypass' : 'user action';
		\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: Order cancelled successfully - Order ID: ' . $order_id . ' - User: ' . get_current_user_id() . ' - Context: ' . $context );
		
		return true;
	}

	public static function process_paid_renew_orders() {
		//Complete: Only expired orders that have status as 'renew_paid'
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE status='renew_paid'",
			ARRAY_A
		);
		
		foreach ( $results as $row ) {
			// if expired
			self::complete_renew_order( $row['order_id'] );
		}
	}

	public static function complete_renew_order( $order_id ) {
		global $wpdb;
		$order_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d AND status = 'renew_paid'",
				intval( $order_id )
			),
			ARRAY_A
		);

		if ( $order_row['status'] != 'completed' ) {

			// Update mds-pixel post status
			wp_update_post( [
				'ID'          => $order_row['ad_id'],
				'post_status' => 'completed',
			] );

			$now = current_time( 'mysql' );

			$wpdb->update(
				MDS_DB_PREFIX . "orders",
				array( 'status' => 'completed', 'date_published' => null, 'date_stamp' => $now ),
				array( 'order_id' => intval( $order_id ) ),
				array( '%s', null, '%s' ),
				array( '%d' )
			);

			// update pixel's order_id

			$wpdb->update(
				MDS_DB_PREFIX . "blocks",
				array( 'order_id' => intval( $order_row['order_id'] ) ),
				array( 'order_id' => intval( $order_row['original_order_id'] ), 'banner_id' => intval( $order_row['banner_id'] ) ),
				array( '%d' ),
				array( '%d', '%d' )
			);

			// update ads' order id
			carbon_set_post_meta( $order_row['ad_id'], 'order', intval( $order_row['order_id'] ) );

			// mark pixels as sold.

			$order_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d",
					intval( $order_id )
				),
				ARRAY_A
			);

			if ( strpos( $order_row['blocks'], "," ) !== false ) {
				$blocks = explode( ",", $order_row['blocks'] );
			} else {
				$blocks = array( 0 => $order_row['blocks'] );
			}
			foreach ( $blocks as $key => $val ) {
				$wpdb->update(
					MDS_DB_PREFIX . "blocks",
					array( 'status' => 'sold' ),
					array( 'block_id' => intval( $val ), 'banner_id' => intval( $order_row['banner_id'] ) ),
					array( '%s' ),
					array( '%d', '%d' )
				);
			}

			$user_info = get_userdata( intval( $order_row['user_id'] ) );

			if ( $order_row['days_expire'] == 0 ) {
				$order_row['days_expire'] = Language::get( 'Never' );
			}

			$banner_data = load_banner_constants( $order_row['banner_id'] );
			$block_count = $order_row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

			$price = Currency::convert_to_default_currency_formatted( $order_row['currency'], $order_row['price'] );

			// TODO: Add a database update to convert the FNAME and LNAME to FIRST_NAME and LAST_NAME in the option for this email.
			$search = [
				'%SITE_NAME%',
				'%FIRST_NAME%',
				'%LAST_NAME%',
				'%FNAME%',
				'%LNAME%',
				'%USER_LOGIN%',
				'%ORDER_ID%',
				'%ORIGINAL_ORDER_ID%',
				'%PIXEL_COUNT%',
				'%BLOCK_COUNT%',
				'%PIXEL_DAYS%',
				'%PRICE%',
				'%SITE_CONTACT_EMAIL%',
				'%SITE_URL%',
			];

			$replace = [
				get_bloginfo( 'name' ),
				$user_info->first_name,
				$user_info->last_name,
				$user_info->first_name,
				$user_info->last_name,
				$user_info->user_login,
				$order_row['order_id'],
				$order_row['original_order_id'],
				$order_row['quantity'],
				$block_count,
				$order_row['days_expire'],
				$price,
				get_bloginfo( 'admin_email' ),
				get_site_url(),
			];

			$message = Emails::get_email_replace(
				$search,
				$replace,
				'order-renewal-content'
			);

			$subject = Emails::get_email_replace(
				$search,
				$replace,
				'order-renewal-subject'
			);

			// Send email to user if enabled
			if ( Emails::get_email( 'email-user-order-completed-renewal' ) === 'yes' ) {
				Mail::send( $user_info->user_email, $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 1 );
			}

			// Send a copy to admin if enabled
			if ( Emails::get_email( 'email-admin-order-completed-renewal' ) === 'yes' ) {
				Mail::send( get_bloginfo( 'admin_email' ), $subject, $message, $user_info->first_name . " " . $user_info->last_name, get_bloginfo( 'admin_email' ), get_bloginfo( 'name' ), 1 );
			}

			// process the grid, if auto_publish is on

			$b_row = load_banner_row( $order_row['banner_id'] );

			if ( $b_row['auto_publish'] == 'Y' ) {
				process_image( $order_row['banner_id'] );
				publish_image( $order_row['banner_id'] );
				process_map( $order_row['banner_id'] );
			}
		}
	}

	public static function display_order( $order_id, $BID ): void {
		global $wpdb;

		$BID   = intval( $BID );
		$sql   = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "banners WHERE banner_id=%d", $BID );
		$b_row = $wpdb->get_row( $sql, ARRAY_A );

		if ( ! $b_row ) {
			echo '<div class="error">' . Language::get( 'Error: Grid not found' ) . '</div>';

			return;
		}

		$sql       = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%d", intval( $order_id ) );
		$order_row = $wpdb->get_row( $sql, ARRAY_A );

		?>
        <div class="mds-order-details">
            <div class="mds-order-details-section">
                <b><?php Language::out( 'Preview:' ); ?></b>
				<?php Ajax::get_ad( $order_row['ad_id'] ); ?>
            </div>
			<?php if ( isset( $order_row['order_id'] ) && $order_row['order_id'] != '' ) { ?>
                <div class="mds-order-details-section">
                    <b><?php Language::out( 'Order ID:' ); ?></b>
					<?php echo intval( $order_row['order_id'] ); ?>
                </div>
			<?php } ?>
            <div class="mds-order-details-section">
                <b><?php Language::out( 'Date:' ); ?></b>
				<?php echo esc_html( $order_row['order_date'] ); ?>
            </div>
            <div class="mds-order-details-section">
                <b><?php Language::out( 'Grid Name:' ); ?></b>
				<?php echo esc_html( $b_row['name'] ); ?>
            </div>
            <div class="mds-order-details-section">
                <b><?php Language::out( 'Quantity:' ); ?></b>
				<?php
				$STATS_DISPLAY_MODE = Options::get_option( 'stats-display-mode' );
				if ( $STATS_DISPLAY_MODE == "PIXELS" ) {
					echo intval( $order_row['quantity'] );
					echo " " . Language::get( 'pixels' );
				} else {
					echo intval( $order_row['quantity'] / ( $b_row['block_width'] * $b_row['block_height'] ) );
					echo " " . Language::get( 'blocks' );
				}
				?>
            </div>
            <div class="mds-order-details-section">
                <b><?php Language::out( 'Expires:' ); ?></b>
				<?php
				if ( $order_row['days_expire'] == 0 ) {
					Language::out( 'Never' );
				} else {
					Language::out_replace( 'In %DAYS_EXPIRE% days from date of publishment', '%DAYS_EXPIRE%', $order_row['days_expire'] );
				}
				?>
            </div>
            <div class="mds-order-details-section">
                <b><?php Language::out( 'Price:' ); ?></b>
				<?php echo esc_html( Currency::convert_to_default_currency_formatted( $order_row['currency'], $order_row['price'] ) ); ?>
            </div>
			<?php if ( isset( $order_row['order_id'] ) && $order_row['order_id'] != '' ) { ?>
                <div class="mds-order-details-section">
                    <b><?php Language::out( 'Status:' ); ?></b>
					<?php echo esc_html( ucfirst( $order_row['status'] ) ); ?>
                </div>
			<?php } ?>
        </div>

		<?php
	}

	public static function reserve_pixels_for_temp_order( $temp_order_row ) {
		global $wpdb;

		$banner_data = load_banner_constants( $temp_order_row['banner_id'] );

		// check if the user can get the order
		if ( ! self::can_user_order( $banner_data, get_current_user_id(), intval( $temp_order_row['package_id'] ) ) ) {
			$error_message = Language::get( 'Cannot place pixels on order. You have reached the order limit for this grid. Please review your orders under Manage Pixels.' );

			if ( wp_doing_ajax() ) {
				echo json_encode( [
					"error" => "true",
					"type"  => "max_orders",
					"data"  => [
						"value" => $error_message,
					]
				] );

				die();
			}

			echo $error_message;

			die();
		}

		require_once MDS_CORE_PATH . 'include/ads.inc.php';

		// Session may have expired if they waited too long so tell them to start over, even though we might still have the file it doesn't match the current session id anymore.
		$block_info       = array();
		$current_order_id = $temp_order_row['order_id'];

		$sql = $wpdb->prepare( "SELECT block_info FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%s", $current_order_id );
		$row = $wpdb->get_row( $sql, ARRAY_A );

		if ( ! empty( $row ) ) {
			$block_info = unserialize( $row['block_info'] );
		}

		$in_str = $temp_order_row['blocks'];

		// Validate $in_str is comma separated integers.
		if ( ! preg_match( '/^(\d+(,\s*\d+)*)$/', $in_str ) ) {
			return false;
		}

		$banner_id = intval( $temp_order_row['banner_id'] );
		$sql       = $wpdb->prepare( "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE banner_id=%d AND block_id IN($in_str) AND order_id != %d", $banner_id, $current_order_id );
		$result    = $wpdb->get_results( $sql );

		if ( ! empty( $result ) ) {
			// the pixels are not available!
			return false;
		}

		// approval status, default is N
		$banner_row = load_banner_row( $temp_order_row['banner_id'] );
		$approved   = $banner_row['auto_approve'];

		$now = current_time( 'mysql' );

		$sql = $wpdb->prepare(
			"UPDATE " . MDS_DB_PREFIX . "orders
        SET user_id = %d,
            blocks = %s,
            status = 'new',
            order_date = %s,
            price = %f,
            quantity = %d,
            banner_id = %d,
            currency = %s,
            days_expire = %d,
            date_stamp = %s,
            package_id = %d,
            ad_id = %d,
            approved = %s
        WHERE order_id = %d",
			get_current_user_id(),
			$in_str,
			$now,
			floatval( $temp_order_row['price'] ),
			intval( $temp_order_row['quantity'] ),
			intval( $temp_order_row['banner_id'] ),
			Currency::get_default_currency(),
			intval( $temp_order_row['days_expire'] ),
			$now,
			intval( $temp_order_row['package_id'] ),
			intval( $temp_order_row['ad_id'] ),
			$approved,
			self::get_current_order_id()
		);
		$wpdb->query( $sql );
		$order_id = self::get_current_order_id();

		$sql = $wpdb->prepare( "UPDATE " . MDS_DB_PREFIX . "orders SET original_order_id=%d WHERE order_id=%d", $order_id, $order_id );
		$wpdb->query( $sql );

		$url      = carbon_get_post_meta( $temp_order_row['ad_id'], MDS_PREFIX . 'url' );
		$alt_text = carbon_get_post_meta( $temp_order_row['ad_id'], MDS_PREFIX . 'text' );

		if ( is_array( $block_info ) ) {

			// Remove previously reserved blocks for this order.
			$wpdb->delete(
				MDS_DB_PREFIX . "blocks",
				array(
					'order_id' => $order_id
				),
				array(
					'%d'
				)
			);

			foreach ( $block_info as $key => $block ) {
				$sql = $wpdb->prepare( "REPLACE INTO `" . MDS_DB_PREFIX . "blocks` ( `block_id`, `user_id`, `status`, `x`, `y`, `image_data`, `url`, `alt_text`, `approved`, `banner_id`, `currency`, `price`, `order_id`, `ad_id`, `click_count`, `view_count`) VALUES (%d, %d, 'reserved', %d, %d, %s, %s, %s, %s, %d, %s, %f, %d, %d, 0, 0)",
					intval( $key ),
					get_current_user_id(),
					intval( $block['map_x'] ),
					intval( $block['map_y'] ),
					$block['image_data'],
					$url,
					$alt_text,
					$approved,
					intval( $temp_order_row['banner_id'] ),
					Currency::get_default_currency(),
					floatval( $block['price'] ),
					intval( $order_id ),
					intval( $temp_order_row['ad_id'] )
				);
				$wpdb->query( $sql );
			}
		}

		//delete_temp_order( get_current_order_id(), false );

		// false = do not delete the ad...

		return $order_id;
	}

	public static function move_order( $block_from, $block_to, $banner_id ) {

		//move_block($block_from, $block_to, $banner_id);

		// get the block_to x,y
		$pos  = Blocks::get_block_position( $block_to, $banner_id );
		$to_x = $pos['x'];
		$to_y = $pos['y'];

		// we need to work out block_from, get the block with the lowest x and y

		$min_max = Blocks::get_blocks_min_max( $block_from, $banner_id );
		$from_x  = $min_max['low_x'];
		$from_y  = $min_max['low_y'];

		// get the position move's difference

		$dx = ( $to_x - $from_x );
		$dy = ( $to_y - $from_y );

		// get the order

		global $wpdb;
		$source_block = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE block_id = %d AND banner_id = %d",
				intval( $block_from ),
				intval( $banner_id )
			),
			ARRAY_A
		);

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE order_id = %d AND banner_id = %d",
				intval( $source_block['order_id'] ),
				intval( $banner_id )
			),
			ARRAY_A
		);

		$banner_data = load_banner_constants( $banner_id );

		$grid_width = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];

		while ( $block_row = array_shift( $result ) ) { // check each block to make sure we can move it.

			$block_to = ( ( $block_row['x'] + $dx ) / $banner_data['BLK_WIDTH'] ) + ( ( ( $block_row['y'] + $dy ) / $banner_data['BLK_HEIGHT'] ) * ( $grid_width / $banner_data['BLK_WIDTH'] ) );

			if ( ! Blocks::is_block_free( $block_to, $banner_id ) ) {
				Language::out( '<span style="color: red;">Cannot move the order - the space chosen is not empty!</span><br/>' );

				return false;
			}
		}

		// Reset array pointer to beginning
		reset( $result );

		while ( $block_row = array_shift( $result ) ) {

			$block_from = ( ( $block_row['x'] ) / $banner_data['BLK_WIDTH'] ) + ( ( $block_row['y'] / $banner_data['BLK_HEIGHT'] ) * ( $grid_width / $banner_data['BLK_WIDTH'] ) );
			$block_to   = ( ( $block_row['x'] + $dx ) / $banner_data['BLK_WIDTH'] ) + ( ( ( $block_row['y'] + $dy ) / $banner_data['BLK_HEIGHT'] ) * ( $grid_width / $banner_data['BLK_WIDTH'] ) );

			Blocks::move_block( $block_from, $block_to, $banner_id );
		}

		return true;
	}

	/**
	 * First check to see if the banner has packages. If it does
	 * then check how many orders the user had.
	 *
	 * @param $banner_data
	 * @param $user_id
	 * @param int $package_id
	 *
	 * @return bool|void|null
	 */
	public static function can_user_order( $banner_data, $user_id, int $package_id = 0 ) {

		// check rank
		$privileged = carbon_get_user_meta( get_current_user_id(), MDS_PREFIX . 'privileged' );
		if ( $privileged == '1' ) {
			return true;
		}

		$BID = $banner_data['BANNER_ID'];

		$has_packages = banner_get_packages( $BID );


		if ( $has_packages ) { // if user has package, check if the user can order this package
			if ( $package_id == 0 ) { // don't know the package id, assume true.

				return true;
			} else {

				$can_get_package = can_user_get_package( $user_id, $package_id );
				return $can_get_package;
			}
		} else {
			
			// check against the banner. (Banner has no packages)
			if ( ( $banner_data['G_MAX_ORDERS'] > 0 ) ) {
				
				global $wpdb;
				// Count user orders for this banner that are not deleted or new
				$sql = $wpdb->prepare(
					"SELECT COUNT(order_id)
					   FROM " . MDS_DB_PREFIX . "orders
					  WHERE banner_id = %d
					    AND status NOT IN ('deleted','new')
					    AND user_id = %d",
					intval( $BID ),
					intval( $user_id )
				);
				
				$count = (int) $wpdb->get_var( $sql );
				
				// Log DB error if present (without breaking the flow)
				if ( ! empty( $wpdb->last_error ) ) {
					\MillionDollarScript\Classes\System\Logs::log( 'MDS DB Error in can_user_order: ' . $wpdb->last_error . ' SQL: ' . $sql );
				}
				
				if ( $count >= intval( $banner_data['G_MAX_ORDERS'] ) ) {
					return false;
				}
				
				return true;
			} else {
				// can make unlimited orders
				return true;
			}
		}
	}

	public static function get_tmp_img_name(): string {
		$uploaddir = Utility::get_upload_path() . "images/";
		$filter    = "tmp_" . self::get_current_order_id() . '.png';
		$file      = $uploaddir . $filter;

		if ( file_exists( $file ) ) {
			return $file;
		}

		return "";
	}

	public static function update_temp_order_timestamp(): void {
		global $wpdb;
		$now      = current_time( 'mysql' );
		$order_id = self::get_current_order_id();
		if ( $order_id !== null ) {
			$sql = $wpdb->prepare( "UPDATE " . MDS_DB_PREFIX . "orders 
						    SET order_date=%s, date_stamp=%s
						    WHERE order_id=%d",
				$now, $now, $order_id );
			$wpdb->query( $sql );
		}
	}

	/**
	 * Check if an order has already been paid for or has a payment commitment
	 *
	 * @param int $order_id Order ID to check
	 * @return bool True if payment exists or order is committed for payment, false otherwise
	 */
	public static function has_payment( int $order_id ): bool {
		global $wpdb;
		
		// Check for actual DEBIT transactions (automated payments)
		$payment_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . MDS_DB_PREFIX . "transactions 
			 WHERE order_id = %d AND type = 'DEBIT'", 
			$order_id
		));
		
		if ( $payment_count > 0 ) {
			return true;
		}
		
		// Check for WooCommerce orders that indicate payment was submitted
		$ad_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ad_id FROM " . MDS_DB_PREFIX . "orders 
			 WHERE order_id = %d", 
			$order_id
		));
		
		if ( $ad_id ) {
			$wc_order_id = get_post_meta( $ad_id, '_wc_order_id', true );
			if ( $wc_order_id ) {
				$wc_order = wc_get_order( $wc_order_id );
				if ( $wc_order ) {
					$wc_status = $wc_order->get_status();
					// If WooCommerce order exists with these statuses, payment was submitted
					if ( in_array( $wc_status, [ 'on-hold', 'processing', 'completed' ] ) ) {
						return true;
					}
				}
			}
		}
		
		// Check order status - only 'completed' orders should be considered as having payment
		// 'confirmed' orders without WooCommerce payment should still show "Pay Now"
		$order_status = $wpdb->get_var( $wpdb->prepare(
			"SELECT status FROM " . MDS_DB_PREFIX . "orders 
			 WHERE order_id = %d", 
			$order_id
		));
		
		return $order_status === 'completed';
	}

	/**
	 * Get header action buttons for new orders in manage page
	 *
	 * @param array $order Order data
	 * @return string HTML for action buttons
	 */
	public static function get_header_action_buttons( array $order ): string {
		if ( $order['status'] !== 'new' ) {
			return '';
		}

		$buttons = '';
		$steps = Steps::get_steps();
		$current_step = $order['current_step'];
		$USE_AJAX = Options::get_option( 'use-ajax' );

		// Determine which button to show based on current step
		if ( $steps[ $current_step ] == Steps::STEP_UPLOAD ) {
			$args = [ 'BID' => $order['banner_id'] ];
			$page_name = 'upload';
			if ( Options::get_option( 'use-ajax' ) == 'SIMPLE' ) {
				$page_name = 'order';
				$args['order_id'] = $order['order_id'];
			}
			$url = esc_url( Utility::get_page_url( $page_name, $args ) );
			$buttons .= "<input class='mds-button mds-upload' type='button' value='" . esc_attr( Language::get( 'Upload' ) ) . "' onclick='window.location=\"" . $url . "\"' />";
		} else if ( $steps[ $current_step ] == Steps::STEP_WRITE_AD ) {
			$args = [ 'BID' => $order['banner_id'] ];
			if ( Options::get_option( 'use-ajax' ) == 'SIMPLE' ) {
				$args['order_id'] = $order['order_id'];
			}
			$url = esc_url( Utility::get_page_url( 'write-ad', $args ) );
			$buttons .= "<input class='mds-button mds-write' type='button' value='" . esc_attr( Language::get( 'Write Ad' ) ) . "' onclick='window.location=\"" . $url . "\"' />";
		} else if ( $steps[ $current_step ] == Steps::STEP_CONFIRM_ORDER ) {
			$args = [ 'BID' => $order['banner_id'] ];
			if ( Options::get_option( 'use-ajax' ) == 'SIMPLE' ) {
				$args['order_id'] = $order['order_id'];
			}
			$url = esc_url( Utility::get_page_url( 'confirm-order', $args ) );
			$buttons .= "<input class='mds-button mds-confirm' type='button' value='" . esc_attr( Language::get( 'Confirm Now' ) ) . "' onclick='window.location=\"" . $url . "\"' />";
		} else if ( $steps[ $current_step ] == Steps::STEP_PAYMENT && !self::has_payment( $order['order_id'] ) ) {
			$args = [
				'order_id' => $order['order_id'],
				'BID'      => $order['banner_id'],
			];
			$url = esc_url( Utility::get_page_url( 'payment', $args ) );
			$buttons .= "<input class='mds-button mds-pay' type='button' value='" . esc_attr( Language::get( 'Pay Now' ) ) . "' onclick='window.location=\"" . $url . "\"' />";
		} else if ( $current_step == 0 ) {
			// Initial step - show appropriate start button
			$args = [ 'BID' => $order['banner_id'] ];
			if ( Options::get_option( 'use-ajax' ) == 'SIMPLE' ) {
				$text = Language::get( 'Upload' );
				$page_name = 'upload';
				$args['order_id'] = $order['order_id'];
			} else {
				$text = Language::get( 'Order' );
				$page_name = 'order';
			}
			$url = esc_url( Utility::get_page_url( $page_name, $args ) );
			$buttons .= "<input class='mds-button mds-upload' type='button' value='" . esc_attr( $text ) . "' onclick='window.location=\"" . $url . "\"' />";
		} else {
			$args = [ 'BID' => $order['banner_id'] ];
			if ( Options::get_option( 'use-ajax' ) == 'SIMPLE' ) {
				$args['order_id'] = $order['order_id'];
			}
			$url = esc_url( Utility::get_page_url( 'order', $args ) );
			$buttons .= "<input class='mds-button mds-continue' type='button' value='" . esc_attr( Language::get( 'Continue' ) ) . "' onclick='window.location=\"" . $url . "\"' />";
		}

		return $buttons;
	}

}