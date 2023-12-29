<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.9
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
use MillionDollarScript\Classes\Orders;
use MillionDollarScript\Classes\Utility;

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
			\MillionDollarScript\Classes\Functions::no_orders();
		} else {
			// If it was numeric check

			// Verify ownership
			$order_id = intval( $_REQUEST['order_id'] );
			if ( ! Orders::is_owned_by( $order_id ) ) {
				\MillionDollarScript\Classes\Functions::no_orders();
			}

			// Check if the order is in progress
			if ( ! \MillionDollarScript\Classes\Orders::is_order_in_progress( $order_id ) ) {
				// Not in progress so redirect to the no orders page
				\MillionDollarScript\Classes\Functions::no_orders();
			}

			// Was found to be an order in progress so set the current order to it
			Orders::set_order_in_progress( $order_id );
		}

	} else {
		// No order_id in request, start a new order
		$order_id = Orders::create_order();
	}
}

// Delete temporary order when the banner was changed.
if ( isset( $_REQUEST['banner_change'] ) && $_REQUEST['banner_change'] != '' ) {
	delete_temp_order( Orders::get_current_order_id() );

	return;
}

$USE_AJAX = Config::get( 'USE_AJAX' );

$banner_data = load_banner_constants( $BID );

// Update time stamp on temp order (if exists)
update_temp_order_timestamp();

$sql     = $wpdb->prepare( "SELECT block_id, status, user_id FROM " . MDS_DB_PREFIX . "blocks WHERE banner_id=%d", $BID );
$results = $wpdb->get_results( $sql, ARRAY_A );

$blocks = array();
foreach ( $results as $row ) {
	$blocks[ $row['block_id'] ] = $row['status'];
}

// Handle file upload
$uploaddir      = \MillionDollarScript\Classes\Utility::get_upload_path() . "images/";
$tmp_image_file = get_tmp_img_name();
$size           = [];
$reqsize        = [];
$pixel_count    = 0;
$block_size     = 0;

if ( isset( $_FILES['graphic'] ) && $_FILES['graphic']['tmp_name'] != '' ) {
	global $f2;

	//$parts = split ('\.', $_FILES['graphic']['name']);
	$parts = $file_parts = pathinfo( $_FILES['graphic']['name'] );
	$ext   = $f2->filter( strtolower( $file_parts['extension'] ) );

	$error              = "";
	$mime_type          = mime_content_type( $_FILES['graphic']['tmp_name'] );
	$allowed_file_types = [ 'image/png', 'image/jpeg', 'image/gif' ];
	if ( ! in_array( $mime_type, $allowed_file_types ) ) {
		$error = "<b>" . Language::get( 'File type not supported.' ) . "</b><br>";
	}

	$messages = "";
	if ( ! empty( $error ) ) {
		$messages           .= $error;
		$image_changed_flag = false;
	} else {
		// clean up is handled by the delete_temp_order($sid) function...

		//delete_temp_order( get_current_order_id() );

		// delete temp_* files older than 24 hours
		$dh = opendir( $uploaddir );
		while ( ( $file = readdir( $dh ) ) !== false ) {

			$elapsed_time = 60 * 60 * 24; // 24 hours

			// delete old files
			$stat = stat( $uploaddir . $file );
			if ( $stat['mtime'] < ( time() - $elapsed_time ) ) {
				if ( str_contains( $file, 'tmp_' . Orders::get_current_order_id() ) ) {
					unlink( $uploaddir . $file );
				}
			}
		}

		$uploadfile = $uploaddir . "tmp_" . Orders::get_current_order_id() . ".$ext";

		if ( move_uploaded_file( $_FILES['graphic']['tmp_name'], $uploadfile ) ) {
			//echo "File is valid, and was successfully uploaded.\n";
			$tmp_image_file = $uploadfile;

			setMemoryLimit( $uploadfile );

			// check the file size for min and max blocks.

			// uploaded image size
			$size = getimagesize( $tmp_image_file );

			// maximum size snapped to block size
			$reqsize = get_required_size( $size[0], $size[1], $banner_data );

			// pixel count
			$pixel_count = $reqsize[0] * $reqsize[1];

			// final size
			$block_size = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

			// if image should be resized automatically make it fit within grid max/min block settings
			if ( Config::get( 'MDS_RESIZE' ) == 'YES' ) {
				$rescale = [];
				if ( ( $block_size > $banner_data['G_MAX_BLOCKS'] ) && ( $banner_data['G_MAX_BLOCKS'] > 0 ) ) {
					$rescale['x'] = min( $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $reqsize[0] );
					$rescale['y'] = min( $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $reqsize[0] );
				} else if ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {
					$rescale['x'] = min( $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $reqsize[0] );
					$rescale['y'] = min( $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $reqsize[0] );
				}

				// resize uploaded image
				if ( class_exists( 'Imagick' ) ) {
					$imagine = new Imagine\Imagick\Imagine();
				} else if ( function_exists( 'gd_info' ) ) {
					$imagine = new Imagine\Gd\Imagine();
				}
				$image = $imagine->open( $tmp_image_file );

				if ( isset( $rescale['x'] ) ) {
					$resize = new Imagine\Image\Box( $rescale['x'], $rescale['y'] );
					$image->resize( $resize );
					$fileinfo = pathinfo( $tmp_image_file );
					$newname  = ( $fileinfo['dirname'] ? $fileinfo['dirname'] . DIRECTORY_SEPARATOR : '' ) . $fileinfo['filename'] . '.png';
					$image->save( $newname );
					$size[0]    = $rescale['x'];
					$size[1]    = $rescale['y'];
					$reqsize[0] = $rescale['x'];
					$reqsize[1] = $rescale['y'];

					// recount pixel count
					$pixel_count = $reqsize[0] * $reqsize[1];

					// recount final size
					$block_size = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
				} else {
					// save to png
					$fileinfo = pathinfo( $tmp_image_file );
					$newname  = ( $fileinfo['dirname'] ? $fileinfo['dirname'] . DIRECTORY_SEPARATOR : '' ) . $fileinfo['filename'] . '.png';
					$image->save( $newname );
				}
			} else {
				// TODO: handle png extension in these cases
				if ( ( $block_size > $banner_data['G_MAX_BLOCKS'] ) && ( $banner_data['G_MAX_BLOCKS'] > 0 ) ) {

					$limit = $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'];

					$messages .= Language::get_replace(
						'<strong style="color:red;">Sorry, the uploaded image is too big. This image has %COUNT% pixels... A limit of %MAX_PIXELS% pixels per order is set.</strong>',
						[ '%MAX_PIXELS%', '%COUNT%' ],
						[ $limit, $pixel_count ]
					);
					unlink( $tmp_image_file );
					unset( $tmp_image_file );
				} else if ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {

					$limit = $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'];

					$messages .= Language::get_replace(
						'<strong style="color:red;">Sorry, you are required to upload an image with at least %MIN_PIXELS% pixels. This image only has %COUNT% pixels...</strong>',
						[ '%MIN_PIXELS%', '%COUNT%' ],
						[ $limit, $pixel_count ]
					);
					unlink( $tmp_image_file );
					unset( $tmp_image_file );
				}
			}
		} else {
			//echo "Possible file upload attack!\n";
			$messages .= Language::get( 'Upload failed. Please try again, or try a different file.' );
		}
	}
}

