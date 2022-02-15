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

ini_set( 'max_execution_time', 10000 );
define( 'NO_HOUSE_KEEP', 'YES' );

require_once __DIR__ . "/../include/init.php";

require( 'admin_common.php' );

global $f2;

$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

if(WP_ENABLED == 'YES') {
	$mds_site_url = WP_URL;
} else {
	$mds_site_url = BASE_HTTP_PATH;
}

?><!DOCTYPE html>
<html lang="">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <title>Million Dollar Script Administration</title>
    <link rel="stylesheet" type="text/css" href="<?php echo $f2->value( BASE_HTTP_PATH ); ?>admin/css/admin.css?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/admin/css/admin.css" ); ?>">
    <script src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>vendor/components/jquery/jquery.min.js?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/vendor/components/jquery/jquery.min.js" ); ?>"></script>
    <script src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>vendor/components/jqueryui/jquery-ui.min.js?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/vendor/components/jqueryui/jquery-ui.min.js" ); ?>"></script>
    <script src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>js/third-party/viselect.cjs.js?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/js/third-party/viselect.cjs.js" ); ?>"></script>
    <link rel="stylesheet" href="<?php echo $f2->value( BASE_HTTP_PATH ); ?>vendor/components/jqueryui/themes/smoothness/jquery-ui.min.css?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/vendor/components/jqueryui/themes/smoothness/jquery-ui.min.css" ); ?>" type="text/css"/>
    <script src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>vendor/jquery-form/form/dist/jquery.form.min.js?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/vendor/jquery-form/form/dist/jquery.form.min.js" ); ?>"></script>

    <script>
		window.mds_data = {
			ajax: '<?php echo BASE_HTTP_PATH; ?>ajax.php',
			mds_site_url: '<?php echo $mds_site_url; ?>',
			BASE_HTTP_PATH: '<?php echo BASE_HTTP_PATH;?>'
		};
    </script>
    <script src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>admin/js/admin.js?ver=<?php echo filemtime( $f2->value( BASE_PATH ) . "/admin/js/admin.js" ); ?>"></script>
