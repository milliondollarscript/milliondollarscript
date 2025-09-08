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

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Payment\Currency;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\WooCommerce\WooCommerceFunctions;
use MillionDollarScript\Classes\Data\DarkModeImages;
use MillionDollarScript\Classes\Services\GridImageGenerator;
use MillionDollarScript\Classes\System\Logs;
use MillionDollarScript\Classes\Admin\Notices;

defined( 'ABSPATH' ) or exit;

global $f2;
$BID = $f2->bid();

function validate_or_defaults(): void {
	if ( ! isset( $_REQUEST['grid_width'] ) || ! is_numeric( $_REQUEST['grid_width'] ) ) {
		$_REQUEST['grid_width'] = 100;
	}

	if ( ! isset( $_REQUEST['grid_height'] ) || ! is_numeric( $_REQUEST['grid_height'] ) ) {
		$_REQUEST['grid_height'] = 100;
	}

	if ( ! isset( $_REQUEST['price_per_block'] ) || ! is_numeric( $_REQUEST['price_per_block'] ) ) {
		$_REQUEST['price_per_block'] = 1;
	}

	if ( ! isset( $_REQUEST['currency'] ) || ! in_array( $_REQUEST['currency'], Currency::get_currencies() ) ) {
		$_REQUEST['currency'] = Currency::get_default_currency();
	}

	if ( ! isset( $_REQUEST['days_expire'] ) || ! is_numeric( $_REQUEST['days_expire'] ) ) {
		$_REQUEST['days_expire'] = 0;
	}

	if ( ! isset( $_REQUEST['max_orders'] ) || ! is_numeric( $_REQUEST['max_orders'] ) ) {
		$_REQUEST['max_orders'] = 1;
	}

	if ( ! isset( $_REQUEST['max_blocks'] ) || ! is_numeric( $_REQUEST['max_blocks'] ) ) {
		$_REQUEST['max_blocks'] = 0;
	}

	if ( ! isset( $_REQUEST['min_blocks'] ) || ! is_numeric( $_REQUEST['min_blocks'] ) ) {
		$_REQUEST['min_blocks'] = 1;
	}

	if ( ! isset( $_REQUEST['auto_approve'] ) || ! in_array( $_REQUEST['auto_approve'], [ 'Y', 'N' ] ) ) {
		$_REQUEST['auto_approve'] = 'N';
	}

	if ( ! isset( $_REQUEST['auto_publish'] ) || ! in_array( $_REQUEST['auto_publish'], [ 'Y', 'N' ] ) ) {
		$_REQUEST['auto_publish'] = 'Y';
	}

	if ( ! isset( $_REQUEST['block_width'] ) || ! is_numeric( $_REQUEST['block_width'] ) ) {
		$_REQUEST['block_width'] = 10;
	}

	if ( ! isset( $_REQUEST['block_height'] ) || ! is_numeric( $_REQUEST['block_height'] ) ) {
		$_REQUEST['block_height'] = 10;
	}

	if ( ! isset( $_REQUEST['nfs_covered'] ) || ! in_array( $_REQUEST['nfs_covered'], [ 'Y', 'N' ] ) ) {
		$_REQUEST['nfs_covered'] = 'N';
	}

	if ( ! isset( $_REQUEST['enabled'] ) || ! in_array( $_REQUEST['enabled'], [ 'Y', 'N' ] ) ) {
		$_REQUEST['enabled'] = 'Y';
	}

	if ( ! isset( $_REQUEST['bgcolor'] ) || $_REQUEST['bgcolor'] != sanitize_hex_color( $_REQUEST['bgcolor'] ) ) {
		$_REQUEST['bgcolor'] = '';
	}
}

validate_or_defaults();

if ( isset( $_REQUEST['reset_image'] ) && $_REQUEST['reset_image'] != '' ) {
	global $wpdb;
	$default = get_default_image( $_REQUEST['reset_image'] );
	$image_column = sanitize_key( $_REQUEST['reset_image'] );
	$wpdb->update(
		MDS_DB_PREFIX . 'banners',
		[ $image_column => $default ],
		[ 'banner_id' => $BID ],
		[ '%s' ],
		[ '%d' ]
	);
}

function display_reset_link( $BID, $image_name ): void {
	if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'edit' ) {
		?>
        <a class="inventory-reset-link" title="Reset to default"
           onclick="confirmLink(this, 'Reset this image to deafult, are you sure?')"
           data-link='<?php echo esc_url( admin_url( 'admin.php?page=mds-manage-grids' ) ); ?>&mds-action=edit&BID=<?php echo $BID; ?>&reset_image=<?php echo urlencode( $image_name ); ?>' href="#">x</a>
		<?php
	}
}

function is_allowed_grid_file( $image_name ): bool {
	$ALLOWED_EXT = array( 'jpg', 'jpeg', 'gif', 'png' );
	$file_parts  = pathinfo( $_FILES[ $image_name ]['name'] );
	$ext         = strtolower( $file_parts['extension'] );
	if ( ! in_array( $ext, $ALLOWED_EXT ) ) {
		return false;
	} else {
		return true;
	}
}

