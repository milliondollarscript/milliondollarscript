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

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Functions;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

require_once MDS_CORE_PATH . "include/ads.inc.php";

// Add a specific body class for the manage page to help with JavaScript targeting
add_filter('body_class', function($classes) {
	$classes[] = 'mds-page-manage';
	return $classes;
});

// check if user has permission to access this page
if ( ! mds_check_permission( "mds_manage_pixels" ) ) {
	require_once MDS_CORE_PATH . "html/header.php";
	Language::out( "No Access" );
	require_once MDS_CORE_PATH . "html/footer.php";
	exit;
}

// Handle order cancellation requests with CSRF protection
$cancellation_message = '';
if ( isset( $_REQUEST['cancel'] ) && $_REQUEST['cancel'] === 'yes' && isset( $_REQUEST['order_id'] ) ) {
	// Verify CSRF nonce first
	if ( ! isset( $_REQUEST['mds_cancel_nonce'] ) || ! wp_verify_nonce( $_REQUEST['mds_cancel_nonce'], 'mds_cancel_order_' . intval( $_REQUEST['order_id'] ) ) ) {
		// Log security violation attempt
		\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: CSRF attempt blocked in manage.php order cancellation - User: ' . get_current_user_id() . ' - Order ID: ' . ( $_REQUEST['order_id'] ?? 'unknown' ) );
		wp_die( esc_html__( 'Security check failed. Please try again.', 'milliondollarscript' ), esc_html__( 'Security Error', 'milliondollarscript' ), [ 'response' => 403 ] );
	}

	$order_id = intval( $_REQUEST['order_id'] );
	$user_id = get_current_user_id();
	
	if ( $order_id > 0 ) {
		// Verify that the order belongs to the current user
		$order = Orders::get_order( $order_id );
		if ( $order && $order->user_id == $user_id ) {
			// Check if this was confirmed via JavaScript (additional security measure)
			if ( isset( $_REQUEST['is_js_confirmed'] ) && $_REQUEST['is_js_confirmed'] === '1' ) {
				// Cancel the order
				Orders::cancel_order( $order_id, false );
				$cancellation_message = Language::get( 'Order has been successfully cancelled.' );
				
				// Log successful cancellation for security auditing
				\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: Order cancellation completed - User: ' . get_current_user_id() . ' - Order ID: ' . $order_id );
			} else {
				// Missing JavaScript confirmation - security concern
				\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: Order cancellation attempted without JS confirmation - User: ' . get_current_user_id() . ' - Order ID: ' . $order_id );
				wp_die( esc_html__( 'Invalid request. Please use the cancel button.', 'milliondollarscript' ), esc_html__( 'Error', 'milliondollarscript' ), [ 'response' => 400 ] );
			}
		} else {
			// Order doesn't exist or doesn't belong to user
			\MillionDollarScript\Classes\System\Logs::log( 'MDS Security: Unauthorized order cancellation attempt - User: ' . get_current_user_id() . ' - Order ID: ' . $order_id );
			wp_die( esc_html__( 'Order not found or access denied.', 'milliondollarscript' ), esc_html__( 'Error', 'milliondollarscript' ), [ 'response' => 404 ] );
		}
	}
}

$wrap = false;
if ( empty( $_REQUEST['change_pixels'] ) && empty( $_FILES ) && empty( $_REQUEST['json'] ) ) {
	$wrap = true;
}

if ( $wrap ) {
	require_once MDS_CORE_PATH . "html/header.php";
}

$gd_info      = gd_info();
$gif_support  = '';
$jpeg_support = '';
$png_support  = '';
if ( ! empty( $gd_info['GIF Read Support'] ) ) {
	$gif_support = "GIF";
}
if ( ! empty( $gd_info['JPEG Support'] ) || ( ! empty( $gd_info['JPG Support'] ) ) ) {
	$jpeg_support = "JPG";
}
if ( ! empty( $gd_info['PNG Support'] ) ) {
	$png_support = "PNG";
}

global $BID, $f2, $wpdb;

// Get the setting for showing the grid dropdown *before* determining BID
$show_grid_dropdown_option = Options::get_option( 'manage-pixels-grid-dropdown', 'yes' ) == 'yes';

