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

namespace MillionDollarScript\Classes\WooCommerce;

use Carbon_Fields\Field;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

class WooCommerceOptions {

	function __construct() {
		add_filter( 'mds_options', [ __CLASS__, 'mds_options' ], 10, 2 );
		add_filter( 'mds_options_save', [ __CLASS__, 'mds_options_save' ], 10, 2 );
	}

	/**
	 * Add WooCommerce tab to Options page.
	 *
	 * @param array $tabs
	 * @param string $prefix
	 *
	 * @return array
	 */
	public static function mds_options( array $tabs, string $prefix ): array {

		$tabs[ Language::get( 'WooCommerce' ) ] = [
			// WooCommerce Integration
			Field::make( 'separator', $prefix . 'separator', Language::get( 'WooCommerce' ) ),

			Field::make( 'radio', $prefix . 'woocommerce', Language::get( 'WooCommerce Integration' ) )
				->set_default_value( 'no' )
				->set_options( [
								'no' => Language::get( 'No' ),
								'yes' => Language::get( 'Yes' ),
							] )
				->set_help_text( Language::get( 'Enable WooCommerce integration. This will attempt to process orders through WooCommerce. Enabling this will automatically install and enable the "WooCommerce" payment module in MDS and create a product in WC for it.' ) ),

			// Hint: registration on My Account page
			Field::make( 'html', $prefix . 'wc_registration_hint', Language::get( 'Registration on My Account page' ) )
			     ->set_conditional_logic( [
				     'relation' => 'AND',
				     [
					     'field'   => $prefix . 'woocommerce',
					     'compare' => '=',
					     'value'   => 'yes',
				     ],
			     ] )
			     ->set_html(
				     '<p class="description">' .
					 Language::get( 'To show a registration form on WooCommerce\'s My Account page, enable the setting in WooCommerce > Settings > Accounts & Privacy under Account creation:' ) .
				     ' <strong>' . Language::get( 'Allow customers to create an account > On "My account" page' ) . '</strong>. ' .
				     '<a href="' . esc_url( admin_url( add_query_arg( [ 'page' => 'wc-settings', 'tab' => 'account' ], 'admin.php' ) ) ) . '">' . Language::get( 'Open WooCommerce Accounts & Privacy settings' ) . '</a>.' .
				     '</p>'
			     ),

			// WooCommerce Product
			Field::make( 'association', $prefix . 'product', Language::get( 'Product' ) )
			     ->set_conditional_logic( array(
				     'relation' => 'AND',
				     array(
					     'field'   => $prefix . 'woocommerce',
					     'compare' => '=',
					     'value'   => 'yes',
				     )
			     ) )
			     ->set_types( array(
				     array(
					     'type'      => 'post',
					     'post_type' => 'product',
				     )
			     ) )
			     ->set_min( 1 )
			     ->set_max( 1 )
			     ->set_help_text( Language::get( 'The product for MDS to use. You should create a new product in WooCommerce and tick the Million Dollar Script Pixels checkbox on it.' ) ),

			// WooCommerce Clear Cart
			Field::make( 'radio', $prefix . 'clear-cart', Language::get( 'Clear cart' ) )
			     ->set_default_value( 'no' )
			     ->set_options( [
				     'no' => Language::get( 'No' ),
				     'yes' => Language::get( 'Yes' ),
				 ] )
			     ->set_conditional_logic( array(
				     'relation' => 'AND',
				     array(
					     'field'   => $prefix . 'woocommerce',
					     'compare' => '=',
					     'value'   => 'yes',
				     )
			     ) )
			     ->set_help_text( Language::get( 'Clear the cart when a MDS product is added to it.' ) ),

			// WooCommerce de-dupe variations
			Field::make( 'radio', $prefix . 'dedupe', Language::get( 'Remove duplicate variations' ) )
			     ->set_default_value( 'no' )
			     ->set_options( [
				     'no' => Language::get( 'No' ),
				     'yes' => Language::get( 'Yes' ),
				 ] )
			     ->set_conditional_logic( array(
				     'relation' => 'AND',
				     array(
					     'field'   => $prefix . 'woocommerce',
					     'compare' => '=',
					     'value'   => 'yes',
				     )
			     ) )
			     ->set_help_text( Language::get( 'Checking this will remove all duplicate variations on the MDS product when the options are saved.' ) ),

			// WooCommerce Auto-complete
			Field::make( 'radio', $prefix . 'wc-auto-complete', Language::get( 'Auto-complete Orders' ) )
			     ->set_default_value( 'yes' )
			     ->set_options( [
				     'no' => Language::get( 'No' ),
				     'yes' => Language::get( 'Yes' ),
				 ] )
			     ->set_conditional_logic( array(
				     'relation' => 'AND',
				     array(
					     'field'   => $prefix . 'woocommerce',
					     'compare' => '=',
					     'value'   => 'yes',
				     )
			     ) )
			     ->set_help_text( Language::get( 'Immediately mark the linked MDS order as completed when WooCommerce changes the order status out of "on-hold". For manual/offline checkouts that bypass WooCommerce entirely, use the “Auto-complete manual payments” option on the Orders tab (visible only when WooCommerce integration is disabled).' ) ),
		];

		return $tabs;
	}

	/**
	 * @param $name string The field name without the prefix
	 * @param $field \Carbon_Fields\Field\Field The Carbon Fields field
	 *
	 * @return \Carbon_Fields\Field\Field
	 */
	public static function mds_options_save( \Carbon_Fields\Field\Field $field, string $name ) {
		switch ( $name ) {
			case 'woocommerce':
				if ( class_exists( 'woocommerce' ) && $field->get_value() == 'yes' ) {
					WooCommerceFunctions::enable_woocommerce_payments();
					// If Account/Register/Login URLs are still defaults, point them to WC My Account
					WooCommerceFunctions::sync_account_register_login_pages_to_myaccount_if_defaults();
				} else {
					WooCommerceFunctions::disable_woocommerce_payments();
				}
				break;
			case 'product':
				if ( class_exists( 'woocommerce' ) ) {
					$field = WooCommerceFunctions::set_default_product( $field );
				}
				break;
			case 'auto-approve':
				WooCommerceFunctions::woocommerce_auto_approve( $field->get_value() );
				break;
			case 'dedupe':
				if ( $field->get_value() == 'yes' ) {
					WooCommerceFunctions::delete_duplicate_variations( WooCommerceFunctions::get_product() );
				}
				break;
			default:
				break;
		}

		return $field;
	}
}