function validate_input(): string {

	$error = "";

	if ( isset( $_REQUEST['enabled'] ) && ! in_array( $_REQUEST['enabled'], [ 'Y', 'N' ] ) ) {
		$error .= "- Is this grid enabled? Grids that aren't enabled will be hidden from users.<br>";
	}

	if ( isset( $_REQUEST['name'] ) && $_REQUEST['name'] == '' ) {
		$error .= "- Grid name not filled in<br>";
	}

	if ( isset( $_REQUEST['grid_width'] ) && $_REQUEST['grid_width'] == '' ) {
		$error .= "- Grid Width not filled in<br>";
	}

	if ( isset( $_REQUEST['grid_height'] ) && $_REQUEST['grid_height'] == '' ) {
		$error .= "- Grid Height not filled in<br>";
	}

	if ( isset( $_REQUEST['days_expire'] ) && $_REQUEST['days_expire'] == '' ) {
		$error .= "- Days Expire not filled in<br>";
	}

	if ( isset( $_REQUEST['max_orders'] ) && $_REQUEST['max_orders'] == '' ) {
		$error .= "- Max orders per customer not filled in<br>";
	}

	if ( isset( $_REQUEST['price_per_block'] ) && $_REQUEST['price_per_block'] == '' ) {
		$error .= "- Price per Block  not filled in<br>";
	}

	if ( isset( $_REQUEST['currency'] ) && $_REQUEST['currency'] == '' ) {
		$error .= "- Currency not filled in<br>";
	}

	if ( isset( $_REQUEST['block_width'] ) && ! is_numeric( $_REQUEST['block_width'] ) ) {
		$error .= "- Block width is not valid<br>";
	}

	if ( isset( $_REQUEST['block_height'] ) && ! is_numeric( $_REQUEST['block_height'] ) ) {
		$error .= "- Block height is not valid<br>";
	}

	if ( isset( $_REQUEST['max_blocks'] ) && ! is_numeric( $_REQUEST['max_blocks'] ) ) {
		$error .= "- Max Blocks is not valid<br>";
	}

	if ( isset( $_REQUEST['min_blocks'] ) && ! is_numeric( $_REQUEST['min_blocks'] ) ) {
		$error .= "- Min Blocks is not valid<br>";
	}

	if ( isset( $_REQUEST['nfs_covered'] ) && ! in_array( $_REQUEST['nfs_covered'], [ 'Y', 'N' ] ) ) {
		$error .= "- Not For Sale Image Coverage is not valid<br>";
	}

	if ( isset( $_FILES['grid_block'] ) && $_FILES['grid_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'grid_block' ) ) {
			$error .= "- Grid Block must be a valid jpg, jpeg, gif, or png file.<br>";
		}
	}

	if ( isset( $_FILES['nfs_block'] ) && $_FILES['nfs_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'nfs_block' ) ) {
			$error .= "- Not For Sale Block must be a valid jpg, jpeg, gif, or png file.<br>";
		}
	}

	if ( isset( $_FILES['usr_grid_block'] ) && $_FILES['usr_grid_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'usr_grid_block' ) ) {
			$error .= "- Not For Sale Block must be a valid jpg, jpeg, gif, or png file.<br>";
		}
	}

	if ( isset( $_FILES['usr_nfs_block'] ) && $_FILES['usr_nfs_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'usr_nfs_block' ) ) {
			$error .= "- User's Not For Sale Block must be a valid jpg, jpeg, gif, or png file.<br>";
		}
	}

	if ( isset( $_FILES['usr_ord_block'] ) && $_FILES['usr_ord_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'usr_ord_block' ) ) {
			$error .= "- User's Ordered Block must be a valid jpg, jpeg, gif, or png file.<br>";
		}
	}

	if ( isset( $_FILES['usr_res_block'] ) && $_FILES['usr_res_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'usr_res_block' ) ) {
			$error .= "- User's Reserved Block must be a valid jpg, jpeg, gif, or png file.<br>";
		}
	}

	if ( isset( $_FILES['usr_sol_block'] ) && $_FILES['usr_sol_block']['tmp_name'] != '' ) {
		if ( ! is_allowed_grid_file( 'usr_sol_block' ) ) {
			$error .= "- User's Sold Block must be a valid jpg, jpeg, gif, or png file.<br>";
		}
	}

	return $error;
}

function is_default(): bool {
	if ( isset( $_REQUEST['BID'] ) && $_REQUEST['BID'] == 1 ) {
		return true;
	}

	return false;
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'disable' ) {
	global $wpdb;
	$result = $wpdb->update(
		MDS_DB_PREFIX . 'banners',
		[ 'enabled' => 'N' ],
		[ 'banner_id' => $BID ],
		[ '%s' ],
		[ '%d' ]
	);
	if ( $result === false ) {
		die( 'Database error: ' . $wpdb->last_error );
	}
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'enable' ) {
	global $wpdb;
	$result = $wpdb->update(
		MDS_DB_PREFIX . 'banners',
		[ 'enabled' => 'Y' ],
		[ 'banner_id' => $BID ],
		[ '%s' ],
		[ '%d' ]
	);
	if ( $result === false ) {
		die( 'Database error: ' . $wpdb->last_error );
	}
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'delete' ) {
	if ( is_default() ) {
		echo "<b>Cannot delete</b> - This is the default grid!<br>";
	} else {

		// check orders..

		global $wpdb;
		$orders = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE status <> 'deleted' AND banner_id = %d",
			$BID
		) );
		if ( empty( $orders ) ) {

			$wpdb->delete(
				MDS_DB_PREFIX . 'blocks',
				[ 'banner_id' => $BID ],
				[ '%d' ]
			);

			$wpdb->delete(
				MDS_DB_PREFIX . 'prices',
				[ 'banner_id' => $BID ],
				[ '%d' ]
			);

			$wpdb->delete(
				MDS_DB_PREFIX . 'banners',
				[ 'banner_id' => $BID ],
				[ '%d' ]
			);

			// DELETE ADS
			// TODO: delete mds-pixels
			// $sql = "select * FROM " . MDS_DB_PREFIX . "ads where banner_id='" . $BID . "' ";
			// $res2 = $wpdb->get_results( $sql );
			// foreach ( $res2 as $row2 ) {
			//
			// 	delete_ads_files( $row2['ad_id'] );
			// 	$wpdb->delete(
			// 	    MDS_DB_PREFIX . "ads",
			// 	    array( 'ad_id' => intval( $row2['ad_id'] ) ),
			// 	    array( '%d' )
			// 	);
			// }

			@unlink( Utility::get_upload_path() . "grids/grid" . $BID . ".jpg" );
			@unlink( Utility::get_upload_path() . "grids/grid" . $BID . ".png" );
			@unlink( Utility::get_upload_path() . "grids/background" . $BID . ".png" );

			// Check if WooCommerce integration is enabled
			if ( Options::get_option( 'woocommerce', 'no', false, 'options' ) ) {
				$product = WooCommerceFunctions::get_product();
				if ( $product ) {
					WooCommerceFunctions::delete_variation( $product, $BID );
				}
			}

		} else {
			echo "<b>Cannot delete</b> - this grid contains some orders in the database.<br>";
		}
	}
}

function get_banner_image_data( $b_row, $image_name ): string {
	if ( isset( $_FILES ) && isset( $_FILES[ $image_name ] ) && isset( $_FILES[ $image_name ]['tmp_name'] ) && $_FILES[ $image_name ]['tmp_name'] ) {
		// Validate uploaded file for security
		$validation = \MillionDollarScript\Classes\System\FileValidator::validate_image_upload( $_FILES[ $image_name ] );
		
		if ( ! $validation['valid'] ) {
			// Log security violation attempt
			error_log( 'MDS Security: Invalid file upload attempt for ' . $image_name . ': ' . $validation['error'] );
			
			// Return default image instead of failing
			return addslashes( get_default_image( $image_name ) );
		}
		
		// Process validated file securely
		$secure_filename = \MillionDollarScript\Classes\System\FileValidator::generate_secure_filename( $_FILES[ $image_name ]['name'], 'grid_' );
		$secure_uploaddir = \MillionDollarScript\Classes\System\FileValidator::get_secure_upload_path( 'grids' );
		$uploadfile = $secure_uploaddir . $secure_filename;
		
		if ( move_uploaded_file( $_FILES[ $image_name ]['tmp_name'], $uploadfile ) ) {
			$fh = fopen( $uploadfile, 'rb' );
			if ( $fh ) {
				$contents = fread( $fh, filesize( $uploadfile ) );
				fclose( $fh );
				$contents = addslashes( base64_encode( $contents ) );
				
				// Clean up temporary file
				unlink( $uploadfile );
			} else {
				error_log( 'MDS Security: Failed to read uploaded file: ' . $uploadfile );
				return addslashes( get_default_image( $image_name ) );
			}
		} else {
			error_log( 'MDS Security: Failed to move uploaded file for ' . $image_name );
			return addslashes( get_default_image( $image_name ) );
		}
	} else if ( isset( $b_row[ $image_name ] ) && $b_row[ $image_name ] != '' ) {
		// use the old image
		$contents = addslashes( ( $b_row[ $image_name ] ) );
	} else {
		$contents = addslashes( get_default_image( $image_name ) );
	}

	return $contents;
}

