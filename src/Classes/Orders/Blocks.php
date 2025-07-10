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

	public static function select_block( $clicked_block, $banner_data, $size, $user_id ) {

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
		$removed_blocks = [];
		$return_val     = [
			'error' => false,
			'type'  => '',
			'data'  => []
		];

		global $wpdb;
		$blocksrow = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, user_id, ad_id, order_id FROM " . MDS_DB_PREFIX . "blocks WHERE block_id = %d AND banner_id = %d",
				intval( $clicked_block ),
				intval( $BID )
			),
			ARRAY_A
		);

		$orderid = Orders::get_current_order_in_progress();

		if ( ( $blocksrow == null || $blocksrow['status'] == '' ) || ( ( $blocksrow['status'] == 'reserved' || $blocksrow['order_id'] == $orderid ) && ( $blocksrow['user_id'] == get_current_user_id() ) ) ) {

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
			if ( isset( $ordersrow ) && $ordersrow['blocks'] != '' ) {
				$blocks = explode( ",", $ordersrow['blocks'] );
				array_walk( $blocks, 'intval' );
			}

			// $blocks2 will be modified based on deselections
			$blocks2 = $blocks;

			$pos            = self::get_block_position( $clicked_block, $BID );
			$blocks_per_row = $banner_data['G_WIDTH'];
			$blocks_per_col = $banner_data['G_HEIGHT'];
			$max_x_px       = $blocks_per_row * $banner_data['BLK_WIDTH'];
			$max_y_px       = $blocks_per_col * $banner_data['BLK_HEIGHT'];
			$max_blocks     = $banner_data['G_MAX_BLOCKS'] == 0 ? $blocks_per_row * $blocks_per_col : $banner_data['G_MAX_BLOCKS'];
			$max_size       = min( floor( sqrt( ( $max_blocks ) ) ), $size );

			for ( $y = 0; $y < $max_size; $y ++ ) {
				$y_pos = $pos['y'] + ( $y % $blocks_per_col ) * $banner_data['BLK_HEIGHT'];
				for ( $x = 0; $x < $max_size; $x ++ ) {
					$x_pos = $pos['x'] + ( $x % $blocks_per_row ) * $banner_data['BLK_WIDTH'];

					if ( $x_pos <= $max_x_px && $y_pos <= $max_y_px ) {
						$clicked_block = self::get_block_id_from_position( $x_pos, $y_pos, $BID );

						if ( isset( $_REQUEST['erase'] ) && $_REQUEST['erase'] == "true" ) {
							// Erase logic
							if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
								unset( $blocks2[ $block ] );
								$removed_blocks[] = $clicked_block;
							}
						} elseif ( ! empty( $size ) ) {
							// Select logic
							$invert = Options::get_option( 'invert-pixels' ) === 'yes';
							if ( $invert && ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
								// deselect
								unset( $blocks2[ $block ] );
								$removed_blocks[] = $clicked_block;
							} else {
								// select
								$clicked_blocks[] = $clicked_block;
							}
						}
					}
				}
			}

			// Check for adjacency
			$is_adjacent = self::check_adjacency( $blocks2, $clicked_blocks, $blocks_per_row, $blocks_per_col );

			// remove $clicked_blocks if not found adjacent to another block
			if ( ! $is_adjacent ) {
				$clicked_blocks = array();
				$return_val     = [
					"error" => "true",
					"type"  => "not_adjacent",
					"data"  => [
						"value" => Language::get( 'You must select a block adjacent to another one.' ),
					]
				];
			}

			// merge blocks
			$new_blocks = array_unique( array_merge( $blocks2, $clicked_blocks ) );

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
			if ( ! $max_selected && $is_adjacent && ! $existing_blocks && ( ( ! empty( $order_blocks ) || $order_blocks == '0' ) || ! empty( $removed_blocks ) || $removed_blocks == '0' ) ) {
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

				$sql = $wpdb->prepare(
					"REPLACE INTO " . MDS_DB_PREFIX . "orders (user_id, order_id, blocks, status, order_date, price, quantity, banner_id, currency, days_expire, date_stamp, ad_id, approved, order_in_progress) VALUES (%d, %d, %s, 'new', NOW(), %f, %d, %d, %s, %d, %s, %d, %s, %s)",
					$user_id,
					$orderid,
					$order_blocks,
					floatval( $price ),
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

				$order_id = $wpdb->insert_id;

				Steps::set_current_step( $order_id, 1 );

				$return_val = [
					"error" => "false",
					"type"  => "order_id",
					"data"  => [
						"value" => intval( $order_id ),
					]
				];

				// Clean up reserved blocks and blocks for this specific order only
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE user_id = %d AND (status = 'reserved' OR (order_id = %d AND status != 'sold')) AND banner_id = %d",
						get_current_user_id(),
						$order_id,
						intval( $BID )
					)
				);

				$cell = 0;

				$blocks_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];
				$blocks_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];

				for ( $y = 0; $y < $blocks_y; $y += $banner_data['BLK_HEIGHT'] ) {
					for ( $x = 0; $x < $blocks_x; $x += $banner_data['BLK_WIDTH'] ) {
						if ( in_array( $cell, $new_blocks ) ) {
							$price = get_zone_price( $BID, $y, $x );

							// reserve block
							$wpdb->replace(
								MDS_DB_PREFIX . "blocks",
								array(
									'block_id' => intval( $cell ),
									'user_id' => get_current_user_id(),
									'status' => 'reserved',
									'x' => intval( $x ),
									'y' => intval( $y ),
									'image_data' => '',
									'url' => '',
									'alt_text' => '',
									'approved' => $banner_data['AUTO_APPROVE'],
									'banner_id' => intval( $BID ),
									'ad_id' => $adid,
									'currency' => Currency::get_default_currency(),
									'price' => floatval( $price ),
									'order_id' => intval( $order_id ),
									'click_count' => 0,
									'view_count' => 0
								),
								array( '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%f', '%d', '%d', '%d' )
							);

							$total += $price;
						}
						$cell ++;
					}
				}

				// update price
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

				// check that we have ad_id, if not then create an ad for this order.
				if ( $adid == 0 ) {
					$_REQUEST['order_id'] = $order_id;
					$_REQUEST['BID']      = $BID;
					$_REQUEST['user_id']  = get_current_user_id();
				}
			}

		} else {

			if ( $blocksrow['status'] == 'nfs' ) {
				$return_val = [
					"error" => "true",
					"type"  => "advertiser_sel_nfs_error",
					"data"  => [
						"value" => Language::get( 'Sorry, cannot select this block of pixels because it is not for sale!' ),
					]
				];
			} else {
				$return_val = [
					"error" => "true",
					"type"  => "advertiser_sel_sold_error",
					"data"  => [
						"value" => Language::get_replace( 'Sorry, cannot select block %BLOCK_ID% because it is on order / sold!', "%BLOCK_ID%", $clicked_block ),
					]
				];
			}
		}

		return $return_val;
	}

	/**
	 * Check adjacency of blocks using Breadth-First Search.
	 */
	public static function check_adjacency( $blocks, $clicked_blocks, $blocks_per_row, $blocks_per_col ): bool {
		$all_blocks = array_merge( $blocks, $clicked_blocks );
		if ( empty( $all_blocks ) ) {
			return true;
		}

		$visited    = [];
		$queue      = [ reset( $all_blocks ) ];
		$directions = [ - 1, 1, - $blocks_per_row, $blocks_per_row ];

		while ( ! empty( $queue ) ) {
			$current = array_shift( $queue );
			if ( in_array( $current, $visited ) ) {
				continue;
			}
			$visited[] = $current;

			foreach ( $directions as $direction ) {
				$neighbor = $current + $direction;
				if ( in_array( $neighbor, $all_blocks ) && ! in_array( $neighbor, $visited ) ) {
					$queue[] = $neighbor;
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

		$cell     = "0";
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