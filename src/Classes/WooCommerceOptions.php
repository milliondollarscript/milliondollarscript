<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.6
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

namespace MillionDollarScript\Classes;

use Carbon_Fields\Field;

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

			Field::make( 'checkbox', $prefix . 'woocommerce', Language::get( 'WooCommerce Integration' ) )
			     ->set_default_value( 'no' )
			     ->set_option_value( 'yes' )
			     ->set_help_text( Language::get( 'Enable WooCommerce integration. This will attempt to process orders through WooCommerce. Enabling this will automatically install and enable the "WooCommerce" payment module in MDS and create a product in WC for it.' ) ),

			// WooCommerce Product
			Field::make( 'association', $prefix . 'product', Language::get( 'Product' ) )
			     ->set_conditional_logic( array(
				     'relation' => 'AND',
				     array(
					     'field'   => $prefix . 'woocommerce',
					     'compare' => '=',
					     'value'   => true,
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
			Field::make( 'checkbox', $prefix . 'clear-cart', Language::get( 'Clear cart' ) )
			     ->set_default_value( 'no' )
			     ->set_option_value( 'yes' )
			     ->set_conditional_logic( array(
				     'relation' => 'AND',
				     array(
					     'field'   => $prefix . 'woocommerce',
					     'compare' => '=',
					     'value'   => true,
				     )
			     ) )
			     ->set_help_text( Language::get( 'Clear the cart when a MDS product is added to it.' ) ),

			// WooCommerce de-dupe variations
			Field::make( 'checkbox', $prefix . 'dedupe', Language::get( 'Remove duplicate variations' ) )
			     ->set_default_value( 'no' )
			     ->set_option_value( 'yes' )
			     ->set_conditional_logic( array(
				     'relation' => 'AND',
				     array(
					     'field'   => $prefix . 'woocommerce',
					     'compare' => '=',
					     'value'   => true,
				     )
			     ) )
			     ->set_help_text( Language::get( 'Checking this will remove all duplicate variations on the MDS product when the options are saved.' ) ),
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
					Functions::enable_woocommerce_payments();
				} else {
					Functions::disable_woocommerce_payments();
				}
				break;
			case 'product':
				if ( class_exists( 'woocommerce' ) ) {
					$field = Functions::set_default_product( $field );
				}
				break;
			case 'auto-approve':
				Functions::woocommerce_auto_approve( $field->get_value() );
				break;
			case 'dedupe':
				if ( $field->get_value() == 'yes' ) {
					\MillionDollarScript\Classes\WooCommerce::delete_duplicate_variations( Functions::get_product() );
				}
				break;
			default:
				break;
		}

		return $field;
	}
}
