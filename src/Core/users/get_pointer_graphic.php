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

use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Logs;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

try {
	mds_wp_login_check();

	if ( class_exists( 'Imagick' ) ) {
		$imagine = new Imagine\Imagick\Imagine();
	} else if ( function_exists( 'gd_info' ) ) {
		$imagine = new Imagine\Gd\Imagine();
	}

	global $f2;
	$BID = $f2->bid();

	$banner_data = load_banner_constants( $BID );

	$imagine = new Imagine\Gd\Imagine();

	$user_id  = get_current_user_id();
	$filename = Orders::get_tmp_img_name();
	if ( file_exists( $filename ) ) {
		$image = $imagine->open( $filename );
	} else {
		$image = $imagine->open( MDS_CORE_PATH . 'images/pointer.png' );
	}

	// autorotate
	$imagine->setMetadataReader( new \Imagine\Image\Metadata\ExifMetadataReader() );
	$filter = new Imagine\Filter\Transformation();
	$filter->add( new Imagine\Filter\Basic\Autorotate() );
	$filter->apply( $image );

	// image size
	$box         = $image->getSize();
	$new_size    = Utility::get_required_size( $box->getWidth(), $box->getHeight(), $banner_data );
	$pixel_count = $new_size[0] * $new_size[1];
	$block_size  = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

	// make it smaller
	if ( Options::get_option( 'resize' ) == 'YES' ) {
		$rescale = [];
		if ( ( $block_size > $banner_data['G_MAX_BLOCKS'] ) && ( $banner_data['G_MAX_BLOCKS'] > 0 ) ) {
			$rescale['x'] = min( $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $new_size[0] );
			$rescale['y'] = min( $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $new_size[0] );
		} else if ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {
			$rescale['x'] = min( $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $new_size[0] );
			$rescale['y'] = min( $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $new_size[0] );
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
	$ext         = "png";
	$mime        = "png";
	$options     = array( 'png_compression_level' => 9 );
	$output_jpeg = Config::get( 'OUTPUT_JPEG' );
	switch ( $output_jpeg ) {
		case 'Y':
			$ext     = "jpg";
			$mime    = "jpeg";
			$options = array( 'jpeg_quality' => Config::get( 'JPEG_QUALITY' ) );
			break;
		case 'GIF':
			$ext     = "gif";
			$mime    = "gif";
			$options = array( 'flatten' => false );
			break;
		case 'N':
			// defaults to png, set above
		default:
			break;
	}
	$image->show( $ext, $options );
} catch ( Exception $e ) {

	Logs::log( $e->getMessage() );
	Logs::log( $e->getTraceAsString() );
}