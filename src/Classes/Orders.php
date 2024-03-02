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

namespace MillionDollarScript\Classes;

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
			WooCommerceFunctions::remove_item_from_cart( $user_id, self::get_current_order_id() );
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
	 * @param $user_id
	 *
	 * @return int
	 */
	public static function create_order( $user_id = null ): int {
		global $wpdb;

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
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
	 * @return int
	 */
	public static function get_ad_id_from_order_id( int $order_id ): int {
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

		return $order_id;
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

	public static function valid_block_statuses(): array {
		return [
			'cancelled',
			'reserved',
			'sold',
			'free',
			'ordered',
			'nfs',
			'denied',
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
			if ( in_array( $block_status, self::valid_block_statuses() ) ) {
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
		$sql       = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE status='new' AND user_id=%d", get_current_user_id() );
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

		if ( $order_id == Orders::get_current_order_id() ) {

			global $wpdb;

			$sql = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id=%d AND order_id=%d", $user_id, $order_id );
			$row = $wpdb->get_row( $sql );

			if ( $row ) {

				// If the cancelled order is in progress then reset the progress.
				if ( Orders::is_order_in_progress( $order_id ) ) {
					Orders::reset_order_progress();
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
				$imagefile = get_tmp_img_name();
				if ( file_exists( $imagefile ) ) {
					unlink( $imagefile );
				}
			}

		} else {
			$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id='" . get_current_user_id() . "' AND order_id='" . $order_id . "'";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
			if ( mysqli_num_rows( $result ) > 0 ) {

				// If the cancelled order is in progress then reset the progress.
				if ( Orders::is_order_in_progress( $order_id ) ) {
					Orders::reset_progress();
				}

				delete_order( $order_id );
			}
		}
	}

	/**
	 * @param $AUTO_PUBLISH
	 * @param float|int|string $BID
	 *
	 * @return array|void
	 */
	public static function complete( $AUTO_PUBLISH, float|int|string $BID ) {
		global $wpdb;

		if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] == Orders::get_current_order_id() ) {
			// convert the temp order to an order.

			$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id='" . intval( Orders::get_current_order_id() ) . "' ";
			$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

			if ( mysqli_num_rows( $order_result ) == 0 ) {
				// no order id found...
				if ( wp_doing_ajax() ) {
					Functions::no_orders();
					wp_die();
				}

				Utility::redirect( Utility::get_page_url( 'no-orders' ) );
			} else if ( $order_row = mysqli_fetch_array( $order_result ) ) {

				$_REQUEST['order_id'] = reserve_pixels_for_temp_order( $order_row );
			} else {

				Language::out( '<h1>Pixel Reservation Not Yet Completed...</h1>' );
				Language::out_replace(
					'<p>We are sorry, it looks like you took too long! Either your session has timed out or the pixels we tried to reserve for you were snapped up by someone else in the meantime! Please go <a href="%ORDER_PAGE%">here</a> and try again.</p>',
					'%ORDER_PAGE%',
					Utility::get_page_url( 'order' )
				);

				require_once MDS_CORE_PATH . "html/footer.php";
				die();
			}
		}

		$sql = $wpdb->prepare(
			"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d AND user_id = %d",
			intval( $_REQUEST['order_id'] ),
			get_current_user_id()
		);

		$row = $wpdb->get_row( $sql, ARRAY_A );

		$privileged = carbon_get_user_meta( get_current_user_id(), 'privileged' );

		if ( ( $row['price'] == 0 ) || ( $privileged == '1' ) ) {
			complete_order( $row['user_id'], $row['order_id'] );
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
			$sql = "select * from " . MDS_DB_PREFIX . "banners where banner_id=" . intval( $order['banner_id'] );
			$b_result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$b_row = mysqli_fetch_array( $b_result );

			if ( $b_row ) {
				echo $b_row['name'];
			}

			?>
        </div>
        <div>
            <b><?php Language::out( "Amount" ); ?>:</b>
			<?php echo Currency::convert_to_default_currency_formatted( $order['currency'], $order['price'] ); ?>
        </div>
        <div>
            <b><?php Language::out( "Time" ); ?>:</b><br />
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

				$to_go = elapsedtime( $exp_time_to_go );

				$elapsed = elapsedtime( $elapsed_time );

				if ( $order['status'] == 'expired' ) {
					$days = "<a href='" . esc_url( Utility::get_page_url( 'payment' ) ) . "?order_id=" . esc_attr( $order['order_id'] ) . "&BID=" . esc_attr( $order['banner_id'] ) . "&renew=true'>" . Language::get( 'Expired!' ) . "</a>";
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
				if ( \MillionDollarScript\Classes\Config::get( 'USE_AJAX' ) == 'SIMPLE' ) {
					$temp_var = '&order_id=' . $order['order_id'];
				}

				$steps    = \MillionDollarScript\Classes\Steps::get_steps();
				$USE_AJAX = Config::get( 'USE_AJAX' );

				switch ( $order['status'] ) {
					case "new":
						$current_step = \MillionDollarScript\Classes\Steps::get_current_step( $order['order_id'] );
						if ( $current_step != 0 ) {
							echo Language::get( 'In progress' ) . '<br>';
							if ( $steps[ $current_step ] == \MillionDollarScript\Classes\Steps::STEP_UPLOAD ) {
								if ( $USE_AJAX == 'SIMPLE' ) {
									$url = Utility::get_page_url( 'order' );
								} else {
									$url = Utility::get_page_url( 'upload' );
								}
								echo "<a class='mds-button mds-upload' href='" . $url . "?BID=" . $order['banner_id'] . "$temp_var'>" . Language::get( 'Upload' ) . "</a>";
							} else if ( $steps[ $current_step ] == \MillionDollarScript\Classes\Steps::STEP_WRITE_AD ) {
								echo "<a class='mds-button mds-write' href='" . Utility::get_page_url( 'write-ad' ) . "?BID=" . $order['banner_id'] . "$temp_var'>" . Language::get( 'Write Ad' ) . "</a>";
							} else if ( $steps[ $current_step ] == \MillionDollarScript\Classes\Steps::STEP_CONFIRM_ORDER ) {
								echo "<a class='mds-button mds-confirm' href='" . Utility::get_page_url( 'confirm-order' ) . "?BID=" . $order['banner_id'] . "$temp_var'>" . Language::get( 'Confirm Now' ) . "</a>";
							} else if ( $steps[ $current_step ] == \MillionDollarScript\Classes\Steps::STEP_PAYMENT ) {
								echo "<a class='mds-button mds-pay' href='" . Utility::get_page_url( 'payment' ) . "?order_id=" . $order['order_id'] . "&BID=" . $order['banner_id'] . "'>" . Language::get( 'Pay Now' ) . "</a>";
							} else {
								echo "<a class='mds-button mds-continue' href='" . Utility::get_page_url( 'order' ) . "?BID=" . $order['banner_id'] . "$temp_var'>" . Language::get( 'Continue' ) . "</a>";
							}
						} else {
							if ( $USE_AJAX == 'SIMPLE' ) {
								$text = Language::get( 'Upload' );
								$url  = Utility::get_page_url( 'upload' );
							} else {
								$text = Language::get( 'Order' );
								$url  = Utility::get_page_url( 'order' );
							}
							echo "<a class='mds-button mds-upload' href='" . $url . "?BID=" . $order['banner_id'] . "$temp_var'>" . $text . "</a>";
						}
						echo "<br><input class='mds-button mds-cancel' type='button' value='" . esc_attr( Language::get( 'Cancel' ) ) . "' onclick='if (!confirmLink(this, \"" . Language::get( 'Cancel, are you sure?' ) . "\")) return false; window.location=\"" . esc_url( Utility::get_page_url( 'manage' ) . "?cancel=yes&order_id=" . $order['order_id'] ) . "\"' >";
						break;
					case "confirmed":
						if ( Orders::is_order_in_progress( $order['order_id'] ) || ( \MillionDollarScript\Classes\WooCommerceFunctions::is_wc_active() ) ) {
							update_user_meta( get_current_user_id(), 'mds_confirm', true );
							echo "<a class='mds-button mds-pay' href='" . Utility::get_page_url( 'payment' ) . "?order_id=" . $order['order_id'] . "&BID=" . $order['banner_id'] . "'>" . Language::get( 'Pay Now' ) . "</a>";
							echo "<br><input class='mds-button mds-cancel' type='button' value='" . esc_attr( Language::get( 'Cancel' ) ) . "' onclick='if (!confirmLink(this, \"" . Language::get( 'Cancel, are you sure?' ) . "\")) return false; window.location=\"" . esc_url( Utility::get_page_url( 'manage' ) . "?cancel=yes&order_id=" . $order['order_id'] ) . "\"' >";
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
								$to_go = elapsedtime( $exp_time_to_go );
							}

							if ( $order['date_published'] != '' ) {
								echo Language::get_replace( 'Expires in: %TIME%', '%TIME%', $to_go );
								echo "<br />";
							}

							if ( ! \MillionDollarScript\Classes\Options::get_option( 'order-locking' ) ) {
								echo "<a class='mds-button mds-manage' href='" . Utility::get_page_url( 'manage' ) . "?mds-action=manage&aid=" . $order['ad_id'] . "'>" . Language::get( 'Manage' ) . "</a>";
							}
						}

						break;
					case 'renew_wait':
					case "expired":
						$time_expired = strtotime( $order['date_stamp'] );

						$time_when_cancel = $time_expired + ( \MillionDollarScript\Classes\Config::get( 'MINUTES_RENEW' ) * 60 );

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
				echo "<br><input class='mds-button mds-cancel' type='button' value='" . esc_attr( Language::get( 'Cancel' ) ) . "' onclick='if (!confirmLink(this, \"" . Language::get( 'Cancel, are you sure?' ) . "}\")) return false; window.location=\"" . esc_url( Utility::get_page_url( 'manage' ) . "?cancel=yes{$temp_var}" ) . "\"' >";
			}
			?>
        </div>
		<?php
	}
}