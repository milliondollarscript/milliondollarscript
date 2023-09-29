<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.1
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
use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

require_once MDS_CORE_PATH . "include/ads.inc.php";

require_once MDS_CORE_PATH . "html/header.php";

global $BID, $f2;

$BID = $f2->bid();

if ( class_exists( 'Imagick' ) ) {
	$imagine = new Imagine\Imagick\Imagine();
} else if ( function_exists( 'gd_info' ) ) {
	$imagine = new Imagine\Gd\Imagine();
}

$banner_data  = load_banner_constants( $BID );
$has_packages = banner_get_packages( $BID );

$size        = [];
$reqsize     = [];
$pixel_count = 0;
$block_size  = 0;
if ( isset( $_FILES['graphic'] ) && $_FILES['graphic']['tmp_name'] != '' ) {
	$uploadok = false;
	if ( is_uploaded_file( $_FILES['graphic']['tmp_name'] ) ) {
		// Notice how to grab MIME type.
		$mime_type = mime_content_type( $_FILES['graphic']['tmp_name'] );

		// If you want to allow certain files
		$allowed_file_types = [ 'image/png', 'image/jpeg', 'image/gif' ];
		if ( ! in_array( $mime_type, $allowed_file_types ) ) {
			$error = "<b>" . Language::get( 'File type not supported.' ) . "</b><br>";
			echo $error;
		} else {
			$uploadok = true;
		}
	}

	if ( $uploadok ) {
		global $wpdb;

		$ad_id         = insert_ad_data();
		$sql_completed = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id=%d AND ad_id=%d", get_current_user_id(), intval( $ad_id ) );
		$row = $wpdb->get_row( $sql_completed, ARRAY_A );
		if ( $row ) {
			if ( $row['status'] != 'completed' ) {
				$order_id                  = get_current_order_id();
				$blocks                    = explode( ',', $row['blocks'] );
				$size                      = get_pixel_image_size( $order_id );
				$pixels                    = $size['x'] * $size['y'];
				$_REQUEST['change_pixels'] = true;

				upload_changed_pixels( $order_id, $BID, $size, $banner_data );
			}
		}
	}
}

$tmp_image_file = get_tmp_img_name();

if ( file_exists( $tmp_image_file ) ) {
	$image = $imagine->open( $tmp_image_file );
}

$cannot_get_package = false;

