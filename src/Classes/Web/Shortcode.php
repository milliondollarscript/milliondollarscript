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

namespace MillionDollarScript\Classes\Web;

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\System\Functions;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\WooCommerce\WooCommerceFunctions;

defined( 'ABSPATH' ) or exit;

class Shortcode {
	public static function defaults(): array {

		return array(
			'id'     => 1,
			'align'  => 'center',
			'width'  => 0,
			'height' => 0,
			'lang'   => 'EN',
			'type'   => 'grid',
		);
	}

	/**
	 * Add milliondollarscript shortcode
	 *
	 * Example usage:
	 * Grid: [milliondollarscript id="1" align="center" width="1000px" height="1000px" type="grid"]
	 * Users: [milliondollarscript id="1" align="center" width="100%" height="auto" type="users"]
	 * List: [milliondollarscript id="1" align="center" width="100%" height="auto" type="list"]
	 * Stats: [milliondollarscript id="1" align="center" width="150px" height="60px" type="stats"]
	 *
	 * @param $atts
	 *
	 * @return string
	 */
	public static function shortcode( $atts ): string {
		if ( Utility::should_stop() && ! wp_doing_ajax() ) {
			return '';
		}

		$atts = shortcode_atts(
			self::defaults(),
			$atts,
			'milliondollarscript'
		);

		$atts = Functions::maybe_set_dimensions( $atts );

		$container_id = uniqid( sanitize_title( $atts['type'] ) . '-' );

		// compile variables to pass to javascript
		$mds_params = array(
			'mds_type'         => $atts['type'],
			'mds_container_id' => $container_id,
			'mds_width'        => $atts['width'],
			'mds_height'       => $atts['height'],
		);

		if ( isset( $_REQUEST['mds-action'] ) && $_REQUEST['mds-action'] == 'confirm' ) {
			update_user_meta( get_current_user_id(), 'mds_confirm', true );
		}

		// Add inline function call to each MDS iframe in the AJAX response
		$mds_data = array(
			'type'         => $mds_params['mds_type'],
			'container_id' => $mds_params['mds_container_id'],
			'align'        => $atts['align'],
			'width'        => $mds_params['mds_width'],
			'height'       => $mds_params['mds_height'],
			'id'           => $atts['id']
		);

		// mds_shortcode action
		do_action( 'mds_shortcode', $atts, $mds_params, $mds_data );

		// Return the container div and the data as a data attribute
		return '<div class="mds-shortcode-container" id="' . esc_attr( $container_id ) . '" data-mds-params="' . esc_attr( json_encode( $mds_data ) ) . '"></div>';
	}

	/**
	 * Shortcode extension
	 *
	 * @param $atts
	 * @param $mds_params
	 * @param $mds_data
	 *
	 * @return void
	 */
	public static function extension( $atts, $mds_params, $mds_data ): void {

		// Handle payment shortcode here so that it can immediately redirect to the checkout page.
		if ( $atts['type'] == 'payment' ) {
			require_once MDS_CORE_PATH . 'users/payment.php';

			$checkout_url         = Options::get_option( 'checkout-url' );
			$is_wc_active         = WooCommerceFunctions::is_wc_active();
			// Check if WooCommerce integration is enabled
			$is_wc_option_enabled = Options::get_option( 'woocommerce' );
			$is_order_id_present  = ! empty( $_REQUEST['order_id'] );
			$is_user_logged_in    = is_user_logged_in();

			if ( ! empty( $checkout_url ) || ( $is_wc_active && $is_wc_option_enabled && $is_order_id_present && $is_user_logged_in ) ) {
				if ( ! empty( $checkout_url ) ) {
					wp_safe_redirect( $checkout_url );
					exit;
				}

				if ( ! wp_doing_ajax() ) {
					wp_safe_redirect( add_query_arg( 'update_cart', wp_create_nonce( 'woocommerce-cart' ), wc_get_checkout_url() ) );
					exit;
				}
			}
		}

		// load scripts
		Functions::register_scripts();
		Functions::enqueue_scripts();
	}
}
