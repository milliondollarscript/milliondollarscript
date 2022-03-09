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
if ( WP_ENABLED == "YES" && WP_USERS_ENABLED == "YES" ) {
	mds_wp_login_check();
}

require_once BASE_PATH . "/html/header.php";
process_login();

// check if user has permission to access this page
if ( ! mds_check_permission( "mds_order_pixels" ) ) {
	_e( "No Access", 'milliondollarscript' );
	require_once BASE_PATH . "/html/footer.php";
	exit;
}

global $f2;
$BID = $f2->bid();

if ( ! is_numeric( $BID ) ) {
	die();
}

$banner_data = load_banner_constants( $BID );

$sql = "SELECT * from " . MDS_DB_PREFIX . "orders where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' and status='new' and banner_id='$BID' ";
$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
$order_row = mysqli_fetch_array( $order_result );

if ( $order_row != null ) {

	// do a test, just in case.
	if ( ( $order_row['user_id'] != '' ) && $order_row['user_id'] != $_SESSION['MDS_ID'] ) {
		die( 'you do not own this order!' );
	}

	// only 1 new order allowed per user per grid
	if ( isset( $_REQUEST['banner_change'] ) && ! empty( $_REQUEST['banner_change'] ) ) {
		// clear the current order
		$_SESSION['MDS_order_id'] = '';

		// delete the old order and associated blocks
		$sql = "delete from " . MDS_DB_PREFIX . "orders where order_id=" . intval( $order_row['order_id'] );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$sql = "delete from " . MDS_DB_PREFIX . "blocks where order_id=" . intval( $order_row['order_id'] );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	} else if ( ( $_SESSION['MDS_order_id'] == '' ) || ( USE_AJAX == 'YES' ) ) {
		// save the order id to session
		$_SESSION['MDS_order_id'] = $order_row['order_id'];
	}
}

