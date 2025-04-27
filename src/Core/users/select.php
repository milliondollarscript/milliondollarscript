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

use MillionDollarScript\Classes\Forms\Forms;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Blocks;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Utility;

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

$USE_AJAX = \MillionDollarScript\Classes\Data\Config::get( 'USE_AJAX' );

$order_id = Orders::get_current_order_id();

if ( ! empty( $order_id ) ) {
	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id='" . get_current_user_id() . "' AND order_id='" . intval( $order_id ) . "'";
} else {
	$sql = "SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id='" . get_current_user_id() . "' AND status='new'";
}
$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
$order_row = mysqli_fetch_array( $order_result );

if ( $order_row != null ) {

	// do a test, just in case.
	if ( ( $order_row['user_id'] != '' ) && $order_row['user_id'] != get_current_user_id() ) {
		die( 'you do not own this order!' );
	}

	// only 1 new order allowed per user per grid
	if ( ! empty( $_REQUEST['banner_change'] ) ) {
		// Reset order progress
		Orders::reset_progress();

		// delete the old order and associated blocks
		$sql = "delete from " . MDS_DB_PREFIX . "orders where order_id=" . intval( $order_row['order_id'] );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$sql = "delete from " . MDS_DB_PREFIX . "blocks where order_id=" . intval( $order_row['order_id'] );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	} else if ( ( empty( Orders::get_current_order_id() ) ) || ( $USE_AJAX == 'YES' ) ) {
		// save the order id to session
		Orders::set_current_order_id( $order_row['order_id'] );
	}
}

$tmp_image_file = Orders::get_tmp_img_name();
if ( file_exists( $tmp_image_file ) ) {
	unlink( $tmp_image_file );
}

