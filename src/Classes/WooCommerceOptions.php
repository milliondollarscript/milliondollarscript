<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.6
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

use Carbon_Fields\Field;

defined( 'ABSPATH' ) or exit;

class WooCommerceOptions {
	function __construct() {
		add_filter( 'mds_options_fields', [ __CLASS__, 'mds_options_fields' ], 10, 2 );
		add_filter( 'mds_options_save', [ __CLASS__, 'mds_options_save' ], 10, 2 );
	}

	public static function mds_options_fields( $fields, $prefix ) {
		$wc_fields = [

			// WooCommerce Integration
			Field::make( 'separator', $prefix . 'separator', __( 'WooCommerce' ) ),

			Field::make( 'checkbox', $prefix . 'woocommerce', __( 'WooCommerce Integration', 'milliondollarscript' ) )
			     ->set_default_value( 'no' )
			     ->set_option_value( 'yes' )
			     ->set_help_text( __( 'Enable WooCommerce integration. This will attempt to process orders through WooCommerce. Enabling this will automatically install and enable the "WooCommerce" payment module in MDS and create a product in WC for it.', 'milliondollarscript' ) ),

			// WooCommerce Product
			Field::make( 'association', $prefix . 'product', __( 'Product', 'milliondollarscript' ) )
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
			     ->set_help_text( __( 'The product for MDS to use. You should create a new product in WooCommerce and check the Million Dollar Script ', 'milliondollarscript' ) ),

			// WooCommerce Clear Cart
			Field::make( 'checkbox', $prefix . 'clear-cart', __( 'Clear cart', 'milliondollarscript' ) )
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
			     ->set_help_text( __( 'Clear the cart when a MDS product is added to it.', 'milliondollarscript' ) ),

			// WooCommerce Auto-approve
			Field::make( 'checkbox', $prefix . 'auto-approve', __( 'Auto-approve', 'milliondollarscript' ) )
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
			     ->set_help_text( __( 'Setting to Yes will automatically approve orders before payments are verified by an admin.', 'milliondollarscript' ) ),
		];

		return array_merge( $fields, $wc_fields );
	}

	public static function mds_options_save( $name, $field ) {
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
			default:
				break;
		}

		return $field;
	}
}
