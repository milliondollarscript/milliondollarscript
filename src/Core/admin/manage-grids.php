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
	$default = get_default_image( $_REQUEST['reset_image'] );
	$sql     = "UPDATE " . MDS_DB_PREFIX . "banners SET `" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['reset_image'] ) . "`='" . mysqli_real_escape_string( $GLOBALS['connection'], $default ) . "' WHERE banner_id='" . $BID . "' ";
	mysqli_query( $GLOBALS['connection'], $sql );
}

function display_reset_link( $BID, $image_name ): void {
	if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'edit' ) {
		?>
        <a class="inventory-reset-link" title="Reset to default"
           onclick="if (! confirmLink(this, 'Reset this image to deafult, are you sure?')) return false;"
           href='<?php echo esc_url( admin_url( 'admin.php?page=mds-manage-grids' ) ); ?>&mds-action=edit&BID=<?php echo $BID; ?>&reset_image=<?php echo urlencode( $image_name ); ?>'>x</a>
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
	$sql = "UPDATE `" . MDS_DB_PREFIX . "banners` SET enabled='N' WHERE banner_id=" . $BID;
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'enable' ) {
	$sql = "UPDATE `" . MDS_DB_PREFIX . "banners` SET enabled='Y' WHERE banner_id=" . $BID;
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
}

if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'delete' ) {
	if ( is_default() ) {
		echo "<b>Cannot delete</b> - This is the default grid!<br>";
	} else {

		// check orders..

		$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders where status <> 'deleted' and banner_id=" . $BID;
		// echo $sql;
		$res = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		if ( mysqli_num_rows( $res ) == 0 ) {

			$sql = "DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE banner_id='" . $BID . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

			$sql = "DELETE FROM " . MDS_DB_PREFIX . "prices WHERE banner_id='" . $BID . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

			$sql = "DELETE FROM " . MDS_DB_PREFIX . "banners WHERE banner_id='" . $BID . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

			// DELETE ADS
			// TODO: delete mds-pixels
			// $sql = "select * FROM " . MDS_DB_PREFIX . "ads where banner_id='" . $BID . "' ";
			// $res2 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
			// while ( $row2 = mysqli_fetch_array( $res2 ) ) {
			//
			// 	delete_ads_files( $row2['ad_id'] );
			// 	$sql = "DELETE from " . MDS_DB_PREFIX . "ads where ad_id='" . intval( $row2['ad_id'] ) . "' ";
			// 	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			// }

			@unlink( Utility::get_upload_path() . "grids/grid" . $BID . ".jpg" );
			@unlink( Utility::get_upload_path() . "grids/grid" . $BID . ".png" );
			@unlink( Utility::get_upload_path() . "grids/background" . $BID . ".png" );

			// Check if WooCommerce integration is enabled
			if ( Options::get_option( 'woocommerce', 'no', false, 'options' ) ) {
				$product = WooCommerceFunctions::get_product();

				WooCommerceFunctions::delete_variation( $product, $BID );
			}

		} else {
			echo "<b>Cannot delete</b> - this grid contains some orders in the database.<br>";
		}
	}
}