if ( $has_packages && ! empty( $_REQUEST['pack'] ) ) {

	// check to make sure this advertiser can order this package
	if ( can_user_get_package( get_current_user_id(), $_REQUEST['pack'] ) ) {

		$sql = "SELECT quantity FROM " . MDS_DB_PREFIX . "orders WHERE order_id='" . intval( $_REQUEST['order_id'] ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$row      = mysqli_fetch_array( $result );
		$quantity = $row['quantity'];

		$block_count = $quantity / ( $banner_data['block_width'] * $banner_data['block_height'] );

		// Now update the order (overwrite the total & days_expire with the package)
		$pack  = get_package( $_REQUEST['pack'] );
		$total = $pack['price'] * $block_count;

		// convert & round off
		$total = convert_to_default_currency( $pack['currency'], $total );

		$sql = "UPDATE " . MDS_DB_PREFIX . "orders SET package_id='" . intval( $_REQUEST['pack'] ) . "', price='" . floatval( $total ) . "',  days_expire='" . intval( $pack['days_expire'] ) . "', currency='" . mysqli_real_escape_string( $GLOBALS['connection'], get_default_currency() ) . "' WHERE order_id='" . intval( get_current_order_id() ) . "'";

		mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	} else {
		$selected_pack      = $_REQUEST['pack'];
		$_REQUEST['pack']   = '';
		$cannot_get_package = true;
	}
}

// if ( isset( $_POST['mds_dest'] ) && isset( $_FILES ) ) {
// 	wp_safe_redirect( Utility::get_page_url( 'write-ad' ) );
//     exit;
// }

// check to make sure MIN_BLOCKS were selected.
$sql = "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE user_id='" . get_current_user_id() . "' AND status='reserved' AND banner_id='$BID' ";
$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
$count             = mysqli_num_rows( $res );
$not_enough_blocks = $count < $banner_data['G_MIN_BLOCKS'];

Language::out_replace(
	[ '%BID%', '%ORDER_URL%' ],
	[ $BID, Utility::get_page_url( 'order' ) ],
	'<p>1. <a href="%ORDER_URL%?BID=%BID%">Select Your pixels</a> -> 2. <b>Image Upload</b> -> 3. Write Your Ad -> 4. Confirm Order -> 5. Payment</p>'
);
?>
    <p id="select_status"><?php echo( $cannot_sel ?? "" ); ?></p>
<?php
/*
$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners order by `name` ";
$res = mysqli_query( $GLOBALS['connection'], $sql );

if ( mysqli_num_rows( $res ) > 1 ) {
	?>
    <div class="fancy-heading"><?php Language::out( 'Available Grids' ); ?></div>
    <div class="mds-select-intro">
		<?php
		Language::out_replace(
			'%GRID_COUNT%',
			mysqli_num_rows( $res ),
			'<p>There are <b>%GRID_COUNT%</b> different grids served by this website! Select the image which you would like to publish your pixels to:</p>'
		);
		?>
    </div>
	<?php display_banner_selecton_form( $BID, get_current_order_id(), $res, 'upload' );
	//
	// if ( ! isset( $_REQUEST['banner_change'] ) && ( ! isset( $_REQUEST['jEditOrder'] ) || $_REQUEST['jEditOrder'] !== 'true' ) ) {
	// 	// If multiple banners only display the selection form first
	// 	require_once MDS_CORE_PATH . "html/footer.php";
	//
	// 	return;
	// }
}*/

if ( isset( $order_exists ) && $order_exists ) {
	Language::out_replace(
		'%HISTORY_URL%',
		Utility::get_page_url( 'history' ),
		'<p>Note: You have placed some pixels on order, but it was not confirmed (green blocks). <a href="%HISTORY_URL%">View Order History</a></p>'
	);
}

// $has_packages = banner_get_packages( $BID );
// if ( $has_packages ) {
// 	display_package_options_table( $BID, '', false );
// } else {
// 	display_price_table( $BID );
// }

$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where order_id='" . intval( get_current_order_id() ) . "' and banner_id='$BID'";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
$order_row = mysqli_fetch_array( $result );

if ( $result->num_rows == 0 ) {
	Language::out_replace(
		[ '%ORDER_URL%', '%BID%' ],
		[ Utility::get_page_url( 'order' ), $BID ],
		'<p>You have no pixels selected on order! Please <a href="%ORDER_URL%?BID=%BID%">select some pixels here</a>.</p>'
	);

	return;
}
$price = $order_row['price'] . ' ' . $order_row['currency'];

?>
    <div class="fancy-heading"><?php Language::out( 'Upload your pixel image' ); ?></div>
<?php
Language::out_replace(
	'%TOTAL_COST%',
	$price,
	'<p>- Upload a GIF, JPEG or PNG graphics file<br/>
- Click "Browse" to find your file on your computer, then click "Upload".<br/>
- Once uploaded, you will be able to position your file over the grid.<br/></p>'
);
?>
    <p>
    <form method='post' action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'mds-form' ); ?>
        <input type="hidden" name="action" value="mds_form_submission">
        <input type="hidden" name="mds_dest" value="upload">
		<?php Language::out( '<p><strong>Upload your pixels:</strong></p>' ); ?>
        <input type='file' accept="image/*" name='graphic' style=' font-size:14px;width:200px;'/>
        <input type='hidden' name='BID' value='<?php echo $BID; ?>'/>
        <input class="mds_upload_image" type='submit' value='<?php echo esc_attr( Language::get( 'Upload' ) ); ?>' style=' font-size:18px;'/>
    </form>

<?php

