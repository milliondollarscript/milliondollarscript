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
use MillionDollarScript\Classes\Forms\Forms;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

// check if user has permission to access this page
if ( ! mds_check_permission( "mds_order_pixels" ) ) {
	require_once MDS_CORE_PATH . "html/header.php";
	Language::out( "No Access" );
	require_once MDS_CORE_PATH . "html/footer.php";
	exit;
}

global $f2, $wpdb;
$BID = $f2->bid();

$order_id = Orders::get_current_order_in_progress();
if ( empty( $order_id ) ) {
	// No order in progress so check if an order id was passed

	if ( ! empty( $_REQUEST['order_id'] ) ) {
		// If an order id was passed
		if ( ! is_numeric( $_REQUEST['order_id'] ) ) {
			// If it was not numeric redirect to the no orders page
			Orders::no_orders();

			return;
		} else {
			// If it was numeric check

			// Verify ownership
			$order_id = intval( $_REQUEST['order_id'] );
			if ( ! Orders::is_owned_by( $order_id ) ) {
				Orders::no_orders();

				return;
			}

			// Check if the order is in progress
			if ( ! Orders::is_order_in_progress( $order_id ) ) {
				// Not in progress so redirect to the no orders page
				Orders::no_orders();

				return;
			}
		}

	} else {

		// Check for any new orders
		$order_row = Orders::find_new_order();
		if ( $order_row == null ) {

			// No existing new order or order_id in request, start a new order
			$order_id = Orders::create_order();
		}
	}
}

// Delete temporary order when the banner was changed.
if ( isset( $_REQUEST['banner_change'] ) && $_REQUEST['banner_change'] != '' ) {
	Orders::delete_temp_order( Orders::get_current_order_id() );

	return;
}

$USE_AJAX = Config::get( 'USE_AJAX' );

$banner_data = load_banner_constants( $BID );

// Update time stamp on temp order (if exists)
Orders::update_temp_order_timestamp();

// Handle file upload results from Forms.php redirect
$uploaddir      = Utility::get_upload_path() . "images/";
$tmp_image_file = Orders::get_tmp_img_name();
$size           = [];
$reqsize        = [];
$pixel_count    = 0;
$block_size     = 0;
$messages       = "";

// Check for upload results from redirect parameters
if ( isset( $_GET['upload_error'] ) ) {
	$messages .= "<b style='color:red;'>" . urldecode( $_GET['upload_error'] ) . "</b><br>";
} elseif ( isset( $_GET['upload_success'] ) ) {
	// Upload was successful, get the uploaded file info
	if ( ! empty( $tmp_image_file ) && file_exists( $tmp_image_file ) ) {
		$size = getimagesize( $tmp_image_file );
		if ( $size ) {
			$reqsize = Utility::get_required_size( $size[0], $size[1], $banner_data );
			$pixel_count = $reqsize[0] * $reqsize[1];
			$block_size = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
		}
	}
}

// Only return early if this is a direct POST to admin-post.php without file upload processing
// When Forms.php includes this file for file upload processing, we should continue execution
if ( isset( $_POST['mds_dest'] ) && str_ends_with( $_SERVER['REQUEST_URI'], 'admin-post.php' ) && ! isset( $_FILES['graphic'] ) ) {
	return;
}

require_once MDS_CORE_PATH . "html/header.php";

if ( ! empty( $tmp_image_file ) ) {

	if ( empty( $size ) ) {
		$size = getimagesize( $tmp_image_file );
	}

	?>
    <style>
		#block_pointer, #block_pointer img {
			width: <?php echo $size[0]; ?>px;
			height: <?php echo $size[1]; ?>px;
			padding: 0;
			margin: 0;
			line-height: <?php echo $size[1]; ?>px;
			font-size: <?php echo $size[1]; ?>px;
			vertical-align: top;
			pointer-events: none;
		}

		#block_pointer {
			cursor: pointer;
			position: absolute;
			left: 0;
			top: 0;
			background: transparent;
			visibility: hidden;
		}
    </style>
	<?php
}

