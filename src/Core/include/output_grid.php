<?php

use MillionDollarScript\Classes\Data\Config;
use Imagine\Image\Box;
use Imagine\Image\Point;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\System\Logs;

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

/**
 * Applies opacity to a GD image resource by modifying its alpha channel.
 *
 * @param \GdImage $image The image resource to modify.
 * @param int $opacity Opacity percentage (0-100).
 * @return bool True on success, false on failure.
 */
function mds_apply_opacity_gd(\GdImage &$image, int $opacity): bool {

    // Check for invalid opacity values
    if ($opacity < 0 || $opacity > 100) {
        return false; 
    }

    // No change needed if opacity is 100%
    if ($opacity == 100) {
        return true; 
    }

    // Convert indexed to true color if necessary
    if (!imageistruecolor($image)) {
        imagetruecolortopalette($image, false, 256); 
    }

    $width = imagesx($image);
    $height = imagesy($image);

    // Disable alpha blending during pixel manipulation
    imagealphablending($image, false); 

    // Ensure alpha channel is saved when the image is output
    imagesavealpha($image, true);

    $opacityFactor = $opacity / 100.0;

    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $rgba = imagecolorat($image, $x, $y);

            // Extract existing alpha (0=opaque, 127=transparent)
            $alpha = ($rgba & 0x7F000000) >> 24; 

            // Calculate new alpha based on opacity factor
            // Formula: New = 127 - ( (127 - Old) * Factor )
            $newAlpha = 127 - (int)(((127 - $alpha) * $opacityFactor)); 

            // Clamp newAlpha to GD's valid range (0-127)
            $newAlpha = max(0, min(127, $newAlpha));

            // Allocate the new color with the calculated alpha
            $color = imagecolorallocatealpha($image, 
                ($rgba & 0xFF0000) >> 16,
                ($rgba & 0x00FF00) >> 8,
                $rgba & 0x0000FF,
                $newAlpha
            );

            // Check for color allocation failure
            if ($color === false) return false; 

            // Set the pixel to the new color
            imagesetpixel($image, $x, $y, $color);
        }
    }

    // Re-enable alpha blending if it might be needed later
    imagealphablending($image, true);

    return true;
}

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
		$BANNER_PATH = \MillionDollarScript\Classes\System\Utility::get_upload_path() . 'grids/';

		// Get the file extension if the file doesn't already have one
		$ext = pathinfo( $file, PATHINFO_EXTENSION );
		if ( empty( $ext ) ) {
			$ext = \MillionDollarScript\Classes\System\Utility::get_file_extension();
		}

		// Get the last modification time of the orders
		$last_modification_time = \MillionDollarScript\Classes\Orders\Orders::get_last_order_modification_time();

		// Get the hash value of the types of blocks to show in the grid
		$typehash = md5( serialize( $types ) );

		// Get the file path and url
		$filename = "grid$BID-$last_modification_time-$typehash";
		$file     = $BANNER_PATH . $filename;
		$fullfile = wp_normalize_path( $file . '.' . $ext );

		// grid1-856848500-1bf8f4d8717252cf8010a67f0b3c50b0.png
		if ( file_exists( $fullfile ) && filemtime( $fullfile ) >= $last_modification_time ) {
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

	// Dynamically select image driver: Imagick if available, otherwise GD
	if ( extension_loaded('imagick') && class_exists('Imagick') ) {
		$imagine = new Imagine\Imagick\Imagine();
	} elseif ( function_exists('gd_info') ) {
		$imagine = new Imagine\Gd\Imagine();
	} else {
		wp_die('No supported image driver available.');
	}

	// Determine if using GD driver
	$useGd = ($imagine instanceof \Imagine\Gd\Imagine);

	$banner_data = load_banner_constants( $BID );

	// Precompute grid dimensions
	$blkW = (int)$banner_data['BLK_WIDTH'];
	$blkH = (int)$banner_data['BLK_HEIGHT'];
	$cols = (int)$banner_data['G_WIDTH'];
	$rows = (int)$banner_data['G_HEIGHT'];
	$gridW = $cols * $blkW;
	$gridH = $rows * $blkH;

	// load blocks
	$block_size  = new Imagine\Image\Box( $blkW, $blkH );
	$palette     = new Imagine\Image\Palette\RGB();
	$color       = $palette->color( '#000', 0 );
	$zero_point  = new Imagine\Image\Point( 0, 0 );
	$blank_block = $imagine->create( $block_size, $color );

	// default grid block
	/** @var \Imagine\Gd\Image $default_block */
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
				// Use glob to find any background image for this grid
				$background_files = glob( \MillionDollarScript\Classes\System\Utility::get_upload_path() . "grids/background{$BID}.*" );
				if ( ! empty($background_files) ) {
					// Open the first found background file
					$background = $imagine->open( $background_files[0] );
				}
				break;
			case 'orders':
				$show_orders       = true;
				$orders_grid_block = $blank_block->copy();
				$tmp_block         = $imagine->load( $banner_data['USR_ORD_BLOCK'] );
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
			// Conditionally resize based on auto-resize option
			if (Options::get_option('resize') == 'YES') {
				// Force the image to fill the entire block space
				$block = $block->thumbnail($block_size, Imagine\Image\ManipulatorInterface::THUMBNAIL_OUTBOUND);
				// Ensure the processed image is exactly the block size
				if ($block->getSize()->getWidth() !== $blkW || $block->getSize()->getHeight() !== $blkH) {
					$blank = $imagine->create($block_size);
					$blank->paste($block, new Point(0, 0));
					$block = $blank;
				}
			}
			$blocks[ $row['block_id'] ] = 'order';
			$orders[ $row['block_id'] ] = $block;
		}
	}

	// Show user's blocks
	if ( isset( $user ) ) {
		$sql = "SELECT block_id,image_data FROM " . MDS_DB_PREFIX . "blocks WHERE `user_id`='" . intval( $user ) . "' AND image_data <> '' AND banner_id='" . intval( $BID ) . "' AND `status` NOT IN ('deleted', 'cancelled') ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		while ( $row = mysqli_fetch_array( $result ) ) {

			$data = $row['image_data'];

			// get block image output
			if ( strlen( $data ) != 0 ) {
				$block = $imagine->load( base64_decode( $data ) );
			} else {
				$block = $orders_grid_block->copy();
			}
			// Conditionally resize based on auto-resize option
			if (Options::get_option('resize') == 'YES') {
				// Force the image to fill the entire block space
				$block = $block->thumbnail($block_size, Imagine\Image\ManipulatorInterface::THUMBNAIL_OUTBOUND);
				// Ensure the processed image is exactly the block size
				$blank = $imagine->create($block_size);
				$blank->paste($block, new Point(0, 0));
				$block = $blank;
			}
			$blocks[ $row['block_id'] ] = 'user';
			$users[ $row['block_id'] ]  = $block;
		}
	}

	// grid size
	$size = new Imagine\Image\Box( $gridW, $gridH );

	// create empty grid
	$map = $imagine->create( $size );
	/** @var \Imagine\Gd\Image $map */
	// detect GD driver for direct imagecopy
	$useGd = $map instanceof Imagine\Gd\Image;
	if ( $useGd ) {
		/** @var \GdImage $dstRes */
		$dstRes = $map->getGdResource();
	}

	// preload price zones
	if ( isset( $show_price_zones ) ) {
		$price_zone_blocks = array();
		$cell              = 0;
		for ( $y = 0; $y < $gridH; $y += $blkH ) {
			for ( $x = 0; $x < $gridW; $x += $blkW ) {

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
	/** @var \Imagine\Gd\Image[][] $grid_back */
	/** @var \Imagine\Gd\Image[][] $grid_front */
	/** @var \Imagine\Gd\Image[][] $grid_price_zone */
	$cell      = 0;
	for ( $row = 0, $y = 0; $row < $rows; $row++, $y += $blkH ) {
		for ( $col = 0, $x = 0; $col < $cols; $col++, $x += $blkW ) {

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
		for ( $row = 0, $y = 0; $row < $rows; $row++, $y += $blkH ) {
			for ( $col = 0, $x = 0; $col < $cols; $col++, $x += $blkW ) {
				if ( isset( $grid_back[ $x ] ) && isset( $grid_back[ $x ][ $y ] ) ) {
					if ( $useGd ) {
						/** @var \Imagine\Gd\Image $img */
						$img = $grid_back[ $x ][ $y ];
						/** @var \GdImage $src */
						$src = $img->getGdResource();
						imagecopy( $dstRes, $src, $x, $y, 0, 0, $blkW, $blkH );
					} else {
						$map->paste( $grid_back[ $x ][ $y ], new Imagine\Image\Point( $x, $y ) );
					}
				} else {
					// add grid behind if nothing's there in case images are transparent
					if ( $useGd ) {
						/** @var \Imagine\Gd\Image $img */
						$img = $default_block;
						/** @var \GdImage $src */
						$src = $img->getGdResource();
						imagecopy( $dstRes, $src, $x, $y, 0, 0, $blkW, $blkH );
					} else {
						$map->paste( $default_block, new Imagine\Image\Point( $x, $y ) );
					}
				}
			}
		}
	}

	// blend in the background
	if ( isset( $background ) ) {
		// Background image size
		$bgsize = $background->getSize();

		// Rescale image to fit within the size of the grid
		$new_dimensions = resize_dimensions( $gridW, $gridH, $bgsize->getWidth(), $bgsize->getHeight() );

		// Resize to max size
		$background->resize( new Imagine\Image\Box( $new_dimensions['width'], $new_dimensions['height'] ) );

		// Get new size
		$bgsize = $background->getSize();

		// calculate coords to paste at
		$bgx = ( $gridW / 2 ) - ( $bgsize->getWidth() / 2 );
		$bgy = ( $gridH / 2 ) - ( $bgsize->getHeight() / 2 );

		// make sure coords aren't outside the box
		if ( $bgx < 0 ) {
			$bgx = 0;
		}
		if ( $bgy < 0 ) {
			$bgy = 0;
		}

		// Get opacity setting (0-100, default 100)
		$opacity = get_option( 'mds_background_opacity_' . $BID, 100 );

		// paste background image into grid
		if ( $useGd ) {
			/** @var \Imagine\Gd\Image $img */
			$img = $background;
			/** @var \GdImage $src */
			$src = $img->getGdResource();

			// Apply opacity to the source background image using our helper function
			if (mds_apply_opacity_gd($src, $opacity)) {
				// Enable alpha blending on the destination before copying
				imagealphablending($dstRes, true);
				imagesavealpha($dstRes, true); // Ensure destination saves alpha too

				// Use imagecopy (respects alpha) instead of imagecopymerge
				imagecopy( $dstRes, $src, $bgx, $bgy, 0, 0, $bgsize->getWidth(), $bgsize->getHeight() );
			} else {
				// Handle error if opacity application failed (optional)
				Logs::log("MDS Error: Failed to apply opacity to GD background image for BID: $BID");
				// As a fallback, maybe copy without opacity?
				// imagecopy( $dstRes, $src, $bgx, $bgy, 0, 0, $bgsize->getWidth(), $bgsize->getHeight() );
			}

		} else {
			// Apply opacity with Imagick (compatible with Imagick 3.x)
			// Opacity isn't standardized in Imagine core effects, so driver-specific logic is needed.
			/** @var \Imagine\Imagick\Image $background */

			// Get the underlying Imagick object
			/** @var \Imagick $imagick */
			$imagick = $background->getImagick(); 
			// Ensure alpha channel is usable
			$imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE); 
			// Multiply alpha channel by opacity factor
			$imagick->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $opacity / 100.0, \Imagick::CHANNEL_ALPHA);
			$map->paste( $background, new Imagine\Image\Point( $bgx, $bgy ) );
		}
	}

	// paste the blocks
	for ( $row = 0, $y = 0; $row < $rows; $row++, $y += $blkH ) {
		for ( $col = 0, $x = 0; $col < $cols; $col++, $x += $blkW ) {
			if ( isset( $grid_front[ $x ] ) && isset( $grid_front[ $x ][ $y ] ) ) {
				if ( $useGd ) {
					/** @var \Imagine\Gd\Image $img */
					$img = $grid_front[ $x ][ $y ];
					/** @var \GdImage $src */
					$src = $img->getGdResource();
					imagecopy( $dstRes, $src, $x, $y, 0, 0, $blkW, $blkH );
				} else {
					$map->paste( $grid_front[ $x ][ $y ], new Imagine\Image\Point( $x, $y ) );
				}
			}
		}
	}

	// paste price zone layer
	for ( $row = 0, $y = 0; $row < $rows; $row++, $y += $blkH ) {
		for ( $col = 0, $x = 0; $col < $cols; $col++, $x += $blkW ) {
			if ( isset( $grid_price_zone[ $x ] ) && isset( $grid_price_zone[ $x ][ $y ] ) ) {
				if ( $useGd ) {
					/** @var \Imagine\Gd\Image $img */
					$img = $grid_price_zone[ $x ][ $y ];
					/** @var \GdImage $src */
					$src = $img->getGdResource();
					imagecopy( $dstRes, $src, $x, $y, 0, 0, $blkW, $blkH );
				} else {
					$map->paste( $grid_price_zone[ $x ][ $y ], new Imagine\Image\Point( $x, $y ) );
				}
			}
		}
	}

	// output price zone text
	if ( isset( $show_price_zones_text ) ) {
		/** @var \Imagine\Gd\Image $zmap */
		// skip PNG roundtrip when using GD driver
		if ( $map instanceof Imagine\Gd\Image ) {
			$zmap = $map;
		} else {
			$gdImagine = new Imagine\Gd\Imagine();
			$binary   = $map->get('png', ['png_compression_level' => 9]);
			/** @var \Imagine\Gd\Image $zmap */
			$zmap      = $gdImagine->load($binary);
			unset( $map );
		}
		/** @var \GdImage $gdRes */
		$gdRes = $zmap->getGdResource();

		$row_c       = 0;
		$col_c       = 0;
		$textcolor   = imagecolorallocate( $gdRes, 0, 0, 0 );
		$textcolor_w = imagecolorallocate( $gdRes, 255, 255, 255 );

		for ( $y = 0; $y < $gridH; $y += $blkH ) {
			for ( $x = 0; $x < $gridW; $x += $blkW ) {

				if ( $y == 0 ) {
					$spaces = str_repeat( ' ', 3 - strlen( $col_c ) );
					imagestringup( $gdRes, 1, $x, 15, $spaces . "$col_c", $textcolor_w );
					imagestringup( $gdRes, 1, $x + 1, 15 + 1, $spaces . "$col_c", $textcolor );
					$col_c ++;
				}
			}

			imagestring( $gdRes, 1, 1, $y, "$row_c", $textcolor_w );
			imagestring( $gdRes, 1, 2, $y + 1, "$row_c", $textcolor );
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
