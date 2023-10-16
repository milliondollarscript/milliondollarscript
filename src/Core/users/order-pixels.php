<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.2
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

// check if user has permission to access this page
if ( ! mds_check_permission( "mds_order_pixels" ) ) {
	require_once MDS_CORE_PATH . "html/header.php";
	Language::out( "No Access" );
	require_once MDS_CORE_PATH . "html/footer.php";
	exit;
}

global $f2;
$BID = $f2->bid();

if ( isset( $_REQUEST['order_id'] ) && ! empty( $_REQUEST['order_id'] ) ) {
	set_current_order_id( $_REQUEST['order_id'] );
	if ( ! is_numeric( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] != get_current_order_id() ) {
		die();
	}
}

// Delete temporary order when the banner was changed.
$reinit = false;
if ( isset( $_REQUEST['banner_change'] ) && $_REQUEST['banner_change'] != '' ) {
	$reinit = true;
	delete_temp_order( get_current_order_id() );

	return;
}

// load order from php
// only allowed 1 new order per banner

global $wpdb;

$sql = $wpdb->prepare(
	"SELECT * from " . MDS_DB_PREFIX . "orders WHERE user_id=%d AND status='new' AND banner_id=%d",
	get_current_user_id(),
	$BID
);

$order_result = $wpdb->get_results( $sql, ARRAY_A );

