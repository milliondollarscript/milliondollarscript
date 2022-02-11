<?php
/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-01-30 17:07:25 EST
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */

try {

	require_once __DIR__ . "/../include/login_functions.php";
	mds_start_session();
	define( 'NO_HOUSE_KEEP', 'YES' );

	require_once __DIR__ . "/../include/init.php";

	if ( class_exists( 'Imagick' ) ) {
		$imagine = new Imagine\Imagick\Imagine();
	} else if ( function_exists( 'gd_info' ) ) {
		$imagine = new Imagine\Gd\Imagine();
	}

	global $f2;
	$BID = $f2->bid();

	$banner_data = load_banner_constants( $BID );

	$imagine = new Imagine\Gd\Imagine();

	$user_id  = $_SESSION['MDS_ID'];
	$filename = get_tmp_img_name();
	if ( file_exists( $filename ) ) {
		$image = $imagine->open( $filename );
	} else {
		$image = $imagine->open( BASE_PATH . '/images/pointer.png' );
	}

	// autorotate
	$imagine->setMetadataReader( new \Imagine\Image\Metadata\ExifMetadataReader() );
	$filter = new Imagine\Filter\Transformation();
	$filter->add( new Imagine\Filter\Basic\Autorotate() );
	$filter->apply( $image );

	// image size
	$box = $image->getSize();
	$new_size = get_required_size( $box->getWidth(), $box->getHeight(), $banner_data );
	$pixel_count = $new_size[0] * $new_size[1];
	$block_size  = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

	// make it smaller
	if ( MDS_RESIZE == 'YES' ) {
		$rescale = [];
		if ( ( $block_size > $banner_data['G_MAX_BLOCKS'] ) && ( $banner_data['G_MAX_BLOCKS'] > 0 ) ) {
			$rescale['x'] = min($banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $new_size[0]);
			$rescale['y'] = min($banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $new_size[0]);
		} else if ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {
			$rescale['x'] = min($banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $new_size[0]);
			$rescale['y'] = min($banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $new_size[0]);
		}

		if ( isset( $rescale['x'] ) ) {

			// only resize if the dimensions are different
			if ( $rescale['x'] != $box->getWidth() || $rescale['y'] != $box->getHeight() ) {
				$resize = new Imagine\Image\Box( $rescale['x'], $rescale['y'] );
				$image->resize( $resize );
			}
		}
	}

	// show
	$ext     = "png";
	$mime    = "png";
	$options = array( 'png_compression_level' => 9 );
	if ( OUTPUT_JPEG == 'Y' ) {
		$ext     = "jpg";
		$mime    = "jpeg";
		$options = array( 'jpeg_quality' => JPEG_QUALITY );
	} else if ( OUTPUT_JPEG == 'N' ) {
		// defaults to png, set above
	} else if ( OUTPUT_JPEG == 'GIF' ) {
		$ext     = "gif";
		$mime    = "gif";
		$options = array( 'flatten' => false );
	}
	$image->show( $ext, $options );
} catch ( Exception $e ) {

	error_log( $e->getMessage() );
	error_log( $e->getTraceAsString() );
}