if ( isset( $image ) && ! empty( $image ) ) {

	?>

    <div class="fancy-heading"><?php Language::out( 'Your Uploaded Pixels' ); ?></div>
    <p>
		<?php
		echo "<img class='mds_pointer_graphic' style=\"border:0px;\" src='" . esc_url( Utility::get_page_url( 'get-pointer-graphic' ) ) . "?BID=" . $BID . "' alt=\"\" /><br />";

		if ( empty( $size ) ) {
			$tmp_size = getimagesize( $tmp_image_file );
			$size     = array( "x" => $tmp_size[0], "y" => $tmp_size[1] );
			unset( $tmp_size );
		}

		Language::out_replace(
			[ '%WIDTH%', '%HEIGHT%' ],
			[ $size['x'], $size['y'] ],
			'The uploaded image is %WIDTH% pixels wide and %HEIGHT% pixels high.'
		);

		?>
        <br/>
		<?php

		if ( empty( $reqsize ) ) {
			$reqsize = [
				'x' => $order_row['quantity'] / $banner_data['BLK_WIDTH'],
				'y' => $order_row['quantity'] / $banner_data['BLK_HEIGHT']
			];
		}

		if ( empty( $pixel_count ) ) {
			$pixel_count = $order_row['quantity'];
		}

		if ( empty( $block_size ) ) {
			$block_size = $order_row['quantity'] / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
		}

		// if image should be resized automatically make it fit within grid max/min block settings
		if ( Config::get( 'MDS_RESIZE' ) == 'YES' ) {

			$rescale = [];
			if ( ( $block_size > $banner_data['G_MAX_BLOCKS'] ) && ( $banner_data['G_MAX_BLOCKS'] > 0 ) ) {
				$rescale['x'] = min( $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $reqsize['x'] );
				$rescale['y'] = min( $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $reqsize['y'] );
			} else if ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {
				$rescale['x'] = min( $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $reqsize['x'] );
				$rescale['y'] = min( $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $reqsize['y'] );
			} else {
				$rescale['x'] = $banner_data['BLK_WIDTH'];
				$rescale['y'] = $banner_data['BLK_HEIGHT'];
			}

			if ( isset( $rescale['x'] ) && isset( $rescale['y'] ) ) {
				// resize uploaded image
				// if ( class_exists( 'Imagick' ) ) {
				// 	$imagine = new Imagine\Imagick\Imagine();
				// } else if ( function_exists( 'gd_info' ) ) {
				// 	$imagine = new Imagine\Gd\Imagine();
				// }
				// $image  = $imagine->open( $tmp_image_file );
				$resize = new Imagine\Image\Box( $rescale['x'], $rescale['y'] );
				$image->resize( $resize );
				$fileinfo = pathinfo( $tmp_image_file );
				$newname  = ( $fileinfo['dirname'] ? $fileinfo['dirname'] . DIRECTORY_SEPARATOR : '' ) . $fileinfo['filename'] . '.png';
				$image->save( $newname );
				// $size[0]    = $rescale['x'];
				// $size[1]    = $rescale['y'];
				// $reqsize[0] = $rescale['x'];
				// $reqsize[1] = $rescale['y'];
			}
		} else {

			if ( ( $block_size > $banner_data['G_MAX_BLOCKS'] ) && ( $banner_data['G_MAX_BLOCKS'] > 0 ) ) {

				$limit = $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'];

				Language::out_replace(
					[ '%MAX_PIXELS%', '%COUNT%' ],
					[ $limit, $pixel_count ],
					'<strong style="color:red;">Sorry, the uploaded image is too big. This image has %COUNT% pixels... A limit of %MAX_PIXELS% pixels per order is set.</strong>'
				);

				unset( $tmp_image_file );
			} else if ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {

				Language::out_replace(
					[ '%MIN_PIXELS%', '%COUNT%' ],
					[ $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'], $pixel_count ],
					'<strong style="color:red;">Sorry, you are required to upload an image with at least %MIN_PIXELS% pixels. This image only has %COUNT% pixels...</strong>'
				);

				unset( $tmp_image_file );
			}
		}

		Language::out_replace(
			[ '%PIXEL_COUNT%', '%BLOCK_COUNT%' ],
			[ $pixel_count, $block_size ],
			'The uploaded image will require you to purchase %PIXEL_COUNT% pixels from the map which is exactly %BLOCK_COUNT% blocks.'
		);

		?>
        <br/>
		<?php

		Language::out_replace(
			[ '%PIXEL_COUNT%', '%BLOCK_COUNT%', '%TOTAL_COST%' ],
			[ $pixel_count, $block_size, $price ],
			'This will cost you %TOTAL_COST%.'
		);

		?>

    </p>
	<?php

	function display_edit_order_button( $order_id ) {
		global $BID;
		?>
        <input type='button' value="<?php echo esc_attr( Language::get( 'Edit Order' ) ); ?>" onclick="window.location='select.php?&jEditOrder=true&BID=<?php echo $BID; ?>&order_id=<?php echo $order_id; ?>'">
		<?php
	}

	$mds_order_id = get_current_order_id();

	if ( ( $order_row['order_id'] == '' ) || ( ( $order_row['quantity'] == '0' ) ) ) {
		Language::out_replace(
			[ '%ORDER_URL%', '%BID%' ],
			[ Utility::get_page_url( 'order' ), $BID ],
			'<p>You have no pixels selected on order! Please <a href="%ORDER_URL%?BID=%BID%">select some pixels here</a>.</p>'
		);
	} else if ( $not_enough_blocks ) {
		Language::out( '<h3>Not enough blocks selected</h3>' );
		Language::out_replace(
			'%MIN_BLOCKS%',
			$banner_data['G_MIN_BLOCKS'],
			'<p>You are required to select at least %MIN_BLOCKS% blocks from the grid. Please go back to select more pixels.</p>'
		);

		display_edit_order_button( $mds_order_id );
	} else {
		if ( isset( $image ) ) {

			?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name="form1">
				<?php wp_nonce_field( 'mds_write_ad' ); ?>
                <input type="hidden" name="action" value="mds_form_submission">
                <input type="hidden" name="mds_dest" value="write-ad">
                <input type="hidden" name="package" value="">
                <input type="hidden" name="selected_pixels" value=''>
                <input type="hidden" name="order_id" value="<?php echo $mds_order_id; ?>">
                <input type="hidden" value="<?php echo $BID; ?>" name="BID">
                <input type="submit" class='big_button' name='submit_button2' id='submit_button2' value='<?php echo esc_attr( Language::get( 'Write Your Ad' ) ); ?>'>
                <hr/>
            </form>
            <img src="<?php echo esc_url( Utility::get_page_url( 'show-map' ) ); ?>?BID=<?php echo $BID; ?>&time=<?php echo( time() ); ?>" width="<?php echo( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ); ?>" height="<?php echo( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ); ?>" usemap="#main"/>
			<?php
		}
	}
}

require_once MDS_CORE_PATH . "html/footer.php";