// --- Refactored BID Determination --- START

if ( $show_grid_dropdown_option ) {
	// --- Option ENABLED --- 
	// Check if grid selection form was submitted with a valid nonce
	if ( isset( $_GET['mds-nonce'] ) && isset( $_GET['BID'] ) && is_numeric( $_GET['BID'] ) ) {
		if ( wp_verify_nonce( sanitize_key( $_GET['mds-nonce'] ), 'mds_manage_grid_select' ) ) {
			// Nonce is valid, use BID from the GET request
			$BID = intval( $_GET['BID'] );
		} else {
			// Nonce is invalid, display error and exit
			if ( $wrap ) {
				require_once MDS_CORE_PATH . "html/header.php";
			}
			wp_die( esc_html__( 'Security check failed. Please try again.', 'milliondollarscript' ), esc_html__( 'Error', 'milliondollarscript' ), [ 'response' => 403 ] );
			if ( $wrap ) {
				require_once MDS_CORE_PATH . "html/footer.php";
			}
			exit;
		}
	} elseif ( isset( $_REQUEST['BID'] ) && is_numeric( $_REQUEST['BID'] ) ) {
		// Option enabled, form NOT submitted, but BID is in the general request (e.g., manual URL)
		$BID = intval( $_REQUEST['BID'] );
	} else {
		// Option enabled, no form submission, no BID in request - default to the shortcode's BID
		$BID = $f2->bid();
	}
} else {
	// --- Option DISABLED --- 
	// Always use the shortcode's BID and ignore any BID in the request
	$BID = $f2->bid(); 
}

// --- Refactored BID Determination --- END

// Load banner data based on the determined BID (This should now use the correct BID)
$banner_data = load_banner_constants( $BID );
$user_id     = get_current_user_id();

// Verify nonce if the grid selection form was submitted via GET
if ( isset( $_GET['mds-nonce'] ) ) {
	if ( ! isset( $_GET['BID'] ) || ! wp_verify_nonce( sanitize_key( $_GET['mds-nonce'] ), 'mds_manage_grid_select' ) ) {
		// Nonce is invalid, display error and exit
		if ( $wrap ) {
			require_once MDS_CORE_PATH . "html/header.php";
		}
		wp_die( esc_html__( 'Security check failed. Please try again.', 'milliondollarscript' ), esc_html__( 'Error', 'milliondollarscript' ), [ 'response' => 403 ] );
		if ( $wrap ) {
			require_once MDS_CORE_PATH . "html/footer.php";
		}
		exit;
	}
	// Nonce is valid, proceed to load banner data with the selected BID
}

// A block was clicked. Fetch the ad_id and initialize $_REQUEST['ad_id']
// If no ad exists for this block, create it.
$mds_pixel = null;
if ( ! empty( $_REQUEST['block_id'] ) || ( isset( $_REQUEST['block_id'] ) && $_REQUEST['block_id'] != 0 ) ) {

	$BID      = intval( $BID );
	$block_id = intval( $_REQUEST['block_id'] );

	$sql     = $wpdb->prepare( "SELECT `user_id`, `ad_id`, `order_id` FROM " . MDS_DB_PREFIX . "blocks WHERE `banner_id`=%d AND `block_id`=%d", $BID, $block_id );
	$blk_row = $wpdb->get_row( $sql, ARRAY_A );

	if ( empty( $blk_row['ad_id'] ) ) {
		// no ad exists, create a new ad_id
		$_POST[ MDS_PREFIX . 'url' ]  = '';
		$_POST[ MDS_PREFIX . 'text' ] = 'Pixel text';
		$_REQUEST['order_id']         = $blk_row['order_id'];
		$_REQUEST['BID']              = $BID;
		$_REQUEST['user_id']          = get_current_user_id();
		$_REQUEST['aid']              = "";
		$ad_id                        = insert_ad_data( $blk_row['order_id'] );

		$wpdb->update(
			MDS_DB_PREFIX . 'orders',
			[ 'ad_id' => intval( $ad_id ) ],
			[ 'order_id' => intval( $blk_row['order_id'] ) ],
			[ '%d' ],
			[ '%d' ]
		);
		if ( $wpdb->last_error ) {
			wp_die( esc_html( $wpdb->last_error ) );
		}
		$wpdb->update(
			MDS_DB_PREFIX . 'blocks',
			[ 'ad_id' => intval( $ad_id ) ],
			[ 'order_id' => intval( $blk_row['order_id'] ) ],
			[ '%d' ],
			[ '%d' ]
		);
		if ( $wpdb->last_error ) {
			wp_die( esc_html( $wpdb->last_error ) );
		}

		$_REQUEST['aid'] = $ad_id;
	} else {
		// initialize $_REQUEST['ad_id']

		// Make sure the mds-pixel exists.
		$mds_pixel = get_post( $blk_row['ad_id'] );

		if ( empty( $mds_pixel ) ) {
			Language::out( 'Unable to find pixels.' );
		} else {
			$_REQUEST['aid'] = $blk_row['ad_id'];
		}

		// bug in previous versions resulted in saving the ad's user_id with a session_id
		// fix user_id here
		// $wpdb->update(
		//     MDS_DB_PREFIX . "ads",
		//     array( 'user_id' => intval( $blk_row['user_id'] ) ),
		//     array( 'order_id' => intval( $blk_row['order_id'] ), 'user_id' => get_current_user_id() ),
		//     array( '%d' ),
		//     array( '%d', '%d' )
		// );
	}
}