function get_banner_image_sql_values( $BID ) {
	$row = "";
	// get banner
	if ( $BID ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . MDS_DB_PREFIX . "banners WHERE banner_id = %d",
			$BID
		), ARRAY_A );
	}

	return ", '" . get_banner_image_data( $row, 'grid_block' ) . "' , '" . get_banner_image_data( $row, 'nfs_block' ) . "', '" . get_banner_image_data( $row, 'tile' ) . "', '" . get_banner_image_data( $row, 'usr_grid_block' ) . "', '" . get_banner_image_data( $row, 'usr_nfs_block' ) . "', '" . get_banner_image_data( $row, 'usr_ord_block' ) . "', '" . get_banner_image_data( $row, 'usr_res_block' ) . "', '" . get_banner_image_data( $row, 'usr_sel_block' ) . "', '" . get_banner_image_data( $row, 'usr_sol_block' ) . "'";
}

function validate_block_size( $image_name, $BID ): bool {
	global $wpdb;

	if ( ! $BID ) {
		// new grid...
		return true;
	}

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners WHERE banner_id = %d";
	$b_row = $wpdb->get_row( $wpdb->prepare( $sql, $BID ), ARRAY_A );

	// If NFS block and nfs_covered is N don't check block size
	if ( $image_name == 'nfs_block' && $b_row['nfs_covered'] == 'N' ) {
		return true;
	}

	$block_w = $_REQUEST['block_width'];
	$block_h = $_REQUEST['block_height'];

	if ( $b_row[ $image_name ] == '' ) {
		// no data, assume that the default image will be loaded..
		return true;
	}

	$imagine = new Imagine\Gd\Imagine();

	try {
		$img = $imagine->load( base64_decode( $b_row[ $image_name ] ) );
	} catch ( \Imagine\Exception\RuntimeException ) {
		return false;
	}

	$temp_file = Utility::get_upload_path() . "temp_block.png";
	$img->save( $temp_file );
	$size = $img->getSize();

	unlink( $temp_file );

	if ( $size->getWidth() != $block_w ) {
		return false;
	}

	if ( $size->getHeight() != $block_h ) {
		return false;
	}

	return true;
}

/**
 * Handle dark mode image generation for a specific grid
 * 
 * @param int $banner_id Banner ID to update
 * @param string $theme_mode Theme mode ('light' or 'dark')
 * @return string HTML result message
 */
function handle_dark_mode_generation( int $banner_id, string $theme_mode ): string {
	try {
		// Create backup first
		$backup_created = backup_single_grid_images( $banner_id, $theme_mode );
		
		// Get theme-appropriate images
		$images = get_theme_images_for_grid( $theme_mode );
		
		if ( empty( $images ) ) {
			return '<p style="color: red;">Error: Failed to generate theme images</p>';
		}

		// Update the specific banner's images
		$result = update_single_banner_images( $banner_id, $images );
		
		if ( $result ) {
			$process_pixels_url = admin_url( 'admin.php?page=mds-process-pixels' );
			$message = '<p style="color: green;">✓ Successfully updated grid images for ' . ucfirst($theme_mode) . ' mode</p>';
			if ( $backup_created ) {
				$message .= '<p><small>Backup created: ' . date('Y-m-d H:i:s') . '</small></p>';
			}
			$message .= '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">';
			$message .= '<p style="margin: 0; color: #856404;"><strong>Next Step Required:</strong> You must now run the Process Pixels task to regenerate the actual grid images.</p>';
			$message .= '<p style="margin: 5px 0 0 0;"><a href="' . esc_url($process_pixels_url) . '" class="button button-primary">Go to Process Pixels</a></p>';
			$message .= '</div>';
			return $message;
		} else {
			return '<p style="color: red;">Error: Failed to update grid images</p>';
		}

	} catch ( Exception $e ) {
		Logs::log( 'MDS Grid Dark Mode Generation Error: ' . $e->getMessage() );
		return '<p style="color: red;">Error: ' . esc_html( $e->getMessage() ) . '</p>';
	}
}

/**
 * Get theme-appropriate images for grid
 * 
 * @param string $theme_mode Theme mode ('light' or 'dark')
 * @return array Associative array of image data
 */
function get_theme_images_for_grid( string $theme_mode ): array {
	// Try dynamic generation first if GD is available and we're in dark mode
	if ( $theme_mode === 'dark' && GridImageGenerator::is_gd_available() ) {
		try {
			return GridImageGenerator::generate_theme_images( $theme_mode );
		} catch ( Exception $e ) {
			Logs::log( 'MDS Dynamic Image Generation Failed: ' . $e->getMessage() );
			// Fall back to static images
		}
	}

	// Use static images as fallback or for light mode
	return DarkModeImages::get_images_by_theme( $theme_mode );
}

/**
 * Update a single banner's images in the database
 * 
 * @param int $banner_id Banner ID to update
 * @param array $images Associative array of image data
 * @return bool True if successful, false otherwise
 */
function update_single_banner_images( int $banner_id, array $images ): bool {
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'mds_banners';
	
	// Only update the images that should be updated for dark mode
	$updateable_images = DarkModeImages::get_updateable_images();
	$update_data = [];
	
	foreach ( $updateable_images as $image_name ) {
		if ( isset( $images[ $image_name ] ) ) {
			$update_data[ $image_name ] = $images[ $image_name ];
		}
	}

	if ( empty( $update_data ) ) {
		return false;
	}

	$result = $wpdb->update(
		$table_name,
		$update_data,
		[ 'banner_id' => $banner_id ],
		array_fill( 0, count( $update_data ), '%s' ), // All images are strings
		[ '%d' ] // banner_id is integer
	);

	return $result !== false;
}

/**
 * Backup current grid images for a single banner
 * 
 * @param int $banner_id Banner ID to backup
 * @param string $theme_mode Current theme mode
 * @return bool True if backup successful
 */
