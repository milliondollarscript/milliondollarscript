<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.5
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

use MillionDollarScript\Classes\Config;

defined( 'ABSPATH' ) or exit;

/**
 * Output grid map
 *
 * @param bool $show Show the grid if true, save to file if false.
 * @param string $file File to save the grid to if $show is false.
 * @param int $BID The grid id to use.
 * @param array $types An array of types of blocks to show.
 *                      array(
 *                          'background',
 *                          'orders',
 *                          'grid',
 *                          'nfs',
 *                          'nfs_front',
 *                          'ordered',
 *                          'reserved',
 *                          'selected',
 *                          'sold',
 *                          'price_zones',
 *                          'price_zones_text',
 *                          'user',
 *                          'not_my_reserved'
 *                       )
 *
 * @param int $user_id
 *
 * @return string Progress output.
 */
function output_grid( $show, $file, $BID, $types, $user_id = 0, $cached = false, $ordering = false ) {
	if ( ! is_numeric( $BID ) ) {
		return false;
	}

	// TODO: add shutdown detection for when this times out
	// TODO: add cleanup of old images

	if ( $cached ) {

		// Banner paths
		$BANNER_PATH = \MillionDollarScript\Classes\Utility::get_upload_path() . 'grids/';

		// Get the file extension if the file doesn't already have one
		$ext = pathinfo( $file, PATHINFO_EXTENSION );
		if ( empty( $ext ) ) {
			$ext = \MillionDollarScript\Classes\Utility::get_file_extension();
		}

		// TODO: Make it save the time somewhere when publishing or updating an order and use that instead of this since this will get slow
		// Get the table checksum
		global $wpdb;
		$checksum = $wpdb->get_var( "CHECKSUM TABLE `" . MDS_DB_PREFIX . "orders`", 1 );

		// Get the hash value of the types of blocks to show in the grid
		$typehash = md5( serialize( $types ) );

		// Get the file path and url
		$filename = "grid$BID-$checksum-$typehash";
		$file     = $BANNER_PATH . $filename;
		$fullfile = wp_normalize_path( $file . '.' . $ext );

		//MillionDollarScript\Classes\Debug::output( $fullfile, 'log' );

		// grid1-856848500-1bf8f4d8717252cf8010a67f0b3c50b0.png
		if ( file_exists( $fullfile ) ) {
			// If the file exists then output it directly.
			header( 'Content-Type:' . 'image/' . $ext );
			header( 'Content-Length: ' . filesize( $fullfile ) );
			readfile( $fullfile );
		} else {
			// The file doesn't exist so save it and then output it.
			// save the grid image since it doesn't exist yet
			output_grid( false, $file, $BID, $types, $user_id, false, $ordering );
			output_grid( false, $file, $BID, $types, $user_id, true, $ordering );
		}

		return "Saved " . $fullfile;
	}

	$progress = 'Please wait.. Processing the Grid image with GD';

	// Was getting weird errors so removed Imagick
// 	[04-Jan-2023 17:13:32 UTC] PHP Fatal error:  Uncaught ImagickException: No IDATs written into file `/home/user/public_html/wp-content/uploads/milliondollarscript/grids/grid158.png' @ error/png.c/MagickPNGErrorHandler/1642 in /home/user/public_html/wp-content/plugins/milliondollarscript-two-main/src/Core/vendor/imagine/imagine/src/Imagick/Image.php:343
// Stack trace:
// #0 /home/user/public_html/wp-content/plugins/milliondollarscript-two-main/src/Core/vendor/imagine/imagine/src/Imagick/Image.php(343): Imagick->writeImages()
// #1 /home/user/public_html/wp-content/plugins/milliondollarscript-two-main/src/Core/include/output_grid.php(539): Imagine\Imagick\Image->save()
// #2 /home/user/public_html/wp-content/plugins/milliondollarscript-two-main/src/Core/include/image_functions.php(107): output_grid()
// #3 /home/user/public_html/wp-content/plugins/milliondollarscript-two-main/src/Core/admin/process.php(47): process_image()
// #4 {main}
//
// Next Imagine\Exception\RuntimeException: Save operation failed in /home/user/public_html/wp-content/plugins/milliondollarscript-two-main/src/Core/vendor/imagine/imagine/src/Imagick/Image.php:345
// Stack trace:
// #0 /home/user/public_html/wp-content/plugins/milliondollarscript-two-main/src/Core/include/output_grid.php(539): Imagine\Imagick\Image->save()
// #1 /home/user/public_html/wp-content/plugins/milliondollarscript-two-main/src/Core/include/image_functions.php(107): output_grid()
// #2 /home/user/public_html/wp-content/plugins/milliondollarscript-two-main/src/Core/admin/process.php(47): process_image()
// #3 {main}
//   thrown in /home/user/public_html/wp-content/plugins/milliondollarscript-two-main/src/Core/vendor/imagine/imagine/src/Imagick/Image.php on line 345

	// if ( class_exists( 'Imagick' ) ) {
	// 	$imagine = new Imagine\Imagick\Imagine();
	// } else if ( function_exists( 'gd_info' ) ) {
	$imagine = new Imagine\Gd\Imagine();
	// }

	$banner_data = load_banner_constants( $BID );

	// load blocks
	$block_size  = new Imagine\Image\Box( $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );
	$palette     = new Imagine\Image\Palette\RGB();
	$color       = $palette->color( '#000', 0 );
	$zero_point  = new Imagine\Image\Point( 0, 0 );
	$blank_block = $imagine->create( $block_size, $color );

	// default grid block
	$default_block = $blank_block->copy();

	if ( ! $ordering ) {
		$tmp_block = $imagine->load( $banner_data['GRID_BLOCK'] );
	} else {
		$tmp_block = $imagine->load( $banner_data['USR_GRID_BLOCK'] );
	}

	$tmp_block->resize( $block_size );
	$default_block->paste( $tmp_block, $zero_point );

	$and_user = $and_not_user = "";

	foreach ( $types as $type ) {
		switch ( $type ) {
			case 'background':
				if ( file_exists( \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/background$BID.png" ) ) {
					$background = $imagine->open( \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/background$BID.png" );
				}
				break;
			case 'orders':
				$show_orders       = true;
				$orders_grid_block = $blank_block->copy();
				$tmp_block         = $imagine->load( $banner_data['USR_GRID_BLOCK'] );
				$tmp_block->resize( $block_size );
				$orders_grid_block->paste( $tmp_block, $zero_point );
				break;
			case 'grid':
				// this is the default grid block created above
				$show_grid = true;
				break;
			case 'nfs':
				$default_nfs_block = $blank_block->copy();
				$tmp_block         = $imagine->load( $banner_data['USR_NFS_BLOCK'] );
				$tmp_block->resize( $block_size );
				$default_nfs_block->paste( $tmp_block, $zero_point );
				break;
			case 'nfs_front':
				$default_nfs_front_block = $blank_block->copy();
				$tmp_block               = $imagine->load( $banner_data['NFS_BLOCK'] );
				$tmp_block->resize( $block_size );
				$default_nfs_front_block->paste( $tmp_block, $zero_point );
				break;
			case 'ordered':
				$default_ordered_block = $blank_block->copy();
				$tmp_block             = $imagine->load( $banner_data['USR_ORD_BLOCK'] );
				$tmp_block->resize( $block_size );
				$default_ordered_block->paste( $tmp_block, $zero_point );
				break;
			case 'reserved':
				$default_reserved_block = $blank_block->copy();
				$tmp_block              = $imagine->load( $banner_data['USR_RES_BLOCK'] );
				$tmp_block->resize( $block_size );
				$default_reserved_block->paste( $tmp_block, $zero_point );
				break;
			case 'selected':
				$default_selected_block = $blank_block->copy();
				$tmp_block              = $imagine->load( $banner_data['USR_SEL_BLOCK'] );
				$tmp_block->resize( $block_size );
				$default_selected_block->paste( $tmp_block, $zero_point );
				break;
			case 'sold':
				$default_sold_block = $blank_block->copy();
				$tmp_block          = $imagine->load( $banner_data['USR_SOL_BLOCK'] );
				$tmp_block->resize( $block_size );
				$default_sold_block->paste( $tmp_block, $zero_point );
				break;
			case 'price_zones':
				$show_price_zones = true;

				$cyan       = $palette->color( array( 0, 255, 255 ), 50 );
				$cyan_block = $imagine->create( $block_size, $cyan );

				$yellow       = $palette->color( array( 255, 255, 0 ), 50 );
				$yellow_block = $imagine->create( $block_size, $yellow );

				$magenta       = $palette->color( array( 255, 0, 255 ), 50 );
				$magenta_block = $imagine->create( $block_size, $magenta );

				$white       = $palette->color( array( 255, 255, 255 ), 50 );
				$white_block = $imagine->create( $block_size, $white );
				break;
			case 'price_zones_text':
				$show_price_zones_text = true;
				break;
			case 'user':
				$user     = $user_id;
				$and_user = " AND `user_id`=" . intval( $user );
				break;
			case 'not_my_reserved':
				$and_not_user = " AND `user_id`!=" . get_current_user_id();
				break;
			default:
				break;
		}
	}

	$blocks = $orders = $price_zones = $users = $nfs = array();

	// preload nfs blocks
	if ( isset( $default_nfs_block ) ) {
		$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE `status`='nfs' AND banner_id='" . intval( $BID ) . "' " . $and_user;
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		// nfs covered images
		if ( isset( $banner_data['NFS_COVERED'] ) == 'Y' ) {
			$sql = "SELECT block_id,image_data FROM " . MDS_DB_PREFIX . "blocks WHERE `status`='nfs' AND image_data <> '' AND banner_id='" . intval( $BID ) . "'";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

			while ( $row = mysqli_fetch_array( $result ) ) {

				$data = $row['image_data'];

				// get block image output
				if ( strlen( $data ) != 0 ) {
					$block = $imagine->load( base64_decode( $data ) );
				} else {
					$block = $default_nfs_block->copy();
				}
				$block->resize( $block_size );

				$blocks[ $row['block_id'] ] = 'nfs';
				$nfs[ $row['block_id'] ]    = $block;
			}
		} else {
			while ( $row = mysqli_fetch_array( $result ) ) {
				$blocks[ $row['block_id'] ] = 'nfs';
				$nfs[ $row['block_id'] ]    = $default_nfs_block;
			}
		}
	}

	// TODO: It should probably reload this from the grid settings when a new image is uploaded.
	// preload nfs_front blocks (nfs blocks appearing in front of the background)
	if ( isset( $default_nfs_front_block ) ) {
		$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE `status`='nfs' AND banner_id='" . intval( $BID ) . "' " . $and_user;
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		// nfs covered images
		if ( isset( $banner_data['NFS_COVERED'] ) && $banner_data['NFS_COVERED'] == 'Y' ) {
			$sql = "SELECT block_id,image_data FROM " . MDS_DB_PREFIX . "blocks WHERE `status`='nfs' AND image_data <> '' AND banner_id='" . intval( $BID ) . "'";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

			while ( $row = mysqli_fetch_array( $result ) ) {

				$data = $row['image_data'];

				// get block image output
				if ( strlen( $data ) != 0 ) {
					$block = $imagine->load( base64_decode( $data ) );
				} else {
					$block = $default_nfs_front_block->copy();
				}
				$block->resize( $block_size );

				$blocks[ $row['block_id'] ] = 'nfs_front';
				$nfs[ $row['block_id'] ]    = $block;
			}
		} else {
			while ( $row = mysqli_fetch_array( $result ) ) {
				$blocks[ $row['block_id'] ] = 'nfs_front';
				$nfs[ $row['block_id'] ]    = $default_nfs_front_block;
			}
		}
	}

	// preload ordered blocks
	if ( isset( $default_ordered_block ) ) {
		$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE `status`='ordered' AND banner_id='" . intval( $BID ) . "' " . $and_user;
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		while ( $row = mysqli_fetch_array( $result ) ) {
			$blocks[ $row['block_id'] ] = 'ordered';
		}
	}

	// preload reserved blocks
	if ( isset( $default_reserved_block ) ) {
		$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE `status`='reserved' AND banner_id='" . intval( $BID ) . "' " . $and_not_user;
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		while ( $row = mysqli_fetch_array( $result ) ) {
			$blocks[ $row['block_id'] ] = 'reserved';
		}

		// from orders table
		$sql = "SELECT blocks FROM " . MDS_DB_PREFIX . "orders WHERE banner_id='" . intval( $BID ) . "' AND `status` <> 'deleted' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		while ( $row = mysqli_fetch_array( $result ) ) {
			$entries = explode( ",", $row['blocks'] );
			foreach ( $entries as $entry ) {
				$blocks[ $entry ] = 'reserved';
			}
		}
	}

	// preload selected blocks
	if ( isset( $default_selected_block ) ) {
		$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE `status`='onorder' AND banner_id='" . intval( $BID ) . "' " . $and_user;
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		while ( $row = mysqli_fetch_array( $result ) ) {
			$blocks[ $row['block_id'] ] = 'selected';
		}
	}

	// preload sold blocks
	if ( isset( $default_sold_block ) ) {
		$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE `status`='sold' AND banner_id='" . intval( $BID ) . "' " . $and_user;
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		while ( $row = mysqli_fetch_array( $result ) ) {
			$blocks[ $row['block_id'] ] = 'sold';
		}
	}

	// preload orders
	if ( isset( $show_orders ) ) {
		$sql = "SELECT block_id,image_data FROM " . MDS_DB_PREFIX . "blocks WHERE approved='Y' AND `status`='sold' AND image_data <> '' AND banner_id='" . intval( $BID ) . "' " . $and_user;
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		while ( $row = mysqli_fetch_array( $result ) ) {

			$data = $row['image_data'];

			// get block image output
			if ( strlen( $data ) != 0 ) {
				$block = $imagine->load( base64_decode( $data ) );
			} else {
				$block = $orders_grid_block->copy();
			}
			$block->resize( $block_size );

			$blocks[ $row['block_id'] ] = 'order';
			$orders[ $row['block_id'] ] = $block;
		}
	}

	// Show user's blocks
	if ( isset( $user ) ) {
		$sql = "SELECT block_id,image_data FROM " . MDS_DB_PREFIX . "blocks WHERE `user_id`='" . intval( $user ) . "' AND image_data <> '' AND banner_id='" . intval( $BID ) . "' AND `status` <> 'deleted' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		while ( $row = mysqli_fetch_array( $result ) ) {

			$data = $row['image_data'];

			// get block image output
			if ( strlen( $data ) != 0 ) {
				$block = $imagine->load( base64_decode( $data ) );
			} else {
				$block = $orders_grid_block->copy();
			}
			$block->resize( $block_size );

			$blocks[ $row['block_id'] ] = 'user';
			$users[ $row['block_id'] ]  = $block;
		}
	}

	// grid size
	$size = new Imagine\Image\Box( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] );

	// create empty grid
	$map = $imagine->create( $size );

	// preload price zones
	if ( isset( $show_price_zones ) ) {
		$price_zone_blocks = array();
		$cell              = 0;
		for ( $y = 0; $y < ( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ); $y += $banner_data['BLK_HEIGHT'] ) {
			for ( $x = 0; $x < ( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ); $x += $banner_data['BLK_WIDTH'] ) {

				$price_zone_color = get_zone_color( $BID, $y, $x );
				switch ( $price_zone_color ) {
					case "cyan":
						$price_zone_blocks[ $cell ] = 'price_zone';
						$price_zones[ $cell ]       = $cyan_block;
						break;
					case "yellow":
						$price_zone_blocks[ $cell ] = 'price_zone';
						$price_zones[ $cell ]       = $yellow_block;
						break;
					case "magenta":
						$price_zone_blocks[ $cell ] = 'price_zone';
						$price_zones[ $cell ]       = $magenta_block;
						break;
					case "white":
						$price_zone_blocks[ $cell ] = 'price_zone';
						$price_zones[ $cell ]       = $white_block;
						break;
					default:
						break;
				}

				$cell ++;
			}
		}
	}

	// preload full grid
	$grid_back = $grid_front = $grid_price_zone = array();
	$cell      = 0;
	for ( $y = 0; $y < ( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ); $y += $banner_data['BLK_HEIGHT'] ) {
		for ( $x = 0; $x < ( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ); $x += $banner_data['BLK_WIDTH'] ) {

			if ( isset( $blocks[ $cell ] ) && $blocks[ $cell ] != '' ) {
				if ( isset( $show_orders ) && $blocks[ $cell ] == "order" ) {
					$grid_front[ $x ][ $y ] = $orders[ $cell ];
				} else if ( isset( $user ) && $blocks[ $cell ] == "user" ) {
					$grid_front[ $x ][ $y ] = $users[ $cell ];
				} else if ( isset( $default_nfs_block ) && $blocks[ $cell ] == "nfs" ) {
					$grid_back[ $x ][ $y ] = $nfs[ $cell ];
				} else if ( isset( $default_nfs_front_block ) && $blocks[ $cell ] == "nfs_front" ) {
					$grid_front[ $x ][ $y ] = $nfs[ $cell ];
				} else if ( isset( $default_ordered_block ) && $blocks[ $cell ] == "ordered" ) {
					$grid_front[ $x ][ $y ] = $default_ordered_block;
				} else if ( isset( $default_reserved_block ) && $blocks[ $cell ] == "reserved" ) {
					$grid_front[ $x ][ $y ] = $default_reserved_block;
				} else if ( isset( $default_selected_block ) && $blocks[ $cell ] == "selected" ) {
					$grid_front[ $x ][ $y ] = $default_selected_block;
				} else if ( isset( $default_sold_block ) && $blocks[ $cell ] == "sold" ) {
					$grid_front[ $x ][ $y ] = $default_sold_block;
				} else if ( isset( $show_grid ) ) {
					$grid_back[ $x ][ $y ] = $default_block;
				}
			} else if ( isset( $show_grid ) ) {
				$grid_back[ $x ][ $y ] = $default_block;
			} else {
				$grid_back[ $x ][ $y ] = $blank_block;
			}

			// price zone grid layer
			if ( isset( $show_price_zones ) && isset( $price_zone_blocks[ $cell ] ) ) {
				$grid_price_zone[ $x ][ $y ] = $price_zones[ $cell ];
			}

			$cell ++;
		}
	}

	// grid and nfs blocks go behind the background
	if ( isset( $show_grid ) || isset( $default_nfs_block ) ) {
		for ( $y = 0; $y < ( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ); $y += $banner_data['BLK_HEIGHT'] ) {
			for ( $x = 0; $x < ( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ); $x += $banner_data['BLK_WIDTH'] ) {
				if ( isset( $grid_back[ $x ] ) && isset( $grid_back[ $x ][ $y ] ) ) {
					$map->paste( $grid_back[ $x ][ $y ], new Imagine\Image\Point( $x, $y ) );
				} else {
					// add grid behind if nothing's there in case images are transparent
					$map->paste( $default_block, new Imagine\Image\Point( $x, $y ) );
				}
			}
		}
	}

	// blend in the background
	if ( isset( $background ) ) {

		// Background image size
		$bgsize = $background->getSize();

		// Rescale image to fit within the size of the grid
		$new_dimensions = resize_dimensions( $size->getWidth(), $size->getHeight(), $bgsize->getWidth(), $bgsize->getHeight() );

		// Resize to max size
		$background->resize( new Imagine\Image\Box( $new_dimensions['width'], $new_dimensions['height'] ) );

		// Get new size
		$bgsize = $background->getSize();

		// calculate coords to paste at
		$bgx = ( $size->getWidth() / 2 ) - ( $bgsize->getWidth() / 2 );
		$bgy = ( $size->getHeight() / 2 ) - ( $bgsize->getHeight() / 2 );

		// make sure coords aren't outside the box
		if ( $bgx < 0 ) {
			$bgx = 0;
		}
		if ( $bgy < 0 ) {
			$bgy = 0;
		}

		// paste background image into grid
		$map->paste( $background, new Imagine\Image\Point( $bgx, $bgy ) );
	}

	// paste the blocks
	for ( $y = 0; $y < ( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ); $y += $banner_data['BLK_HEIGHT'] ) {
		for ( $x = 0; $x < ( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ); $x += $banner_data['BLK_WIDTH'] ) {
			if ( isset( $grid_front[ $x ] ) && isset( $grid_front[ $x ][ $y ] ) ) {
				$map->paste( $grid_front[ $x ][ $y ], new Imagine\Image\Point( $x, $y ) );
			}
		}
	}

	// paste price zone layer
	for ( $y = 0; $y < ( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ); $y += $banner_data['BLK_HEIGHT'] ) {
		for ( $x = 0; $x < ( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ); $x += $banner_data['BLK_WIDTH'] ) {
			if ( isset( $grid_price_zone[ $x ] ) && isset( $grid_price_zone[ $x ][ $y ] ) ) {
				$map->paste( $grid_price_zone[ $x ][ $y ], new Imagine\Image\Point( $x, $y ) );
			}
		}
	}

	// output price zone text
	if ( isset( $show_price_zones_text ) ) {
		$imagine = new Imagine\Gd\Imagine();

		$zmap = $imagine->load( $map );

		unset( $map );

		$row_c       = 0;
		$col_c       = 0;
		$textcolor   = imagecolorallocate( $zmap->getGdResource(), 0, 0, 0 );
		$textcolor_w = imagecolorallocate( $zmap->getGdResource(), 255, 255, 255 );

		for ( $y = 0; $y < ( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ); $y += $banner_data['BLK_HEIGHT'] ) {
			for ( $x = 0; $x < ( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ); $x += $banner_data['BLK_WIDTH'] ) {

				if ( $y == 0 ) {
					$spaces = str_repeat( ' ', 3 - strlen( $col_c ) );
					imagestringup( $zmap->getGdResource(), 1, $x, 15, $spaces . "$col_c", $textcolor_w );
					imagestringup( $zmap->getGdResource(), 1, $x + 1, 15 + 1, $spaces . "$col_c", $textcolor );
					$col_c ++;
				}
			}

			imagestring( $zmap->getGdResource(), 1, 1, $y, "$row_c", $textcolor_w );
			imagestring( $zmap->getGdResource(), 1, 2, $y + 1, "$row_c", $textcolor );
			$row_c ++;
		}

		$map = $zmap;
	}

	// set output options
	$ext     = "png";
	$mime    = "png";
	$options = array( 'png_compression_level' => 9 );

	$OUTPUT_JPEG = Config::get( 'OUTPUT_JPEG' );

	if ( $OUTPUT_JPEG == 'Y' ) {
		$ext     = "jpg";
		$mime    = "jpeg";
		$options = array( 'jpeg_quality' => Config::get( 'JPEG_QUALITY' ) );
	} else if ( $OUTPUT_JPEG == 'N' ) {
		// defaults to png, set above
	} else if ( $OUTPUT_JPEG == 'GIF' ) {
		$ext     = "gif";
		$mime    = "gif";
		$options = array( 'flatten' => false );
	}

	// output
	if ( $show ) {
		if ( Config::get( 'INTERLACE_SWITCH' ) == 'YES' ) {
			$map->interlace( Imagine\Image\ImageInterface::INTERLACE_LINE );
		}

		$map->show( $mime, $options );
	} else {

		$pathinfo = pathinfo( $file );
		$filename = wp_normalize_path( $pathinfo['dirname'] . DIRECTORY_SEPARATOR . $pathinfo['filename'] . "." . $ext );
		if ( ! touch( $filename ) ) {
			$progress .= "<b>Warning:</b> The script does not have permission write to " . $filename . " or the directory does not exist<br>";
		}
		$map->save( $filename, $options );
		$progress .= "<br>Saved as " . $filename . "<br>";
	}

	return $progress;
}
