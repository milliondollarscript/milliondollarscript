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

namespace MillionDollarScript\Classes\Orders;

use MillionDollarScript\Classes\Data\Config;
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

		$sql = "SELECT status, user_id, ad_id, order_id FROM " . MDS_DB_PREFIX . "blocks WHERE block_id=" . intval( $clicked_block ) . " AND banner_id=" . intval( $BID );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		$blocksrow = mysqli_fetch_array( $result );

		$orderid = Orders::get_current_order_in_progress();

		if ( ( $blocksrow == null || $blocksrow['status'] == '' ) || ( ( $blocksrow['status'] == 'reserved' || $blocksrow['order_id'] == $orderid ) && ( $blocksrow['user_id'] == get_current_user_id() ) ) ) {

			if ( $orderid == null ) {
				$orderid = Orders::create_order( $user_id, $BID );
			}

			// put block on order
			$sql = "SELECT blocks,status,ad_id,order_id FROM " . MDS_DB_PREFIX . "orders WHERE user_id=" . get_current_user_id() . " AND order_id=" . $orderid . " AND banner_id=" . intval( $BID ) . " AND status!='deleted'";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
			$ordersrow = mysqli_fetch_array( $result );
			if ( isset( $ordersrow ) && $ordersrow['blocks'] != '' ) {
				$blocks = explode( ",", $ordersrow['blocks'] );
				array_walk( $blocks, 'intval' );
			}

			// $blocks2 will be modified based on deselections
			$blocks2 = $blocks;

			$is_adjacent = false;

			$pos            = self::get_block_position( $clicked_block, $BID );
			$blocks_per_row = $banner_data['G_WIDTH'];
			$blocks_per_col = $banner_data['G_HEIGHT'];
			$max_x_px       = $blocks_per_row * $banner_data['BLK_WIDTH'];
			$max_y_px       = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];
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
								$is_adjacent      = true;
							}
						} elseif ( ! empty( $size ) ) {
							// Select logic
							$invert = Config::get( 'INVERT_PIXELS' ) === 'YES';
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

			// max x and y values
			$max_x = $banner_data['G_WIDTH'];
			$max_y = $banner_data['G_HEIGHT'];

			// check for adjacent blocks if more than one block exists
			if ( sizeof( $blocks ) > 0 && sizeof( $clicked_blocks ) > 0 ) {
				$id = 0;

				for ( $y = 0; $y < $max_y; $y ++ ) {
					for ( $x = 0; $x < $max_x; $x ++ ) {
						if ( in_array( $id, $blocks ) ) {
							// check all clicked blocks at once for adjacency
							foreach ( $clicked_blocks as $clicked_block2 ) {
								// top
								if ( $id - $max_x == $clicked_block2 && $y >= 0 ) {
									$is_adjacent = true;
								}

								// bottom
								if ( $id + $max_x == $clicked_block2 && $y <= $max_y ) {
									$is_adjacent = true;
								}

								// left
								if ( $id - 1 == $clicked_block2 && $x >= 0 ) {
									$is_adjacent = true;
								}

								// right
								if ( $id + 1 == $clicked_block2 && $x <= $max_x ) {
									$is_adjacent = true;
								}
							}
						}

						$id ++;
					}
				}
			} else {
				// only one block so pretend it's adjacent
				$is_adjacent = true;
			}

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
				$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE order_id != " . $orderid . " AND block_id IN(" . $order_blocks . ") AND banner_id=" . intval( $BID );
				$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
				$num_rows = mysqli_num_rows( $result );
				if ( $num_rows > 0 ) {
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
					$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE order_id != " . $orderid . " AND block_id IN(" . $order_blocks . ") AND banner_id=" . intval( $BID );
					$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
					$num_rows = mysqli_num_rows( $result );
					if ( $num_rows > 0 ) {
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
//				error_log( "Current order id: " . $current_order_id );
//				\MillionDollarScript\Classes\System\Debug::log_trace();

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
					mysqli_real_escape_string( $GLOBALS['connection'], $order_blocks ),
					floatval( $price ),
					intval( $quantity ),
					intval( $BID ),
					mysqli_real_escape_string( $GLOBALS['connection'], Currency::get_default_currency() ),
					intval( $banner_data['DAYS_EXPIRE'] ),
					$now,
					$adid,
					mysqli_real_escape_string( $GLOBALS['connection'], $banner_data['AUTO_APPROVE'] ),
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

				$sql = "DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE user_id=" . get_current_user_id() . " AND (status='reserved' OR order_id='" . $order_id . "') AND banner_id='" . intval( $BID ) . "' ";
				mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

				$cell = 0;

				$blocks_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];
				$blocks_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];

				for ( $y = 0; $y < $blocks_y; $y += $banner_data['BLK_HEIGHT'] ) {
					for ( $x = 0; $x < $blocks_x; $x += $banner_data['BLK_WIDTH'] ) {
						if ( in_array( $cell, $new_blocks ) ) {
							$price = get_zone_price( $BID, $y, $x );

							// reserve block
							$sql = "REPLACE INTO `" . MDS_DB_PREFIX . "blocks` ( `block_id` , `user_id` , `status` , `x` , `y` , `image_data` , `url` , `alt_text`, `approved`, `banner_id`, `ad_id`, `currency`, `price`, `order_id`, `click_count`, `view_count`) VALUES (" . intval( $cell ) . ",  " . get_current_user_id() . " , 'reserved' , " . intval( $x ) . " , " . intval( $y ) . " , '' , '' , '', '" . mysqli_real_escape_string( $GLOBALS['connection'], $banner_data['AUTO_APPROVE'] ) . "', " . intval( $BID ) . ", " . $adid . ", '" . mysqli_real_escape_string( $GLOBALS['connection'], Currency::get_default_currency() ) . "', " . floatval( $price ) . ", " . intval( $order_id ) . ", 0, 0)";
							mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

							$total += $price;
						}
						$cell ++;
					}
				}

				// update price
				$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET price='" . floatval( $total ) . "' WHERE order_id='" . intval( $order_id ) . "'";
				mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

				$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET original_order_id='" . intval( $order_id ) . "' WHERE order_id='" . intval( $order_id ) . "'";
				mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

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

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "blocks where block_id=" . intval( $block_id ) . " and banner_id=" . intval( $banner_id );

		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );

		$sql = "select * from " . MDS_DB_PREFIX . "blocks where order_id=" . intval( $row['order_id'] );
		$result3 = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		// find high x, y & low x, y
		// low x,y is the top corner, high x,y is the bottom corner

		$high_x = $high_y = $low_x = $low_y = "";
		while ( $block_row = mysqli_fetch_array( $result3 ) ) {

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
		$sql = "SELECT * from " . MDS_DB_PREFIX . "blocks where block_id=" . intval( $block_from ) . " AND banner_id=" . intval( $banner_id );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$source_block = mysqli_fetch_array( $result );

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

		$sql = "REPLACE INTO `" . MDS_DB_PREFIX . "blocks` ( `block_id` , `user_id` , `status` , `x` , `y` , `image_data` , `url` , `alt_text`, `file_name`, `mime_type`,  `approved`, `published`, `banner_id`, `currency`, `price`, `order_id`, `click_count`, `view_count`, `ad_id`) VALUES (" . intval( $block_to ) . ",  " . intval( $source_block['user_id'] ) . " , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['status'] ) . "' , " . intval( $x ) . " , " . intval( $y ) . " , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['image_data'] ) . "' , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['url'] ) . "' , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['alt_text'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['file_name'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['mime_type'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['approved'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['published'] ) . "', " . intval( $banner_id ) . ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['currency'] ) . "', " . floatval( $source_block['price'] ) . ", " . intval( $source_block['order_id'] ) . ", " . intval( $source_block['click_count'] ) . ", " . intval( $source_block['view_count'] ) . ", " . intval( $source_block['ad_id'] ) . ")";

		global $f2;
		$f2->debug( "Moved Block - " . $sql );

		mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		# delete 'from' block
		$sql = "DELETE from " . MDS_DB_PREFIX . "blocks WHERE block_id='" . intval( $block_from ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		$f2->debug( "Deleted block_from - " . $sql );

		// Update the order record
		$sql = "SELECT * from " . MDS_DB_PREFIX . "orders WHERE order_id='" . intval( $source_block['order_id'] ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$order_row  = mysqli_fetch_array( $result );
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

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET blocks='" . $sql_blocks . "' WHERE order_id=" . intval( $source_block['order_id'] );
		# update the customer's order
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		$f2->debug( "Updated order - " . $sql );

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
		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "blocks` WHERE `block_id`='" . intval( $block_id ) . "' AND `banner_id`='" . intval( $banner_id ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

		while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
			if ( $row['status'] == 'reserved' ) {
				$sql = "DELETE FROM `" . MDS_DB_PREFIX . "blocks` WHERE `status`='reserved' AND `block_id`='" . intval( $block_id ) . "' AND `banner_id`='" . $row['banner_id'] . "'";
				mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql . " (cancel order) " );
			}
		}

		// process the grid, if auto_publish is on
		$b_row = load_banner_row( $row['banner_id'] );
		if ( $b_row['auto_publish'] == 'Y' ) {
			process_image( $row['banner_id'] );
			publish_image( $row['banner_id'] );
			process_map( $row['banner_id'] );
		}
	}

	public static function is_block_free( $block_id, $banner_id ) {

		$sql = "SELECT * from " . MDS_DB_PREFIX . "blocks where block_id='" . intval( $block_id ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
		//echo "$sql<br>";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		if ( mysqli_num_rows( $result ) == 0 ) {

			return true;
		} else {

			return false;
		}
	}
}