$cannot_sel = "";
if ( isset( $_REQUEST['select'] ) && ! empty( $_REQUEST['select'] ) ) {
	if ( defined( 'BLOCK_SELECTION_MODE' ) && BLOCK_SELECTION_MODE == 'YES' ) {
		if ( $_REQUEST['sel_mode'] == 'sel4' ) {

			$max_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
			$max_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

			$cannot_sel = select_block( $_REQUEST['map_x'], $_REQUEST['map_y'] );
			if ( ( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'] <= $max_x ) ) {
				$cannot_sel = select_block( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'], $_REQUEST['map_y'] );
			}
			if ( ( $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] <= $max_y ) ) {
				$cannot_sel = select_block( $_REQUEST['map_x'], $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] );
			}
			if ( ( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'] <= $max_x ) && ( $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] <= $max_y ) ) {
				$cannot_sel = select_block( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'], $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] );
			}
		} else if ( $_REQUEST['sel_mode'] == 'sel6' ) {

			$max_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
			$max_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

			$cannot_sel = select_block( $_REQUEST['map_x'], $_REQUEST['map_y'] );

			if ( ( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'] <= $max_x ) ) {
				$cannot_sel = select_block( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'], $_REQUEST['map_y'] );
			}
			if ( ( $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] <= $max_y ) ) {
				$cannot_sel = select_block( $_REQUEST['map_x'], $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] );
			}
			if ( ( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'] <= $max_x ) && ( $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] <= $max_y ) ) {
				$cannot_sel = select_block( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'], $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] );
			}

			if ( ( $_REQUEST['map_x'] + ( $banner_data['BLK_WIDTH'] * 2 ) <= $max_x ) ) {
				$cannot_sel = select_block( $_REQUEST['map_x'] + ( $banner_data['BLK_WIDTH'] * 2 ), $_REQUEST['map_y'] );
			}

			if ( ( $_REQUEST['map_x'] + ( $banner_data['BLK_WIDTH'] * 2 ) <= $max_x ) && ( $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] <= $max_y ) ) {
				$cannot_sel = select_block( $_REQUEST['map_x'] + ( $banner_data['BLK_WIDTH'] * 2 ), $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] );
			}
		} else {
			$cannot_sel = select_block( $_REQUEST['map_x'], $_REQUEST['map_y'] );
		}
	} else {
		$cannot_sel = select_block( $_REQUEST['map_x'], $_REQUEST['map_y'] );
	}
}

$block_str = ( ! isset( $order_row['blocks'] ) || $order_row['blocks'] == "" ) ? "-1" : $order_row['blocks'];

// load any existing blocks for this order
$order_blocks = array();

if ( isset( $order_row['blocks'] ) && $order_row['blocks'] != "" ) {

	$block_ids = explode( ',', $order_row['blocks'] );
	foreach ( $block_ids as $block_id ) {
		$pos            = get_block_position( $block_id, $BID );
		$order_blocks[] = array(
			'block_id' => $block_id,
			'x'        => $pos['x'],
			'y'        => $pos['y'],
		);
	}
}

?>

    <script>
		const select = {
			USE_AJAX: '<?php echo USE_AJAX; ?>',
			block_str: '<?php echo $block_str; ?>',
			grid_width: parseInt('<?php echo $banner_data['G_WIDTH']; ?>'),
			grid_height: parseInt('<?php echo $banner_data['G_HEIGHT']; ?>'),
			BLK_WIDTH: parseInt('<?php echo $banner_data['BLK_WIDTH']; ?>'),
			BLK_HEIGHT: parseInt('<?php echo $banner_data['BLK_HEIGHT']; ?>'),
			G_PRICE: parseFloat('<?php echo $banner_data['G_PRICE']; ?>'),
			blocks: JSON.parse('<?php echo json_encode( $order_blocks ); ?>'),
			user_id: parseInt('<?php echo $_SESSION['MDS_ID']; ?>'),
			BID: parseInt('<?php echo $BID; ?>'),
			time: '<?php echo time(); ?>',
			advertiser_max_order: '<?php echo js_out_prep( $label['advertiser_max_order'] ); ?>',
			not_adjacent: '<?php echo js_out_prep( $label['not_adjacent'] ); ?>',
			no_blocks_selected: '<?php echo js_out_prep( $label['no_blocks_selected'] ); ?>',
			BASE_HTTP_PATH: '<?php echo BASE_HTTP_PATH; ?>'
		}
    </script>
    <script src="../js/select.js"></script>

    <style>
		#block_pointer {
			padding: 0;
			margin: 0;
			cursor: pointer;
			position: absolute;
			left: 0;
			top: 0;
			background-color: transparent;
			visibility: hidden;
			height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
			width: <?php echo $banner_data['BLK_WIDTH']; ?>px;
			line-height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
			font-size: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
			user-select: none;
			-webkit-user-select: none;
			-webkit-touch-callout: none;
			-moz-user-select: none;
			box-shadow: inset 0 0 0 1px #000;
		}

		span[id^='block'] {
			padding: 0;
			margin: 0;
			cursor: pointer;
			position: absolute;
			background-color: #FFFFFF;
			width: <?php echo $banner_data['BLK_WIDTH']; ?>px;
			height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
			line-height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
			font-size: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
		}

		#pixel_container {
			width: <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>px;
			position: relative;
			margin: 0 auto;
			max-width: 100%;
		}

		#pixelimg {
			width: <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>px;
			height: auto;
			border: none;
			outline: none;
			cursor: pointer;
			user-select: none;
			-moz-user-select: none;
			-webkit-tap-highlight-color: transparent !important;
			margin: 0 auto;
			float: none;
			display: block;
			background: transparent;
			max-width: 100%;
		}
    </style>

    <p>
		<?php echo $label['advertiser_sel_trail']; ?>
    </p>

    <p id="select_status"><?php echo $cannot_sel; ?></p>

<?php

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners order by `name`";
$res = mysqli_query( $GLOBALS['connection'], $sql );

if ( mysqli_num_rows( $res ) > 1 ) {
	?>
    <div class="fancy_heading" style="width:85%;"><?php echo $label['advertiser_sel_pixel_inv_head']; ?></div>
    <p>
		<?php
		$label['advertiser_sel_select_intro'] = str_replace( "%IMAGE_COUNT%", mysqli_num_rows( $res ), $label['advertiser_sel_select_intro'] );
		echo $label['advertiser_sel_select_intro'];
		?>
    </p>
	<?php display_banner_selecton_form( $BID, $order_row['order_id'], $res ); ?>
	<?php
}