// Display ad editing forms if the ad was clicked, or 'Edit' button was pressed.
if ( ( ( isset( $_REQUEST['mds_dest'] ) && $_REQUEST['mds_dest'] == 'manage' ) || isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'manage' ) && ! empty( $_REQUEST['aid'] ) ) {
	$ad_id = intval( $_REQUEST['aid'] );

	// Call manage_pixel to handle potential uploads and display the form
	// Capture the return value
	$manage_result = Functions::manage_pixel( $ad_id, $mds_pixel, $banner_data, $BID, $gif_support, $jpeg_support, $png_support );

	// If manage_pixel was called (regardless of success/failure, as it handles its own output/errors),
	// stop further execution on *this* page (manage.php).
	return; 
}

// Show Grid Selection Dropdown if enabled
$grid_selector_form_html = ''; // Initialize variable

if ( $show_grid_dropdown_option ) {
	global $wpdb;
	$banners = $wpdb->get_results( "SELECT banner_id, name FROM " . MDS_DB_PREFIX . "banners ORDER BY name ASC", ARRAY_A );

	if ( $banners && count( $banners ) > 1 ) { // Only show if more than one grid exists
		ob_start(); // Start output buffering
		?>
		<div class="mds-grid-selector-form mds-box">
			<form method="get" action="<?php echo esc_url( MillionDollarScript\Classes\System\Utility::get_page_url( 'manage' ) ); ?>">
				<?php
				// Add hidden fields from current query string to persist them, except for BID itself
				foreach ( $_GET as $key => $value ) {
					if ( $key !== 'BID' && $key !== 'mds-nonce' ) { // Avoid duplicating BID and nonce
						echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
					}
				}
				wp_nonce_field( 'mds_manage_grid_select', 'mds-nonce' );
				?>
				<label for="mds-grid-select"><?php MillionDollarScript\Classes\Language\Language::out( 'Select Grid:' ); ?></label>
				<select name="BID" id="mds-grid-select">
					<?php foreach ( $banners as $banner ) : ?>
						<option value="<?php echo esc_attr( $banner['banner_id'] ); ?>" <?php selected( $banner['banner_id'], $BID ); ?>>
							<?php echo esc_html( $banner['name'] ); ?> (<?php echo esc_html( $banner['banner_id'] ); ?>)
						</option>
					<?php endforeach; ?>
				</select>
				<?php $button_text = MillionDollarScript\Classes\Language\Language::get( 'Select Grid' ); ?>
				<input type="submit" class="button" value="<?php echo esc_attr( $button_text ); ?>" />
			</form>
		</div>
		<?php
		$grid_selector_form_html = ob_get_clean(); // Get buffered content and clean buffer
	}
}

$offset = isset( $_REQUEST['offset'] ) ? intval( $_REQUEST['offset'] ) : 0;

