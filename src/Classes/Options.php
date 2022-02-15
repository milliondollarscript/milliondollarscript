<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.2
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

use Carbon_Fields\Container;
use Carbon_Fields\Field;

defined( 'ABSPATH' ) or exit;

class Options {
	public const prefix = 'milliondollarscript_';

	/**
	 * Register the options.
	 *
	 * @link https://docs.carbonfields.net#/containers/theme-options
	 */
	public static function register() {

		Container::make( 'theme_options', self::prefix . 'options', __( 'Million Dollar Script Options', 'milliondollarscript' ) )
		         ->set_page_parent( 'MillionDollarScript' )
		         ->set_page_file( self::prefix . 'options' )
		         ->set_page_menu_title( 'Options' )
		         ->add_fields( array(

			         // MDS install path
			         Field::make( 'text', self::prefix . 'path', __( 'MDS install path', 'milliondollarscript' ) )
			              ->set_default_value( wp_normalize_path( MDS_CORE_PATH ) )
			              ->set_help_text( __( 'The path to the folder where MDS is installed.', 'milliondollarscript' ) ),

			         // Admin Integration
			         Field::make( 'checkbox', self::prefix . 'admin', __( 'Admin Integration', 'milliondollarscript' ) )
			              ->set_default_value( 'yes' )
			              ->set_option_value( 'yes' )
			              ->set_help_text( __( 'Enable admin integration. This will add a new MU plugin to the wp-content/mu-plugins folder in order to adjust some cookie settings. Once you enable this you will be logged out of WP and when you log back in you will be see an Admin submenu under Million Dollar Script to access MDS admin.', 'milliondollarscript' ) ),

			         // Users Integration
			         Field::make( 'checkbox', self::prefix . 'users', __( 'Users Integration', 'milliondollarscript' ) )
			              ->set_default_value( 'yes' )
			              ->set_option_value( 'yes' )
			              ->set_help_text( __( 'Enable integration with MDS users. This will register users in MDS when they are registered in WP.', 'milliondollarscript' ) ),

			         // WooCommerce Integration
			         Field::make( 'checkbox', self::prefix . 'woocommerce', __( 'WooCommerce Integration', 'milliondollarscript' ) )
			              ->set_default_value( 'no' )
			              ->set_option_value( 'yes' )
			              ->set_help_text( __( 'Enable WooCommerce integration. This will attempt to process orders through WooCommerce. Recommended to install and enable the "Payment" payment module in MDS and creating a product in WC for it.', 'milliondollarscript' ) ),

			         // WooCommerce Product
			         Field::make( 'association', self::prefix . 'product', __( 'Product', 'milliondollarscript' ) )
			              ->set_conditional_logic( array(
				              'relation' => 'AND',
				              array(
					              'field'   => self::prefix . 'woocommerce',
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
			         Field::make( 'checkbox', self::prefix . 'clear-cart', __( 'Clear cart', 'milliondollarscript' ) )
			              ->set_default_value( 'no' )
			              ->set_option_value( 'yes' )
			              ->set_conditional_logic( array(
				              'relation' => 'AND',
				              array(
					              'field'   => self::prefix . 'woocommerce',
					              'compare' => '=',
					              'value'   => true,
				              )
			              ) )
			              ->set_help_text( __( 'Clear the cart when a MDS product is added to it.', 'milliondollarscript' ) ),

			         // WooCommerce Auto-approve
			         Field::make( 'checkbox', self::prefix . 'auto-approve', __( 'Auto-approve', 'milliondollarscript' ) )
			              ->set_default_value( 'no' )
			              ->set_option_value( 'yes' )
			              ->set_conditional_logic( array(
				              'relation' => 'AND',
				              array(
					              'field'   => self::prefix . 'woocommerce',
					              'compare' => '=',
					              'value'   => true,
				              )
			              ) )
			              ->set_help_text( __( 'Setting to Yes will automatically approve orders before payments are verified by an admin.', 'milliondollarscript' ) ),

			         // Register page
			         Field::make( 'text', self::prefix . 'register-page', __( 'Register Page', 'milliondollarscript' ) )
			              ->set_default_value( wp_registration_url() )
			              ->set_help_text( __( 'The page where users can register for an account. If left empty will redirect to the default WP registration page. Set the height to auto for the Buy Pixels/Users page to auto scale to fit in the height of the page. If using a custom login page such as with Ultimate Member then enter the full registration page URL here.', 'milliondollarscript' ) ),

			         // Validate page
			         Field::make( 'text', self::prefix . 'validate-page', __( 'Validate Page', 'milliondollarscript' ) )
			              ->set_default_value( "" )
			              ->set_help_text( __( 'The page where users can validate their account. You should add the Million Dollar Script validation block/shortcode there. Set the height to auto for the Buy Pixels/Users page to auto scale to fit in the height of the page. If using a custom login page such as with Ultimate Member then you likely do not have to use this feature.', 'milliondollarscript' ) ),

			         // Login page
			         Field::make( 'text', self::prefix . 'login-page', __( 'Login Page', 'milliondollarscript' ) )
			              ->set_default_value( wp_login_url() )
			              ->set_help_text( __( 'The login page to redirect users to. If left empty will redirect to the default WP login. Set the height to auto for the Buy Pixels/Users page to auto scale to fit in the height of the page. If using a custom login page such as with Ultimate Member then enter the full login URL here.', 'milliondollarscript' ) ),

			         // Forgot password page
			         Field::make( 'text', self::prefix . 'forgot-password-page', __( 'Forgot Password Page', 'milliondollarscript' ) )
			              ->set_default_value( wp_lostpassword_url() )
			              ->set_help_text( __( 'The URL to the forgot password page. If left empty will redirect to the default WP forgot password page. Set the height to auto for the Buy Pixels/Users page to auto scale to fit in the height of the page. If using a custom login page such as with Ultimate Member then enter the full forgot password page URL here.', 'milliondollarscript' ) ),

			         // Login Redirect
			         Field::make( 'text', self::prefix . 'login-redirect', __( 'Login Redirect', 'milliondollarscript' ) )
			              ->set_default_value( wp_login_url() )
			              ->set_help_text( __( 'The URL to redirect users to after login.', 'milliondollarscript' ) ),

			         // Delete data on uninstall
			         Field::make( 'checkbox', self::prefix . 'delete-data', __( 'Delete data on uninstall?', 'milliondollarscript' ) )
			              ->set_default_value( 'no' )
			              ->set_option_value( 'yes' )
			              ->set_help_text( __( 'If yes then all database tables created by this plugin will be completely deleted when the plugin is uninstalled.', 'milliondollarscript' ) ),

		         ) );
	}

	public static function load() {
		\Carbon_Fields\Carbon_Fields::boot();
	}

	/**
	 * Hook into Carbon FIelds filter to sanitize some fields on save.
	 *
	 * @param $field \Carbon_Fields\Field\Field
	 *
	 * @return \Carbon_Fields\Field\Field
	 */
	public static function save( \Carbon_Fields\Field\Field $field ): \Carbon_Fields\Field\Field {
		$name = str_replace( '_' . self::prefix, '', $field->get_name() );

		switch ( $name ) {
			case 'path':
				// Normalize path field
				$field->set_value( wp_normalize_path( $field->get_value() ) );
				break;
			case 'users':
			case 'admin':
				// Save admin defines if enabled
				if ( $field->get_value() == 'yes' ) {
					\MillionDollarScript\milliondollarscript_install_mu_plugin( 'cookies' );
				} else {
					\MillionDollarScript\milliondollarscript_delete_mu_plugin( 'cookies' );
				}
				break;
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

	/**
	 * Get options from database
	 *
	 * @param $name
	 * @param $container_id
	 * @param null $default
	 *
	 * @return mixed
	 */
	public static function get_option( $name, $container_id = 'options', $default = null ) {
		$n = '_' . self::prefix . $name;
		$c = '_' . self::prefix . $container_id;

		$opts = get_option( $n, $default );
		$val  = $opts;

		if ( 'all' == $name ) {
			$val = $opts;
		} elseif ( is_array( $opts ) && array_key_exists( $c, $opts ) ) {
			$val = $opts[ $c ];
		}

		return $val;
	}

	/**
	 * Get path to MDS install
	 *
	 * @return string
	 */
	public static function get_mds_path(): string {
		return trailingslashit( Options::get_option( 'path', 'options', ABSPATH . 'milliondollarscript' ) );
	}

	/**
	 * Check if MDS folder and config were found in the set path
	 *
	 * @param null $mds_path Optional MDS path
	 *
	 * @return int Returns 0 if not found, 1 if path was found
	 */
	public static function mds_installed( $mds_path = null ): int {
		$mds_found = 0;

		if ( $mds_path == null ) {
			$mds_path = self::get_mds_path();
		}

		if ( file_exists( $mds_path ) ) {
			// path was found
			$mds_found = 1;
		}

		return $mds_found;
	}

}