if ( isset( $order_exists ) && $order_exists ) {
	echo "<p>" . $label['advertiser_order_not_confirmed'] . "</p>";
}

$has_packages = banner_get_packages( $BID );
if ( $has_packages ) {
	display_package_options_table( $BID, '', false );
} else {
	display_price_table( $BID );
}
?>
    <div class="fancy_heading" style="width:85%;"><?php echo $label['advertiser_select_pixels_head']; ?></div>
<?php
$label['advertiser_select_instructions2'] = str_replace( '%PIXEL_C%', $banner_data['BLK_HEIGHT'] * $banner_data['BLK_WIDTH'], $label['advertiser_select_instructions2'] );
$label['advertiser_select_instructions2'] = str_replace( '%BLK_HEIGHT%', $banner_data['BLK_HEIGHT'], $label['advertiser_select_instructions2'] );
$label['advertiser_select_instructions2'] = str_replace( '%BLK_WIDTH%', $banner_data['BLK_WIDTH'], $label['advertiser_select_instructions2'] );
echo $label['advertiser_select_instructions2'];

if ( ! isset( $_REQUEST['sel_mode'] ) ) {
	$_REQUEST['sel_mode'] = 'sel1';
}
?>

    <form method="post" action="select.php" name='pixel_form'>
        <p><b><?php
				if ( defined( 'BLOCK_SELECTION_MODE' ) && BLOCK_SELECTION_MODE == 'YES' ) {
				echo $label['selection_mode']; ?></b> <input type="radio" id='sel1' name='sel_mode' value='sel1' <?php if ( ( $_REQUEST['sel_mode'] == '' ) || ( $_REQUEST['sel_mode'] == 'sel1' ) ) {
				echo " checked ";
			} ?> > <label for='sel1'><?php echo $label['select1']; ?></label> | <input type="radio" name='sel_mode' id='sel4' value='sel4' <?php if ( ( $_REQUEST['sel_mode'] == 'sel4' ) ) {
				echo " checked ";
			} ?> > <label for="sel4"><?php echo $label['select4']; ?></label> | <input type="radio" name='sel_mode' id='sel6' value='sel6' <?php if ( ( $_REQUEST['sel_mode'] == 'sel6' ) ) {
				echo " checked ";
			} ?> > <label for="sel6"><?php echo $label['select6']; ?></label>
			<?php
			} else {
				?>
                <input type="hidden" id='sel1' name='sel_mode' value='sel1' checked="checked"/>
				<?php
			}
			?>
        </p>
        <p>
            <input type="button" name='submit_button1' id='submit_button1' value='<?php echo htmlspecialchars( $label['advertiser_buy_button'] ); ?>' onclick='form1Submit(event)'>
            <input type="button" name='reset_button' id='reset_button' value='<?php echo htmlspecialchars( $label['advertiser_reset_button'] ); ?>' onclick='reset_pixels()'>
        </p>

        <input type="hidden" value="1" name="select">
        <input type="hidden" value="<?php echo $BID; ?>" name="BID">

        <div id="pixel_container">
            <div id="blocks"></div>
            <span id='block_pointer'></span>
            <img id="pixelimg" draggable="false" unselectable="on" src="show_selection.php?BID=<?php echo $BID; ?>&amp;gud=<?php echo time(); ?>" alt=""/>
        </div>

        <input type="hidden" name="action" value="select">
    </form>
    <div style='display:none;background-color: #ffffff; border-color:#C0C0C0; border-style:solid;padding:10px'>
        <hr>

        <form method="post" action="order.php" id="form1" name="form1">
            <input type="hidden" name="package" value="">
            <input type="hidden" name="selected_pixels" value=''>
            <input type="hidden" name="order_id" value="<?php echo $order_row['order_id'] ?? ''; ?>">
            <input type="hidden" value="<?php echo $BID; ?>" name="BID">
            <input type="submit" name='submit_button2' id='submit_button2' value='<?php echo htmlspecialchars( $label['advertiser_buy_button'] ); ?>'>
            <hr>
        </form>

    </div>

<?php require_once BASE_PATH . "/html/footer.php"; ?>