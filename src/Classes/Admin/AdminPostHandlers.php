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
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *    Million Dollar Script
 *    Pixels to Profit: Ignite Your Revolution
 *    https://milliondollarscript.com/
 *
 */

namespace MillionDollarScript\Classes\Admin;

use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Logs;
use MillionDollarScript\Classes\System\Utility;

defined( 'ABSPATH' ) or exit;

/**
 * Admin-post handlers for user-facing order actions.
 *
 * These run before any theme output, then redirect immediately to preserve clean headers.
 */
class AdminPostHandlers {
	/**
	 * Register admin-post hooks for logged-in and non-logged-in users.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_mds_confirm_order', [ self::class, 'confirm_order' ] );
		add_action( 'admin_post_mds_complete_order', [ self::class, 'complete_order' ] );
		add_action( 'admin_post_mds_cancel_order', [ self::class, 'cancel_order' ] );

		// Defensive: redirect non-logged-in users to login page, preserving referer
		add_action( 'admin_post_nopriv_mds_confirm_order', [ self::class, 'redirect_to_login' ] );
		add_action( 'admin_post_nopriv_mds_complete_order', [ self::class, 'redirect_to_login' ] );
		add_action( 'admin_post_nopriv_mds_cancel_order', [ self::class, 'redirect_to_login' ] );
	}

	/**
	 * Redirect anonymous users to login.
	 *
	 * @return void
	 */
	public static function redirect_to_login(): void {
		$redirect_to = wp_get_referer();
		if ( ! $redirect_to ) {
			$redirect_to = home_url( '/' );
		}
		wp_safe_redirect( wp_login_url( $redirect_to ) );
		exit;
	}

	/**
	 * Ensure user is authenticated.
	 *
	 * @return void
	 */
	protected static function require_auth(): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect(); // exits
		}
	}

	/**
	 * Get an integer POST value safely.
	 *
	 * @param string $key
	 *
	 * @return int
	 */
	protected static function post_int( string $key ): int {
		return isset( $_POST[ $key ] ) ? absint( wp_unslash( $_POST[ $key ] ) ) : 0;
	}

	/**
	 * Verify a per-order nonce (action_{order_id}).
	 *
	 * @param string $base_action Base action name (e.g., 'mds_confirm_order').
	 * @param int    $order_id    Order ID.
	 *
	 * @return void
	 */
	protected static function verify_nonce( string $base_action, int $order_id ): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$action = $base_action . '_' . $order_id;
		if ( ! $order_id || ! $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
			Logs::log( '[MDS] Security: Nonce failed', [ 'action' => $action, 'order_id' => $order_id, 'user' => get_current_user_id() ] );
			wp_die( esc_html__( 'Security check failed. Please try again.', 'milliondollarscript-two' ), esc_html__( 'Security Error', 'milliondollarscript-two' ), [ 'response' => 403 ] );
		}
	}

	/**
	 * Enforce that the current user owns the order (or is admin).
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	protected static function enforce_ownership( int $order_id ): void {
		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			wp_die( esc_html__( 'Not authorized.', 'milliondollarscript-two' ), esc_html__( 'Error', 'milliondollarscript-two' ), [ 'response' => 403 ] );
		}
		if ( ! Orders::is_owned_by( $order_id, $current_user_id ) && ! current_user_can( 'manage_options' ) ) {
			Logs::log( '[MDS] Security: Ownership denied', [ 'order_id' => $order_id, 'user' => $current_user_id ] );
			wp_die( esc_html__( 'Order not found or access denied.', 'milliondollarscript-two' ), esc_html__( 'Error', 'milliondollarscript-two' ), [ 'response' => 403 ] );
		}
	}

	/**
	 * Handle Confirm & Pay: redirect to payment page with mds-action=confirm.
	 * Preserve existing behavior; payment.php will process.
	 *
	 * @return void
	 */
	public static function confirm_order(): void {
		self::require_auth();
		$order_id = self::post_int( 'order_id' );
		$BID      = self::post_int( 'BID' );

		self::verify_nonce( 'mds_confirm_order', $order_id );
		self::enforce_ownership( $order_id );

		$confirm_nonce = wp_create_nonce( 'mds-confirm-action' );
		$dest          = Utility::get_page_url( 'payment' );
		if ( empty( $dest ) ) {
			$dest = home_url( '/' );
		}
		Utility::redirect( $dest, [
			'mds-action' => 'confirm',
			'order_id'   => $order_id,
			'BID'        => $BID,
			'_wpnonce'   => $confirm_nonce,
		] );
	}

	/**
	 * Handle Complete Order button (free/privileged path): redirect to payment with mds-action=complete.
	 * Preserve existing behavior; payment.php will process.
	 *
	 * @return void
	 */
	public static function complete_order(): void {
		self::require_auth();
		$order_id = self::post_int( 'order_id' );
		$BID      = self::post_int( 'BID' );

		self::verify_nonce( 'mds_complete_order', $order_id );
		self::enforce_ownership( $order_id );

		$order = Orders::get_order( $order_id );
		$price = null;
		if ( $order ) {
			$price = is_object( $order ) ? $order->price : ( $order['price'] ?? null );
		}
		$is_free = $price !== null && floatval( $price ) <= 0;

		$privileged_flag = carbon_get_user_meta( get_current_user_id(), MDS_PREFIX . 'privileged' );
		$is_privileged  = in_array( strtolower( (string) $privileged_flag ), [ '1', 'yes', 'true', 'privileged' ], true );

		if ( $order && ( $is_free || $is_privileged ) ) {
			Orders::complete_order( get_current_user_id(), $order_id );
			Orders::reset_order_progress();

			$custom_thank_you = Options::get_option( 'thank-you-page', '' );
			if ( ! empty( $custom_thank_you ) ) {
				Utility::redirect( $custom_thank_you );
			}

			$thank_you_url = Utility::get_page_url( 'thank-you' );
			if ( ! empty( $thank_you_url ) ) {
				Utility::redirect( $thank_you_url );
			}

			Utility::redirect( Utility::get_page_url( 'manage', [ 'mds_notice' => 'order_completed', 'order_id' => $order_id ] ) );
		}

		$dest = Utility::get_page_url( 'payment' );
		if ( empty( $dest ) ) {
			$dest = home_url( '/' );
		}
		$confirm_nonce = wp_create_nonce( 'mds-confirm-action' );
		Utility::redirect( $dest, [
			'mds-action' => 'complete',
			'order_id'   => $order_id,
			'BID'        => $BID,
			'_wpnonce'   => $confirm_nonce,
		] );
	}

	/**
	 * Handle Cancel: immediately cancel server-side and redirect back to Manage.
	 *
	 * @return void
	 */
	public static function cancel_order(): void {
		self::require_auth();
		$order_id = self::post_int( 'order_id' );

		self::verify_nonce( 'mds_cancel_order', $order_id );
		self::enforce_ownership( $order_id );

		Orders::cancel_order( $order_id, false );

		$dest = Utility::get_page_url( 'manage' );
		if ( empty( $dest ) ) {
			$dest = home_url( '/' );
		}
		Utility::redirect( $dest, [
			'mds_notice' => 'order_cancelled',
			'order_id'   => $order_id,
		] );
	}
}
