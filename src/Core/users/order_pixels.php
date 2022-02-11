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

require_once __DIR__ . "/../include/login_functions.php";
mds_start_session();
require_once __DIR__ . "/../include/init.php";

global $f2, $label;

$BID             = $f2->bid();
$_SESSION['BID'] = $BID;

if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] != '' ) {

	$_SESSION['MDS_order_id'] = $_REQUEST['order_id'];

	if ( ( ! is_numeric( $_REQUEST['order_id'] ) ) && ( $_REQUEST['order_id'] != 'temp' ) ) {
		die();
	}
}
/*

Delete temporary order when the banner was changed.

*/
$reinit = false;
if ( ( isset( $_REQUEST['banner_change'] ) && $_REQUEST['banner_change'] != '' ) || ( isset( $_FILES['graphic'] ) && $_FILES['graphic']['tmp_name'] != '' ) ) {
	$reinit = true;
	delete_temp_order( session_id() );
}

// load order from php
// only allowed 1 new order per banner

$sql          = "SELECT * from " . MDS_DB_PREFIX . "orders where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' and status='new' and banner_id='$BID' ";
$order_result = mysqli_query( $GLOBALS['connection'], $sql );

// do a test, just in case.
if ( mysqli_num_rows( $order_result ) > 0 ) {
	$order_row = mysqli_fetch_array( $order_result );

	if ( $order_row['user_id'] != '' && $order_row['user_id'] != $_SESSION['MDS_ID'] ) {
		die( 'you do not own this order!' );
	}
}

if ( ( ! isset( $_SESSION["MDS_order_id"] ) || $_SESSION["MDS_order_id"] == '' ) || ( USE_AJAX == 'YES' ) ) {
	// guess the order id
	$_SESSION["MDS_order_id"] = isset( $order_row ) ? $order_row["order_id"] : get_current_order_id();
}

$banner_data = load_banner_constants( $BID );

// Update time stamp on temp order (if exists)

update_temp_order_timestamp();

$sql = "select block_id, status, user_id FROM " . MDS_DB_PREFIX . "blocks where banner_id='$BID' ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
while ( $row = mysqli_fetch_array( $result ) ) {
	$blocks[ $row['block_id'] ] = $row['status'];
}

require_once BASE_PATH . "/html/header.php";