function get_banner_image_data( $b_row, $image_name ): string {
	$uploaddir = Utility::get_upload_path() . "grids/";
	if ( isset( $_FILES ) && isset( $_FILES[ $image_name ] ) && isset( $_FILES[ $image_name ]['tmp_name'] ) && $_FILES[ $image_name ]['tmp_name'] ) {
		// a new image was uploaded
		$uploadfile = $uploaddir . $image_name . $_FILES[ $image_name ]['name'];
		move_uploaded_file( $_FILES[ $image_name ]['tmp_name'], $uploadfile );
		$fh       = fopen( $uploadfile, 'rb' );
		$contents = fread( $fh, filesize( $uploadfile ) );
		fclose( $fh );
		$contents = addslashes( base64_encode( $contents ) );
		unlink( $uploadfile );
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
		$sql = "SELECT * FROM `" . MDS_DB_PREFIX . "banners` WHERE `banner_id`='" . intval( $BID ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );
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

if ( isset( $_REQUEST['submit'] ) && $_REQUEST['submit'] != '' ) {

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
			$sql = "INSERT INTO `" . MDS_DB_PREFIX . "banners` ( `banner_id` , `grid_width` , `grid_height` , `days_expire` , `price_per_block`, `name`, `currency`, `max_orders`, `block_width`, `block_height`, `max_blocks`, `min_blocks`, `date_updated`, `bgcolor`, `auto_publish`, `auto_approve`, `nfs_covered`, `enabled` $image_sql_fields ) VALUES (NULL, '" . intval( $_REQUEST['grid_width'] ) . "', '" . intval( $_REQUEST['grid_height'] ) . "', '" . intval( $_REQUEST['days_expire'] ) . "', '" . floatval( $_REQUEST['price_per_block'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['name'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['currency'] ) . "', '" . intval( $_REQUEST['max_orders'] ) . "', '" . intval( $_REQUEST['block_width'] ) . "', '" . intval( $_REQUEST['block_height'] ) . "', '" . intval( $_REQUEST['max_blocks'] ) . "', '" . intval( $_REQUEST['min_blocks'] ) . "', '" . $now . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['bgcolor'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['auto_publish'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['auto_approve'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['nfs_covered'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['enabled'] ) . "' $image_sql_values);";
		} else {
			$sql = "REPLACE INTO `" . MDS_DB_PREFIX . "banners` ( `banner_id` , `grid_width` , `grid_height` , `days_expire` , `price_per_block`, `name`, `currency`, `max_orders`, `block_width`, `block_height`, `max_blocks`, `min_blocks`, `date_updated`, `bgcolor`, `auto_publish`, `auto_approve`, `nfs_covered`, `enabled` $image_sql_fields ) VALUES ('" . $BID . "', '" . intval( $_REQUEST['grid_width'] ) . "', '" . intval( $_REQUEST['grid_height'] ) . "', '" . intval( $_REQUEST['days_expire'] ) . "', '" . floatval( $_REQUEST['price_per_block'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['name'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['currency'] ) . "', '" . intval( $_REQUEST['max_orders'] ) . "', '" . intval( $_REQUEST['block_width'] ) . "', '" . intval( $_REQUEST['block_height'] ) . "', '" . intval( $_REQUEST['max_blocks'] ) . "', '" . intval( $_REQUEST['min_blocks'] ) . "', '" . $now . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['bgcolor'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['auto_publish'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['auto_approve'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['nfs_covered'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['enabled'] ) . "' $image_sql_values);";
		}

		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mds_sql_error( $sql ) );

		$BID = mysqli_insert_id( $GLOBALS['connection'] );

		// TODO: Add individual order expiry dates
		$sql = "UPDATE `" . MDS_DB_PREFIX . "orders` SET days_expire=" . intval( $_REQUEST['days_expire'] ) . " WHERE banner_id=" . $BID;
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

		$_REQUEST['new'] = '';

		// WooCommerce integration
		// Check if WooCommerce is active and integration is enabled
		if ( WooCommerceFunctions::is_wc_active() && Options::get_option( 'woocommerce', 'no', false, 'options' ) ) {
			// Get product from CarbonFields
			$product_option = Options::get_option( 'product', null, true );

			// Product id
			$product_id = $product_option[0]['id'];

			// WC Product
			$product = wc_get_product( $product_id );

			WooCommerceFunctions::update_attributes( $product );
			//
			// if ( $new ) {
			// 	// Add attributes to product
			// 	\MillionDollarScript\Classes\System\Functions::add_variation( $product, $BID );
			// } else {
			// 	// Update attributes on product
			// 	\MillionDollarScript\Classes\System\Functions::update_variation( $product, $BID );
			// }

			$product->save();
		}
	}

	return;
}

?>
    <p>Here you can manage your grid(s) expiration, max/min orders per grid, price, dimensions, images, or add and
        delete them.</p>
    <p>Note: A grid with 100 rows and 100 columns and a block size of 10x10 is a million pixels. Setting this to a
        larger value may affect the memory & performance of the script.</p>

    <input type="button" style="background-color:#66FF33" value="New Grid..."
           onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=mds-manage-grids&mds-action=new&new=1' ) ); ?>'">
    <br>

<?php

if ( isset( $_REQUEST['new'] ) && $_REQUEST['new'] == '1' ) {
	echo "<h4>New Grid</h4>";
}
if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'edit' ) {
	echo "<h4>Edit Grid #";

	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners WHERE `banner_id`='" . $BID . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row                         = mysqli_fetch_array( $result );
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

					$sql   = "SELECT * FROM " . MDS_DB_PREFIX . "blocks where banner_id=" . $BID . " AND status <> 'nfs' limit 1 ";
					$b_res = mysqli_query( $GLOBALS['connection'], $sql );

					if ( isset( $row ) && isset( $row['banner_id'] ) && $row['banner_id'] != '' && mysqli_num_rows( $b_res ) > 0 ) {
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
		$result = mysqli_query( $GLOBALS['connection'], "SELECT * FROM `" . MDS_DB_PREFIX . "banners` ORDER BY `enabled` DESC, `date_updated` ASC, `publish_date` ASC, `banner_id` ASC" ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		while ( $row = mysqli_fetch_array( $result, MYSQLI_ASSOC ) ) {
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
                    <a onclick="if (! confirmLink(this, 'Delete grid <?php echo intval( $row['banner_id'] ); ?>?')) return false;"
                       href='<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>manage-grids&mds-action=delete&BID=<?php echo $row['banner_id']; ?>'>Delete</a>
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