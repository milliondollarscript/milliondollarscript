<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.0
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

use MillionDollarScript\Classes\Language;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

require_once MDS_CORE_PATH . "html/header.php";

// check if user has permission to access this page
if ( ! mds_check_permission( "mds_order_pixels" ) ) {
	Language::out( "No Access" );
	require_once MDS_CORE_PATH . "html/footer.php";
	exit;
}

global $f2;
$BID = $f2->bid();

if ( ! is_numeric( $BID ) ) {
	die();
}

$banner_data = load_banner_constants( $BID );

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id='" . get_current_user_id() . "' AND status='new'";
$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
$order_row = mysqli_fetch_array( $order_result );

if ( $order_row != null ) {

	// do a test, just in case.
	if ( ( $order_row['user_id'] != '' ) && $order_row['user_id'] != get_current_user_id() ) {
		die( 'you do not own this order!' );
	}

	// only 1 new order allowed per user per grid
	if ( isset( $_REQUEST['banner_change'] ) && ! empty( $_REQUEST['banner_change'] ) ) {
		// clear the current order
		delete_current_order_id();

		// delete the old order and associated blocks
		$sql = "delete from " . MDS_DB_PREFIX . "orders where order_id=" . intval( $order_row['order_id'] );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$sql = "delete from " . MDS_DB_PREFIX . "blocks where order_id=" . intval( $order_row['order_id'] );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	} else if ( ( empty( get_current_order_id() ) ) || ( USE_AJAX == 'YES' ) ) {
		// save the order id to session
		set_current_order_id( $order_row['order_id'] );
	}
}

$tmp_image_file = get_tmp_img_name();
if ( file_exists( $tmp_image_file ) ) {
	unlink( $tmp_image_file );
}

// load any existing blocks for this order
$order_row_blocks = ! empty( $order_row['blocks'] ) ? $order_row['blocks'] : '';
$block_ids        = $order_row_blocks !== '' ? array_map( 'intval', explode( ',', $order_row_blocks ) ) : [];
$block_str        = $order_row_blocks !== '' ? implode( ',', $block_ids ) : "";
$order_blocks     = array_map( function ( $block_id ) use ( $BID ) {
	$pos = get_block_position( $block_id, $BID );

	return [
		'block_id' => $block_id,
		'x'        => $pos['x'],
		'y'        => $pos['y'],
	];
}, $block_ids );

?>
    <script>
		const select = {
			NONCE: '<?php echo wp_create_nonce( 'mds-select' ); ?>',
			UPDATE_ORDER: '<?php echo Utility::get_page_url( 'update-order' ); ?>',
			USE_AJAX: '<?php echo USE_AJAX; ?>',
			block_str: '<?php echo $block_str; ?>',
			grid_width: parseInt('<?php echo $banner_data['G_WIDTH']; ?>', 10),
			grid_height: parseInt('<?php echo $banner_data['G_HEIGHT']; ?>', 10),
			BLK_WIDTH: parseInt('<?php echo $banner_data['BLK_WIDTH']; ?>', 10),
			BLK_HEIGHT: parseInt('<?php echo $banner_data['BLK_HEIGHT']; ?>', 10),
			G_PRICE: parseFloat('<?php echo $banner_data['G_PRICE']; ?>'),
			blocks: JSON.parse('<?php echo json_encode( $order_blocks ); ?>'),
			user_id: parseInt('<?php echo get_current_user_id(); ?>', 10),
			BID: parseInt('<?php echo $BID; ?>', 10),
			time: '<?php echo time(); ?>',
			advertiser_max_order: '<?php echo esc_js( Language::get( 'Cannot place pixels on order. You have reached the order limit for this grid. Please review your Order History.' ) ); ?>',
			not_adjacent: '<?php echo esc_js( Language::get( 'You must select a block adjacent to another one.' ) ); ?>',
			no_blocks_selected: '<?php echo esc_js( Language::get( 'You have no blocks selected.' ) ); ?>',
			MDS_CORE_URL: '<?php echo MDS_CORE_URL; ?>',
			INVERT_PIXELS: '<?php echo INVERT_PIXELS; ?>',
			WAIT: '<?php echo esc_js( Language::get( 'Please Wait! Reserving Pixels...' ) ); ?>'
		}
    </script>

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
			z-index: 10001;
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
			z-index: 10000;
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