// load any existing blocks for this order
$order_row_blocks = '';
if ( isset( $order_row['blocks'] ) ) {
	if ( ! empty( $order_row['blocks'] ) || $order_row['blocks'] == '0' ) {
		$order_row_blocks = $order_row['blocks'];
	}
}
$block_ids    = $order_row_blocks !== '' ? array_map( 'intval', explode( ',', $order_row_blocks ) ) : [];
$block_str    = $order_row_blocks !== '' ? implode( ',', $block_ids ) : "";
$order_blocks = array_map( function ( $block_id ) use ( $BID ) {
	$pos = Blocks::get_block_position( $block_id, $BID );

	return [
		'block_id' => $block_id,
		'x'        => $pos['x'],
		'y'        => $pos['y'],
	];
}, $block_ids );
?>
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
			'There are <b>%GRID_COUNT%</b> different images served by this website! Select the image which you would like to publish your pixels to:',
			'%GRID_COUNT%',
			mysqli_num_rows( $res )
		);
		?>
    </div>
	<?php

	Forms::display_banner_selecton_form( $BID, $order_row['order_id'] ?? 0, $res, 'select' );
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
		'Note: You have placed some pixels on order, but it was not confirmed (green blocks). <a href="%MANAGE_URL%">Manage Pixels</a>',
		'%MANAGE_URL%',
		Utility::get_page_url( 'manage' )
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

        // Instructions
		Language::out_replace(
			'<h3>Instructions:</h3>
                    <p>Each square represents a block of %PIXEL_C% pixels (%BLK_WIDTH%x%BLK_HEIGHT%). Select the blocks that you want, and then press the \'Buy Pixels Now\' button.</p>',
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
		);

        // Legend
		$imagine    = new Imagine\Gd\Imagine();
		$banner_row = load_banner_row( $BID );
		$usr_grid_block = '<img class="mds-legend-block" src="data:image/png;base64,' . $banner_row['usr_grid_block'] . '" alt="" />';
		$usr_sel_block = '<img class="mds-legend-block" src="data:image/png;base64,' . $banner_row['usr_sel_block'] . '" alt="" />';
		$usr_sol_block = '<img class="mds-legend-block" src="data:image/png;base64,' . $banner_row['usr_sol_block'] . '" alt="" />';
		$usr_res_block = '<img class="mds-legend-block" src="data:image/png;base64,' . $banner_row['usr_res_block'] . '" alt="" />';
		$usr_ord_block = '<img class="mds-legend-block" src="data:image/png;base64,' . $banner_row['usr_ord_block'] . '" alt="" />';
		$usr_nfs_block = '<img class="mds-legend-block" src="data:image/png;base64,' . $banner_row['usr_nfs_block'] . '" alt="" />';

		Language::out_replace(
			'%USR_GRID_BLOCK% Available<br />
%USR_SEL_BLOCK% Selected (click to deselect)<br />
%USR_SOL_BLOCK% Sold<br />
%USR_RES_BLOCK% Reserved<br />
%USR_ORD_BLOCK% Ordered<br />
%USR_NFS_BLOCK% Not for sale<br />',
			[
				'%USR_GRID_BLOCK%',
				'%USR_SEL_BLOCK%',
				'%USR_SOL_BLOCK%',
				'%USR_RES_BLOCK%',
				'%USR_ORD_BLOCK%',
				'%USR_NFS_BLOCK%',
			],
			[
				$usr_grid_block,
				$usr_sel_block,
				$usr_sol_block,
				$usr_res_block,
				$usr_ord_block,
				$usr_nfs_block,
			],
			true,
			true
		);
		?>
    </div>
    <form method="post" id="pixel_form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name='pixel_form'>
		<?php wp_nonce_field( 'mds-form' ); ?>
        <input type="hidden" name="action" value="mds_form_submission">
	    <?php
	    $mds_dest = apply_filters( 'mds_dest_select', 'upload' );
	    ?>
        <input type="hidden" name="mds_dest" value="<?php echo esc_attr( $mds_dest ); ?>">

        <input type="hidden" value="1" name="select">
        <input type="hidden" name="package" value="">
        <input type="hidden" name="selected_pixels" value='<?php echo $block_str; ?>'>
        <input type="hidden" name="order_id" value="<?php echo( isset( $order_row['order_id'] ) ? intval( $order_row['order_id'] ) : '' ); ?>">
        <input type="hidden" name="BID" value="<?php echo intval( $BID ); ?>">

		<?php
		// If the grid max blocks isn't set to unlimited and is greater than 1 and has a max and min block size that aren't equal then display the selection size options.
		$max_min_equal = $banner_data['G_MAX_BLOCKS'] == $banner_data['G_MIN_BLOCKS'];
		if ( ( $banner_data['G_MAX_BLOCKS'] == 0 || $banner_data['G_MAX_BLOCKS'] > 1 ) && ! $max_min_equal ) {

			?>
            <div class="mds-select-wrapper">
                <div class="mds-select-prompt">
					<?php
					if ( \MillionDollarScript\Classes\Data\Config::get( 'BLOCK_SELECTION_MODE' ) == 'YES' ) {
					?>
                </div>
                <div class="mds-select-items">
					<?php

					// Calculate the minimum and maximum block sizes based on the grid dimensions
					$min_size       = isset( $banner_data['G_MIN_BLOCKS'] ) ? intval( $banner_data['G_MIN_BLOCKS'] ) : 1;
					$max_size       = min( intval( $banner_data['G_WIDTH'] ), intval( $banner_data['G_HEIGHT'] ) );
					$selection_size = isset( $_REQUEST['selection_size'] ) ? intval( $_REQUEST['selection_size'] ) : $min_size;
					$total_blocks   = $banner_data['G_WIDTH'] * $banner_data['G_HEIGHT'];

					// Calculate the maximum allowed total blocks based on the grid dimensions and G_MAX_BLOCKS
					$max_total_blocks = min( $banner_data['G_MAX_BLOCKS'], $total_blocks );

					// Set the maximum values for the selection_size and total_blocks inputs based on G_MAX_BLOCKS
					$max_selection_size     = min( $max_size, floor( sqrt( $banner_data['G_MAX_BLOCKS'] ) ) );
					$max_total_blocks_input = min( $max_selection_size * $max_selection_size, $max_total_blocks );
					if ( $max_selection_size == 0 ) {
						$max_selection_size = $max_size;
					}
					if ( $max_total_blocks_input == 0 ) {
						$max_total_blocks_input = $total_blocks;
					}

					// Adjust the minimum and maximum size of the pointer based on the grid dimensions
					$min_size_adjusted = $min_size;
					$max_size_adjusted = $max_selection_size;

					// Adjust the block size and total blocks based on the total blocks and the grid dimensions
					if ( $total_blocks > $max_total_blocks_input ) {
						$total_blocks = $max_total_blocks_input;
					}

					if ( $total_blocks > 0 ) {
						$selection_size_adjusted = floor( sqrt( $max_total_blocks_input / $total_blocks ) );
					} else {
						$selection_size_adjusted = $min_size_adjusted;
					}

					$block_size_adjusted   = floor( $max_size / $selection_size_adjusted );
					$total_blocks_adjusted = min( $selection_size_adjusted * $selection_size_adjusted, $max_total_blocks_input );
					$total_blocks          = $max_total_blocks_input / ( $selection_size_adjusted * $selection_size_adjusted );
					$total_blocks          = max( 1, $total_blocks );

					// Swap min_size and max_size if min_size_adjusted is greater than max_size_adjusted
					if ( $min_size_adjusted > $max_size_adjusted ) {
						$temp              = $min_size_adjusted;
						$min_size_adjusted = $max_size_adjusted;
						$max_size_adjusted = $temp;
					}

					?>
                    <div class="mds-input-item">
                        <div class="mds-input-item mds-slider">
                            <label for="mds-selection-size-slider"><?php Language::out( 'Selection Size' ); ?></label>
                            <input type="range" id="mds-selection-size-slider" name="selection_size" min="<?php echo $min_size_adjusted; ?>" max="<?php echo $max_selection_size; ?>"
                                   value="<?php echo $selection_size_adjusted; ?>">
                        </div>
                        <div class="mds-break"></div>
                        <div class="mds-input-item mds-number">
                            <label for="mds-selection-size-value"><?php Language::out( 'Size' ); ?></label>
                            <input type="number" id="mds-selection-size-value" value="<?php echo $selection_size_adjusted; ?>" min="<?php echo $min_size_adjusted; ?>"
                                   max="<?php echo $max_selection_size; ?>">
                        </div>
                        <div class="mds-input-item mds-number">
                            <label for="mds-total-blocks-value"><?php Language::out( 'Blocks' ); ?></label>
                            <input type="number" id="mds-total-blocks-value" value="<?php echo $total_blocks_adjusted; ?>" min="<?php echo $min_size_adjusted; ?>"
                                   max="<?php echo $max_total_blocks_input; ?>">
                        </div>
                    </div>
					<?php
					} else {
						$min_size = isset( $banner_data['G_MIN_BLOCKS'] ) ? intval( $banner_data['G_MIN_BLOCKS'] ) : 1;
						?>
                        <input type="hidden" id='selection_size' name='selection_size' value='<?php echo $min_size; ?>'/>
						<?php
					}
					?>
                    <div class="mds-input-item">
                        <div class="mds-select-input">
							<?php
							$checked = '';
							if ( ( isset( $_REQUEST['erase'] ) && $_REQUEST['erase'] == 'true' ) ) {
								$checked = " checked ";
							}
							?>
                            <input type="checkbox" name='erase' id='erase' value='true' <?php echo $checked; ?> />
                            <label for="erase">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round" class="icon-erase">
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
            </div>
			<?php
		} else {
			// Size selection is not enabled so set the selection size to the maximum allowed blocks.
			if ( $max_min_equal ) {
				$size_value = intval( $banner_data['G_MAX_BLOCKS'] );
			} else {
				$size_value = 1;
			}

			// Calculate the maximum square size that can be fit within the maximum block limit
			$max_square_size = floor( sqrt( $banner_data['G_MAX_BLOCKS'] ) );

			// Calculate the total blocks value
			$total_blocks_value = $max_square_size * $max_square_size;

			// Set the values of the slider and size inputs to the maximum square size
			?>
            <input type="hidden" id="mds-selection-size-slider" name="selection_size" value="<?php echo $max_square_size; ?>">
            <input type="hidden" id="mds-selection-size-value" value="<?php echo $max_square_size; ?>">
            <input type="hidden" id="mds-total-blocks-value" value="<?php echo $total_blocks_value; ?>">
			<?php
		}
		?>
        <div id="submit-buttons">
            <input type="button" name='submit_button1' id='submit_button1' value='<?php echo esc_attr( Language::get( 'Buy Pixels Now' ) ); ?>'>
            <input type="button" name='reset_button' id='reset_button' value='<?php echo esc_attr( Language::get( 'Reset' ) ); ?>'>
        </div>

        <div id="pixel_container">
            <div id="blocks"></div>
            <span id='block_pointer'></span>
            <div class="mds-pixel-wrapper">
                <img id="pixelimg" draggable="false" unselectable="on" src="<?php echo esc_url( Utility::get_page_url( 'show-selection', [ 'BID' => $BID, 'gud' => time() ] ) ); ?>"
                     data-original-width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>"
                     data-original-height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>"
                     alt=""/>
            </div>
        </div>

    </form>

    <script>
        // Function for scaling image map coordinates based on current image dimensions
        function scaleImageMap() {
            // Get the image element
            var img = document.getElementById('pixelimg');
            if (!img) return;
            
            // Get original dimensions from data attributes
            var origWidth = parseInt(img.getAttribute('data-original-width'), 10) || <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>;
            var origHeight = parseInt(img.getAttribute('data-original-height'), 10) || <?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>;
            
            // Get current dimensions
            var currentWidth = img.clientWidth || img.offsetWidth;
            var currentHeight = img.clientHeight || img.offsetHeight;
            
            // Calculate scaling factors
            var scaleX = currentWidth / origWidth;
            var scaleY = currentHeight / origHeight;
            
            // Scale the grid blocks positioning if necessary
            var blocks = document.querySelectorAll('.gridblock');
            if (blocks && blocks.length > 0) {
                for (var i = 0; i < blocks.length; i++) {
                    var block = blocks[i];
                    var x = parseInt(block.getAttribute('data-x'), 10) || 0;
                    var y = parseInt(block.getAttribute('data-y'), 10) || 0;
                    
                    if (x && y) {
                        var scaledX = Math.round(x * scaleX);
                        var scaledY = Math.round(y * scaleY);
                        block.style.left = scaledX + 'px';
                        block.style.top = scaledY + 'px';
                    }
                }
            }
            
            // Scale the pointer position if needed
            if (window.mds_pointer_x !== undefined && window.mds_pointer_y !== undefined) {
                var scaledX = Math.round(window.mds_pointer_x * scaleX);
                var scaledY = Math.round(window.mds_pointer_y * scaleY);
                var pointer = document.getElementById('block_pointer');
                if (pointer) {
                    pointer.style.left = scaledX + 'px';
                    pointer.style.top = scaledY + 'px';
                }
            }
        }
        
        // Make the function globally available for the rescale_grid function
        window.scaleImageMap = scaleImageMap;
        
        // Call on page load
        jQuery(document).ready(function() {
            scaleImageMap();
            
            // Call when window is resized
            window.addEventListener('resize', function() {
                if (window.selectResizeTimer) {
                    clearTimeout(window.selectResizeTimer);
                }
                window.selectResizeTimer = setTimeout(scaleImageMap, 150);
            });
            
            // Call when image loads
            document.getElementById('pixelimg').addEventListener('load', scaleImageMap);
        });
    </script>

<?php require_once MDS_CORE_PATH . "html/footer.php"; ?>