</head>
<body>
<script>

	let debug = false;

	// Initialize
	let grid;
	let pointer;
	let BLK_WIDTH = <?php echo $banner_data['BLK_WIDTH']; ?>;
	let BLK_HEIGHT = <?php echo $banner_data['BLK_HEIGHT']; ?>;
	let GRD_WIDTH = <?php echo $banner_data['G_WIDTH']; ?>;
	let GRD_HEIGHT = <?php echo $banner_data['G_HEIGHT']; ?>;

	let $block_target;

	window.onload = function () {
		grid = document.getElementById("pixelimg");
		pointer = document.getElementById('block_pointer');
		handle_mouse_events();
	};

	window.winWidth = 0;
	window.winHeight = 0;
	initFrameSize();

	function initFrameSize() {

		var myWidth = 0, myHeight = 0;
		if (typeof (window.innerWidth) === 'number') {
			//Non-IE
			myWidth = window.innerWidth;
			myHeight = window.innerHeight;
		} else if (document.documentElement &&
			(document.documentElement.clientWidth || document.documentElement.clientHeight)) {
			//IE 6+ in 'standards compliant mode'
			myWidth = document.documentElement.clientWidth;
			myHeight = document.documentElement.clientHeight;
		} else if (document.body && (document.body.clientWidth || document.body.clientHeight)) {
			//IE 4 compatible
			myWidth = document.body.clientWidth;
			myHeight = document.body.clientHeight;
		}
		window.winWidth = myWidth;
		window.winHeight = myHeight;

	}

	pos = 'right';

	h_padding = 10;
	v_padding = 10;

	/*

	Block moving functions

	*/

	var bm_move_order_state = false;
	var bm_move_block_state = false;

	var BID = <?php echo $BID; ?>;

	function bm_state_change(button) {

		is_moving = false;

		if (button === 'MOVE_ORDER') {
			bm_move_block_state = false;
			document.button_move_b.src = 'images/move_b.gif';
			if (bm_move_order_state) {
				$block_target = undefined;
				bm_move_order_state = false;
				document.button_move.src = 'images/move.gif';
			} else {
				bm_move_order_state = true;
				document.button_move.src = 'images/move_down.gif';
			}
		}

		if (button === 'MOVE_BLOCK') {
			bm_move_order_state = false;
			document.button_move.src = 'images/move.gif';

			if (bm_move_block_state) {
				$block_target = undefined;
				bm_move_block_state = false;
				document.button_move_b.src = 'images/move_b.gif';
			} else {
				bm_move_block_state = true
				document.button_move_b.src = 'images/move_b_down.gif';
			}
		}

		if ((bm_move_block_state === true) || (bm_move_order_state === true)) {
			document.body.style.cursor = 'move';
		} else {
			document.body.style.cursor = 'default';
		}
	}

	var is_moving = false;

	var cb_from;

	function do_block_click(banner_id, offset) {

		document.body.style.cursor = 'default';
		is_moving = true;
		let cb = get_clicked_block(offset);

		if (bm_move_order_state) {
			$(pointer).find('img').attr('src', 'get_pointer_image2.php?BID=' + banner_id + '&block_id=' + cb);
		} else {
			$(pointer).find('img').attr('src', 'get_pointer_image.php?BID=' + banner_id + '&block_id=' + cb);
		}
		cb_from = cb

		show_pointer(offset);
	}

	function put_pixels(offset) {

		is_moving = false;

		document.move_form.cb_to.value = get_clicked_block(offset);
		document.move_form.cb_from.value = cb_from;

		if (bm_move_order_state) {
			document.move_form.move_type.value = 'O'; // Move order
		} else {
			document.move_form.move_type.value = 'B'; // Move block

		}

		document.move_form.submit();

		$(pointer).find('img').attr('src', 'images/pointer.png');

	}

	function get_pointer_size() {
		let size = {};

		if (bm_move_order_state) {
			size.width = $block_target.data('order_width');
			size.height = $block_target.data('order_height');
		} else {
			size.width = BLK_WIDTH;
			size.height = BLK_HEIGHT;
		}

		return size;
	}

	function round_to_block(offset) {
		// drop 1/10 from the OffsetX and OffsetY, eg 612 becomes 610
		offset.x = Math.floor(offset.x / BLK_WIDTH) * BLK_WIDTH;
		offset.y = Math.floor(offset.y / BLK_HEIGHT) * BLK_HEIGHT;

		return offset;
	}

	function show_pointer(offset) {
		pointer.style.visibility = 'visible';
		pointer.style.display = 'block';

		offset = round_to_block(offset);

		let x = offset.x + grid.offsetLeft;
		let y = offset.y + grid.offsetTop;

		pointer.style.left = x + "px";
		pointer.style.top = y + "px";

		pointer.map_x = x;
		pointer.map_y = y;

		let size = get_pointer_size();
		pointer.style.width = size.width + "px";
		pointer.style.height = size.height + "px";
	}

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

	function getOffset(x, y) {
		if (grid == null) {
			// grid may not be loaded yet
			return null;
		}

		let pos = getObjCoords(grid);

		let offset = {};
		let scrollLeft = 0;
		let scrollTop = 0;

		offset.x = x - pos.x + scrollLeft;
		offset.y = y - pos.y + scrollTop;

		offset = round_to_block(offset);

		return offset;
	}

	function get_clicked_block(offset) {
		let X = (offset.x / BLK_WIDTH);
		let Y = (offset.y / BLK_HEIGHT) * GRD_WIDTH;

		return Math.round(X + Y);
	}

	function center_block(coords) {
		let size = get_pointer_size();
		coords.x -= (size.width / 2) - (BLK_WIDTH / 2);
		coords.y -= (size.height / 2) - (BLK_HEIGHT / 2);
		return coords;
	}

	function handle_mouse_events() {

		$($(grid).add(pointer)).on('mousemove', function (event) {
			if ($block_target === undefined || (!bm_move_block_state && !bm_move_order_state)) {
				return
			}

			let coords = center_block({
				x: event.originalEvent.pageX,
				y: event.originalEvent.pageY
			});
			let offset = getOffset(coords.x, coords.y);
			if (offset == null) {
				return false;
			}

			show_pointer(offset);
		});

		let areas = jQuery('area');

		areas.on('click', function (event) {
			event.preventDefault();

			$block_target = $(event.currentTarget);

			let offset = getOffset(event.originalEvent.pageX, event.originalEvent.pageY);
			if (offset == null) {
				return false;
			}

			if (bm_move_block_state || bm_move_order_state) {
				do_block_click(<?php echo $BID; ?>, offset)
			} else {
				window.parent.location = '<?php echo $f2->value( BASE_HTTP_PATH ); ?>admin/#orders.php?user_id=' + $block_target.data('user_id') + '&BID=<?php echo $BID; ?>&order_id=' + $block_target.data('order_id');
			}

			return false;
		});

		$('#block_pointer').on('click', function (event) {
			event.preventDefault();

			let coords = center_block({
				x: event.originalEvent.pageX,
				y: event.originalEvent.pageY
			});
			let offset = getOffset(coords.x, coords.y);
			if (offset == null) {
				return false;
			}

			put_pixels(offset);

			return false;
		});
	}