# List Ads
// echo "<!-- DEBUG: Calling manage_list_ads with BID = " . esc_html($BID) . " -->"; // Remove debug output
ob_start();
$count    = manage_list_ads( $offset );
$contents = ob_get_contents();
ob_end_clean();

if ( $count > 0 ) {
	?>
    <div class="fancy-heading"><?php Language::out( 'Manage your pixels' ); ?></div>
	<?php
	$sql   = $wpdb->prepare( "SELECT grid_width,grid_height, block_width, block_height, bgcolor, time_stamp FROM " . MDS_DB_PREFIX . "banners WHERE (banner_id = %d)", $BID );
	$b_row = $wpdb->get_row( $sql, ARRAY_A );

	$b_row['block_width']  = ( ! empty( $b_row['block_width'] ) ) ? $b_row['block_width'] : 10;
	$b_row['block_height'] = ( ! empty( $b_row['block_height'] ) ) ? $b_row['block_height'] : 10;

	$user_id = get_current_user_id();

	$sql = $wpdb->prepare(
		"SELECT * FROM " . MDS_DB_PREFIX . "blocks WHERE (user_id = %d) AND (status = 'sold') AND banner_id=%d",
		$user_id,
		intval( $BID )
	);
	$result = $wpdb->get_results( $sql );
	$pixels = count( $result ) * ( $b_row['block_width'] * $b_row['block_height'] );

	$sql     = $wpdb->prepare( "SELECT block_id FROM " . MDS_DB_PREFIX . "blocks WHERE (user_id = %d) AND (status = 'ordered')", $user_id );
	$result  = $wpdb->get_results( $sql );
	$ordered = count( $result ) * ( $b_row['block_width'] * $b_row['block_height'] );

	// Replacements for custom page links in WP
	$order_page   = Utility::get_page_url( 'order' );
	$manage_page  = Utility::get_page_url( 'manage' );
	$account_page = Options::get_option( 'account-page' );

	Language::out_replace(
		'<p>Here you can manage your pixels.<p>',
		[
			'%PIXEL_COUNT%',
			'%BLOCK_COUNT%',
			'%MANAGE_URL%',
		],
		[
			$pixels,
			( $pixels / ( $b_row['block_width'] * $b_row['block_height'] ) ),
			Utility::get_page_url( 'manage' ),
		]
	);

	Language::out_replace(
		'<p><a class="mds-order-pixels mds-button" href="%ORDER_URL%">Order Pixels</a></p>',
		[
			'%ORDER_URL%',
		],
		[
			$order_page,
		]
	);

	Language::out_replace(
		'<p>You own %PIXEL_COUNT% blocks.</p>',
		[
			'%PIXEL_COUNT%',
			'%BLOCK_COUNT%',
			'%MANAGE_URL%',
		],
		[
			$pixels,
			( $pixels / ( $b_row['block_width'] * $b_row['block_height'] ) ),
			Utility::get_page_url( 'manage' ),
		]
	);

	Language::out_replace(
		'<p>You have %PIXEL_ORD_COUNT% pixels on order (%BLOCK_ORD_COUNT% blocks).</p>',
		[
			'%PIXEL_ORD_COUNT%',
			'%BLOCK_ORD_COUNT%',
			'%ORDER_URL%',
		],
		[
			$ordered,
			( $ordered / ( $b_row['block_width'] * $b_row['block_height'] ) ),
			$order_page,
		]
	);

	Language::out_replace( '<p>Your pixels were clicked %CLICK_COUNT% times.</p>', '%CLICK_COUNT%', number_format( intval( get_user_meta( $user_id, MDS_PREFIX . 'click_count', true ) ) ) );
	Language::out_replace( '<p>Your pixels were viewed %VIEW_COUNT% times.</p>', '%VIEW_COUNT%', number_format( intval( get_user_meta( $user_id, MDS_PREFIX . 'view_count', true ) ) ) );

	echo $grid_selector_form_html;

	// Display success message if order was cancelled
	if ( ! empty( $cancellation_message ) ) {
		?>
		<div class="mds-success-message"><?php echo esc_html( $cancellation_message ); ?></div>
		<?php
	}

	echo $contents;

	// inform the user about the approval status of the images.

	$sql = $wpdb->prepare(
		"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id=%d AND status='completed' AND approved='N' AND banner_id=%d",
		get_current_user_id(),
		intval( $BID )
	);
	$result4 = $wpdb->get_results( $sql );
	if ( $wpdb->last_error ) {
		wp_die( esc_html( $wpdb->last_error ) );
	}

	if ( count( $result4 ) > 0 ) {
		?>
        <div class="mds-error-message"><?php Language::out( 'Note: Your pixels are waiting for approval. They will be scheduled to go live once they are approved.' ); ?></div>
		<?php
	} else {

		$sql = $wpdb->prepare(
			"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id=%d AND status='completed' AND approved='Y' AND published='Y' AND banner_id=%d",
			get_current_user_id(),
			intval( $BID )
		);
		$result4 = $wpdb->get_results( $sql );
		if ( $wpdb->last_error ) {
			wp_die( esc_html( $wpdb->last_error ) );
		}

		if ( count( $result4 ) > 0 ) {
			?>
            <div class="mds-success-message"><?php Language::out( 'Note: Your pixels are now published and live!' ); ?></div>
			<?php
		} else {

			$sql = $wpdb->prepare(
				"SELECT * FROM " . MDS_DB_PREFIX . "orders WHERE user_id=%d AND status='completed' AND approved='Y' AND published='N' AND banner_id=%d",
				get_current_user_id(),
				intval( $BID )
			);
			$result4 = $wpdb->get_results( $sql );
			if ( $wpdb->last_error ) {
				wp_die( esc_html( $wpdb->last_error ) );
			}

			if ( count( $result4 ) > 0 ) {
				?>
                <div class="mds-caution-message"><?php Language::out( 'Your pixels are now approved! They are now waiting for the webmaster to publish them on to the public grid.' ); ?></div>
				<?php
			}
		}
	}
	?>

    <div class="mds-info-message">
		<?php
		Language::out( '"Completed" - Orders where the transaction was successfully completed.<br />
"Confirmed" - Orders confirmed by you, but the transaction has not been completed.<br />
"Pending" - Orders confirmed by you, but the transaction has not been approved.<br />
"Denied" - Denied by the administrator.<br />
"Cancelled" - Orders that have been cancelled.<br />
"Expired" - Pixels were expired after the specified term. You can renew this order.<br />' );
		?>
    </div>

    <div class="mds-caution-message">
		<?php
		Language::out( 'Your blocks are shown on the grid below. Only your blocks are shown.<br />
- Click your block to edit it.<br />
- <b>Red</b> blocks have no pixels yet. Click on them to upload your pixels.' );
		?>
    </div>
	<?php
	// Generate the Area map form the current sold blocks.
	$sql = $wpdb->prepare(
		"SELECT b.* FROM " . MDS_DB_PREFIX . "blocks AS b
    INNER JOIN " . MDS_DB_PREFIX . "orders AS o ON b.order_id = o.order_id
    WHERE b.user_id=%d AND b.status IN ('sold','ordered','denied') AND b.banner_id=%d
    AND o.status NOT IN ('confirmed', 'new', 'deleted', 'cancelled')",
		get_current_user_id(),
		intval( $BID )
	);

	$results = $wpdb->get_results( $sql, ARRAY_A );
	if ( ! empty( $results ) ) { ?>
        <div class="mds-grid-frame">
            <div class="mds-grid-status">
                <div class="mds-grid-preloader" data-loader-src="<?php echo esc_url( MDS_BASE_URL . 'src/Assets/images/ajax-loader.gif' ); ?>"
                     data-original-width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>"
                     data-original-height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>"
                     hidden>
                    <span class="mds-grid-preloader__spinner" aria-hidden="true"></span>
                </div>
                <div class="mds-grid-feedback" role="alert" aria-live="polite" hidden>
                    <p class="mds-grid-feedback__message"><?php Language::out( "We're having trouble loading the grid image right now. This can happen if the grid is very large or the server needs more time." ); ?></p>
                    <button type="button" class="mds-grid-feedback__retry"><?php Language::out( 'Retry loading image' ); ?></button>
                </div>
            </div>
            <div class="publish-grid">
                <map name="main" id="main">
				<?php
				// Check if order locking is enabled
				$order_locking_enabled = Options::get_option( 'order-locking', 'no' ) == 'yes';

				foreach ( $results as $row ) {

					// Sanitize alt_text
					$text = sanitize_text_field( $row['alt_text'] );

					// Prepare coordinates
					$coords = sprintf(
						"%d,%d,%d,%d",
						$row['x'],
						$row['y'],
						$row['x'] + $banner_data['BLK_WIDTH'],
						$row['y'] + $banner_data['BLK_HEIGHT']
					);

					// Prepare href - disable if order locking is on
					// Default to disabled
					$href = '#';
					if ( ! $order_locking_enabled ) {
						$href = esc_url(
							add_query_arg(
								[ 'mds-action' => 'manage', 'aid' => $row['ad_id'] ],
								Utility::get_page_url( 'manage' )
							)
						);
					}

					// Output area
					printf(
						'<area shape="RECT" coords="%s" href="%s" title="%s" alt="%s"/>',
						$coords,
						$href,
						esc_attr( $text ),
						esc_attr( $text )
					);

				}
				?>
            </map>
			<?php
			// Prepare src
			$src = esc_url(
				add_query_arg(
					[ 'BID' => $BID, 'time' => time() ],
					Utility::get_page_url( 'show-map' )
				)
			);

			// Prepare width and height
			$width  = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
			$height = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

			// Output img with specific class and data attributes for JS targeting
			printf(
				'<img id="publish-grid" class="mds-manage-grid" src="%s" data-grid-src="%s" width="%d" height="%d" usemap="#main" alt="" data-original-width="%d" data-original-height="%d"/>',
				$src,
				$src,
				$width,
				$height,
				$width,
				$height
			);
			?>

			<!-- Inline script for image map scaling to ensure alignment of clickable areas with the grid image -->
			<script>
			(function() {
				const gridImage = document.getElementById('publish-grid');
				const frame = gridImage ? gridImage.closest('.mds-grid-frame') : document.querySelector('.mds-grid-frame');
				const preloader = frame ? frame.querySelector('.mds-grid-preloader') : null;
				const feedback = frame ? frame.querySelector('.mds-grid-feedback') : null;
				const retryButton = feedback ? feedback.querySelector('.mds-grid-feedback__retry') : null;
				const preferredLoader = preloader ? preloader.getAttribute('data-loader-src') : null;
				const resolvedLoader = (typeof window.MDSResolveLoaderUrl === 'function')
					? window.MDSResolveLoaderUrl(preferredLoader)
					: preferredLoader;
				const baseSrc = gridImage ? (gridImage.getAttribute('data-grid-src') || gridImage.getAttribute('src')) : '';

				const loaderSrc = resolvedLoader || (typeof window.MDS !== 'undefined' && window.MDS.MDS_BASE_URL
					? window.MDS.MDS_BASE_URL + 'src/Assets/images/ajax-loader.gif'
					: null);

				function showPreloader(options) {
					if (!preloader) {
						return;
					}
					const spinnerMarkup = loaderSrc
						? "<img class=\'mds-grid-preloader__spinner \' src=\'" + loaderSrc + "\' alt=\'\' aria-hidden=\'true\' width=\'32\' height=\'32\'/>"
						: "<span class=\'mds-grid-preloader__spinner\' aria-hidden=\'true\'></span>";
					preloader.setAttribute('data-loader-src', loaderSrc || '');
					if (preloader.children.length === 0) {
						preloader.innerHTML = spinnerMarkup;
					}
					preloader.removeAttribute('hidden');
					preloader.dataset.mdsPreloaderActive = '1';
					const opts = options || {};
					if (opts.hideImage !== false) {
						hideImage();
					}
				}

				function hidePreloader() {
					if (preloader) {
						preloader.setAttribute('hidden', 'hidden');
						preloader.innerHTML = '';
						delete preloader.dataset.mdsPreloaderActive;
					}
				}

				function hideImage() {
					if (gridImage) {
						gridImage.classList.add('mds-grid-image--hidden');
					}
				}

				function showImage() {
					if (gridImage) {
						gridImage.classList.remove('mds-grid-image--hidden');
					}
				}

				const controls = gridImage ? {
					showPreloader: (opts) => showPreloader(opts),
					hidePreloader: () => hidePreloader(),
					showFeedback: () => toggleFeedback(true),
					hideFeedback: () => toggleFeedback(false),
					hideImage: () => hideImage(),
					showImage: () => showImage(),
				} : null;

				if (gridImage && controls) {
					gridImage.mdsGridControls = controls;
				}

				showPreloader();

				function toggleFeedback(show) {
					if (!feedback) {
						return;
					}
					if (show) {
						feedback.removeAttribute('hidden');
						feedback.classList.add('is-visible');
						hideImage();
					} else {
						feedback.setAttribute('hidden', 'hidden');
						feedback.classList.remove('is-visible');
						showImage();
					}
				}

				function scaleImageMap() {
					var img = document.getElementById('publish-grid');
					var map = document.querySelector('map[name="main"]');
				
					if (!img || !map) {
						return;
					}
				
					var areas = map.querySelectorAll('area');
					if (!areas.length) {
						return;
					}
				
					var origWidth = <?php echo $width; ?>;
					var origHeight = <?php echo $height; ?>;
					var currentWidth = img.clientWidth || img.offsetWidth;
					var currentHeight = img.clientHeight || img.offsetHeight;
					var scaleX = currentWidth / origWidth;
					var scaleY = currentHeight / origHeight;
				
					Array.prototype.forEach.call(areas, function(area) {
						if (!area.hasAttribute('data-original-coords')) {
							area.setAttribute('data-original-coords', area.getAttribute('coords'));
						}

						var originalCoords = area.getAttribute('data-original-coords');
						var coordsArray = originalCoords.split(',');
						var scaledCoords = [];

						for (var i = 0; i < coordsArray.length; i++) {
							var scaled = i % 2 === 0 ?
								Math.round(parseInt(coordsArray[i], 10) * scaleX) :
								Math.round(parseInt(coordsArray[i], 10) * scaleY);
							scaledCoords.push(scaled);
						}

						area.setAttribute('coords', scaledCoords.join(','));
					});
				}

				function handleLoad() {
					if (!gridImage) {
						return;
					}
					if (gridImage.naturalWidth === 0 || gridImage.naturalHeight === 0) {
						hidePreloader();
						hideImage();
						toggleFeedback(true);
						return;
					}
					scaleImageMap();
					hidePreloader();
					showImage();
					toggleFeedback(false);
				}

				function handleError() {
					hidePreloader();
					hideImage();
					toggleFeedback(true);
				}

				if (gridImage) {
					if (gridImage.complete) {
						if (gridImage.naturalWidth === 0 || gridImage.naturalHeight === 0) {
							handleError();
						} else {
							handleLoad();
						}
					} else {
						toggleFeedback(false);
						showPreloader();
					}
					gridImage.addEventListener('load', handleLoad);
					gridImage.addEventListener('error', handleError);
				}

				if (retryButton && gridImage) {
					retryButton.addEventListener('click', function(event) {
						event.preventDefault();
						toggleFeedback(false);
						showPreloader();
						var separator = baseSrc.indexOf('?') === -1 ? '?' : '&';
						gridImage.src = baseSrc + separator + '_mds_retry=' + Date.now();
					});
				}

				window.addEventListener('resize', function() {
					if (window.mapResizeTimer) {
						clearTimeout(window.mapResizeTimer);
					}
					window.mapResizeTimer = setTimeout(scaleImageMap, 150);
				});
			})();
			</script>
		</div>
        </div>
	<?php }
} else {
	Language::out_replace( 'You have no pixels yet. Go <a href="%ORDER_URL%">here</a> to order pixels.', '%ORDER_URL%', Utility::get_page_url( 'order' ) );
}

// Allow extensions to add scripts and additional content to the manage page
do_action( 'mds_manage_pixels_page_footer', get_current_user_id(), $BID );
?>

<?php require_once MDS_CORE_PATH . "html/footer.php"; ?>
