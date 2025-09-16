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
use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\Orders\Steps;
use MillionDollarScript\Classes\Payment\Currency;
use MillionDollarScript\Classes\System\Functions;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

mds_wp_login_check();

require_once( __DIR__ . "/../include/ads.inc.php" );

global $f2;

$BID = $f2->bid();

function display_edit_order_button( $order_id ): void {
	global $BID;
	?>
        <input type='button' value='<?php echo esc_attr( Language::get( 'Go Back' ) ); ?>'
               onclick="window.location='<?php echo esc_url( Utility::get_page_url( 'order', [ 'BID' => $BID, 'order_id' => $order_id ] ) ); ?>'">
	<?php
}

Orders::update_temp_order_timestamp();

// Robustly resolve current order context using request hints (order_id/aid) and mark in-progress
$incoming_order_id = isset( $_REQUEST['order_id'] ) && is_numeric( $_REQUEST['order_id'] ) ? intval( $_REQUEST['order_id'] ) : 0;
$incoming_aid      = isset( $_REQUEST['aid'] ) && is_numeric( $_REQUEST['aid'] ) ? intval( $_REQUEST['aid'] ) : 0;
$resolved          = Orders::ensure_current_order_context( $incoming_order_id ?: null, $incoming_aid ?: null, true );

$order_id = $resolved ?: Orders::get_current_order_id();

if ( empty( $order_id ) ) {
	if ( wp_doing_ajax() ) {
		Orders::no_orders();
		wp_die();
	}

	Utility::redirect( Utility::get_page_url( 'no-orders' ) );
}

global $wpdb;
$order_result = $wpdb->get_results( $wpdb->prepare( "select * from " . MDS_DB_PREFIX . "orders where order_id=%s", $order_id ) );
if ( $wpdb->last_error ) {
	mds_sql_error( $wpdb->last_error );
}

// check if we have pixels...
if ( count( $order_result ) == 0 ) {
	if ( wp_doing_ajax() ) {
		Orders::no_orders();
		wp_die();
	}

	Utility::redirect( Utility::get_page_url( 'no-orders' ) );
}

$order_row = (array) $order_result[0];

// get the banner ID
global $BID;
$BID = $order_row['banner_id'];

$banner_data = load_banner_constants( $BID );

// Gate: if order exists but has no blocks and is not 'new', show guidance to select more blocks
if ( empty( $order_row ) || ( isset( $order_row['status'] ) && $order_row['status'] !== 'new' && empty( $order_row['blocks'] ) && $order_row['blocks'] != '0' ) ) {

	require_once MDS_CORE_PATH . "html/header.php";
	Functions::not_enough_blocks( $order_row['order_id'] ?? null, $banner_data['G_MIN_BLOCKS'] );
	require_once MDS_CORE_PATH . "html/footer.php";

	return;
}

// Submission-time validation: adjacency/rectangle and min/max blocks
$blocks_per_row = $banner_data['G_WIDTH'];
$blocks_array = [];
if ( isset( $order_row['blocks'] ) && $order_row['blocks'] !== '' ) {
	$blocks_array = array_map( 'intval', explode( ',', $order_row['blocks'] ) );
}

$errors_sub = [];
// Enforce min blocks
$min_blocks = intval( $banner_data['G_MIN_BLOCKS'] );
if ( $min_blocks > 0 && count( $blocks_array ) < $min_blocks ) {
	$errors_sub[] = Language::get_replace( 'You must select at least %MAX_BLOCKS% blocks.', '%MAX_BLOCKS%', $min_blocks );
}
// Enforce max blocks
$max_blocks = intval( $banner_data['G_MAX_BLOCKS'] );
if ( $max_blocks > 0 && count( $blocks_array ) > $max_blocks ) {
	$errors_sub[] = Language::get_replace( 'Maximum blocks selected. (%MAX_BLOCKS% allowed per order)', '%MAX_BLOCKS%', $max_blocks );
}
// Enforce adjacency/rectangle depending on option
$mode = Options::get_option( 'selection-adjacency-mode', 'ADJACENT' );
if ( $mode !== 'NONE' ) {
	if ( $mode === 'RECTANGLE' ) {
		if ( ! \MillionDollarScript\Classes\Orders\Blocks::check_adjacency( $blocks_array, $blocks_per_row ) ) {
			$errors_sub[] = Language::get( 'You must select blocks forming a rectangle or square.' );
		}
	} else {
		if ( ! \MillionDollarScript\Classes\Orders\Blocks::check_contiguous( $blocks_array, $blocks_per_row ) ) {
			$errors_sub[] = Language::get( 'You must select a block adjacent to another one.' );
		}
	}
}