function backup_single_grid_images( int $banner_id, string $theme_mode ): bool {
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'mds_banners';
	$backup_key = 'mds_grid_backup_' . $banner_id . '_' . $theme_mode . '_' . time();
	
	// Get current images for the specific banner
	$banner = $wpdb->get_row( $wpdb->prepare( "
		SELECT banner_id, grid_block, nfs_block, tile, usr_grid_block, usr_nfs_block, usr_ord_block 
		FROM {$table_name}
		WHERE banner_id = %d
	", $banner_id ) );

	if ( ! $banner ) {
		return false;
	}

	// Store backup as WordPress option
	return update_option( $backup_key, $banner );
}

if ( isset( $_REQUEST['submit'] ) && $_REQUEST['submit'] != '' ) {

	global $wpdb;
	$error = validate_input();

	if ( $error != '' ) {

		echo "<span style='color:red;'>Error: cannot save due to the following errors:</span><br>";
		echo "<span style='color:red;'>$error</span>";
	} else {
		$image_sql_fields = ', grid_block, nfs_block, tile, usr_grid_block, usr_nfs_block, usr_ord_block, usr_res_block, usr_sel_block, usr_sol_block ';
		$image_sql_values = get_banner_image_sql_values( $BID );
		$now              = current_time( 'mysql' );

		$new = false;
		if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'new' ) {
			$new = true;
			$sql = $wpdb->prepare(
				"INSERT INTO " . MDS_DB_PREFIX . "banners ( banner_id, grid_width, grid_height, days_expire, price_per_block, name, currency, max_orders, block_width, block_height, max_blocks, min_blocks, date_updated, bgcolor, auto_publish, auto_approve, nfs_covered, enabled, grid_block, nfs_block, tile, usr_grid_block, usr_nfs_block, usr_ord_block, usr_res_block, usr_sel_block, usr_sol_block ) VALUES (NULL, %d, %d, %d, %f, %s, %s, %d, %d, %d, %d, %d, %s, %s, %s, %s, %s, %s" . str_repeat(", %s", 9) . ")",
				intval( $_REQUEST['grid_width'] ),
				intval( $_REQUEST['grid_height'] ),
				intval( $_REQUEST['days_expire'] ),
				floatval( $_REQUEST['price_per_block'] ),
				$_REQUEST['name'],
				$_REQUEST['currency'],
				intval( $_REQUEST['max_orders'] ),
				intval( $_REQUEST['block_width'] ),
				intval( $_REQUEST['block_height'] ),
				intval( $_REQUEST['max_blocks'] ),
				intval( $_REQUEST['min_blocks'] ),
				$now,
				$_REQUEST['bgcolor'],
				$_REQUEST['auto_publish'],
				$_REQUEST['auto_approve'],
				$_REQUEST['nfs_covered'],
				$_REQUEST['enabled'],
				get_banner_image_data( '', 'grid_block' ),
				get_banner_image_data( '', 'nfs_block' ),
				get_banner_image_data( '', 'tile' ),
				get_banner_image_data( '', 'usr_grid_block' ),
				get_banner_image_data( '', 'usr_nfs_block' ),
				get_banner_image_data( '', 'usr_ord_block' ),
				get_banner_image_data( '', 'usr_res_block' ),
				get_banner_image_data( '', 'usr_sel_block' ),
				get_banner_image_data( '', 'usr_sol_block' )
			);
		} else {
			// Get current banner data for existing images
			global $wpdb;
			$current_banner = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "banners WHERE banner_id = %d",
				$BID
			), ARRAY_A );
			
			$sql = $wpdb->prepare(
				"REPLACE INTO " . MDS_DB_PREFIX . "banners ( banner_id, grid_width, grid_height, days_expire, price_per_block, name, currency, max_orders, block_width, block_height, max_blocks, min_blocks, date_updated, bgcolor, auto_publish, auto_approve, nfs_covered, enabled, grid_block, nfs_block, tile, usr_grid_block, usr_nfs_block, usr_ord_block, usr_res_block, usr_sel_block, usr_sol_block ) VALUES (%d, %d, %d, %d, %f, %s, %s, %d, %d, %d, %d, %d, %s, %s, %s, %s, %s, %s" . str_repeat(", %s", 9) . ")",
				$BID,
				intval( $_REQUEST['grid_width'] ),
				intval( $_REQUEST['grid_height'] ),
				intval( $_REQUEST['days_expire'] ),
				floatval( $_REQUEST['price_per_block'] ),
				$_REQUEST['name'],
				$_REQUEST['currency'],
				intval( $_REQUEST['max_orders'] ),
				intval( $_REQUEST['block_width'] ),
				intval( $_REQUEST['block_height'] ),
				intval( $_REQUEST['max_blocks'] ),
				intval( $_REQUEST['min_blocks'] ),
				$now,
				$_REQUEST['bgcolor'],
				$_REQUEST['auto_publish'],
				$_REQUEST['auto_approve'],
				$_REQUEST['nfs_covered'],
				$_REQUEST['enabled'],
				get_banner_image_data( $current_banner, 'grid_block' ),
				get_banner_image_data( $current_banner, 'nfs_block' ),
				get_banner_image_data( $current_banner, 'tile' ),
				get_banner_image_data( $current_banner, 'usr_grid_block' ),
				get_banner_image_data( $current_banner, 'usr_nfs_block' ),
				get_banner_image_data( $current_banner, 'usr_ord_block' ),
				get_banner_image_data( $current_banner, 'usr_res_block' ),
				get_banner_image_data( $current_banner, 'usr_sel_block' ),
				get_banner_image_data( $current_banner, 'usr_sol_block' )
			);
		}

		global $wpdb;
		$result = $wpdb->query( $sql );
		if ( $result === false ) {
			die( 'Database error: ' . $wpdb->last_error );
		}

		$BID = $wpdb->insert_id;

		// Persist Grid Blocks Layering + overlay selections (no DB migration)
		$effectiveBID = ( isset($BID) && intval($BID) > 0 ) ? intval($BID) : ( isset($_REQUEST['BID']) ? intval($_REQUEST['BID']) : 0 );
		if ( $effectiveBID > 0 ) {
			// Layering toggle
			$layering = ( isset($_POST['grid_blocks_layering']) && $_POST['grid_blocks_layering'] === 'above' ) ? 'above' : 'below';
			update_option( 'mds_grid_blocks_layering_' . $effectiveBID, $layering );

			// Handle overlay uploads only when Above is selected
			if ( $layering === 'above' ) {
				$overlay_opt = get_option( 'mds_grid_overlay_images_' . $effectiveBID, array() );
				if ( ! is_array( $overlay_opt ) ) { $overlay_opt = array(); }
				$overlay_dir = \MillionDollarScript\Classes\System\Utility::get_upload_path() . 'grids/overlays/';
				if ( ! file_exists( $overlay_dir ) ) { wp_mkdir_p( $overlay_dir ); }

				$map = array(
					'overlay_public_light'   => 'public-light',
					'overlay_public_dark'    => 'public-dark',
					'overlay_ordering_light' => 'ordering-light',
					'overlay_ordering_dark'  => 'ordering-dark',
				);

				foreach ( $map as $input => $variant ) {
					if ( isset($_FILES[$input]) && isset($_FILES[$input]['tmp_name']) && is_uploaded_file($_FILES[$input]['tmp_name']) ) {
						$orig_name = sanitize_file_name( wp_unslash( $_FILES[$input]['name'] ) );
						$check = wp_check_filetype_and_ext( $_FILES[$input]['tmp_name'], $orig_name, array( 'png' => 'image/png', 'gif' => 'image/gif' ) );
if ( ! in_array( $check['type'], array('image/png','image/gif'), true ) ) {
							// Invalid type, ignore this upload and add admin notice
							Notices::add_notice( sprintf( 'Invalid overlay file type for %s. Only PNG or GIF are allowed.', esc_html( $orig_name ) ), 'error' );
							continue;
						}
						$ext = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );
						$dest_basename = 'overlay-' . $variant . '-' . $effectiveBID . '.' . $ext;
						$dest = wp_normalize_path( $overlay_dir . $dest_basename );
						if ( move_uploaded_file( $_FILES[$input]['tmp_name'], $dest ) ) {
							list($ctx,$mode) = array_pad( explode('-', $variant), 2, '' );
							if ( ! isset( $overlay_opt[ $ctx ] ) || ! is_array( $overlay_opt[ $ctx ] ) ) { $overlay_opt[ $ctx ] = array(); }
							$overlay_opt[ $ctx ][ $mode ] = $dest_basename;
						}
					}
				}

				update_option( 'mds_grid_overlay_images_' . $effectiveBID, $overlay_opt );

				// Save grid background colors (light/dark)
				$existing_bg = get_option( 'mds_grid_bg_colors_' . $effectiveBID, array('light' => '#ffffff', 'dark' => '#14181c') );
				$light = isset($_POST['grid_bg_color_light']) ? sanitize_hex_color( $_POST['grid_bg_color_light'] ) : ($existing_bg['light'] ?? '#ffffff');
				$dark  = isset($_POST['grid_bg_color_dark'])  ? sanitize_hex_color( $_POST['grid_bg_color_dark'] )  : ($existing_bg['dark'] ?? '#14181c');
				if ( empty($light) ) { $light = '#ffffff'; }
				if ( empty($dark) ) { $dark = '#14181c'; }
				update_option( 'mds_grid_bg_colors_' . $effectiveBID, array( 'light' => $light, 'dark' => $dark ) );
			}
		}

		// TODO: Add individual order expiry dates
		$wpdb->update(
			MDS_DB_PREFIX . 'orders',
			[ 'days_expire' => intval( $_REQUEST['days_expire'] ) ],
			[ 'banner_id' => $BID ],
			[ '%d' ],
			[ '%d' ]
		);

		$_REQUEST['new'] = '';

		// WooCommerce integration
		// Check if WooCommerce is active and integration is enabled
		if ( WooCommerceFunctions::is_wc_active() && Options::get_option( 'woocommerce', 'no', false, 'options' ) ) {
			$product = WooCommerceFunctions::get_product();
			if ( $product ) {
				WooCommerceFunctions::update_attributes( $product );
				$product->save();
			}
		}
	}

	return;
}