if ( ! empty( $order_result ) ) {
	$order_row = $order_result[0];

	if ( $order_row['user_id'] != '' && (int) $order_row['user_id'] !== get_current_user_id() ) {
		die( Language::get( 'You do not own this order!' ) );
	}
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
			if ( $stat[9] < ( time() - $elapsed_time ) ) {
				if ( strpos( $file, 'tmp_' . get_current_order_id() ) !== false ) {
					unlink( $uploaddir . $file );
				}
			}
		}

		$uploadfile = $uploaddir . "tmp_" . get_current_order_id() . ".$ext";

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
						[ '%MAX_PIXELS%', '%COUNT%' ],
						[ $limit, $pixel_count ],
						'<strong style="color:red;">Sorry, the uploaded image is too big. This image has %COUNT% pixels... A limit of %MAX_PIXELS% pixels per order is set.</strong>'
					);
					unlink( $tmp_image_file );
					unset( $tmp_image_file );
				} else if ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {

					$limit = $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'];

					$messages .= Language::get_replace(
						[ '%MIN_PIXELS%', '%COUNT%' ],
						[ $limit, $pixel_count ],
						'<strong style="color:red;">Sorry, you are required to upload an image with at least %MIN_PIXELS% pixels. This image only has %COUNT% pixels...</strong>'
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

?>

    <script>

		var selectedBlocks = [];
		var selBlocksIndex = 0;
		var trip_count = 0;

		function check_selection(OffsetX, OffsetY) {

			// Trip to the database.
			var xmlhttp;

			if (typeof XMLHttpRequest !== "undefined") {
				xmlhttp = new XMLHttpRequest();
			}

			// Note: do not use &amp; for & here
			xmlhttp.open("GET", "<?php echo Utility::get_page_url( 'check-selection' ); ?>?user_id=<?php echo get_current_user_id();?>&map_x=" + OffsetX + "&map_y=" + OffsetY + "&block_id=" + get_clicked_block() + "&BID=<?php echo $BID . "&t=" . time(); ?>", true);

			if (trip_count !== 0) {
				// trip_count: global variable counts how many times it goes to the server
				document.getElementById('submit_button1').disabled = true;
				document.getElementById('submit_button2').disabled = true;
				window.$block_pointer.css('cursor', 'wait');
				window.$pixelimg.css('cursor', 'wait');
			}

			xmlhttp.onreadystatechange = function () {
				if (xmlhttp.readyState === 4) {

					// bad selection - not available
					let parsed = JSON.parse(xmlhttp.responseText);
					if (parsed.type === 'unavailable') {
						alert(parsed.data.value);
						is_moving = true;
					}

					document.getElementById('submit_button1').disabled = false;
					document.getElementById('submit_button2').disabled = false;

					window.$block_pointer.css('cursor', 'pointer');
					window.$pixelimg.css('cursor', 'pointer');
				}
			};

			xmlhttp.send(null);
		}

		function make_selection(event) {

			event.stopPropagation();
			event.preventDefault();

			window.reserving = true;

			window.$block_pointer.css('cursor', 'wait');
			window.$pixelimg.css('cursor', 'wait');
			document.body.style.cursor = 'wait';
			let submit1 = document.getElementById('submit_button1');
			let submit2 = document.getElementById('submit_button2');
			submit1.disabled = true;
			submit2.disabled = true;
			submit1.value = "<?php esc_js( Language::get( 'Please Wait! Reserving Pixels...' ) ); ?>";
			submit2.value = "<?php esc_js( Language::get( 'Please Wait! Reserving Pixels...' ) ); ?>";
			submit1.style.cursor = 'wait';
			submit2.style.cursor = 'wait';

			let ajax_data = {
				user_id: <?php echo( get_current_user_id() ?? '""' ); ?>,
				map_x: window.$block_pointer.map_x,
				map_y: window.$block_pointer.map_y,
				block_id: get_clicked_block(),
				BID: <?php echo $BID; ?>,
				t: <?php echo time(); ?>
			};

			jQuery.ajax({
				method: 'POST',
				url: '<?php echo Utility::get_page_url( 'make-selection' ); ?>',
				data: ajax_data,
				dataType: 'html',
				crossDomain: true,
			}).done(function (data) {
				if (data.indexOf('E432') > -1) {
					alert(data);
					window.$block_pointer.css('cursor', 'pointer');
					window.$pixelimg.css('cursor', 'pointer');
					document.body.style.cursor = 'pointer';
					submit1.disabled = false;
					submit2.disabled = false;
					submit1.value = "<?php esc_js( Language::get( 'Write Your Ad' ) ); ?>";
					submit2.value = "<?php esc_js( Language::get( 'Write Your Ad' ) ); ?>";
					submit1.style.cursor = 'pointer';
					submit2.style.cursor = 'pointer';
					window.reserving = false;
					is_moving = true;
				} else {
					document.form1.submit();
				}

			}).fail(function (jqXHR, textStatus, errorThrown) {
			}).always(function () {
			});
		}

		// Initialize
		var block_str = "<?php echo( isset( $order_row ) ? $order_row["blocks"] : '' ); ?>";
		trip_count = 0;

		var pos;

		function getObjCoords(obj) {
			var rect = obj.getBoundingClientRect();
			var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
			var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
			return {x: rect.left + scrollLeft, y: rect.top + scrollTop};
		}

		function show_pointer(e) {
			if (!is_moving) return;

			var pos = getObjCoords(window.$pixelimg[0]);

			if (e.offsetX != undefined) {
				var OffsetX = e.offsetX;
				var OffsetY = e.offsetY;
			} else {
				var OffsetX = e.pageX - pos.x;
				var OffsetY = e.pageY - pos.y;
			}

			OffsetX = Math.floor(OffsetX / <?php echo $banner_data['BLK_WIDTH']; ?>) *<?php echo $banner_data['BLK_WIDTH']; ?>;
			OffsetY = Math.floor(OffsetY / <?php echo $banner_data['BLK_HEIGHT']; ?>) *<?php echo $banner_data['BLK_HEIGHT']; ?>;

			if (isNaN(OffsetX) || isNaN(OffsetY) || OffsetX < 0 || OffsetY < 0) {
				return;
			}

			if (window.pointer_height + OffsetY > <?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];?>) {
			} else {
				window.$block_pointer.css('top', pos.y + OffsetY + 'px');
				window.$block_pointer.map_y = OffsetY;
			}

			if (window.pointer_width + OffsetX > <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];?>) {
			} else {
				window.$block_pointer.map_x = pos.x + OffsetX;
				window.$block_pointer.css('left', pos.x + OffsetX + 'px');
			}

			return true;
		}

		var i_count = 0;

		function show_pointer2(e) {
			//function called when mouse is over the actual pointing image

			if (!is_moving) return;

			var pos = getObjCoords(window.$pixelimg[0]);
			var p_pos = getObjCoords(window.$block_pointer[0]);

			if (e.offsetX !== undefined) {
				var OffsetX = e.offsetX;
				var OffsetY = e.offsetY;
				var ie = true;
			} else {
				var OffsetX = e.pageX - pos.x;
				var OffsetY = e.pageY - pos.y;
				var ie = false;
			}

			if (ie) { // special routine for internet explorer...

				var rel_posx = p_pos.x - pos.x;
				var rel_posy = p_pos.y - pos.y;

				window.$block_pointer.map_x = rel_posx;
				window.$block_pointer.map_y = rel_posy;

				if (isNaN(OffsetX) || isNaN(OffsetY)) {
					return
				}

				if (OffsetX >=<?php echo $banner_data['BLK_WIDTH']; ?>) {
					// move the pointer right
					if (rel_posx + window.pointer_width >= <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>) {
					} else {
						window.$block_pointer.map_x = p_pos.x +<?php echo $banner_data['BLK_WIDTH']; ?>;
						window.$block_pointer.css('left', window.$block_pointer.map_x + 'px');
					}

				}

				if (OffsetY ><?php echo $banner_data['BLK_HEIGHT']; ?>) {
					// move the pointer down

					if (rel_posy + window.pointer_height >= <?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>) {

						//return
					} else {

						window.$block_pointer.map_y = p_pos.y +<?php echo $banner_data['BLK_HEIGHT']; ?>;
						window.$block_pointer.css('top', window.$block_pointer.map_y + 'px');
					}
				}

			} else {

				var tOffsetX = Math.floor(OffsetX / <?php echo $banner_data['BLK_WIDTH']; ?>) *<?php echo $banner_data['BLK_WIDTH']; ?>;
				var tOffsetY = Math.floor(OffsetY / <?php echo $banner_data['BLK_HEIGHT']; ?>) *<?php echo $banner_data['BLK_HEIGHT']; ?>;

				if (isNaN(OffsetX) || isNaN(OffsetY)) {
					return
				}

				if (OffsetX > tOffsetX) {
					if (window.pointer_width + tOffsetX > <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];?>) {
						// don't move left
					} else {
						window.$block_pointer.map_x = tOffsetX;
						window.$block_pointer.css('left', pos.x + tOffsetX + 'px');
					}
				}

				if (OffsetY > tOffsetY) {
					if (window.pointer_height + tOffsetY > <?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];?>) {
						// don't move down
					} else {
						window.$block_pointer.css('top', pos.y + tOffsetY + 'px');
						window.$block_pointer.map_y = tOffsetY;
					}
				}
			}
		}

		function get_clicked_block() {

			var grid_width =<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];?>;
			var grid_height =<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];?>;

			var blk_width = <?php echo $banner_data['BLK_WIDTH']; ?>;
			var blk_height = <?php echo $banner_data['BLK_HEIGHT']; ?>;

			var clicked_block = ((window.$block_pointer.map_x) / blk_width) + ((window.$block_pointer.map_y / blk_height) * (grid_width / blk_width));

			if (clicked_block === 0) {
				// convert to string
				clicked_block = "0";
			}
			return clicked_block;
		}

		function do_block_click() {

			if (window.reserving) {
				return;
			}

			trip_count = 1;
			check_selection(window.$block_pointer.map_x, window.$block_pointer.map_y);
			low_x = window.$block_pointer.map_x;
			low_y = window.$block_pointer.map_y;

			is_moving = !is_moving;
		}

		var low_x = 0;
		var low_y = 0;

		<?php

		// get the top-most, left-most block
		$low_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
		$low_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

		$sql = $wpdb->prepare( "SELECT block_info FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%s", get_current_order_id() );
		$result = $wpdb->get_results( $sql );

		if ( ! empty( $result ) ) {
			$block_info = unserialize( $result[0]->block_info );
		}

		$init = false;
		if ( isset( $block_info ) && is_array( $block_info ) ) {

			foreach ( $block_info as $block ) {

				if ( $low_x >= $block['map_x'] ) {
					$low_x = $block['map_x'];
					$init  = true;
				}

				if ( $low_y >= $block['map_y'] ) {
					$low_y = $block['map_y'];
					$init  = true;
				}
			}
		}

		if ( ! $init || $reinit === true ) {
			$low_x     = 0;
			$low_y     = 0;
			$is_moving = " is_moving=true ";
		} else {
			$is_moving = " is_moving=false ";
		}

		echo "low_x = $low_x;";
		echo "low_y = $low_y; $is_moving";

		?>

		function move_image_to_selection() {
			let pos = getObjCoords(window.$pixelimg[0]);

			window.$block_pointer.css('top', pos.y + low_y + 'px');
			window.$block_pointer.map_y = low_y;

			window.$block_pointer.css('left', pos.x + low_x + 'px');
			window.$block_pointer.map_x = low_x;

			window.$block_pointer.css('visibility', 'visible');
		}
    </script>