// Output any messages
if ( ! empty( $messages ) ) {
	echo $messages;
}

Utility::show_nav_status( 1 );

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners ORDER BY `name`";
$res = $wpdb->get_results( $sql, ARRAY_A );

if ( count( $res ) > 1 ) {
	?>
    <div class="fancy-heading"><?php Language::out( 'Available Grids' ); ?></div>
    <p>
		<?php Forms::display_banner_selecton_form( $BID, Orders::get_current_order_id(), $res, 'order' ); ?>
    </p>
	<?php
}

if ( isset( $order_exists ) && $order_exists ) {
	Language::out_replace( '<p>Note: You have placed some pixels on order, but it was not confirmed (green blocks). <a href="%MANAGE_URL%">Manage Pixels</a></p>', '%MANAGE_URL%', esc_url( Utility::get_page_url( 'manage' ) ) );
}

$package_id = intval( $_REQUEST['package'] ?? 0 );
if ( $package_id == 0 ) {
	$package_id = get_order_package( $order_id );
}

$has_packages = banner_get_packages( $BID );
if ( $has_packages ) {
	display_package_options_table( $BID, $package_id, true );
} else {
	display_price_table( $BID );
}

?>
    <div class="fancy-heading"><?php Language::out( 'Upload your pixel image' ); ?></div>
<?php
Language::out( '- Upload a GIF, JPEG or PNG graphics file<br />
- Click \'Browse\' to find your file on your computer, then click \'Upload\'.<br />
- Once uploaded, you will be able to position your file over the grid.<br />' );

?>
    <p>
    <form method='post' action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'mds-form' ); ?>
        <input type="hidden" name="action" value="mds_form_submission">
        <?php
        $mds_dest = apply_filters( 'mds_dest_order_pixels', 'order' );
        ?>
        <input type="hidden" name="mds_dest" value="<?php echo esc_attr( $mds_dest ); ?>">

		<?php Language::out( '<p><strong>Upload your pixels:</strong></p>' ); ?>
        <input type='file' accept="image/*" name='graphic' style='font-size:14px;width:200px;'/><br/>
        <input type='hidden' name='BID' value='<?php echo $BID; ?>'/>
        <input type='hidden' name='package' value='<?php echo $package_id; ?>'/>
        <input type="hidden" name="order_id" value="<?php echo esc_attr( Orders::get_current_order_id() ); ?>">
        <input class="mds_upload_image" type='submit' value='<?php echo esc_attr( Language::get( 'Upload' ) ); ?>'
               style=' font-size:18px;'/>
    </form>