?>

    <script type="text/javascript">

		var selectedBlocks = [];
		var selBlocksIndex = 0;

		//Begin -J- Edit: Custom functions for resize bug
		//Taken from http://www.quirksmode.org/js/findpos.html; but modified
		function findPosX(obj) {
			var curleft = 0;
			if (obj.offsetParent) {
				while (obj.offsetParent) {
					curleft += obj.offsetLeft;
					obj = obj.offsetParent;
				}
			} else if (obj.x)
				curleft += obj.x;
			return curleft;
		}

		//Taken from http://www.quirksmode.org/js/findpos.html; but modified
		function findPosY(obj) {
			var curtop = 0;
			if (obj.offsetParent) {
				while (obj.offsetParent) {
					curtop += obj.offsetTop;
					obj = obj.offsetParent;
				}
			} else if (obj.y)
				curtop += obj.y;
			return curtop;
		}

		var trip_count = 0;

		function check_selection(OffsetX, OffsetY) {

			// Trip to the database.
			var xmlhttp;

			if (typeof XMLHttpRequest !== "undefined") {
				xmlhttp = new XMLHttpRequest();
			}

			// Note: do not use &amp; for & here
			xmlhttp.open("GET", "check_selection.php?user_id=<?php echo $_SESSION['MDS_ID'];?>&map_x=" + OffsetX + "&map_y=" + OffsetY + "&block_id=" + get_clicked_block() + "&BID=<?php echo $BID . "&t=" . time(); ?>", true);

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
					if (xmlhttp.responseText.indexOf('E432') > -1) {
						alert(xmlhttp.responseText);
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
			submit1.value = "<?php echo $f2->nl2html( $label['reserving_pixels'] ); ?>";
			submit2.value = "<?php echo $f2->nl2html( $label['reserving_pixels'] ); ?>";
			submit1.style.cursor = 'wait';
			submit2.style.cursor = 'wait';

			let ajax_data = {
				user_id: <?php echo( isset( $_SESSION['MDS_ID'] ) && ! empty( $_SESSION['MDS_ID'] ) ? intval( $_SESSION['MDS_ID'] ) : '""' ); ?>,
				map_x: window.$block_pointer.map_x,
				map_y: window.$block_pointer.map_y,
				block_id: get_clicked_block(),
				BID: <?php echo $BID; ?>,
				t: <?php echo time(); ?>
			};

			$.ajax({
				method: 'POST',
				url: 'make_selection.php',
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
					submit1.value = "<?php echo $f2->rmnlraw( $label['advertiser_write_ad_button'] ); ?>";
					submit2.value = "<?php echo $f2->rmnlraw( $label['advertiser_write_ad_button'] ); ?>";
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
			var pos = {x: 0, y: 0};
			var curtop = 0;
			var curleft = 0;
			if (obj.offsetParent) {
				while (obj.offsetParent) {
					curtop += obj.offsetTop;
					curleft += obj.offsetLeft;
					obj = obj.offsetParent;
				}
			} else if (obj.y) {
				curtop += obj.y;
				curleft += obj.x;
			}
			pos.x = curleft;
			pos.y = curtop;
			return pos;
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

			if (e.offsetX != undefined) {
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
					//alert ('naan');
					return

				}
				if (OffsetX > tOffsetX) {

					if (window.pointer_width + tOffsetX > <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];?>) {
						// dont move left
					} else {
						window.$block_pointer.map_x = tOffsetX;
						window.$block_pointer.css('left', pos.x + tOffsetX + 'px');
					}

				}

				if (OffsetY > tOffsetY) {

					if (window.pointer_height + tOffsetY > <?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];?>) { // dont move down

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
				clicked_block = "0";// convert to string

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

		$sql = "SELECT block_info FROM " . MDS_DB_PREFIX . "temp_orders WHERE session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );

		if ( mysqli_num_rows( $result ) > 0 ) {
			$block_info = unserialize( $row['block_info'] );
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
    <style>
		#block_pointer {
			height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
			width: <?php echo $banner_data['BLK_WIDTH']; ?>px;
			padding: 0;
			margin: 0;
			line-height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
			font-size: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
		}
    </style>
<?php

$tmp_image_file = get_tmp_img_name();
$size           = [];
$reqsize        = [];
$pixel_count    = 0;
$block_size     = 0;
if ( isset( $_FILES['graphic'] ) && $_FILES['graphic']['tmp_name'] != '' ) {

	global $f2;

	$uploaddir = \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/";

	//$parts = split ('\.', $_FILES['graphic']['name']);
	$parts = $file_parts = pathinfo( $_FILES['graphic']['name'] );
	$ext   = $f2->filter( strtolower( $file_parts['extension'] ) );

	// CHECK THE EXTENSION TO MAKE SURE IT IS ALLOWED
	$ALLOWED_EXT = array( 'jpg', 'jpeg', 'gif', 'png' );

	$error = "";
	if ( ! in_array( $ext, $ALLOWED_EXT ) ) {
		$error              .= "<strong><font color='red'>" . $label['advertiser_file_type_not_supp'] . " ($ext)</font></strong><br />";
		$image_changed_flag = false;
	}
	if ( ! empty( $error ) ) {
		//echo "<font color='red'>Error, image upload failed</font>";
		echo $error;
	} else {

		// clean up is handled by the delete_temp_order($sid) function...

		delete_temp_order( session_id() );

		// delete temp_* files older than 24 hours
		$dh = opendir( $uploaddir );
		while ( ( $file = readdir( $dh ) ) !== false ) {

			$elapsed_time = 60 * 60 * 24; // 24 hours

			// delete old files
			$stat = stat( $uploaddir . $file );
			if ( $stat[9] < ( time() - $elapsed_time ) ) {
				if ( strpos( $file, 'tmp_' . md5( session_id() ) ) !== false ) {
					unlink( $uploaddir . $file );
				}
			}
		}

		$uploadfile = $uploaddir . "tmp_" . md5( session_id() ) . ".$ext";

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
			if ( MDS_RESIZE == 'YES' ) {
				$rescale = [];
				if ( ( $block_size > $banner_data['G_MAX_BLOCKS'] ) && ( $banner_data['G_MAX_BLOCKS'] > 0 ) ) {
					$rescale['x'] = min( $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $reqsize[0] );
					$rescale['y'] = min( $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $reqsize[0] );
				} else if ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {
					$rescale['x'] = min( $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'], $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'], $reqsize[0] );
					$rescale['y'] = min( $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_HEIGHT'], $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'], $reqsize[0] );
				}

				if ( isset( $rescale['x'] ) ) {
					// resize uploaded image
					if ( class_exists( 'Imagick' ) ) {
						$imagine = new Imagine\Imagick\Imagine();
					} else if ( function_exists( 'gd_info' ) ) {
						$imagine = new Imagine\Gd\Imagine();
					}
					$image  = $imagine->open( $tmp_image_file );
					$resize = new Imagine\Image\Box( $rescale['x'], $rescale['y'] );
					$image->resize( $resize );
					$image->save();
					$size[0]    = $rescale['x'];
					$size[1]    = $rescale['y'];
					$reqsize[0] = $rescale['x'];
					$reqsize[1] = $rescale['y'];

					// recount pixel count
					$pixel_count = $reqsize[0] * $reqsize[1];

					// recount final size
					$block_size = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
				}
			} else {

				if ( ( $block_size > $banner_data['G_MAX_BLOCKS'] ) && ( $banner_data['G_MAX_BLOCKS'] > 0 ) ) {

					$limit = $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'];

					$label['max_pixels_required'] = str_replace( '%MAX_PIXELS%', $limit, $label['max_pixels_required'] );
					$label['max_pixels_required'] = str_replace( '%COUNT%', $pixel_count, $label['max_pixels_required'] );
					echo "<strong style='color:red;'>";
					echo $label['max_pixels_required'];
					echo "</strong>";
					unlink( $tmp_image_file );
					unset( $tmp_image_file );
				} else if ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {

					$label['min_pixels_required'] = str_replace( '%COUNT%', $pixel_count, $label['min_pixels_required'] );
					$label['min_pixels_required'] = str_replace( '%MIN_PIXELS%', $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'], $label['min_pixels_required'] );
					echo "<strong style='color:red;'>";
					echo $label['min_pixels_required'];
					echo "</strong>";
					unlink( $tmp_image_file );
					unset( $tmp_image_file );
				}
			}
		} else {
			//echo "Possible file upload attack!\n";
			echo $label['pixel_upload_failed'];
		}
	}
}

// pointer.png

?>

    <span id="block_pointer" style='cursor: pointer;position:absolute;left:0px; top:0px;background:transparent; visibility:hidden '><img src="get_pointer_graphic.php?BID=<?php echo $BID; ?>" alt=""/></span>

    <p>
		<?php
		show_nav_status( 1 );
		?>
    </p>

    <p id="select_status"><?php echo( isset( $cannot_sel ) ? $cannot_sel : "" ); ?></p>

<?php

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners order by `name` ";
$res = mysqli_query( $GLOBALS['connection'], $sql );

if ( mysqli_num_rows( $res ) > 1 ) {
	?>
    <div class="fancy_heading" style="width:85%;"><?php echo $label['advertiser_sel_pixel_inv_head']; ?></div>
    <p>
		<?php

		$label['advertiser_sel_select_intro'] = str_replace( "%IMAGE_COUNT%", mysqli_num_rows( $res ), $label['advertiser_sel_select_intro'] );

		//echo $label['advertiser_sel_select_intro'];

		?>

    </p>
    <p>
		<?php display_banner_selecton_form( $BID, get_current_order_id(), $res ); ?>
    </p>
	<?php
}

if ( isset( $order_exists ) && $order_exists ) {
	echo "<p>" . $label['advertiser_order_not_confirmed'] . "</p>";
}
?>

<?php

$has_packages = banner_get_packages( $BID );
if ( $has_packages ) {
	display_package_options_table( $BID, '', false );
} else {
	display_price_table( $BID );
}

?>
    <div class="fancy_heading" style="width:85%;"><?php echo $label['pixel_uploaded_head']; ?></div>
    <p>
		<?php echo $label['upload_pix_description']; ?>
    </p>
    <p>
    <form method='post' action="<?php echo htmlentities( $_SERVER['PHP_SELF'] ); ?>" enctype="multipart/form-data">
        <p><strong><?php echo $label['upload_your_pix']; ?></strong></p>
        <input type='file' name='graphic' style=' font-size:14px;'/><br/>
        <input type='hidden' name='BID' value='<?php echo $BID; ?>'/>
        <input class="mds_upload_image" type='submit' value='<?php echo $f2->rmnl( $label['pix_upload_button'] ); ?>' style=' font-size:18px;'/>
    </form>

<?php

if ( isset( $tmp_image_file ) && ! empty( $tmp_image_file ) ) {

	?>

    <div class="fancy_heading" style="margin-top:20px;width:85%;"><?php echo $label['your_uploaded_pix']; ?></div>
    <p>
		<?php

		echo "<img class='mds_pointer_graphic' style=\"border:0px;\" src='get_pointer_graphic.php?BID=" . $BID . "' alt=\"\" /><br />";

		if ( empty( $size ) ) {
			$size = getimagesize( $tmp_image_file );
		}

		?><?php
		$label['upload_image_size'] = str_replace( "%WIDTH%", $size[0], $label['upload_image_size'] );
		$label['upload_image_size'] = str_replace( "%HEIGHT%", $size[1], $label['upload_image_size'] );

		echo $label['upload_image_size'];
		?>
        <br/>
		<?php

		if ( empty( $reqsize ) ) {
			$reqsize = get_required_size( $size[0], $size[1], $banner_data );
		}

		if ( empty( $pixel_count ) ) {
			$pixel_count = $reqsize[0] * $reqsize[1];
		}

		if ( empty( $block_size ) ) {
			$block_size = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
		}

		$label['advertiser_require_pur'] = str_replace( '%PIXEL_COUNT%', $pixel_count, $label['advertiser_require_pur'] );
		$label['advertiser_require_pur'] = str_replace( '%BLOCK_COUNT%', $block_size, $label['advertiser_require_pur'] );
		echo $label['advertiser_require_pur'];
		?>

    </p>
	<?php //echo $label['advertiser_select_instructions']; ?>

    <form method="post" action="order_pixels.php" name='pixel_form'>
        <p>
            <input type="button" class='big_button' <?php if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] != 'temp' ) {
				echo 'disabled';
			} ?> name='submit_button1' id='submit_button1' value='<?php echo $f2->rmnl( $label['advertiser_write_ad_button'] ); ?>' onclick="make_selection(event);">

        </p>

        <input type="hidden" value="1" name="select">
        <input type="hidden" value="<?php echo $BID; ?>" name="BID">

        <img style="cursor: pointer;" id="pixelimg" <?php if ( ( USE_AJAX == 'YES' ) || ( USE_AJAX == 'SIMPLE' ) ) { ?><?php } ?> type="image" name="map" value='Select Pixels.' width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>" height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>" src="show_selection.php?BID=<?php echo $BID; ?>&amp;gud=<?php echo time(); ?>" alt=""/>

        <input type="hidden" name="action" value="select">
    </form>
    <div style='background-color: #ffffff; border-color:#C0C0C0; border-style:solid;padding:10px'>
        <hr>

        <form method="post" action="write_ad.php" name="form1">
            <input type="hidden" name="package" value="">
            <input type="hidden" name="selected_pixels" value=''>
            <input type="hidden" name="order_id" value="<?php echo get_current_order_id(); ?>">
            <input type="hidden" value="<?php echo $BID; ?>" name="BID">
            <input type="submit" class='big_button' <?php if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] != 'temp' ) {
				echo 'disabled';
			} ?> name='submit_button2' id='submit_button2' value='<?php echo $f2->rmnl( $label['advertiser_write_ad_button'] ); ?>' onclick="make_selection(event);">
            <hr/>
        </form>
    </div>

    <script type="text/javascript">
		document.form1.selected_pixels.value = block_str;
		$(function () {
			window.pointer_width = <?php echo $reqsize[0]; ?>;
			window.pointer_height =  <?php echo $reqsize[1]; ?>;

			window.$block_pointer = $('#block_pointer');
			window.$pixelimg = $('#pixelimg');

			window.onresize = move_image_to_selection;
			window.onload = move_image_to_selection;
			move_image_to_selection();

			window.$block_pointer.on('mousemove', function (event) {
				show_pointer2(event);
			});

			window.$block_pointer.on('click', function (event) {
				do_block_click();
			});

			window.$pixelimg.on('mousemove', function (event) {
				show_pointer(event);
			});

			window.$pixelimg.on('load', function () {
				remove_ajax_loader();
			});

			add_ajax_loader(window.$pixelimg.parent());
		});
    </script>

	<?php
}

require_once BASE_PATH . "/html/footer.php";

?>