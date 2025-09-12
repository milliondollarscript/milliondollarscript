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

use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Payment\Currency;
use MillionDollarScript\Classes\System\Utility;

class Blocks {

	public static function select_block( $banner_data, $user_id, $blocks_to_add = [], $blocks_to_remove = [], $block_set = null ) {

		$BID = $banner_data['banner_id'];

		// Check if max_orders < order count
		if ( ! Orders::can_user_order( $banner_data, $user_id ) ) {
			// order count > max orders
			return [
				"error" => "true",
				"type"  => "max_orders",
				"data"  => [
					"value" => Language::get_replace( '<b><span style="color:red">Cannot place pixels on order.</span> You have reached the order limit for this grid. Please review your order history under <a href="%MANAGE_URL%">Manage Pixels</a>.</b>', '%MANAGE_URL%', esc_url( Utility::get_page_url( 'manage' ) ) ),
				]
			];
		}

		if ( ! function_exists( 'load_ad_values' ) ) {
			require_once( MDS_CORE_PATH . "include/ads.inc.php" );
		}

		$blocks         = [];
		$clicked_blocks = [];
		$return_val     = [
			'error' => false,
			'type'  => '',
			'data'  => []
		];

		global $wpdb;

		$orderid = Orders::get_current_order_in_progress();

		// Check if any of the blocks to be added are already taken
		if ( ! empty( $blocks_to_add ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $blocks_to_add ), '%d' ) );
			$query        = $wpdb->prepare(
				"SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE block_id IN ($placeholders) AND banner_id = %d AND (status IN ('sold','cancelled') OR (status = 'reserved' AND user_id != %d))",
				array_merge( $blocks_to_add, [ intval( $BID ), $user_id ] )
			);
			$taken_blocks = $wpdb->get_col( $query );