<?php
if ( ! empty( $tmp_image_file ) ) {

	if ( empty( $size ) ) {
		$size = getimagesize( $tmp_image_file );
	}

	?>
    <style>
		#block_pointer {
			width: <?php echo $size[0]; ?>px;
			height: <?php echo $size[1]; ?>px;
			padding: 0;
			margin: 0;
			line-height: <?php echo $size[1]; ?>px;
			font-size: <?php echo $size[1]; ?>px;
		}
    </style>
	<?php
}

// Output any messages
if ( ! empty( $messages ) ) {
	echo $messages;
}

// pointer.png
?>

    <span id="block_pointer" style='cursor: pointer;position:absolute;left:0; top:0;background:transparent; visibility:hidden '><img src="<?php echo Utility::get_page_url( 'get-pointer-graphic' ); ?>?BID=<?php echo $BID; ?>" alt=""/></span>

<?php
show_nav_status( 1 );

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners ORDER BY `name`";
$res = $wpdb->get_results( $sql, ARRAY_A );

if ( count( $res ) > 1 ) {
	?>
    <div class="fancy-heading"><?php Language::out( 'Available Grids' ); ?></div>
    <p>
		<?php display_banner_selecton_form( $BID, get_current_order_id(), $res, 'order' ); ?>
    </p>
	<?php
}