?>
    <p>Here you can manage your grid(s) expiration, max/min orders per grid, price, dimensions, images, or add and
        delete them.</p>
    <p>Note: A grid with 100 rows and 100 columns and a block size of 10x10 is a million pixels. Setting this to a
        larger value may affect the memory & performance of the script.</p>

    <input type="button" class="mds-admin-action-new" value="New Grid..."
           onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=mds-manage-grids&mds-action=new&new=1' ) ); ?>'">
    <br>

<?php

if ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] == '1' ) {
	echo "<h4>New Grid</h4>";
}
if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'edit' ) {
	echo "<h4>Edit Grid #";

	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM " . MDS_DB_PREFIX . "banners WHERE banner_id = %d",
		$BID
	), ARRAY_A );
	$_REQUEST['BID']             = $row['banner_id'];
	$_REQUEST['grid_width']      = $row['grid_width'];
	$_REQUEST['grid_height']     = $row['grid_height'];
	$_REQUEST['days_expire']     = $row['days_expire'];
	$_REQUEST['max_orders']      = $row['max_orders'];
	$_REQUEST['price_per_block'] = $row['price_per_block'];
	$_REQUEST['name']            = $row['name'];
	$_REQUEST['currency']        = $row['currency'];
	$_REQUEST['block_width']     = $row['block_width'];
	$_REQUEST['block_height']    = $row['block_height'];
	$_REQUEST['max_blocks']      = $row['max_blocks'];
	$_REQUEST['min_blocks']      = $row['min_blocks'];
	$_REQUEST['bgcolor']         = $row['bgcolor'];
	$_REQUEST['auto_approve']    = $row['auto_approve'];
	$_REQUEST['auto_publish']    = $row['auto_publish'];
	$_REQUEST['nfs_covered']     = $row['nfs_covered'];
	$_REQUEST['enabled']         = $row['enabled'];

	echo intval( $row['banner_id'] ) . "</h4>";
}

if ( ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] != '' ) || ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'edit' ) ) {

	$size_error_msg = "Error: Invalid size! Must be " . htmlspecialchars( $_REQUEST['block_width'] ) . "x" . htmlspecialchars( $_REQUEST['block_height'] );

	$mds_admin_ajax_nonce = wp_create_nonce( 'mds_admin_ajax_nonce' );

	?>
    <form enctype="multipart/form-data" action='<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>' method="post">
		<?php wp_nonce_field( 'mds-admin' ); ?>
        <input type="hidden" name="action" value="mds_admin_form_submission"/>
        <input type="hidden" name="mds_dest" value="manage-grids"/>

        <input type="hidden"
               value="<?php echo( isset( $_REQUEST['new'] ) ? htmlspecialchars( $_REQUEST['new'] ) : "" ); ?>"
               name="new">
        <input type="hidden"
               value="<?php echo( isset( $_REQUEST['edit'] ) ? htmlspecialchars( $_REQUEST['edit'] ) : "" ); ?>"
               name="edit">
        <input type="hidden"
               value="<?php echo( isset( $_REQUEST['mds-action'] ) ? htmlspecialchars( $_REQUEST['mds-action'] ) : "" ); ?>"
               name="mds-action">
        <input type="hidden" value="<?php echo( isset( $_REQUEST['BID'] ) ? $BID : "" ); ?>" name="BID">
        <input type="hidden"
               value="<?php echo( isset( $_REQUEST['edit_anyway'] ) ? htmlspecialchars( $_REQUEST['edit_anyway'] ) : "" ); ?>"
               name="edit_anyway">

        <input class="inventory-save" type="submit" name="submit" value="Save Grid Settings">

        <div class="inventory-container">
            <div class="inventory-column">
                <div class="inventory-entry">
                    <div class="inventory-title">Grid Name</div>
                    <div class="inventory-content">
                        <label>
                            <input id="inventory-grid-name" autofocus tabindex="0" size="30" type="text" name="name"
                                   value="<?php echo( isset( $_REQUEST['name'] ) ? esc_attr( $_REQUEST['name'] ) : "" ); ?>"/>
                            <script>
								jQuery(function () {
									let grid_name = jQuery("#inventory-grid-name");
									if (grid_name.val().length === 0) {
										grid_name.focus();
									}
								});
                            </script>
                        </label> eg. My Million Pixel Grid
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Enabled</div>
                    <div class="inventory-content">
                        <label>
                            <input type="radio" name="enabled" value="Y" <?php if ( $_REQUEST['enabled'] == 'Y' ) {
								echo " checked ";
							} ?> >
                            Grid is enabled and will be selectable by the user to order from.
                        </label>
                        <br/>
                        <label>
                            <input type="radio" name="enabled" value="N" <?php if ( $_REQUEST['enabled'] == 'N' ) {
								echo " checked ";
							} ?> >
                            Grid is disabled and will not be available to select when ordering.
                        </label>
                    </div>
                </div>
                <div class="inventory-entry">
					<?php

					global $wpdb;
					$b_res = $wpdb->get_results( $wpdb->prepare(
						"SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE banner_id = %d AND status <> 'nfs' LIMIT 1",
						$BID
					) );

					if ( isset( $row ) && isset( $row['banner_id'] ) && $row['banner_id'] != '' && ! empty( $b_res ) ) {
						$locked = true;
					} else {
						$locked = false;
					}

					if ( isset( $_REQUEST['edit_anyway'] ) && $_REQUEST['edit_anyway'] != '' ) {
						$locked = false;
					}

					?>
                    <div class="inventory-title">Grid Width</div>
                    <div class="inventory-content">
						<?php
						$disabled = "";
						if ( ! $locked ) {
							?>
                            <label>
                                <input <?php echo $disabled; ?> size="2" type="text" name="grid_width"
                                                                value="<?php echo intval( $_REQUEST['grid_width'] ); ?>"/>
                            </label> Measured in blocks (default block size is 10x10 pixels)
						<?php } else { ?>
                            <b><?php echo intval( $_REQUEST['grid_width'] ); ?>
                                <input type='hidden'
                                       value='<?php echo( isset( $row ) ? ( $row['grid_width'] ?? '' ) : '' ); ?>'
                                       name='grid_width'>
                                Blocks.</b> Note: Cannot change width because the grid is in use by an advertiser. [<a
                                    href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>manage-grids&mds-action=edit&BID=<?php echo $BID; ?>&edit_anyway=1'>Edit
                                Anyway</a>]
						<?php } ?>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Grid Height</div>
                    <div class="inventory-content">
						<?php

						if ( ! $locked ) {
							?>
                            <label>
                                <input <?php echo $disabled; ?> size="2" type="text" name="grid_height"
                                                                value="<?php echo intval( $_REQUEST['grid_height'] ); ?>"/>
                            </label> Measured in blocks (default block size is 10x10 pixels)
						<?php } else { ?>
                            <b><?php echo intval( $_REQUEST['grid_height'] ); ?>
                                <input type='hidden'
                                       value='<?php echo( isset( $row ) ? ( $row['grid_height'] ?? '' ) : '' ); ?>'
                                       name='grid_height'>
                                Blocks.</b>  Note: Cannot change height because the grid is in use by an advertiser. [<a
                                    href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>manage-grids&mds-action=edit&BID=<?php echo $BID; ?>&edit_anyway=1'>Edit
                                Anyway</a>]";
						<?php } ?>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Price per block</div>
                    <div class="inventory-content">
                        <label>
                            <input size="1" type="text" name="price_per_block"
                                   value="<?php echo floatval( $_REQUEST['price_per_block'] ); ?>"/>
                        </label>(How much for 1 block of pixels?)
                    </div>
                    <div class="inventory-title">Currency</div>
                    <div class="inventory-content">
                        <label>
                            <select name="currency">
								<?php
								Currency::currency_option_list( $_REQUEST['currency'] );

								?>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Days to Expire</div>
                    <div class="inventory-content">
                        <label>
                            <input <?php echo $disabled; ?> size="1" type="text" name="days_expire"
                                                            value="<?php echo intval( $_REQUEST['days_expire'] ); ?>"/>
                        </label>(How many days until pixels expire? Enter 0 for unlimited.)
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Max orders Per Customer</div>
                    <div class="inventory-content">
                        <label>
                            <input <?php echo $disabled; ?> size="1" type="text" name="max_orders"
                                                            value="<?php echo intval( $_REQUEST['max_orders'] ); ?>"/>
                        </label>(How many orders per 1 customer? Enter 0 for unlimited.)<br>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Max blocks</div>
                    <div class="inventory-content">
                        <label>
                            <input size="1" type="text" name="max_blocks"
                                   value="<?php echo intval( $_REQUEST['max_blocks'] ); ?>"/>
                        </label>(Maximum amount of blocks the customer is allowerd to purchase? Enter 0 for
                        unlimited.)<br>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Min blocks</div>
                    <div class="inventory-content">
                        <label>
                            <input size="1" type="text" name="min_blocks"
                                   value="<?php echo intval( $_REQUEST['min_blocks'] ); ?>"/>
                        </label>(Minumum amount of blocks the customer has to purchase per order? Enter 1 or 0 for no
                        limit.)<br>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Approve Automatically?</div>
                    <div class="inventory-content">
                        <label>
                            <input type="radio" name="auto_approve"
                                   value="Y" <?php if ( $_REQUEST['auto_approve'] == 'Y' ) {
								echo " checked ";
							} ?> >
                        </label>Yes. Approve all pixels automatically as they are submitted.<br>
                        <label>
                            <input type="radio" name="auto_approve"
                                   value="N" <?php if ( $_REQUEST['auto_approve'] == 'N' ) {
								echo " checked ";
							} ?> >
                        </label>No, approve manually from the Admin.<br>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Publish Automatically?</div>
                    <div class="inventory-content">
                        <label>
                            <input type="radio" name="auto_publish"
                                   value="Y" <?php if ( $_REQUEST['auto_publish'] == 'Y' ) {
								echo " checked ";
							} ?> >
                        </label>Yes. Process the grid image(s) automatically, every time when the pixels are approved,
                        expired or disapproved.<br>
                        <label>
                            <input type="radio" name="auto_publish"
                                   value="N" <?php if ( $_REQUEST['auto_publish'] == 'N' ) {
								echo " checked ";
							} ?> >
                        </label>No, Process manually from the admin<br>
                    </div>
                </div>
            </div>
            <div class="inventory-column">
                <div class="inventory-section-title">Block Configuration</div>
                <div class="inventory-entry">
                    <div class="inventory-title">Block Size</div>
                    <div class="inventory-content">
                        <label>
                            <input type="text" name="block_width" size="2" style="font-size: 18pt"
                                   value="<?php echo intval( $_REQUEST['block_width'] ); ?>">
                        </label>
                        &nbsp;X&nbsp;
                        <label>
                            <input type="text" name="block_height" size="2" style="font-size: 18pt"
                                   value="<?php echo intval( $_REQUEST['block_height'] ); ?>">
                        </label>
                        <br/>(Width X Height, default is 10x10 in pixels)
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Not For Sale Image Coverage</div>
                    <div class="inventory-content">
                        <label>
                            <input type="radio" name="nfs_covered"
                                   value="N" <?php if ( $_REQUEST['nfs_covered'] == 'N' ) {
								echo " checked ";
							} ?> >
                            Show the NFS image on every NFS block.
                        </label>
                        <br/>
                        <label>
                            <input type="radio" name="nfs_covered"
                                   value="Y" <?php if ( $_REQUEST['nfs_covered'] == 'Y' ) {
								echo " checked ";
							} ?> >
                            Show a single image across all NFS blocks.
                        </label>
                    </div>
                </div>
                
                <?php
                // Dark Mode Image Management Section
                $current_theme = Options::get_option( 'theme_mode', 'light' );
                ?>
                <div class="inventory-section-title">
                    Dark Mode Image Management
                </div>
                <div class="inventory-entry">
                    <div class="inventory-content">
                        <p>Current theme mode: <strong><?php echo ucfirst($current_theme); ?></strong></p>
                        <p>Generate theme-appropriate grid images automatically based on your color settings.</p>
                        
                        <?php if ( isset( $_REQUEST['mds_grid_dark_mode_action'] ) ): ?>
                            <div class="notice notice-info">
                                <?php
                                $action_result = '';
                                if ( $_REQUEST['mds_grid_dark_mode_action'] === 'generate_dark' ) {
                                    $action_result = handle_dark_mode_generation( $BID, 'dark' );
                                } elseif ( $_REQUEST['mds_grid_dark_mode_action'] === 'generate_light' ) {
                                    $action_result = handle_dark_mode_generation( $BID, 'light' );
                                }
                                echo $action_result;
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin: 10px 0;">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-manage-grids&mds-action=edit&BID=' . $BID . '&mds_grid_dark_mode_action=generate_dark' ) ); ?>" 
                               class="button button-secondary"
                               onclick="return confirm('This will replace current grid images with dark mode versions. Continue?');">
                                Generate Dark Mode Images
                            </a>
                            
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-manage-grids&mds-action=edit&BID=' . $BID . '&mds_grid_dark_mode_action=generate_light' ) ); ?>" 
                               class="button button-secondary"
                               onclick="return confirm('This will replace current grid images with light mode versions. Continue?');">
                                Generate Light Mode Images
                            </a>
                        </div>
                        
                        <p><small><strong>Note:</strong> This will update the grid images for this specific grid only. Backup copies are created automatically.</small></p>
                    </div>
                </div>
                
                <div class="inventory-section-title">Grid Blocks Layering</div>
                <div class="inventory-entry">
                    <div class="inventory-title">Blocks position</div>
                    <div class="inventory-content">
                        <?php $mds_blocks_layering = get_option( 'mds_grid_blocks_layering_' . intval($BID), 'below' ); ?>
                        <label>
                            <input type="radio" name="grid_blocks_layering" value="below" <?php echo ( $mds_blocks_layering !== 'above' ) ? 'checked' : ''; ?>>
                            Background image above the grid blocks (default)
                        </label>
                        <br/>
                        <label>
                            <input type="radio" name="grid_blocks_layering" value="above" <?php echo ( $mds_blocks_layering === 'above' ) ? 'checked' : ''; ?>>
                            Grid blocks above the background image
                        </label>
                        <p class="description">When set to "Grid blocks above", upload overlay-style images (transparent PNG or GIF) that contain only the grid lines. If you don't upload one, a bundled default will be used.</p>
                        <?php $bg_page_url = admin_url( 'admin.php?page=mds-backgrounds&BID=' . intval( $BID ) ); ?>
                        <p style="margin-top:6px;">
                            <a class="button button-secondary" href="<?php echo esc_url( $bg_page_url ); ?>">Open Backgrounds page</a>
                        </p>
                    </div>
                </div>

                <?php
                // Prepare current overlay selections and URLs for display
                $overlay_opt = get_option( 'mds_grid_overlay_images_' . intval($BID), array() );
                $overlay_upload_url = \MillionDollarScript\Classes\System\Utility::get_upload_url() . 'grids/overlays/';
                $bw = intval($_REQUEST['block_width']);
                $bh = intval($_REQUEST['block_height']);
                $overlay_fields = array(
                    'overlay_public_light'    => array('label' => 'Public overlay (Light)',    'key_ctx' => 'public',   'key_mode' => 'light',   'default' => MDS_CORE_URL . 'images/overlay-public-light.png'),
                    'overlay_public_dark'     => array('label' => 'Public overlay (Dark)',     'key_ctx' => 'public',   'key_mode' => 'dark',    'default' => MDS_CORE_URL . 'images/overlay-public-dark.png'),
                    'overlay_ordering_light'  => array('label' => 'Ordering overlay (Light)',  'key_ctx' => 'ordering', 'key_mode' => 'light',   'default' => MDS_CORE_URL . 'images/overlay-ordering-light.png'),
                    'overlay_ordering_dark'   => array('label' => 'Ordering overlay (Dark)',   'key_ctx' => 'ordering', 'key_mode' => 'dark',    'default' => MDS_CORE_URL . 'images/overlay-ordering-dark.png'),
                );
                ?>
                <div id="mds-overlay-selectors" <?php echo ($mds_blocks_layering === 'above') ? '' : 'style="display:none"'; ?>>
                    <?php $bg_colors = get_option( 'mds_grid_bg_colors_' . intval($BID), array('light' => '#ffffff', 'dark' => '#14181c') ); ?>
                    <div class="inventory-entry">
                        <div class="inventory-title">Grid background color</div>
                        <div class="inventory-content">
                            <label style="margin-right:12px; display:inline-block;">
                                <span style="display:inline-block; min-width:110px;">Light mode:</span>
                                <input type="color" name="grid_bg_color_light" value="<?php echo esc_attr( $bg_colors['light'] ?? '#ffffff' ); ?>">
                            </label>
                            <label style="display:inline-block;">
                                <span style="display:inline-block; min-width:110px;">Dark mode:</span>
                                <input type="color" name="grid_bg_color_dark" value="<?php echo esc_attr( $bg_colors['dark'] ?? '#14181c' ); ?>">
                            </label>
                            <p class="description">Used when no background image is uploaded. Light default: #ffffff, Dark default: #14181c.</p>
                        </div>
                    </div>
                    <div class="inventory-entry">
                        <div class="inventory-title">Overlay images</div>
                        <div class="inventory-content">
                            <p>Recommended size: <?php echo $bw; ?>x<?php echo $bh; ?> (must match the block size for perfect alignment).</p>
                        </div>
                    </div>
                    <?php foreach ( $overlay_fields as $name => $meta ): 
                        $current = $overlay_opt[$meta['key_ctx']][$meta['key_mode']] ?? '';
                        $current_url = $current ? esc_url( $overlay_upload_url . $current ) : '';
                    ?>
                        <div class="inventory-entry">
                            <div class="inventory-title"><?php echo esc_html($meta['label']); ?></div>
                            <div class="inventory-content">
                                <?php if ( $current ): ?>
                                    <div style="margin-bottom:6px;">
                                        <img src="<?php echo $current_url; ?>" alt="" style="max-width:120px; background:#eee; padding:2px;">
                                        <div><small>Current file: <?php echo esc_html($current); ?></small></div>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-bottom:6px;">
                                        <img src="<?php echo esc_url( $meta['default'] ); ?>" alt="" style="max-width:120px; background:#eee; padding:2px;">
                                        <div><small>Using plugin default.</small></div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="<?php echo esc_attr($name); ?>" accept="image/png,image/gif" size="10" />
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <script>
                jQuery(function($){
                    function toggleOverlaySelectors(){
                        const val = $('input[name="grid_blocks_layering"]:checked').val();
                        if(val === 'above'){
                            $('#mds-overlay-selectors').slideDown(150);
                        } else {
                            $('#mds-overlay-selectors').slideUp(150);
                        }
                    }
                    $('input[name="grid_blocks_layering"]').on('change', toggleOverlaySelectors);
                    toggleOverlaySelectors();
                });
                </script>

                <div class="inventory-section-title">Block Graphics - Displayed on the public Grid</div>
                <div class="inventory-entry">
                    <div class="inventory-title">Grid Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'grid_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php
						display_reset_link( $BID, 'grid_block' );
						?>
                        <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=get-block-image&amp;t=<?php echo time(); ?>&amp;BID=<?php echo $BID; ?>&amp;image_name=grid_block"
                             alt=""/>
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="grid_block" size="10"/>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Not For Sale Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'nfs_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'nfs_block' ); ?>
                        <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=get-block-image&amp;t=<?php echo time(); ?>&amp;BID=<?php echo $BID; ?>&amp;image_name=nfs_block"
                             alt=""/>
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="nfs_block" size="10"/>
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Background Tile</div>
                    <div class="inventory-content">
						<?php display_reset_link( $BID, 'tile' ); ?>
						<?php
						$banner_data = load_banner_constants( $BID );
						$bgstyle     = "";
						if ( ! empty( $banner_data['G_BGCOLOR'] ) ) {
							$bgstyle = ' style="background-color:' . $banner_data['G_BGCOLOR'] . ';"';
						}
						?>
                        <img<?php echo $bgstyle; ?>
                                src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=get-block-image&amp;t=<?php echo time(); ?>&amp;BID=<?php echo $BID; ?>&amp;image_name=tile"
                                alt=""/>
                        <input type="file" name="tile" size="10"/>
                        <br/>(This tile is used the fill the space behind the grid image. The tile will be seen before
                        the grid image is loaded.)
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Background Color</div>
                    <div class="inventory-content">
                        <label>
                            <input type='text' name='bgcolor' size='7'
                                   value='<?php echo htmlspecialchars( $_REQUEST['bgcolor'] ); ?>'/>
                        </label> eg. #ffffff
                    </div>
                </div>
                <div class="inventory-section-title">
                    Block Graphics - Displayed on the ordering Grid
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Grid Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_grid_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_grid_block' ); ?>
                        <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=get-block-image&amp;t=<?php echo time(); ?>&amp;BID=<?php echo $BID; ?>&amp;image_name=usr_grid_block"
                             alt=""/>
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_grid_block" size="10">
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Not For Sale Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_nfs_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_nfs_block' ); ?>
                        <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=get-block-image&amp;t=<?php echo time(); ?>&amp;BID=<?php echo $BID; ?>&amp;image_name=usr_nfs_block"
                             alt=""/>
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_nfs_block" size="10">
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Ordered Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_ord_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_ord_block' ); ?>
                        <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=get-block-image&amp;t=<?php echo time(); ?>&amp;BID=<?php echo $BID; ?>&amp;image_name=usr_ord_block"
                             alt=""/>
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_ord_block" size="10">
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Reserved Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_res_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_res_block' ); ?>
                        <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=get-block-image&amp;t=<?php echo time(); ?>&amp;BID=<?php echo $BID; ?>&amp;image_name=usr_res_block"
                             alt=""/>
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_res_block" size="10">
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Selected Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_sel_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_sel_block' ); ?>
                        <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=get-block-image&amp;t=<?php echo time(); ?>&amp;BID=<?php echo $BID; ?>&amp;image_name=usr_sel_block"
                             alt=""/>
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_sel_block" size="10">
                    </div>
                </div>
                <div class="inventory-entry">
                    <div class="inventory-title">Sold Block</div>
                    <div class="inventory-content<?php
					$valid = validate_block_size( 'usr_sol_block', $BID );
					if ( ! $valid ) {
						echo ' inventory-error';
					}
					?>">
						<?php display_reset_link( $BID, 'usr_sol_block' ); ?>
                        <img src="<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=get-block-image&amp;t=<?php echo time(); ?>&amp;BID=<?php echo $BID; ?>&amp;image_name=usr_sol_block"
                             alt=""/>
						<?php
						if ( ! $valid ) {
							echo $size_error_msg;
							unset( $valid );
						}
						?>
                        <input type="file" name="usr_sol_block" size="10">
                    </div>
                </div>
            </div>
        </div>

        <input class="inventory-save" type="submit" name="submit" value="Save Grid Settings">
    </form>
    <hr>
	<?php

	if ( $locked ) {
		?>
        Note: The Grid Width and Grid Height fields are locked because this image has some pixels on order / sold.
		<?php
	}
} else {

	function render_offer( $price, $currency, $max_orders, $days_expire ) {
		?>

		<?php

		?>
        <small>Days:</small> <b><?php if ( $days_expire > 0 ) {
				echo $days_expire;
			} else {
				echo "unlimited";
			} ?></b> <small>Max Ord</small>: <b><?php if ( $max_orders > 0 ) {
				echo $max_orders;
			} else {
				echo "unlimited";
			} ?></b> <small>Price/100</small>: <b><?php echo $price; ?><?php echo $currency; ?></b><br>

		<?php
	}

	?>

    <div class="inventory2-container">
        <div class="inventory2-title">
            Action
        </div>
        <div class="inventory2-title">
            Grid ID
        </div>
        <div class="inventory2-title">
            Name
        </div>
        <div class="inventory2-title">
            Grid Width
        </div>
        <div class="inventory2-title">
            Grid Height
        </div>
        <div class="inventory2-title">
            Offer
        </div>
        <div class="inventory2-title">
            Today's Clicks
        </div>
        <div class="inventory2-title">
            Total Clicks
        </div>

		<?php
		global $wpdb;
		$results = $wpdb->get_results(
			"SELECT * FROM " . MDS_DB_PREFIX . "banners ORDER BY enabled DESC, date_updated ASC, publish_date ASC, banner_id ASC",
			ARRAY_A
		);
		foreach ( $results as $row ) {
			?>
            <div class="inventory2-content">
                <a href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>manage-grids&mds-action=edit&BID=<?php echo $row['banner_id']; ?>'>Edit</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>packages&BID=<?php echo $row['banner_id']; ?>">
                    Packages</a>
				<?php
				if ( $row['enabled'] == 'Y' ) {
					?>
                    <a href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>manage-grids&mds-action=disable&BID=<?php echo $row['banner_id']; ?>'>Disable</a>
					<?php
				} else {
					?>
                    <a href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>manage-grids&mds-action=enable&BID=<?php echo $row['banner_id']; ?>'>Enable</a>
					<?php
				}
				if ( $row['banner_id'] != '1' ) {
					?>
                    <a onclick="confirmLink(this, 'Delete grid <?php echo intval( $row['banner_id'] ); ?>?')"
                       data-link='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>manage-grids&mds-action=delete&BID=<?php echo $row['banner_id']; ?>' href="#">Delete</a>
					<?php
				}
				?>
            </div>
            <div class="inventory2-content">
				<?php echo $row['banner_id']; ?>
            </div>
            <div class="inventory2-content">
				<?php echo $row['name']; ?>
            </div>
            <div class="inventory2-content">
				<?php echo $row['grid_width']; ?> blocks
            </div>
            <div class="inventory2-content">
				<?php echo $row['grid_height']; ?> blocks
            </div>
            <div class="inventory2-content">
				<?php
				$banner_packages = banner_get_packages( $row['banner_id'] );
				if ( ! $banner_packages ) {
					// render the default offer
					render_offer( $row['price_per_block'], $row['currency'], $row['max_orders'], $row['days_expire'] );
				} else {
					foreach ( $banner_packages as $p_row ) {
						render_offer( $p_row['price'], $p_row['currency'], $p_row['max_orders'], $p_row['days_expire'] );
					}
				}
				?>
            </div>
            <div class="inventory2-content">
				<?php echo Utility::get_clicks_for_today( $row['banner_id'] ); ?>
            </div>
            <div class="inventory2-content">
				<?php echo Utility::get_clicks_for_banner( $row['banner_id'] ); ?>
            </div>
			<?php
		}
		?>
    </div>
	<?php
}