<?php

Language::out( '<p>1. <b>Select Your Pixels</b> -> 2. Image Upload -> 3. Write Your Ad -> 4. Confirm Order -> 5. Payment</p>' );

$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners order by `name`";
$res = mysqli_query( $GLOBALS['connection'], $sql );

if ( mysqli_num_rows( $res ) > 1 ) {
	?>
    <div class="fancy-heading"><?php Language::out( 'Available Grids' ); ?></div>
    <div class="mds-select-intro">
		<?php
		Language::out_replace(
			'%GRID_COUNT%',
			mysqli_num_rows( $res ),
			'There are <b>%GRID_COUNT%</b> different images served by this website! Select the image which you would like to publish your pixels to:'
		);
		?>
    </div>
	<?php

	display_banner_selecton_form( $BID, $order_row['order_id'] ?? 0, $res, 'select' );
	//
	// if ( ! isset( $_REQUEST['banner_change'] ) && ( ! isset( $_REQUEST['jEditOrder'] ) || $_REQUEST['jEditOrder'] !== 'true' ) ) {
	// 	// If multiple banners only display the selection form first
	// 	require_once MDS_CORE_PATH . "html/footer.php";
	//
	// 	return;
	// }
}

if ( isset( $order_exists ) && $order_exists ) {
	Language::out_replace(
		'%HISTORY_URL%',
		Utility::get_page_url( 'history' ),
		'Note: You have placed some pixels on order, but it was not confirmed (green blocks). <a href="%HISTORY_URL%">View Order History</a>'
	);
}

$has_packages = banner_get_packages( $BID );
if ( $has_packages ) {
	display_package_options_table( $BID, '', true );
} else {
	display_price_table( $BID );
}
?>
    <div class="fancy-heading"><?php Language::out( 'Select Pixels' ); ?></div>
    <div class="mds-select-instructions">
		<?php
		Language::out_replace(
			[
				'%PIXEL_C%',
				'%BLK_HEIGHT%',
				'%BLK_WIDTH%',
			],
			[
				$banner_data['BLK_HEIGHT'] * $banner_data['BLK_WIDTH'],
				$banner_data['BLK_HEIGHT'],
				$banner_data['BLK_WIDTH'],
			],
			'<h3>Instructions:</h3>
<p>
Each square represents a block of %PIXEL_C% pixels (%BLK_WIDTH%x%BLK_HEIGHT%). Select the blocks that you want, and then press the \'Buy Pixels Now\' button.<br /><br />
- Click on a White block to select it.<br />
- Green blocks are selected by you.<br />
- Click on a Green block to un-select.<br />
- Red blocks have been sold.<br />
- Yellow blocks are reserved by someone else.<br />
- Orange have been ordered by you.<br />
- Click the \'Buy Pixels Now\' button below when you are finished.<br />
</p>'
		);
		?>
    </div>