if ( isset( $_POST['mds_dest'] ) || str_ends_with( $_SERVER['REQUEST_URI'], 'admin-post.php' ) ) {
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

show_nav_status( 1 );

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners ORDER BY `name`";
$res = $wpdb->get_results( $sql, ARRAY_A );

if ( count( $res ) > 1 ) {
	?>
    <div class="fancy-heading"><?php Language::out( 'Available Grids' ); ?></div>
    <p>
		<?php display_banner_selecton_form( $BID, Orders::get_current_order_id(), $res, 'order' ); ?>
    </p>
	<?php
}

if ( isset( $order_exists ) && $order_exists ) {
	Language::out_replace( '<p>Note: You have placed some pixels on order, but it was not confirmed (green blocks). <a href="%HISTORY_URL%">View Order History</a></p>', '%HISTORY_URL%', Utility::get_page_url( 'history' ) );
}

$has_packages = banner_get_packages( $BID );
if ( $has_packages ) {
	display_package_options_table( $BID, '', true );
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
        <input type="hidden" name="mds_dest" value="order">

		<?php Language::out( '<p><strong>Upload your pixels:</strong></p>' ); ?>
        <input type='file' accept="image/*" name='graphic' style='font-size:14px;width:200px;'/><br/>
        <input type='hidden' name='BID' value='<?php echo $BID; ?>'/>
        <input class="mds_upload_image" type='submit' value='<?php echo esc_attr( Language::get( 'Upload' ) ); ?>'
               style=' font-size:18px;'/>
    </form>

<?php
if ( ! empty( $tmp_image_file ) ) {
	?>

    <div class="fancy-heading"><?php Language::out( 'Your uploaded pixels:' ); ?></div>
	<?php

	echo "<img class='mds_pointer_graphic' style=\"border:0px;\" src='" . esc_url( Utility::get_page_url( 'get-pointer-graphic' ) ) . "?BID=" . $BID . "' alt=\"\" /><br />";

	Language::out_replace(
		'The uploaded image is %WIDTH% pixels wide and %HEIGHT% pixels high.<br />',
		[ '%WIDTH%', '%HEIGHT%' ],
		[ $size[0], $size[1] ]
	);

	if ( empty( $reqsize ) ) {
		$reqsize = get_required_size( $size[0], $size[1], $banner_data );
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

        <div id="pixels-container">
            <img style="cursor: pointer;max-width: <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>px;"
                 id="pixelimg" <?php if ( ( $USE_AJAX == 'YES' ) || ( $USE_AJAX == 'SIMPLE' ) ) { ?><?php } ?>
                 type="image" name="map" value='Select Pixels.'
                 width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>"
                 height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>"
                 src="<?php echo Utility::get_page_url( 'show-selection' ); ?>?BID=<?php echo $BID; ?>&amp;gud=<?php echo time(); ?>"
                 alt=""/>
            <span id="block_pointer"><img
                        src="<?php echo Utility::get_page_url( 'get-pointer-graphic' ); ?>?BID=<?php echo $BID; ?>"
                        alt=""/></span>
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
        jQuery(document).ready(function () {
            window.pointer_width = <?php echo $reqsize[0]; ?>;
            window.pointer_height =  <?php echo $reqsize[1]; ?>;
        });
    </script>

	<?php
}

require_once MDS_CORE_PATH . "html/footer.php";

?>