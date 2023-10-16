<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.3
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

defined( 'ABSPATH' ) or exit;

ini_set( 'max_execution_time', 10000 );

global $f2;

$BID = $f2->bid();

$banner_data = load_banner_constants( $BID );

$mds_site_url = get_site_url();

?>
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
			document.button_move_b.src = '<?php echo esc_url(MDS_CORE_URL . 'admin/images/move_b.gif'); ?>';
			if (bm_move_order_state) {
				$block_target = undefined;
				bm_move_order_state = false;
				document.button_move.src = '<?php echo esc_url(MDS_CORE_URL . 'admin/images/move.gif'); ?>';
			} else {
				bm_move_order_state = true;
				document.button_move.src = '<?php echo esc_url(MDS_CORE_URL . 'admin/images/move_down.gif'); ?>';
			}
		}

		if (button === 'MOVE_BLOCK') {
			bm_move_order_state = false;
			document.button_move.src = '<?php echo esc_url(MDS_CORE_URL . 'admin/images/move.gif'); ?>';

			if (bm_move_block_state) {
				$block_target = undefined;
				bm_move_block_state = false;
				document.button_move_b.src = '<?php echo esc_url(MDS_CORE_URL . 'admin/images/move_b.gif'); ?>';
			} else {
				bm_move_block_state = true
				document.button_move_b.src = '<?php echo esc_url(MDS_CORE_URL . 'admin/images/move_b_down.gif'); ?>';
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
			jQuery(pointer).find('img').attr('src', '<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>get-pointer-image2&BID=' + banner_id + '&block_id=' + cb);
		} else {
			jQuery(pointer).find('img').attr('src', '<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>get-pointer-image&BID=' + banner_id + '&block_id=' + cb);
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

		jQuery(pointer).find('img').attr('src', '<?php echo esc_url(MDS_CORE_URL . 'admin/images/pointer.png'); ?>');

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

		jQuery(jQuery(grid).add(pointer)).on('mousemove', function (event) {
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

			$block_target = jQuery(event.currentTarget);

			let offset = getOffset(event.originalEvent.pageX, event.originalEvent.pageY);
			if (offset == null) {
				return false;
			}

			if (bm_move_block_state || bm_move_order_state) {
				do_block_click(<?php echo $BID; ?>, offset)
			} else {
				window.parent.location = '<?php echo esc_url( admin_url( 'admin.php?page=mds-' ) ); ?>orders&user_id=' + $block_target.data('user_id') + '&BID=<?php echo $BID; ?>&order_id=' + $block_target.data('order_id');
			}

			return false;
		});

		jQuery('#block_pointer').on('click', function (event) {
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
<span id='block_pointer' style='font-size:1px;line-height:1px;cursor: pointer;position:absolute;left:0; top:0;background-color:#FFFFFF; visibility:hidden; '><img src='<?php echo esc_url(MDS_CORE_URL . 'admin/images/pointer.png'); ?>'></span>

<form method='post' name="move_form" action='<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>'>
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission"/>
    <input type="hidden" name="mds_dest" value="map-of-orders"/>
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

?>

<IMG name='button_move' SRC="<?php echo esc_url(MDS_CORE_URL . 'admin/images/move.gif'); ?>" WIDTH="24" HEIGHT="20" BORDER="0" ALT="Move Order" onclick='bm_state_change("MOVE_ORDER")'>
<IMG name='button_move_b' SRC="<?php echo esc_url(MDS_CORE_URL . 'admin/images/move_b.gif'); ?>" WIDTH="24" HEIGHT="20" BORDER="0" ALT="Move Block" onclick='bm_state_change("MOVE_BLOCK")'>
<br />
<map name="main" id="main">

	<?php

	global $wpdb;
	$transient_key = 'mds_blocks_banner_id_' . $BID;
	$blocks        = get_transient( $transient_key );

	if ( false === $blocks ) {
		$blocks = $wpdb->get_results( $wpdb->prepare( "SELECT block_id, order_id, user_id, status, x, y, url FROM " . MDS_DB_PREFIX . "blocks WHERE banner_id = %d", intval( $BID ) ) );
		set_transient( $transient_key, $blocks, 6 * HOUR_IN_SECONDS );
	}

	$current_time = strtotime( gmdate( 'r' ) );

	foreach ( $blocks as $row ) {
		$size = get_pixel_image_size( $row->order_id );

		$user_id     = intval( $row->user_id );
		$click_count = intval( get_user_meta( $user_id, MDS_PREFIX . 'click_count', true ) );
		$user_info   = get_userdata( $user_id );

		$order_transient_key = 'mds_order_row_order_id_' . $row->order_id;
		$order_row           = get_transient( $order_transient_key );

		if ( false === $order_row ) {
			$order_row = $wpdb->get_row( $wpdb->prepare( "SELECT order_id, published, date_published, days_expire FROM " . MDS_DB_PREFIX . "orders WHERE order_id = %d", intval( $row->order_id ) ) );
			set_transient( $order_transient_key, $order_row, 6 * HOUR_IN_SECONDS );
		}

		if ( isset( $order_row->days_expire ) && $order_row->days_expire > 0 ) {

			if ( $order_row->published != 'Y' ) {
				$time_start = strtotime( gmdate( 'r' ) );
			} else {
				$time_start = strtotime( $order_row->date_published . " GMT" );
			}

			$elapsed_time = $current_time - $time_start;
			$elapsed_days = floor( $elapsed_time / 60 / 60 / 24 );

			$exp_time = ( $order_row->days_expire * 24 * 60 * 60 );

			$exp_time_to_go = $exp_time - $elapsed_time;
			$exp_days_to_go = floor( $exp_time_to_go / 60 / 60 / 24 );

			$to_go = elapsedtime( $exp_time_to_go );

			$elapsed = elapsedtime( $elapsed_time );

			$days = "$elapsed passed<br> $to_go to go (" . $order_row->days_expire . ")";

			if ( $order_row->published != 'Y' ) {
				$days = "not published";
			} else if ( $exp_time_to_go <= 0 ) {
				$days .= 'Expired!';
			}
		} else {

			$days = "Never";
		}

		$alt_text = "<b>Customer:</b> " . ( $user_info->first_name ?? '' ) . " " . ( $user_info->last_name ?? '' ) . " <br><b>Username:</b> " . ( $user_info->user_login ?? '' ) . "<br><b>Email:</b> " . ( $user_info->user_email ?? '' ) . "<br><b>Order</b> # : " . ( $row->order_id ?? '' ) . " <br> <b>Block Status:</b> " . ( $row->status ?? '' ) . "<br><b>Published:</b> " . ( $order_row->published ?? '' ) . "<br><b>Approved:</b> " . ( $order_row->published ?? '' ) . "<br><b>Expires:</b> " . ( $days ?? '' ) . "<br><b>Click Count:</b> " . ( $click_count ?? '' ) . "<br><b>Block ID:</b> " . ( $row->block_id ?? '' ) . "<br><b>Co-ordinate:</b> x:" . ( $row->x ?? '' ) . ", y:" . ( $row->y ?? '' );

		?>

        <area
                href="<?php echo( $row->url ?? '' ); ?>"
                alt=""
                shape="RECT"
                coords="<?php echo $row->x ?? ''; ?>,<?php echo $row->y ?? ''; ?>,<?php echo ( $row->x ?? 0 ) + $banner_data['BLK_WIDTH']; ?>,<?php echo ( $row->y ?? 0 ) + $banner_data['BLK_HEIGHT']; ?>"
                target="_blank"
                data-user_id="<?php echo( $row->user_id ?? '' ); ?>"
                data-order_id="<?php echo $row->order_id ?? ''; ?>"
                data-order_width="<?php echo $size['x'] ?? ''; ?>"
                data-order_height="<?php echo $size['y'] ?? ''; ?>"
        >
	<?php } ?>

</map>
<?php
$mds_admin_ajax_nonce = wp_create_nonce( 'mds_admin_ajax_nonce' );
?>
<img draggable="false" unselectable="on" usemap="#main" id="pixelimg" src='<?php echo esc_url( admin_url( 'admin-ajax.php?action=mds_admin_ajax&mds_admin_ajax_nonce=' . $mds_admin_ajax_nonce ) ); ?>&amp;mds-ajax=show-map&amp;time=<?php echo time(); ?>&amp;BID=<?php echo $BID ?>' alt=""/>