<?php
if ( ! isset( $_REQUEST['sel_mode'] ) ) {
	$_REQUEST['sel_mode'] = 'sel1';
}
?>
    <form method="post" id="pixel_form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name='pixel_form'>
		<?php wp_nonce_field( 'mds-form' ); ?>
        <input type="hidden" name="action" value="mds_form_submission">
        <input type="hidden" name="mds_dest" value="upload">

        <input type="hidden" value="1" name="select">
        <input type="hidden" name="package" value="">
        <input type="hidden" name="selected_pixels" value='<?php echo $block_str; ?>'>
        <input type="hidden" name="order_id" value="<?php echo( isset( $order_row['order_id'] ) ? intval( $order_row['order_id'] ) : '' ); ?>">
        <input type="hidden" name="BID" value="<?php echo intval( $BID ); ?>">

        <div class="mds-select-wrapper">
            <div class="mds-select-prompt">
				<?php
				// TODO: Disable extra selection modes based on the number of blocks allowed in the selected grid
				// TODO: Add support for custom selection modes
				if ( defined( 'BLOCK_SELECTION_MODE' ) && BLOCK_SELECTION_MODE == 'YES' ) {
				Language::out( 'Selection Mode:' ); ?>
            </div>
            <div class="mds-select-radios">
                <div class="mds-select-radio">
                    <input type="radio" id='sel1' name='sel_mode' value='sel1' <?php if ( ( $_REQUEST['sel_mode'] == '' ) || ( $_REQUEST['sel_mode'] == 'sel1' ) ) {
						echo " checked ";
					} ?> />
                    <label for='sel1'>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-single">
                            <rect x="5" y="5" width="14" height="14"/>
                        </svg>
						<?php Language::out( '1 block at a time' ); ?>
                    </label>
                </div>
                <div class="mds-select-radio">
                    <input type="radio" name='sel_mode' id='sel4' value='sel4' <?php if ( ( $_REQUEST['sel_mode'] == 'sel4' ) ) {
						echo " checked ";
					} ?> />
                    <label for="sel4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-square">
                            <rect x="4.5" y="4.5" width="7" height="7"/>
                            <rect x="12.5" y="4.5" width="7" height="7"/>
                            <rect x="4.5" y="12.5" width="7" height="7"/>
                            <rect x="12.5" y="12.5" width="7" height="7"/>
                        </svg>
						<?php Language::out( '4 blocks at a time' ); ?>
                    </label>
                </div>
                <div class="mds-select-radio">
                    <input type="radio" name='sel_mode' id='sel6' value='sel6' <?php if ( ( $_REQUEST['sel_mode'] == 'sel6' ) ) {
						echo " checked ";
					} ?> />
                    <label for="sel6">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 26 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-rectangle">
                            <rect x="2" y="4" width="7" height="7"/>
                            <rect x="9" y="4" width="7" height="7"/>
                            <rect x="16" y="4" width="7" height="7"/>
                            <rect x="2" y="11" width="7" height="7"/>
                            <rect x="9" y="11" width="7" height="7"/>
                            <rect x="16" y="11" width="7" height="7"/>
                        </svg>
						<?php Language::out( '6 blocks at a time' ); ?>
                    </label>
                </div>
				<?php
				} else {
					?>
                    <input type="hidden" id='sel1' name='sel_mode' value='sel1' checked="checked"/>
					<?php
				}
				?>
                <div class="mds-select-radio">
					<?php
					$checked = '';
					if ( ( $_REQUEST['sel_mode'] == 'erase' ) ) {
						$checked = " checked ";
					}
					?>
                    <input type="radio" name='sel_mode' id='erase' value='erase' <?php echo $checked; ?> />
                    <label for="erase">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-erase">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
						<?php Language::out( 'Erase' ); ?>
                    </label>
                </div>
            </div>
        </div>
        <div id="submit-buttons">
            <input type="button" name='submit_button1' id='submit_button1' value='<?php echo esc_attr( Language::get( 'Buy Pixels Now' ) ); ?>' onclick='formSubmit(event)'>
            <input type="button" name='reset_button' id='reset_button' value='<?php echo esc_attr( Language::get( 'Reset' ) ); ?>' onclick='reset_pixels()'>
        </div>

        <div id="pixel_container">
            <div id="blocks"></div>
            <span id='block_pointer'></span>
            <div class="mds-pixel-wrapper">
                <img id="pixelimg" draggable="false" unselectable="on" src="<?php echo esc_url( Utility::get_page_url( 'show-selection' ) ); ?>?BID=<?php echo $BID; ?>&amp;gud=<?php echo time(); ?>" alt=""/>
            </div>
        </div>

    </form>

<?php require_once MDS_CORE_PATH . "html/footer.php"; ?>