if ( ! empty( $errors_sub ) ) {
	require_once MDS_CORE_PATH . "html/header.php";
	foreach ( $errors_sub as $err ) {
		echo '<div class="mds-error">' . esc_html( $err ) . '</div>';
	}
	display_edit_order_button( $order_row['order_id'] );
	require_once MDS_CORE_PATH . "html/footer.php";
	return;
}

/* Login -> Select pixels -> Write ad -> Confirm order */
if ( ! is_user_logged_in() ) {
	mds_wp_login_check();
} else {
	// The user is logged in

	// Get the MDS Pixels post id by the order id
	$post         = FormFields::get_pixel_from_order_id( $order_id );
	$mds_pixel_id = $post?->ID;

	// Check if there is a pixel post for this order yet.
	if ( empty( $mds_pixel_id ) ) {
		require_once MDS_CORE_PATH . "html/header.php";
		echo '<div class="mds-error">' . esc_html( Language::get( 'Please write your ad before confirming your order.' ) ) . '</div>';
		display_edit_order_button( $order_row['order_id'] );
		require_once MDS_CORE_PATH . "html/footer.php";

		return;
	}

	// Check if the required fields were filled
	$errors = [];
	$fields = FormFields::get_fields();
	foreach ( $fields as $field ) {
		$field_name  = $field->get_base_name();
		$field_label = $field->get_label();

		$field_value = carbon_get_post_meta( $mds_pixel_id, $field_name );

		if ( $field_name == MDS_PREFIX . 'text' && ! Options::get_option( 'text-optional', 'no' ) == 'yes' ) {
			if ( empty( $field_value ) && ! Options::get_option( 'text-optional', 'no' ) == 'yes' ) {
				$errors[] = Language::get_replace( 'The %FIELD% field is required.', '%FIELD%', $field_label );
			}

		} else if ( $field_name == MDS_PREFIX . 'url' && ! Options::get_option( 'url-optional', 'no' ) == 'yes' ) {
			if ( empty( $field_value ) && ! Options::get_option( 'url-optional', 'no' ) == 'yes' ) {
				$errors[] = Language::get_replace( 'The %FIELD% field is required.', '%FIELD%', $field_label );
			}

		} else if ( $field_name == MDS_PREFIX . 'image' && ! Options::get_option( 'image-optional', 'yes' ) == 'yes' ) {
			if ( empty( $field_value ) && ! Options::get_option( 'image-optional', 'yes' ) == 'yes' ) {
				$errors[] = Language::get_replace( 'The %FIELD% field is required.', '%FIELD%', $field_label );
			}
		}

		/**
		 * Apply filter to validate an order.
		 *
		 * @param array $errors The array of validation errors.
		 * @param string $field_name The name of the field being validated.
		 * @param mixed $field_value The value of the field being validated.
		 * @param string $field_label The label of the field being validated.
		 *
		 * @return string|null  The error message returned by the filter, or null if no error.
		 */
		$filter_result = apply_filters( 'mds-confirm-order-validation', $errors, $field_name, $field_value, $field_label );

		// Check if the filter result is a non-empty string indicating an error
		if ( is_string( $filter_result ) && ! empty( $filter_result ) ) {
			$errors[] = $filter_result;
		} else {
			// No error returned by the filter
			$filter_result = null;
		}
	}

	// Display errors
	if ( ! empty( $errors ) ) {
		foreach ( $errors as $error ) {
				echo '<div class="mds-error">' . $error . '</div>';
			}

			display_edit_order_button( $order_row['order_id'] );

			return;
	}

	$has_packages = banner_get_packages( $BID );

	$cannot_get_package = false;

	if ( $has_packages && isset( $_REQUEST['pack'] ) && $_REQUEST['pack'] != '' ) {
		// has packages, and a package was selected...

		// check to make sure this advertiser can order this package

		if ( can_user_get_package( get_current_user_id(), $_REQUEST['pack'] ) ) {

			$quantity = $wpdb->get_var( $wpdb->prepare( "SELECT quantity FROM " . MDS_DB_PREFIX . "orders WHERE order_id=%s", Orders::get_current_order_id() ) );
			if ( $wpdb->last_error ) {
				mds_sql_error( $wpdb->last_error );
			}

			$block_count = $quantity / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

			// Now update the order (overwrite the total & days_expire with the package)
			$pack  = get_package( $_REQUEST['pack'] );
			$total = $pack['price'] * $block_count;

			// convert & round off
			$total = Currency::convert_to_default_currency( $pack['currency'], $total );

			$wpdb->update(
				MDS_DB_PREFIX . "orders",
				[
					'package_id' => intval( $_REQUEST['pack'] ),
					'price' => floatval( $total ),
					'days_expire' => intval( $pack['days_expire'] ),
					'currency' => Currency::get_default_currency()
				],
				[ 'order_id' => Orders::get_current_order_id() ],
				[ '%d', '%f', '%d', '%s' ],
				[ '%s' ]
			);
			if ( $wpdb->last_error ) {
				mds_sql_error( $wpdb->last_error );
			}

			$order_row['price']       = $total;
			$order_row['pack']        = $_REQUEST['pack'];
			$order_row['days_expire'] = $pack['days_expire'];
			$order_row['currency']    = Currency::get_default_currency();
		} else {
			$selected_pack      = $_REQUEST['pack'];
			$_REQUEST['pack']   = '';
			$cannot_get_package = true;
		}
	}

	$privileged = carbon_get_user_meta( get_current_user_id(), MDS_PREFIX . 'privileged' );

	// Check if order confirmation is disabled
	if ( Options::get_option( 'confirm-orders', 'yes' ) == 'no' ) {
		$params             = [];
		$params['BID']      = $BID;
		$params['order_id'] = $order_row['order_id'];

		if ( ( $order_row['price'] == 0 ) || ( $privileged == '1' ) ) {
			Steps::update_step( 'complete' );
			$params['mds-action'] = 'complete';

			// go straight to publish...
			$page_url = Utility::get_page_url( 'manage' );
		} else {
			Steps::update_step( 'payment' );
			$params['mds-action'] = 'confirm';
			$params['_wpnonce'] = wp_create_nonce( 'mds-confirm-action' );

			// go to payment
			$page_url = Utility::get_page_url( 'payment' );
		}

		Utility::redirect( $page_url, $params );
		exit;
	}

	require_once MDS_CORE_PATH . "html/header.php";

	Utility::show_nav_status( 3 );

	$p_max_ord = 0;
	if ( ( $has_packages ) && ( isset( $_REQUEST['pack'] ) && $_REQUEST['pack'] == '' ) ) {
		?>
        <form method="post" name="confirm-order" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'mds-form' ); ?>
            <input type="hidden" name="action" value="mds_form_submission">
            <input type="hidden" name="mds_dest" value="confirm-order">
            <input type="hidden" name="selected_pixels" value="<?php echo isset( $_REQUEST['selected_pixels'] ) ? htmlspecialchars( $_REQUEST['selected_pixels'] ) : ''; ?>">
            <input type="hidden" name="order_id" value="<?php echo intval( $order_row['order_id'] ); ?>">
            <input type="hidden" name="BID" value="<?php echo $BID; ?>">
			<?php
			display_package_options_table( $BID, $_REQUEST['pack'], true );
			?>
            <input class='big_button' type='button' value='<?php echo esc_attr( Language::get( '<< Previous' ) ); ?>'
			onclick='window.location="<?php echo esc_url( Utility::get_page_url( 'write-ad', [ 'BID' => $BID, 'aid' => $order_row['ad_id'] ] ) ); ?>"'>
            <input class='big_button' type='submit' value='<?php echo esc_attr( Language::get( 'Next >>' ) ); ?>'>
        </form>
		<?php
		if ( $cannot_get_package ) {

			$p_row = $wpdb->get_row( $wpdb->prepare( "SELECT * from " . MDS_DB_PREFIX . "packages where package_id=%d", intval( $selected_pack ) ), ARRAY_A );
			if ( $wpdb->last_error ) {
				mds_sql_error( $wpdb->last_error );
			}
			$p_max_ord = $p_row['max_orders'];

			Language::out_replace( '<p><span style="color:red">Error: Cannot place order. This price option is limited to %MAX_ORDERS% per customer.</span><br/>Please select another option, or check your order history under <a href="%MANAGE_URL%">Manage Pixels</a>.</p>', [
				'%MAX_ORDERS%',
				'%MANAGE_URL%'
			], [ $p_row['max_orders'], esc_url( Utility::get_page_url( 'manage' ) ) ] );
		}
	} else {

		Orders::display_order( $order_row['order_id'], $BID );

		?>
        <div class="mds-button-container">
		<?php

		display_edit_order_button( $order_row['order_id'] );

		if ( ! Orders::can_user_order( $banner_data, get_current_user_id(), ( $_REQUEST['pack'] ?? 0 ) ) ) {
			// one more check before continue

			if ( ! $p_max_ord ) {
				$max = $banner_data['G_MAX_ORDERS'];
			} else {
				$max = $p_max_ord;
			}

			Language::out_replace( '<p><span style="color:red">Error: Cannot place order. This price option is limited to %MAX_ORDERS% per customer.</span><br/>Please select another option, or check your order history under <a href="%MANAGE_URL%">Manage Pixels</a>.</p>', [
				'%MAX_ORDERS%',
				'%MANAGE_URL%'
			], [ $max, Utility::get_page_url( 'manage' ) ] );
		} else {

			// Check if order confirmation is enabled
			if ( Options::get_option( 'confirm-orders', 'yes' ) == 'yes' ) {
				// Confirm order page buttons
				if ( ( $order_row['price'] == 0 ) || ( $privileged == '1' ) ) {
					// go straight to publish...
					?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mds-inline-form">
                        <input type="hidden" name="action" value="mds_complete_order" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr( $order_row['order_id'] ); ?>" />
                        <input type="hidden" name="BID" value="<?php echo esc_attr( $BID ); ?>" />
                        <?php wp_nonce_field( 'mds_complete_order_' . $order_row['order_id'] ); ?>
                        <button type="submit" class="mds-button mds-complete"><?php echo esc_html( Language::get( 'Complete Order' ) ); ?></button>
                    </form>
					<?php
				} else {
					// go to payment
					?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mds-inline-form">
                        <input type="hidden" name="action" value="mds_confirm_order" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr( $order_row['order_id'] ); ?>" />
                        <input type="hidden" name="BID" value="<?php echo esc_attr( $BID ); ?>" />
                        <?php wp_nonce_field( 'mds_confirm_order_' . $order_row['order_id'] ); ?>
                        <button type="submit" class="mds-button mds-confirm"><?php echo esc_html( Language::get( 'Confirm & Pay' ) ); ?></button>
                    </form>
					<?php
				}
				?>
                </div>
				<?php
			}
		}
		?>
		<?php
	}
}

require_once MDS_CORE_PATH . "html/footer.php";