</script>
<span id='block_pointer' style='font-size:1px;line-height:1px;cursor: pointer;position:absolute;left:0; top:0;background-color:#FFFFFF; visibility:hidden; '><img src='images/pointer.png'></span>

<form method='post' name="move_form" action='map_iframe.php'>
    <input name='cb_from' type="hidden" value="">
    <input name='cb_to' type="hidden" value="">
    <input name='move_type' type="hidden" value="B">
    <input name='BID' type="hidden" value="<?php echo $BID; ?>">
</form>

<?php

if ( isset( $_REQUEST['move_type'] ) && ! empty( $_REQUEST['move_type'] ) ) {

	if ( $_REQUEST['move_type'] == 'B' ) {
		// move block
		move_block( $_REQUEST['cb_from'], $_REQUEST['cb_to'], $BID );
	} else if ( $_REQUEST['move_type'] == 'O' ) {
		// move order
		move_order( $_REQUEST['cb_from'], $_REQUEST['cb_to'], $BID );
	}
}

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE  banner_id=" . intval( $BID );
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

?>

<IMG name='button_move' SRC="images/move.gif" WIDTH="24" HEIGHT="20" BORDER="0" ALT="Move Order" onclick='bm_state_change("MOVE_ORDER")'>
<IMG name='button_move_b' SRC="images/move_b.gif" WIDTH="24" HEIGHT="20" BORDER="0" ALT="Move Block" onclick='bm_state_change("MOVE_BLOCK")'>
<map name="main" id="main">

	<?php

    // TODO: optimize this better
	while ( $row = mysqli_fetch_array( $result ) ) {
		$size = get_pixel_image_size( $row['order_id'] );

		$sql = "select * from " . MDS_DB_PREFIX . "users where ID=" . intval( $row['user_id'] );
		$res = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$user_row = mysqli_fetch_array( $res );

		$sql = "select * from " . MDS_DB_PREFIX . "orders where order_id=" . intval( $row['order_id'] );
		$res = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$order_row = mysqli_fetch_array( $res );

		if ( $order_row['days_expire'] > 0 ) {

			if ( $order_row['published'] != 'Y' ) {
				$time_start = strtotime( gmdate( 'r' ) );
			} else {
				$time_start = strtotime( $order_row['date_published'] . " GMT" );
			}

			$elapsed_time = strtotime( gmdate( 'r' ) ) - $time_start;
			$elapsed_days = floor( $elapsed_time / 60 / 60 / 24 );

			$exp_time = ( $order_row['days_expire'] * 24 * 60 * 60 );

			$exp_time_to_go = $exp_time - $elapsed_time;
			$exp_days_to_go = floor( $exp_time_to_go / 60 / 60 / 24 );

			$to_go = elapsedtime( $exp_time_to_go );

			$elapsed = elapsedtime( $elapsed_time );

			$days = "$elapsed passed<br> $to_go to go (" . $order_row['days_expire'] . ")";

			if ( $order_row['published'] != 'Y' ) {
				$days = "not published";
			} else if ( $exp_time_to_go <= 0 ) {
				$days .= 'Expired!';
			}
		} else {

			$days = "Never";
		}

		$alt_text = "<b>Customer:</b> " . $user_row['FirstName'] . " " . $user_row['LastName'] . " <br><b>Username:</b> " . $user_row['Username'] . "<br><b>Email:</b> " . $user_row['Email'] . "<br><b>Order</b> # : " . $row['order_id'] . " <br> <b>Block Status:</b> " . $row['status'] . "<br><b>Published:</b> " . $order_row['published'] . "<br><b>Approved:</b> " . $order_row['published'] . "<br><b>Expires:</b> " . $days . "<br><b>Click Count:</b> " . $row['click_count'] . "<br><b>Block ID:</b> " . $row['block_id'] . "<br><b>Co-ordinate:</b> x:" . $row['x'] . ", y:" . $row['y'] . "";

		?>

        <area
                href="<?php echo( $row['url'] ); ?>"
                alt=""
                shape="RECT"
                coords="<?php echo $row['x']; ?>,<?php echo $row['y']; ?>,<?php echo $row['x'] + $banner_data['BLK_WIDTH']; ?>,<?php echo $row['y'] + $banner_data['BLK_HEIGHT']; ?>"
                target="_blank"
                data-user_id="<?php echo( $row['user_id'] ); ?>"
                data-order_id="<?php echo $row['order_id']; ?>"
                data-order_width="<?php echo $size['x']; ?>"
                data-order_height="<?php echo $size['y']; ?>"
        >
	<?php } ?>

</map>

<img draggable="false" unselectable="on" usemap="#main" id="pixelimg" src='show_map.php?BID=<?php echo $BID; ?>&time=<?php echo time(); ?>' alt=""/>
</body>
</html>