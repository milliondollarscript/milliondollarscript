<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.2
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

use Carbon_Fields\Container;
use Carbon_Fields\Field;

defined( 'ABSPATH' ) or exit;

class Options {
	private static array $tabs;

	/**
	 * Register the options.
	 *
	 * @link https://docs.carbonfields.net#/containers/theme-options
	 */
	public static function register(): void {

		$capabilities = Capabilities::get();

		self::$tabs = [
			'Pages' => [

				// Account Page
				Field::make( 'text', MDS_PREFIX . 'account-page', Language::get( 'Account Page' ) )
				     ->set_default_value( get_edit_profile_url() )
				     ->set_help_text( Language::get( 'The page where users can modify their account details. If left empty will redirect to the default WP profile page. If WooCommerce integration is enabled it will go to the WooCommerce My Account page. Set the height to auto for the Buy Pixels/Users page to auto scale to fit in the height of the page. If using a custom login page such as with Ultimate Member then enter the full registration page URL here.' ) ),

				// Register Page
				Field::make( 'text', MDS_PREFIX . 'register-page', Language::get( 'Register Page' ) )
				     ->set_default_value( wp_registration_url() )
				     ->set_help_text( Language::get( 'The page where users can register for an account. If left empty will redirect to the default WP registration page. Set the height to auto for the Buy Pixels/Users page to auto scale to fit in the height of the page. If using a custom login page such as with Ultimate Member then enter the full registration page URL here.' ) ),

				// Login Page
				Field::make( 'text', MDS_PREFIX . 'login-page', Language::get( 'Login Page' ) )
				     ->set_default_value( wp_login_url() )
				     ->set_help_text( Language::get( 'The login page to redirect users to. If left empty will redirect to the default WP login. Set the height to auto for the Buy Pixels/Users page to auto scale to fit in the height of the page. If using a custom login page such as with Ultimate Member then enter the full login URL here.' ) ),

				// Login Link Target
				Field::make( 'select', MDS_PREFIX . 'login-target', Language::get( 'Login Page Target' ) )
				     ->set_default_value( 'current' )
				     ->set_options( [
					     'current'  => 'Current Page',
					     'redirect' => 'Redirect',
				     ] )
				     ->set_help_text( Language::get( 'How to open the login page when shown. When set to Current Page it will attempt to load the login page into the current page content. If that fails or it\'s set to Redirect then it will redirect to the default WP login page. If you want to further customize the login/registration page beyond the options here then you can install a plugin like Ultimate Member and set the URLs here to the pages provided by it such as Account, Register, Login, and Forgot Password pages.' ) ),

				// Forgot Password Page
				Field::make( 'text', MDS_PREFIX . 'forgot-password-page', Language::get( 'Forgot Password Page' ) )
				     ->set_default_value( wp_lostpassword_url() )
				     ->set_help_text( Language::get( 'The URL to the forgot password page. If left empty will redirect to the default WP forgot password page. Set the height to auto for the Buy Pixels/Users page to auto scale to fit in the height of the page. If using a custom login page such as with Ultimate Member then enter the full forgot password page URL here.' ) ),

				// Checkout URL
				Field::make( 'text', MDS_PREFIX . 'checkout-url', Language::get( 'Checkout URL' ) )
				     ->set_default_value( '' )
				     ->set_help_text( Language::get( 'The URL to your checkout page. If left empty and WooCommerce integration is enabled (note that this option only appears if WooCommerce is installed), your customers will be automatically redirected to the WooCommerce checkout page. If you don\'t specify a URL and WooCommerce integration is disabled, a default message will be displayed. If auto-approve is disabled the message will ask customers to wait for their order to be approved. If auto-approve is enabled, the message will inform them their order has been successfully submitted. Placeholders: %AMOUNT%, %CURRENCY%, %QUANTITY%, %ORDERID, %USERID%, %GRID%, %PIXELID%' ) ),

				// Dynamic Page
				Field::make( 'text', MDS_PREFIX . 'dynamic-id', Language::get( 'Dynamic Page ID' ) )
				     ->set_default_value( '' )
				     ->set_attribute( 'type', 'number' )
				     ->set_help_text( Language::get( 'The page id to use to act as your dynamic page. If entered, this page will be used to essentially trick WP into thinking the dynamically generated pages by this plugin such as /milliondollarscript/order, /milliondollarscript/manage, /milliondollarscript/history, etc. are this page. The reason you might want to do this is so that you can assign styles, headers/footers or other things to that page and they will show on these dynamic pages properly.' ) ),

				// Confirm Order page
				Field::make( 'select', MDS_PREFIX . 'confirm-orders', Language::get( 'Confirm Orders Page' ) )
				     ->set_default_value( 'yes' )
				     ->set_options( [
					     'yes' => 'Yes',
					     'no'  => 'No',
				     ] )
				     ->set_help_text( Language::get( 'Enable the Confirm Order page which shows after filling out the fields and before the checkout page.' ) ),

				// Thank-you Page
				Field::make( 'text', MDS_PREFIX . 'thank-you-page', Language::get( 'Thank-you Page' ) )
				     ->set_default_value( '' )
				     ->set_help_text( Language::get( 'The thank-you page to redirect users to. If left empty will redirect to a default page with a thank-you message.' ) ),
			],

			'Login' => [

				// Login Redirect
				Field::make( 'text', MDS_PREFIX . 'login-redirect', Language::get( 'Login Redirect' ) )
				     ->set_default_value( home_url() )
				     ->set_help_text( Language::get( 'The URL to redirect users to after login.' ) ),

				// Logout Redirect
				Field::make( 'text', MDS_PREFIX . 'logout-redirect', Language::get( 'Logout Redirect' ) )
				     ->set_default_value( home_url() )
				     ->set_help_text( Language::get( 'The URL to redirect users to after logout.' ) ),

				// WP Login Header Image
				Field::make( 'image', MDS_PREFIX . 'login-header-image', Language::get( 'Login Header Image' ) )
				     ->set_default_value( '' )
				     ->set_help_text( Language::get( 'The login header image used only on the default WP login page. If this isn\'t set then it will try to use the theme logo. If that isn\'t set it\'ll show nothing but the login box unless you set the Login Header Text below. A good size for this image is 320 X 100 pixels.' ) ),

				// WP Login Header Text
				Field::make( 'text', MDS_PREFIX . 'login-header-text', Language::get( 'Login Header Text' ) )
				     ->set_default_value( '' )
				     ->set_help_text( Language::get( 'The text to show above the Login page. This will only be shown if there is no Login Header Image set above.' ) ),

			],

			'Popup' => [

				// Popup Template
				Field::make( 'rich_text', MDS_PREFIX . 'popup-template', Language::get( 'Popup Template' ) )
				     ->set_settings( array( 'media_buttons' => false ) )
				     ->set_default_value( '%text%<br/>
<span color="green">%url%</span><br/>
%image%<br/>
' )
				     ->set_help_text( Language::get( 'This is the template for the popup that displays when a block on the grid is interacted with. Placeholders: %text%, %url%, %image%' ) ),

				// Max Popup Size
				Field::make( 'text', MDS_PREFIX . 'max-popup-size', Language::get( 'Max Popup Size' ) )
				     ->set_default_value( '350' )
				     ->set_attribute( 'type', 'number' )
				     ->set_help_text( Language::get( 'The maximum popup size in pixels. The fields will be scaled to fit within this width automatically.' ) ),

				// Max Image Size
				Field::make( 'text', MDS_PREFIX . 'max-image-size', Language::get( 'Max Image Size' ) )
				     ->set_default_value( '350' )
				     ->set_attribute( 'type', 'number' )
				     ->set_help_text( Language::get( 'The maximum image size in pixels. The system will attempt to resize images larger than this.' ) ),

				// Link Target
				Field::make( 'select', MDS_PREFIX . 'link-target', Language::get( 'Link Target' ) )
				     ->set_default_value( '_self' )
				     ->set_options( [
					     '_self'   => 'Open in Same Tab/Window',
					     '_blank'  => 'Open in New Tab/Window',
					     '_parent' => 'Open in Parent Context',
					     '_top'    => 'Open in Full Window',
				     ] )
				     ->set_help_text( Language::get( 'How to open links in the popup when they are clicked.' ) ),
			],

			'Orders' => [

				// Expire Orders
				Field::make( 'select', MDS_PREFIX . 'expire-orders', Language::get( 'Expire Orders' ) )
				     ->set_default_value( 'yes' )
				     ->set_options( [
					     'yes' => 'Yes',
					     'no'  => 'No',
				     ] )
				     ->set_help_text( Language::get( 'Enable expiration of orders.' ) ),

				// Auto-approve
				Field::make( 'checkbox', MDS_PREFIX . 'auto-approve', Language::get( 'Auto-approve' ) )
				     ->set_default_value( 'no' )
				     ->set_option_value( 'yes' )
				     ->set_help_text( Language::get( 'Setting to Yes will automatically approve orders before payments are verified by an admin.' ) ),
			],

			'Permissions' => [

				// Enable Permissions?
				Field::make( 'checkbox', MDS_PREFIX . 'permissions', Language::get( 'Enable Permissions?' ) )
				     ->set_default_value( 'no' )
				     ->set_option_value( 'yes' )
				     ->set_help_text( Language::get( 'Enable permission system for user roles and the following capabilities: mds_my_account, mds_order_pixels, mds_manage_pixels, mds_order_history, mds_logout' ) ),

				// Enable Capabilities
				Field::make( 'multiselect', MDS_PREFIX . 'capabilities', Language::get( 'Enable Capabilities' ) )
				     ->set_default_value( $capabilities )
				     ->add_options( $capabilities )
				     ->set_conditional_logic( [
					     'relation' => 'AND',
					     [
						     'field'   => MDS_PREFIX . 'permissions',
						     'value'   => true,
						     'compare' => '=',
					     ]
				     ] )
				     ->set_help_text( Language::get( 'Select any number of capabilities to enable. Once enabled you can use a plugin like User Role Editor to allow access to any of the MDS menus that appear in the Buy Pixels / users area. If deselected it will show the related MDS menu item and allow access to the page. If selected it will only show the menu item if the user belongs to a role that has the listed capability.' ) ),

			],

			'System' => [

				// Update Language
				Field::make( 'html', MDS_PREFIX . 'update-language', Language::get( 'Update Language' ) )
				     ->set_html( '<div class="button button-primary" id="mds_update_language" style="margin-top: 10px;">' . Language::get( 'Update Language' ) . '</div/>' )
				     ->set_help_text( Language::get( 'This will automatically add any missing entries to the plugin language file.' ) ),

				// Delete data on uninstall?
				Field::make( 'checkbox', MDS_PREFIX . 'delete-data', Language::get( 'Delete data on uninstall?' ) )
				     ->set_default_value( 'no' )
				     ->set_option_value( 'yes' )
				     ->set_help_text( Language::get( 'If yes then all database tables created by this plugin will be completely deleted when the plugin is uninstalled.' ) ),

				// Plugin updates
				Field::make( 'select', MDS_PREFIX . 'updates', Language::get( 'Plugin updates' ) )
				     ->set_default_value( 'yes' )
				     ->set_options( [
					     'yes' => 'Update',
					     'no'  => 'Don\'t update',
					     'dev' => 'Development'
				     ] )
				     ->set_help_text( Language::get( 'If Update then updates will be done normally like all other plugins. If Don\'t update then no updates will be looked for. If Development then updates will be checked for in the development branch.' ) ),
			],
		];

		self::$tabs = apply_filters( 'mds_options', self::$tabs, MDS_PREFIX );

		$container = Container::make( 'theme_options', MDS_PREFIX . 'options', Language::get( 'Million Dollar Script Options' ) )
		                      ->set_page_parent( 'milliondollarscript' )
		                      ->set_page_file( MDS_PREFIX . 'options' )
		                      ->set_page_menu_title( 'Options' );

		foreach ( self::$tabs as $title => $fields ) {
			$container->add_tab( Language::get( $title ), $fields );
		}
	}

	public static function add_tab( $title, $fields ): void {
		self::$tabs[ $title ] = $fields;
	}

	public static function load(): void {
		\Carbon_Fields\Carbon_Fields::boot();
	}

	/**
	 * Hook into Carbon Fields filter to sanitize some fields on save.
	 *
	 * @param $field \Carbon_Fields\Field\Field
	 *
	 * @return \Carbon_Fields\Field\Field
	 */
	public static function save( \Carbon_Fields\Field\Field $field ): \Carbon_Fields\Field\Field {
		$name = str_replace( '_' . MDS_PREFIX, '', $field->get_name() );

		switch ( $name ) {
			case 'path':
				// Normalize path field
				$field->set_value( wp_normalize_path( $field->get_value() ) );
				break;
			case 'popup-template':
				$field->set_value( nl2br( $field->get_value() ) );
				break;
			case 'update-language':
				if ( $field->get_value() === 'off' ) {
					delete_option( MDS_PREFIX . 'pot_needs_updating' );
					delete_transient( MDS_PREFIX . 'pot_strings' );
				}
				break;
			case 'users':
			case 'admin':
			default:
				$field = apply_filters( 'mds_options_save', $field, $name );
				break;
		}

		return $field;
	}

	/**
	 * Get options from database
	 *
	 * @param $name
	 * @param null $default
	 * @param bool $carbon_fields
	 * @param string $container_id
	 *
	 * @return mixed
	 */
	public static function get_option( $name, $default = null, bool $carbon_fields = true, string $container_id = 'options' ): mixed {
		if ( $carbon_fields && function_exists( 'carbon_get_theme_option' ) ) {
			return carbon_get_theme_option( MDS_PREFIX . $name );
		}

		$n = '_' . MDS_PREFIX . $name;
		$c = '_' . MDS_PREFIX . $container_id;

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
	 * @return mixed
	 */
	public static function getTabs(): array {
		return self::$tabs;
	}

	/**
	 * @param mixed $tabs
	 */
	public static function setTabs( array $tabs ): void {
		self::$tabs = $tabs;
	}

	public static function enqueue_scripts() {

		wp_register_script(
			MDS_PREFIX . 'admin-options-js',
			MDS_BASE_URL . 'src/Assets/js/admin-options.min.js',
			[ 'jquery' ],
			filemtime( MDS_CORE_PATH . '/admin/js/admin-options.min.js' ),
			true
		);

		wp_localize_script( MDS_PREFIX . 'admin-options-js', 'MDS', [
			'nonce'   => wp_create_nonce( 'mds_admin_nonce' ),
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		] );

		wp_enqueue_script( MDS_PREFIX . 'admin-options-js' );
	}
}
