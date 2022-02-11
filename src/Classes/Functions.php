<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.1
 * @author Ryan Rhode
 * @copyright (C) 2022, Ryan Rhode
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3
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
 */

namespace MillionDollarScript\Classes;

defined( 'ABSPATH' ) or exit;

class Functions {

	/**
	 * Enqueue scripts and styles
	 */
	public static function enqueue_scripts() {
		wp_enqueue_style( 'mds-css', MDS_BASE_URL . 'src/Assets/css/mds.css', [], filemtime( MDS_BASE_PATH . 'src/Assets/css/mds.css' ) );

		wp_enqueue_script( 'mds-js', MDS_BASE_URL . 'src/Assets/js/mds.js', [ 'jquery' ], filemtime( MDS_BASE_PATH . 'src/Assets/js/mds.js' ), true );

		wp_add_inline_script( 'mds-js', 'const MDS = ' . json_encode( array(
				'users' => Options::get_option( 'users', 'options', 'no' ),
			) ), 'before' );
	}

	public static function get_product_id() {
		// Check for MDS product, if one doesn't exist create one.
		$product_id = 0;

		$wc_query = new \WP_Query( [
			'posts_per_page' => 1,
			'post_type'      => 'product',
			'meta_key'       => '_milliondollarscript',
			'meta_value'     => 'yes',
			'meta_compare'   => '=='
		] );
		if ( $wc_query->have_posts() ) {
			while ( $wc_query->have_posts() ) {
				$wc_query->the_post();
				$product_id = get_the_ID();
			}
			wp_reset_postdata();
		} else {

			// Insert the post into the database
			$product_id = wp_insert_post( [
				'post_title'   => 'Pixels',
				'post_content' => '',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_type'    => 'product'
			] );

			if ( $product_id ) {
				add_post_meta( $product_id, '_regular_price', 1 );
				add_post_meta( $product_id, '_price', 1 );
				add_post_meta( $product_id, '_stock_status', 'instock' );
				add_post_meta( $product_id, '_milliondollarscript', 'yes' );
			}
		}

		return $product_id;
	}

	public static function enable_woocommerce_payments() {
		global $wpdb;

		// Disable "Payment" module
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'EXTERNAL_ENABLED', 'val' => 'N' ] );

		// Enable WooCommerce module
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_ENABLED', 'val' => 'Y' ] );

		$product_id = self::get_product_id();

		$checkout_url = wc_get_checkout_url() . '?add-to-cart=' . $product_id . '&quantity=%QUANTITY%';
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_URL', 'val' => $checkout_url ] );

		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_AUTO_APPROVE', 'val' => 'yes' ] );
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_BUTTON_TEXT', 'val' => '' ] );
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_BUTTON_IMAGE', 'val' => '' ] );
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_REDIRECT', 'val' => 'yes' ] );
	}

	public static function disable_woocommerce_payments() {
		global $wpdb;
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_ENABLED', 'val' => 'N' ] );
	}

	public static function woocommerce_auto_approve( $val = 'yes' ) {
		global $wpdb;
		if ( empty( $val ) ) {
			$val = 'no';
		}
		$wpdb->replace( MDS_DB_PREFIX . 'config', [ 'key' => 'WOOCOMMERCE_AUTO_APPROVE', 'val' => $val ] );
	}

	public static function set_default_product( \Carbon_Fields\Field\Field $field ) {
		$product_id = self::get_product_id();

		$field->set_value( [
			[
				'value'   => 'post:product:' . $product_id,
				'type'    => 'post',
				'subtype' => 'product',
				'id'      => $product_id,
			],
		] );

		return $field;
	}
}