			if ( ! empty( $taken_blocks ) ) {
				return [
					"error" => "true",
					"type"  => "advertiser_sel_sold_error",
					"data"  => [
						"value" => Language::get_replace( 'Sorry, cannot select block %BLOCK_ID% because it is on order / sold!', "%BLOCK_ID%", implode( ', ', $taken_blocks ) ),
					]
				];
			}
		}

		if ( $orderid == null ) {
			$orderid = Orders::create_order( $user_id, $BID );
		}

		// put block on order
		$ordersrow = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT blocks,status,ad_id,order_id FROM " . MDS_DB_PREFIX . "orders WHERE user_id = %d AND order_id = %d AND banner_id = %d AND status != 'deleted'",
				get_current_user_id(),
				$orderid,
				intval( $BID )
			),
			ARRAY_A
		);

		$current_blocks = [];
		if ( isset( $ordersrow ) && $ordersrow['blocks'] != '' ) {
			$current_blocks = explode( ",", $ordersrow['blocks'] );
			array_walk( $current_blocks, 'intval' );
		}

		if ( $block_set !== null ) {
			// If a specific block set is provided (for inversion), use it directly.
			$new_block_set = $block_set;
		} else {
			// Original logic: Add new blocks
			$new_block_set = array_unique( array_merge( $current_blocks, $blocks_to_add ) );

			// Original logic: Remove blocks to be removed
			$new_block_set = array_diff( $new_block_set, $blocks_to_remove );
		}

		$new_blocks_for_validation = array_values( $new_block_set );


		$blocks_per_row = $banner_data['G_WIDTH'];

		$selection_phase = ! empty( $GLOBALS['MDS_SELECTION_PHASE'] );

		// Enforce adjacency according to option, but only when not in selection phase.
		$mode        = Options::get_option( 'selection-adjacency-mode', 'ADJACENT' );
		$is_adjacent = true;
		if ( ! $selection_phase && $mode !== 'NONE' ) {
			if ( $mode === 'RECTANGLE' ) {
				$is_adjacent = self::check_adjacency( $new_blocks_for_validation, $blocks_per_row );
			} else {
				$is_adjacent = self::check_contiguous( $new_blocks_for_validation, $blocks_per_row );
			}
		}

		if ( ! $is_adjacent ) {
			$error_type = ( $mode === 'RECTANGLE' ) ? 'not_rectangle' : 'not_adjacent';
			$message    = ( $mode === 'RECTANGLE' )
				? Language::get( 'You must select blocks forming a rectangle or square.' )
				: Language::get( 'You must select a block adjacent to another one.' );
			return [
				"error" => "true",
				"type"  => $error_type,
				"data"  => [
					"value" => $message,
				]
			];
		}

		// The blocks are adjacent, so we can proceed with the new set of blocks.
		$new_blocks = $new_blocks_for_validation;

		// check max blocks
		$max_selected = false;
		if ( $banner_data['G_MAX_BLOCKS'] > 0 ) {
			if ( sizeof( $new_blocks ) > $banner_data['G_MAX_BLOCKS'] ) {
				$max_selected = true;
				$return_val   = [
					"error" => "true",
					"type"  => "max_blocks_selected",
					"data"  => [
						"value" => Language::get_replace( 'Maximum blocks selected. (%MAX_BLOCKS% allowed per order)', '%MAX_BLOCKS%', $banner_data['G_MAX_BLOCKS'] ),
					]
				];
			}
		}

		$order_blocks = implode( ",", $new_blocks );

		// don't overwrite existing blocks from other orders
		$existing_blocks = false;
		if ( count( $new_blocks ) > 1 ) {
			// single block is already handled, only handle multiple here
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE order_id != %d AND block_id IN(" . $order_blocks . ") AND banner_id = %d",
					$orderid,
					intval( $BID )
				)
			);
			if ( count( $results ) > 0 ) {
				$return_val = [
					"error" => "true",
					"type"  => "advertiser_sel_sold_error",
					"data"  => [
						"value" => Language::get_replace( 'Sorry, cannot select block %BLOCK_ID% because it is on order / sold!', "%BLOCK_ID% ", '' ),
					]
				];

				$existing_blocks = true;
			}
		}

		// if conditions are met then select new blocks
		if ( ! $max_selected && $is_adjacent && ! $existing_blocks && ( ( $order_blocks !== '' ) || ! empty( $blocks_to_remove ) || in_array( 0, $blocks_to_remove, true ) ) ) {
			$price      = $total = 0;
			$num_blocks = sizeof( $new_blocks );
			$quantity   = ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] ) * $num_blocks;
			$now        = current_time( 'mysql' );

			$orderid = 0;
			if ( isset( $ordersrow['order_id'] ) ) {
				$orderid = intval( $ordersrow['order_id'] );
			}

			$adid = 0;
			if ( isset( $ordersrow['ad_id'] ) ) {
				$adid = intval( $ordersrow['ad_id'] );
			}

			// One last check to make sure we aren't replacing existing blocks from other orders
			if ( ! empty( $order_blocks ) || $order_blocks == '0' ) {
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE order_id != %d AND block_id IN(" . $order_blocks . ") AND banner_id = %d",
						$orderid,
						intval( $BID )
					)
				);
				if ( count( $results ) > 0 ) {
					return [
						"error" => "true",
						"type"  => "advertiser_sel_sold_error",
						"data"  => [
							"value" => Language::get_replace( 'Sorry, cannot select block %BLOCK_ID% because it is on order / sold!', "%BLOCK_ID% ", '' ),
						]
					];
				}
			}

			global $wpdb;

			$user_id = get_current_user_id();

			$current_order_id = Orders::get_current_order_in_progress( $user_id );
			if ( $current_order_id != null ) {
				// Set all orders as not in progress for the current user.
				$sql = $wpdb->prepare(
					"UPDATE " . MDS_DB_PREFIX . "orders SET order_in_progress='N' WHERE user_id=%d",
					$user_id
				);
				$wpdb->query( $sql );
			}

			// Begin transactional bulk path to reserve blocks efficiently
			$wpdb->query( 'START TRANSACTION' );
			$transaction_ok = true;
			try {
				// Create/refresh the order row (status=new, zero price for now)
				$sql = $wpdb->prepare(
					"REPLACE INTO " . MDS_DB_PREFIX . "orders (user_id, order_id, blocks, status, order_date, price, quantity, banner_id, currency, days_expire, date_stamp, ad_id, approved, order_in_progress) VALUES (%d, %d, %s, 'new', NOW(), %f, %d, %d, %s, %d, %s, %d, %s, %s)",
					$user_id,
					$orderid,
					$order_blocks,
					floatval( 0 ),
					intval( $quantity ),
					intval( $BID ),
					Currency::get_default_currency(),
					intval( $banner_data['DAYS_EXPIRE'] ),
					$now,
					$adid,
					$banner_data['AUTO_APPROVE'],
					'Y'
				);
				$wpdb->query( $sql );

				$order_id = intval( $wpdb->insert_id );
				if ( $order_id === 0 ) {
					// Fallback: use the existing order id if available, otherwise fetch current in-progress
					$order_id = $orderid ?: intval( Orders::get_current_order_in_progress( $user_id ) );
				}

				Steps::set_current_step( $order_id, 1 );

				$return_val = [
					"error" => "false",
					"type"  => "order_id",
					"data"  => [
						"value" => intval( $order_id ),
					]
				];

				// Clean up: remove any of this user's reserved blocks for this banner
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE user_id = %d AND (status = 'reserved' OR (order_id = %d AND status != 'sold')) AND banner_id = %d",
						get_current_user_id(),
						$order_id,
						intval( $BID )
					)
				);

				// Delete specifically removed blocks from the database (safety)
				if ( ! empty( $blocks_to_remove ) ) {
					$placeholders_r = implode( ',', array_fill( 0, count( $blocks_to_remove ), '%d' ) );
					$wpdb->query(
						$wpdb->prepare(
							"DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE user_id = %d AND block_id IN ($placeholders_r) AND banner_id = %d",
							array_merge( [ get_current_user_id() ], $blocks_to_remove, [ intval( $BID ) ] )
						)
					);
				}

				// Build rows and compute total using direct math (no full-grid scan)
				$total = 0.0;
				$rows  = [];
				$chunk_size = 500; // prevent very large single statements

				foreach ( $new_blocks as $cell ) {
					$cell        = intval( $cell );
					$x_index     = $cell % $banner_data['G_WIDTH'];
					$y_index     = intdiv( $cell, $banner_data['G_WIDTH'] );
					$x           = $x_index * $banner_data['BLK_WIDTH'];
					$y           = $y_index * $banner_data['BLK_HEIGHT'];
					$price_block = get_zone_price( $BID, $y, $x );
					$total      += floatval( $price_block );

					$rows[] = [
						'block_id'   => $cell,
						'user_id'    => $user_id,
						'status'     => 'reserved',
						'x'          => $x,
						'y'          => $y,
						'image_data' => '',
						'url'        => '',
						'alt_text'   => '',
						'approved'   => $banner_data['AUTO_APPROVE'],
						'banner_id'  => intval( $BID ),
						'ad_id'      => $adid,
						'currency'   => Currency::get_default_currency(),
						'price'      => floatval( $price_block ),
						'order_id'   => intval( $order_id ),
						'clicks'     => 0,
						'views'      => 0,
					];
				}

				// Bulk insert/upsert in chunks with guard against overriding other users' reservations
				$columns = "(block_id, user_id, status, x, y, image_data, url, alt_text, approved, banner_id, ad_id, currency, price, order_id, click_count, view_count)";
				$values_place = "(%d, %d, %s, %d, %d, %s, %s, %s, %s, %d, %d, %s, %f, %d, %d, %d)";
				$on_dupe = " ON DUPLICATE KEY UPDATE \
					user_id = IF(user_id = VALUES(user_id), VALUES(user_id), user_id), \
					status = IF(user_id = VALUES(user_id), 'reserved', status), \
					x = IF(user_id = VALUES(user_id), VALUES(x), x), \
					y = IF(user_id = VALUES(user_id), VALUES(y), y), \
					price = IF(user_id = VALUES(user_id), VALUES(price), price), \
					order_id = IF(user_id = VALUES(user_id), VALUES(order_id), order_id), \
					currency = IF(user_id = VALUES(user_id), VALUES(currency), currency), \
					approved = IF(user_id = VALUES(user_id), VALUES(approved), approved), \
					ad_id = IF(user_id = VALUES(user_id), VALUES(ad_id), ad_id)";

				for ( $i = 0; $i < count( $rows ); $i += $chunk_size ) {
					$chunk = array_slice( $rows, $i, $chunk_size );
					$params = [];
					$placeholders = [];
					foreach ( $chunk as $r ) {
						$placeholders[] = $values_place;
						$params[] = $r['block_id'];
						$params[] = $r['user_id'];
						$params[] = $r['status'];
						$params[] = $r['x'];
						$params[] = $r['y'];
						$params[] = $r['image_data'];
						$params[] = $r['url'];
						$params[] = $r['alt_text'];
						$params[] = $r['approved'];
						$params[] = $r['banner_id'];
						$params[] = $r['ad_id'];
						$params[] = $r['currency'];
						$params[] = $r['price'];
						$params[] = $r['order_id'];
						$params[] = $r['clicks'];
						$params[] = $r['views'];
					}
					$sql = "INSERT INTO " . MDS_DB_PREFIX . "blocks " . $columns . " VALUES " . implode( ',', $placeholders ) . $on_dupe;
					$wpdb->query( $wpdb->prepare( $sql, $params ) );
				}

				// Concurrency check: ensure all intended blocks are now reserved by this user/order
				if ( ! empty( $new_blocks ) ) {
					$place_in = implode( ',', array_fill( 0, count( $new_blocks ), '%d' ) );
					$params_ck = array_merge( $new_blocks, [ intval( $BID ), $user_id, $order_id ] );
					$sql_ck = $wpdb->prepare(
						"SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE block_id IN ($place_in) AND banner_id = %d AND NOT (user_id = %d AND order_id = %d AND status = 'reserved')",
						$params_ck
					);
					$conflicts = $wpdb->get_col( $sql_ck );
					if ( ! empty( $conflicts ) ) {
						$wpdb->query( 'ROLLBACK' );
						return [
							"error" => "true",
							"type"  => "advertiser_sel_sold_error",
							"data"  => [
								"value" => Language::get_replace( 'Sorry, cannot select block %BLOCK_ID% because it is on order / sold!', "%BLOCK_ID%", implode( ', ', array_map( 'intval', $conflicts ) ) ),
							]
						];
					}
				}

				// Update order totals and blocks once
				$wpdb->update(
					MDS_DB_PREFIX . "orders",
					array( 'price' => floatval( $total ) ),
					array( 'order_id' => intval( $order_id ) ),
					array( '%f' ),
					array( '%d' )
				);

				$wpdb->update(
					MDS_DB_PREFIX . "orders",
					array( 'original_order_id' => intval( $order_id ) ),
					array( 'order_id' => intval( $order_id ) ),
					array( '%d' ),
					array( '%d' )
				);

				$wpdb->update(
					MDS_DB_PREFIX . "orders",
					array( 'blocks' => $order_blocks ),
					array( 'order_id' => intval( $order_id ) ),
					array( '%s' ),
					array( '%d' )
				);

				// check that we have ad_id, if not then create an ad for this order.
				if ( $adid == 0 ) {
					$_REQUEST['order_id'] = $order_id;
					$_REQUEST['BID']      = $BID;
					$_REQUEST['user_id']  = get_current_user_id();
				}

				$wpdb->query( 'COMMIT' );
			} catch ( \Throwable $txe ) {
				$transaction_ok = false;
				$wpdb->query( 'ROLLBACK' );
				return [
					"error" => "true",
					"type"  => "exception",
					"data"  => [
						"value" => "Selection failed: " . $txe->getMessage(),
					]
				];
			}
		}

		// This part is tricky as we don't have a single "clicked_block" anymore.
		// The previous logic was checking a single block.
		// The new logic in the AJAX request already pre-validates if blocks are taken.
		// We can probably remove this whole `else` block or adapt it.
		// For now, let's assume the pre-validation is enough.

		$return_val['data']['added']   = array_values( array_map( 'intval', (array) $blocks_to_add ) );
		$return_val['data']['removed'] = array_values( array_map( 'intval', (array) $blocks_to_remove ) );

		return $return_val;
	}

	public static function invert_selection( $banner_data, $user_id, $selection ) {
		$BID = $banner_data['banner_id'];

		$order_id = Orders::get_current_order_id();

		if ( $order_id == null ) {
			$order_id = Orders::create_order( $user_id, $BID );
		}

		$current_blocks = self::get_blocks_for_order( $order_id );

		// Calculate symmetric difference
		$blocks_to_keep_from_current = array_diff( $current_blocks, $selection );
		$blocks_to_add_from_selection = array_diff( $selection, $current_blocks );
		$new_block_set = array_unique( array_merge( $blocks_to_keep_from_current, $blocks_to_add_from_selection ) );

		// Calculate added and removed blocks for select_block
		$added_blocks = array_map('intval', array_diff($new_block_set, $current_blocks));
		$removed_blocks = array_map('intval', array_diff($current_blocks, $new_block_set));

		$result = self::select_block( $banner_data, $user_id, $added_blocks, $removed_blocks, $new_block_set );

		return $result;
	}

	public static function remove_blocks( $banner_data, $user_id, $blocks_to_remove ) {
		$BID = $banner_data['banner_id'];

		$order_id = Orders::get_current_order_id();

		if ( $order_id == null ) {
			$order_id = Orders::create_order( $user_id, $BID );
		}

		$current_blocks = self::get_blocks_for_order( $order_id );
		$new_block_set = array_map('intval', array_diff( $current_blocks, $blocks_to_remove ));
		$result = self::select_block( $banner_data, $user_id, [], [], $new_block_set );
		
		return $result;
	}

	public static function get_blocks_for_order( $order_id ) {
		global $wpdb;

		$blocks = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT blocks FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d",
				$order_id
			)
		);

		if ( is_null( $blocks ) || $blocks === '' ) {
			return [];
		}

		return array_map( 'intval', explode( ',', $blocks ) );
	}

	/**
	 * Checks if the selected blocks form a solid, contiguous rectangle.
	 *
	 * @param array $blocks
	 * @param array $clicked_blocks
	 * @param int   $blocks_per_row
	 *
	 * @return bool
	 */
	public static function check_adjacency( $all_blocks, $blocks_per_row ): bool {
		// Normalize to unique integer IDs to avoid string/integer key mismatches
		$all_blocks = array_values( array_unique( array_map( 'intval', (array) $all_blocks ) ) );
		$count      = count( $all_blocks );

		if ( $count <= 1 ) {
			return true;
		}

		// Compute x,y sets
		$xs = [];
		$ys = [];
		$set = [];
		foreach ( $all_blocks as $block_id ) {
			$x = $block_id % $blocks_per_row;
			$y = intdiv( $block_id, $blocks_per_row );
			$xs[$x] = true;
			$ys[$y] = true;
			$set[$block_id] = true;
		}

		$xs = array_keys( $xs );
		$ys = array_keys( $ys );
		sort( $xs );
		sort( $ys );

		$width  = count( $xs );
		$height = count( $ys );

		// Size must match rectangle area
		if ( $count !== ( $width * $height ) ) {
			return false;
		}

		// Ensure xs and ys are consecutive (no gaps)
		for ( $i = 1; $i < $width; $i++ ) {
			if ( $xs[$i] !== $xs[$i - 1] + 1 ) {
				return false;
			}
		}
		for ( $j = 1; $j < $height; $j++ ) {
			if ( $ys[$j] !== $ys[$j - 1] + 1 ) {
				return false;
			}
		}

		// Ensure all positions in rectangle are present
		foreach ( $ys as $y ) {
			foreach ( $xs as $x ) {
				$block_id = $y * $blocks_per_row + $x;
				if ( ! isset( $set[ $block_id ] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Checks if selected blocks are 4-neighbor contiguous (not necessarily rectangular).
	 *
	 * @param array $all_blocks
	 * @param int   $blocks_per_row
	 *
	 * @return bool
	 */
	public static function check_contiguous( $all_blocks, int $blocks_per_row ): bool {
		if ( empty( $all_blocks ) ) {
			return true;
		}

		$all_blocks = array_values( array_unique( array_map( 'intval', $all_blocks ) ) );
		$set        = array_flip( $all_blocks );

		$start = $all_blocks[0];
		$visited = [];
		$queue   = [ $start ];
		$visited[$start] = true;

		while ( ! empty( $queue ) ) {
			$current = array_shift( $queue );

			// Compute 4-neighbors with row-boundary checks
			$neighbors = [];
			// Up
			$up = $current - $blocks_per_row;
			if ( $up >= 0 ) {
				$neighbors[] = $up;
			}
			// Down
			$down = $current + $blocks_per_row;
			$neighbors[] = $down;
			// Left (only if not in first column)
			if ( $current % $blocks_per_row !== 0 ) {
				$neighbors[] = $current - 1;
			}
			// Right (only if not in last column)
			if ( $current % $blocks_per_row !== ( $blocks_per_row - 1 ) ) {
				$neighbors[] = $current + 1;
			}

			foreach ( $neighbors as $n ) {
				if ( isset( $set[ $n ] ) && ! isset( $visited[ $n ] ) ) {
					$visited[ $n ] = true;
					$queue[]       = $n;
				}
			}
		}

		return count( $visited ) === count( $all_blocks );
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

	public static function get_blocks_min_max( $block_id, $banner_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE block_id = %d AND banner_id = %d",
				intval( $block_id ),
				intval( $banner_id )
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$block_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE order_id = %d",
				intval( $row['order_id'] )
			),
			ARRAY_A
		);

		// find high x, y & low x, y
		// low x,y is the top corner, high x,y is the bottom corner

		$high_x = $high_y = $low_x = $low_y = "";
		foreach ( $block_rows as $block_row ) {

			if ( $high_x == '' ) {
				$high_x = $block_row['x'];
				$high_y = $block_row['y'];
				$low_x  = $block_row['x'];
				$low_y  = $block_row['y'];
			}

			if ( $block_row['x'] > $high_x ) {
				$high_x = $block_row['x'];
			}

			if ( $block_row['y'] > $high_y ) {
				$high_y = $block_row['y'];
			}

			if ( $block_row['y'] < $low_y ) {
				$low_y = $block_row['y'];
			}

			if ( $block_row['x'] < $low_x ) {
				$low_x = $block_row['x'];
			}
		}

		$ret           = array();
		$ret['high_x'] = $high_x;
		$ret['high_y'] = $high_y;
		$ret['low_x']  = $low_x;
		$ret['low_y']  = $low_y;

		return $ret;
	}

	public static function move_block( $block_from, $block_to, $banner_id ) {

		# reserve block_to
		if ( ! self::is_block_free( $block_to, $banner_id ) ) {
			echo "<span style=\"color: red; \">Cannot move the block - the space chosen is not empty!</span><br/>";

			return false;
		}

		#load block_from
		global $wpdb;
		$source_block = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE block_id = %d AND banner_id = %d",
				intval( $block_from ),
				intval( $banner_id )
			),
			ARRAY_A
		);

		// get the position and check range, do not move if out of range

		$pos = self::get_block_position( $block_to, $banner_id );

		$x = $pos['x'];
		$y = $pos['y'];

		$banner_data = load_banner_constants( $banner_id );

		if ( ( $x === '' ) || ( $x > ( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ) ) || $x < 0 ) {
			echo "<b>x is $x</b><br>";

			return false;
		}

		if ( ( $y === '' ) || ( $y > ( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ) ) || $y < 0 ) {
			echo "<b>y is $y</b><br>";

			return false;
		}

		$wpdb->replace(
			MDS_DB_PREFIX . "blocks",
			array(
				'block_id' => intval( $block_to ),
				'user_id' => intval( $source_block['user_id'] ),
				'status' => $source_block['status'],
				'x' => intval( $x ),
				'y' => intval( $y ),
				'image_data' => $source_block['image_data'],
				'url' => $source_block['url'],
				'alt_text' => $source_block['alt_text'],
				'file_name' => $source_block['file_name'],
				'mime_type' => $source_block['mime_type'],
				'approved' => $source_block['approved'],
				'published' => $source_block['published'],
				'banner_id' => intval( $banner_id ),
				'currency' => $source_block['currency'],
				'price' => floatval( $source_block['price'] ),
				'order_id' => intval( $source_block['order_id'] ),
				'click_count' => intval( $source_block['click_count'] ),
				'view_count' => intval( $source_block['view_count'] ),
				'ad_id' => intval( $source_block['ad_id'] )
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%d', '%d', '%d', '%d' )
		);



		# delete 'from' block
		$wpdb->delete(
			MDS_DB_PREFIX . "blocks",
			array( 'block_id' => intval( $block_from ), 'banner_id' => intval( $banner_id ) ),
			array( '%d', '%d' )
		);

		// Update the order record
		$order_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d AND banner_id = %d",
				intval( $source_block['order_id'] ),
				intval( $banner_id )
			),
			ARRAY_A
		);
		$blocks     = array();
		$new_blocks = array();
		$blocks     = explode( ',', $order_row['blocks'] );
		foreach ( $blocks as $item ) {
			if ( $block_from == $item ) {
				$item = $block_to;
			}
			$new_blocks[] = intval( $item );
		}

		$sql_blocks = implode( ',', $new_blocks );

		# update the customer's order
		$wpdb->update(
			MDS_DB_PREFIX . "orders",
			array( 'blocks' => $sql_blocks ),
			array( 'order_id' => intval( $source_block['order_id'] ) ),
			array( '%s' ),
			array( '%d' )
		);

		return true;
	}

	public static function get_block_position( $block_id, $banner_id ) {

		$cell     = 0;
		$ret['x'] = 0;
		$ret['y'] = 0;

		$banner_data = load_banner_constants( $banner_id );

		for ( $i = 0; $i < $banner_data['G_HEIGHT']; $i ++ ) {
			for ( $j = 0; $j < $banner_data['G_WIDTH']; $j ++ ) {
				if ( $block_id == $cell ) {
					$ret['x'] = $j * $banner_data['BLK_WIDTH'];
					$ret['y'] = $i * $banner_data['BLK_HEIGHT'];

					return $ret;
				}
				$cell ++;
			}
		}

		return $ret;
	}

	public static function get_block_id_from_position( $x, $y, $banner_id ) {
		$id          = 0;
		$banner_data = load_banner_constants( $banner_id );
		$max_y       = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];
		$max_x       = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];

		for ( $y2 = 0; $y2 < $max_y; $y2 += $banner_data['BLK_HEIGHT'] ) {
			for ( $x2 = 0; $x2 < $max_x; $x2 += $banner_data['BLK_WIDTH'] ) {
				if ( $x == $x2 && $y == $y2 ) {
					return $id;
				}
				$id ++;
			}
		}

		return $id;
	}

	public static function unreserve_block( $block_id, $banner_id ) {
		global $wpdb;

		$banner_id = intval( $banner_id );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE block_id = %d AND banner_id = %d",
				intval( $block_id ),
				$banner_id
			),
			ARRAY_A
		);

		if ( $row ) {
			if ( $row['status'] == 'reserved' ) {
				$wpdb->delete(
					MDS_DB_PREFIX . "blocks",
					array( 'status' => 'reserved', 'block_id' => intval( $block_id ), 'banner_id' => $row['banner_id'] ),
					array( '%s', '%d', '%d' )
				);
			}

			// process the grid, if auto_publish is on
			$b_row = load_banner_row( $banner_id );
			if ( $b_row['auto_publish'] == 'Y' ) {
				process_image( $banner_id );
				publish_image( $banner_id );
				process_map( $banner_id );
			}
		}
	}

	public static function is_block_free( $block_id, $banner_id ) {
		global $wpdb;
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . MDS_DB_PREFIX . "blocks WHERE block_id = %d AND banner_id = %d",
				intval( $block_id ),
				intval( $banner_id )
			)
		);
		
		return $result == 0;
	}
}