if ( isset( $order_exists ) && $order_exists ) {
	Language::out_replace( '%HISTORY_URL%', Utility::get_page_url( 'history' ), '<p>Note: You have placed some pixels on order, but it was not confirmed (green blocks). <a href="%HISTORY_URL%">View Order History</a></p>' );
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
        <input class="mds_upload_image" type='submit' value='<?php echo esc_attr( Language::get( 'Upload' ) ); ?>' style=' font-size:18px;'/>
    </form>

<?php
if ( ! empty( $tmp_image_file ) ) {
	?>

    <div class="fancy-heading"><?php Language::out( 'Your uploaded pixels:' ); ?></div>
	<?php

	echo "<img class='mds_pointer_graphic' style=\"border:0px;\" src='" . esc_url( Utility::get_page_url( 'get-pointer-graphic' ) ) . "?BID=" . $BID . "' alt=\"\" /><br />";

	Language::out_replace(
		[ '%WIDTH%', '%HEIGHT%' ],
		[ $size[0], $size[1] ],
		'The uploaded image is %WIDTH% pixels wide and %HEIGHT% pixels high.<br />'
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
		[ '%PIXEL_COUNT%', '%BLOCK_COUNT%' ],
		[ $pixel_count, $block_size ],
		'The uploaded image will require you to purchase %PIXEL_COUNT% pixels from the map which is exactly %BLOCK_COUNT% blocks.<br />'
	);
	?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name='pixel_form'>
		<?php wp_nonce_field( 'mds-form' ); ?>
        <input type="hidden" name="action" value="mds_form_submission">
        <input type="hidden" name="mds_dest" value="order">
        <p>
            <input type="button" class='big_button' <?php if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] != get_current_order_id() ) {
				echo 'disabled';
			} ?> name='submit_button1' id='submit_button1' value='<?php echo esc_attr( Language::get( 'Write Your Ad' ) ) ?>' onclick="make_selection(event);return false;">

        </p>

        <input type="hidden" value="1" name="select">
        <input type="hidden" value="<?php echo $BID; ?>" name="BID">

        <img style="cursor: pointer;max-width: <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>px;" id="pixelimg" <?php if ( ( $USE_AJAX == 'YES' ) || ( $USE_AJAX == 'SIMPLE' ) ) { ?><?php } ?> type="image" name="map" value='Select Pixels.' width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>" height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>" src="<?php echo Utility::get_page_url( 'show-selection' ); ?>?BID=<?php echo $BID; ?>&amp;gud=<?php echo time(); ?>" alt=""/>

        <input type="hidden" name="form_action" value="select">
    </form>
    <div class="mds-write-button">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name="form1">
			<?php wp_nonce_field( 'mds-form' ); ?>
            <input type="hidden" name="action" value="mds_form_submission">
            <input type="hidden" name="mds_dest" value="write-ad">
            <input type="hidden" name="package" value="">
            <input type="hidden" name="selected_pixels" value=''>
            <input type="hidden" name="order_id" value="<?php echo get_current_order_id(); ?>">
            <input type="hidden" value="<?php echo $BID; ?>" name="BID">
            <input type="submit" class='big_button' <?php if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] != get_current_order_id() ) {
				echo 'disabled';
			} ?> name='submit_button2' id='submit_button2' value='<?php echo esc_attr( Language::get( 'Write Your Ad' ) ) ?>' onclick="make_selection(event);return false;">
        </form>
    </div>

    <script type="text/javascript">
		document.form1.selected_pixels.value = block_str;
		jQuery(document).ready(function () {
			window.pointer_width = <?php echo $reqsize[0]; ?>;
			window.pointer_height =  <?php echo $reqsize[1]; ?>;

			window.$block_pointer = jQuery('#block_pointer');
			window.$pixelimg = jQuery('#pixelimg');

			window.$block_pointer.on('mousemove', function (event) {
				show_pointer2(event);
			});

			window.$block_pointer.on('click', function () {
				do_block_click();
			});

			window.$pixelimg.on('mousemove', function (event) {
				show_pointer(event);
			});

			window.$pixelimg.on('load', function () {
				window.onresize = move_image_to_selection;
				window.onload = move_image_to_selection;
				move_image_to_selection();
				remove_ajax_loader();
			});

			function add_ajax_loader(container) {
				let $ajax_loader = jQuery("<div class='ajax-loader'></div>");
				jQuery(container).append($ajax_loader)
				$ajax_loader.css('top', jQuery(container).position().top).css('left', (jQuery(container).width() / 2) - ($ajax_loader.width() / 2));
			}

			function remove_ajax_loader() {
				jQuery('.ajax-loader').remove();
			}

			add_ajax_loader(window.$pixelimg.parent());
		});
    </script>

	<?php
}

require_once MDS_CORE_PATH . "html/footer.php";

?>