<?php
if ( ! empty( $tmp_image_file ) ) {
	?>

    <div class="fancy-heading"><?php Language::out( 'Your uploaded pixels:' ); ?></div>
	<?php

	echo "<img class='mds_pointer_graphic' style=\"border:0px;\" src='" . esc_url( Utility::get_page_url( 'get-pointer-graphic', [ 'BID' => $BID ] ) ) . "' alt=\"\" /><br />";

	Language::out_replace(
		'The uploaded image is %WIDTH% pixels wide and %HEIGHT% pixels high.<br />',
		[ '%WIDTH%', '%HEIGHT%' ],
		[ $size[0], $size[1] ]
	);

	if ( empty( $reqsize ) ) {
		$reqsize = Utility::get_required_size( $size[0], $size[1], $banner_data );
	}

	if ( empty( $pixel_count ) ) {
		$pixel_count = $reqsize[0] * $reqsize[1];
	}

	if ( empty( $block_size ) ) {
		$block_size = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
	}

	Language::out_replace(
		'The uploaded image will require you to purchase %PIXEL_COUNT% pixels from the map which is exactly %BLOCK_COUNT% blocks.<br />',
		[ '%PIXEL_COUNT%', '%BLOCK_COUNT%' ],
		[ $pixel_count, $block_size ]
	);
	?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name='pixel_form'>
		<?php wp_nonce_field( 'mds-form' ); ?>
        <input type="hidden" name="action" value="mds_form_submission">
        <input type="hidden" name="mds_dest" value="order">
        <p>
            <input type="button"
                   class='big_button' <?php if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] != Orders::get_current_order_id() ) {
				echo 'disabled';
			} ?> name='submit_button1' id='submit_button1'
                   value='<?php echo esc_attr( Language::get( 'Write Your Ad' ) ) ?>'/>

        </p>

        <input type="hidden" value="1" name="select">
        <input type="hidden" value="<?php echo $BID; ?>" name="BID">

        <div id="pixels-container" style="max-width: 100%;">
            <img style="cursor: pointer; max-width: 100%; width: auto; height: auto;"
                 id="pixelimg" <?php if ( ( $USE_AJAX == 'YES' ) || ( $USE_AJAX == 'SIMPLE' ) ) { ?><?php } ?>
                 type="image" name="map" value='Select Pixels.'
                 width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>"
                 height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>"
                 src="<?php echo esc_url( Utility::get_page_url( 'show-selection', [ 'BID' => $BID, 'gud' => time() ] ) ); ?>"
                 data-original-width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>"
                 data-original-height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>"
                 alt=""/>
            <span id="block_pointer" style="position: absolute; display: block;" 
                  data-original-width="<?php echo $reqsize[0]; ?>" 
                  data-original-height="<?php echo $reqsize[1]; ?>">
                <img src="<?php echo esc_url( Utility::get_page_url( 'get-pointer-graphic', [ 'BID' => $BID ] ) ); ?>"
                     alt=""
                     style="width: 100%; height: 100%;"/>
            </span>
        </div>
        <input type="hidden" name="form_action" value="select">
    </form>
    <div class="mds-write-button">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name="form1">
			<?php wp_nonce_field( 'mds-form' ); ?>
            <input type="hidden" name="action" value="mds_form_submission">
            <input type="hidden" name="mds_dest" value="write-ad">
            <input type="hidden" name="package" value="">
            <input type="hidden" name="selected_pixels" value=''>
            <input type="hidden" name="order_id" value="<?php echo Orders::get_current_order_id(); ?>">
            <input type="hidden" value="<?php echo $BID; ?>" name="BID">
            <input type="submit"
                   class='big_button' <?php if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] != Orders::get_current_order_id() ) {
				echo 'disabled';
			} ?> name='submit_button2' id='submit_button2'
                   value='<?php echo esc_attr( Language::get( 'Write Your Ad' ) ) ?>'/>
        </form>
    </div>

    
    <script>
        // Pass critical grid and pointer data to JavaScript
        var MDS_GRID_DATA = {
            grid_width_blocks: <?php echo $banner_data['G_WIDTH']; ?>,
            grid_height_blocks: <?php echo $banner_data['G_HEIGHT']; ?>,
            pointer_width: <?php echo $reqsize[0]; ?>,
            pointer_height: <?php echo $reqsize[1]; ?>,
            block_width: <?php echo $banner_data['BLK_WIDTH']; ?>,
            block_height: <?php echo $banner_data['BLK_HEIGHT']; ?>,
            orig_width_px: <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>,
            orig_height_px: <?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>,
            debug_mode: <?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>
        };
        
        // Make all grid data available to the global MDS_OBJECT
        if (window.MDS_OBJECT) {
            window.MDS_OBJECT.grid_data = MDS_GRID_DATA;
            window.MDS_OBJECT.grid_width = MDS_GRID_DATA.grid_width_blocks;
            window.MDS_OBJECT.grid_height = MDS_GRID_DATA.grid_height_blocks;
        }
    </script>

	<?php
}

require_once MDS_CORE_PATH . "html/footer.php";

?>