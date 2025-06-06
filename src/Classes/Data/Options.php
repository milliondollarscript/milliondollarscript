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

namespace MillionDollarScript\Classes\Data;

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\User\Capabilities;
use MillionDollarScript\Classes\WooCommerce\WooCommerceFunctions;
use MillionDollarScript\Classes\Web\Styles;

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

		$woocommerce_logic = [];
		if ( WooCommerceFunctions::is_wc_active() ) {
			$woocommerce_logic = array(
				'relation' => 'AND',
				array(
					'field'   => MDS_PREFIX . 'woocommerce',
					'compare' => '=',
					'value'   => 'no',
				),
			);
		}

		self::$tabs = [
			Language::get( 'Pages' ) => [

				// Create Pages
				Field::make( 'html', MDS_PREFIX . 'create-pages', Language::get( 'Create Pages' ) )
					/* eslint-disable-next-line */
					->set_html( '<div class="button button-primary" id="mds_create_pages" style="margin-top: 10px;">' . Language::get( 'Create Pages' ) . '</div/>' )
					->set_help_text( Language::get( 'Clicking this button will create multiple pages for use with MDS.' ) ),

				// Delete Pages
				Field::make( 'html', MDS_PREFIX . 'delete-pages', Language::get( 'Delete Pages' ) )
					->set_html( '<div class="button button-primary" id="mds_delete_pages" style="margin-top: 10px;">' . Language::get( 'Delete Pages' ) . '</div/>' )
					->set_help_text( Language::get( 'Clicking this button will delete all pages created by MDS.' ) ),

				// MDS Pages
				Field::make( 'html', MDS_PREFIX . 'mds_pages', Language::get( 'MDS Pages' ) )
					->set_html( Language::get( '<h3>The following options will automatically use the pages created above. You can change them. These are the page id #</h3>' ) ),

				// Grid Page
				Field::make( 'text', MDS_PREFIX . 'grid-page', Language::get( 'Grid Page' ) )
					->set_attribute( 'type', 'number' )
					->set_help_text( Language::get( 'The grid page.' ) ),

				// Users Order Page
				Field::make( 'text', MDS_PREFIX . 'users-order-page', Language::get( 'Users Order Page' ) )
					->set_attribute( 'type', 'number' )
					->set_help_text( Language::get( 'The order page for Users.' ) ),

				// Users Write Ad Page
				Field::make( 'text', MDS_PREFIX . 'users-write-ad-page', Language::get( 'Users Write Ad Page' ) )
					->set_attribute( 'type', 'number' )
					->set_help_text( Language::get( 'The write ad page for Users.' ) ),

				// Users Confirm Order Page
				Field::make( 'text', MDS_PREFIX . 'users-confirm-order-page', Language::get( 'Users Confirm Order Page' ) )
					->set_attribute( 'type', 'number' )
					->set_help_text( Language::get( 'The confirm order page for Users.' ) ),

				// Users Payment Page
				Field::make( 'text', MDS_PREFIX . 'users-payment-page', Language::get( 'Users Payment Page' ) )
					->set_attribute( 'type', 'number' )
					->set_help_text( Language::get( 'The payment page for Users.' ) ),

				// Users Manage Page
				Field::make( 'text', MDS_PREFIX . 'users-manage-page', Language::get( 'Users Manage Page' ) )
					->set_attribute( 'type', 'number' )
					->set_help_text( Language::get( 'The manage page for Users.' ) ),

				// Users Thank-You Page
				Field::make( 'text', MDS_PREFIX . 'users-thank-you-page', Language::get( 'Users Thank You Page' ) )
					->set_attribute( 'type', 'number' )
					->set_help_text( Language::get( 'The thank you page for Users.' ) ),

				// Users List Page
				Field::make( 'text', MDS_PREFIX . 'users-list-page', Language::get( 'Users List Page' ) )
					->set_attribute( 'type', 'number' )
					->set_help_text( Language::get( 'The list page for Users.' ) ),

				// Users Upload Page
				Field::make( 'text', MDS_PREFIX . 'users-upload-page', Language::get( 'Users Upload Page' ) )
					->set_attribute( 'type', 'number' )
				     ->set_help_text( Language::get( 'The upload page for Users.' ) ),

				// Users No Orders Page
				Field::make( 'text', MDS_PREFIX . 'users-no-orders-page', Language::get( 'Users No Orders Page' ) )
					->set_attribute( 'type', 'number' )
					->set_help_text( Language::get( 'The no orders page for Users.' ) ),
			],

			Language::get( 'URLs & Redirects' ) => [

				// WP Pages
				Field::make( 'html', MDS_PREFIX . 'wp_pages', Language::get( 'WP Pages' ) )
					->set_html( Language::get( '<h3>The following options allow you to manage links and redirects to other pages.</h3>' ) ),

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
				Field::make( 'radio', MDS_PREFIX . 'login-target', Language::get( 'Login Page Target' ) )
					->set_default_value( 'current' )
					->set_options( [
						'current'  => Language::get( 'Current Page' ),
						'redirect' => Language::get( 'Redirect' ),
					] )
					->set_help_text( Language::get( 'How to open the login page when shown. When set to Current Page it will attempt to load the login page into the current page content. If that fails or it\'s set to Redirect then it will redirect to the default WP login page. If you want to further customize the login/registration page beyond the options here then you can install a plugin like Ultimate Member and set the URLs here to the pages provided by it such as Account, Register, Login, and Forgot Password pages.' ) ),

				// Forgot Password Page
				Field::make( 'text', MDS_PREFIX . 'forgot-password-page', Language::get( 'Forgot Password Page' ) )
					->set_default_value( wp_lostpassword_url() )
					->set_help_text( Language::get( 'The URL to the forgot password page. If left empty will redirect to the default WP forgot password page. Set the height to auto for the Buy Pixels/Users page to auto scale to fit in the height of the page. If using a custom login page such as with Ultimate Member then enter the full forgot password page URL here.' ) ),

				// Checkout URL
				Field::make( 'text', MDS_PREFIX . 'checkout-url', Language::get( 'Checkout URL' ) )
					->set_default_value( '' )
					->set_help_text( Language::get( 'The URL to your checkout page. If left empty and WooCommerce integration is enabled (that option only appears if WooCommerce is installed), your customers will be automatically redirected to the WooCommerce checkout page. If you don\'t specify a URL and WooCommerce integration is disabled, a default message will be displayed. If auto-approve is disabled the message will ask customers to wait for their order to be approved. If auto-approve is enabled, the message will inform them their order has been successfully submitted. Placeholders: %AMOUNT%, %CURRENCY%, %QUANTITY%, %ORDERID, %USERID%, %GRID%, %PIXELID%' ) ),

				// Thank-you Page URL
				Field::make( 'text', MDS_PREFIX . 'thank-you-page', Language::get( 'Thank-you Page URL' ) )
					->set_default_value( '' )
					->set_help_text( Language::get( 'The thank-you page to redirect users to. If left empty will redirect to a default page with a thank-you message.' ) ),

				// Dynamic Page
				Field::make( 'text', MDS_PREFIX . 'dynamic-id', Language::get( 'Dynamic Page ID' ) )
					->set_default_value( '' )
					->set_attribute( 'type', 'number' )
					->set_help_text( Language::get( 'The page id to use to act as your dynamic page. If entered, this page will be used to essentially trick WP into thinking the dynamically generated pages by this plugin such as /milliondollarscript/order, /milliondollarscript/manage, etc. are this page. The reason you might want to do this is so that you can assign styles, headers/footers or other things to that page and they will show on these dynamic pages properly.' ) ),

				// Confirm Order page
				Field::make( 'radio', MDS_PREFIX . 'confirm-orders', Language::get( 'Confirm Orders Page' ) )
					->set_default_value( 'yes' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Enable the Confirm Order page which shows after filling out the fields and before the checkout page.' ) ),

				// MDS Pixels page template
				Field::make( 'radio', MDS_PREFIX . 'mds-pixel-template', Language::get( 'Enable MDS Pixels Pages?' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Set to Yes to enable the MDS Pixels page template for the MDS Pixels pages. Visiting a MDS Pixels page will then display the same details shown in the popup boxes. If set to No then when visiting a MDS Pixels page it will show a 404 (Page Not Found) error page.' ) ),

				// Exclude from search
				Field::make( 'radio', MDS_PREFIX . 'exclude-from-search', Language::get( 'Exclude from search' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'If checked then pixels will be excluded from search results. Note: Only the text and title fields are searchable.' ) ),

				// Endpoint
				Field::make( 'text', MDS_PREFIX . 'endpoint', Language::get( 'Endpoint' ) )
					->set_default_value( 'milliondollarscript' )
					->set_help_text( Language::get( 'The endpoint to use for dynamic pages. Example: /<span style="color: red;">endpoint</span>/order. If left empty will use the default: milliondollarscript' ) ),

				// URL Cloaking
				Field::make( 'radio', MDS_PREFIX . 'enable-cloaking', Language::get( 'Enable URL cloaking?' ) )
					->set_options( [
						'YES' => Language::get( 'Yes - All links will point directly to the Advertiser\'s URL. Click tracking will be managed by JavaScript.' ),
						'NO'  => Language::get( 'No - All links will be re-directed to the /milliondollarscript/click/ endpoint.' ),
					] )
					->set_default_value( 'YES' )
					->set_help_text( Language::get( 'Supposedly, when enabled, the advertiser\'s link will get a better advantage from search engines.' ) ),

				// Link Validation
				Field::make( 'radio', MDS_PREFIX . 'validate-link', Language::get( 'Validate URL?' ) )
					->set_options( [
						'YES' => Language::get( 'Yes - The script will try to connect to the Advertiser\'s URL to make sure that the link is correct.' ),
						'NO'  => Language::get( 'No' ),
					] )
					->set_default_value( 'NO' )
					->set_help_text( Language::get( 'If enabled, validates URLs before allowing them to be saved.' ) ),

				// Redirect Available Blocks
				Field::make( 'radio', MDS_PREFIX . 'redirect-switch', Language::get( 'Redirect when clicking available blocks?' ) )
					->set_options( [
						'YES' => Language::get( 'Yes - When an available block is clicked, redirect to specific URL' ),
						'NO'  => Language::get( 'No' ),
					] )
					->set_default_value( 'NO' ),

				// Redirect URL
				Field::make( 'text', MDS_PREFIX . 'redirect-url', Language::get( 'Redirect URL' ) )
					->set_default_value( 'https://www.example.com' )
					->set_help_text( Language::get( 'URL to redirect to when an available block is clicked (if enabled above).' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'    => MDS_PREFIX . 'redirect-switch',
							'value'    => 'YES',
							'compare'  => '=',
						],
					] ),
			],

			Language::get( 'Styles' ) => [

				// TODO: Dark Mode toggle. Has to recolor all MDS styles to invert them and invert the grid image colors as well.

				// Button Color (Primary Background)
				Field::make( 'color', MDS_PREFIX . 'button-color', Language::get( 'Button Color (Primary)' ) )
					->set_default_value( '#0073aa' )
					->set_palette( [ '#0073aa' ] )
					->set_help_text( Language::get( 'Background color for primary buttons.' ) ),

				// Button Text Color (Primary)
				Field::make( 'color', MDS_PREFIX . 'button_text_color', Language::get( 'Button Text Color (Primary)' ) )
					->set_default_value( '#ffffff' ) // Default assumption: white text
					->set_palette( [ '#ffffff', '#000000' ] )
					->set_help_text( Language::get( 'Text color for primary buttons.' ) ),

				// Button Secondary Background
				Field::make( 'color', MDS_PREFIX . 'button_secondary_bg', Language::get( 'Button Secondary Background' ) )
					->set_default_value( '#91877D' )
					->set_palette( [ '#91877D' ] )
					->set_help_text( Language::get( 'Background color for secondary buttons.' ) ),

				// Button Secondary Text
				Field::make( 'color', MDS_PREFIX . 'button_secondary_text', Language::get( 'Button Secondary Text' ) )
					->set_default_value( '#ffffff' ) // Default from mds.css for .btn-secondary
					->set_palette( [ '#ffffff', '#000000' ] )
					->set_help_text( Language::get( 'Text color for secondary buttons.' ) ),

				// Button Success Background
				Field::make( 'color', MDS_PREFIX . 'button_success_bg', Language::get( 'Button Success Background' ) )
					->set_default_value( '#28a745' )
					->set_palette( [ '#28a745' ] )
					->set_help_text( Language::get( 'Background color for success buttons.' ) ),

				// Button Success Text
				Field::make( 'color', MDS_PREFIX . 'button_success_text', Language::get( 'Button Success Text' ) )
					->set_default_value( '#ffffff' ) // Default from mds.css for .btn-success
					->set_palette( [ '#ffffff', '#000000' ] )
					->set_help_text( Language::get( 'Text color for success buttons.' ) ),

				// Button Danger Background
				Field::make( 'color', MDS_PREFIX . 'button_danger_bg', Language::get( 'Button Danger Background' ) )
					->set_default_value( '#f44336' )
					->set_palette( [ '#f44336' ] )
					->set_help_text( Language::get( 'Background color for danger/delete buttons.' ) ),

				// Button Danger Text
				Field::make( 'color', MDS_PREFIX . 'button_danger_text', Language::get( 'Button Danger Text' ) )
					->set_default_value( '#ffffff' ) // Default from mds.css for .btn-danger
					->set_palette( [ '#ffffff', '#000000' ] )
					->set_help_text( Language::get( 'Text color for danger/delete buttons.' ) ),

				// Primary Color
				Field::make( 'color', MDS_PREFIX . 'primary_color', Language::get( 'Primary Color' ) )
					->set_default_value( '#ff0000' )
					->set_palette( [ '#ff0000' ] )
					->set_help_text( Language::get( 'Primary accent color used for highlights, links, etc.' ) ),

				// Secondary Color
				Field::make( 'color', MDS_PREFIX . 'secondary_color', Language::get( 'Secondary Color' ) )
					->set_default_value( '#000000' )
					->set_palette( [ '#000000' ] )
					->set_help_text( Language::get( 'Secondary accent color.' ) ),

				// Background Color
				Field::make( 'color', MDS_PREFIX . 'background_color', Language::get( 'Background Color' ) )
					->set_default_value( '#ffffff' )
					->set_palette( [ '#ffffff' ] )
					->set_help_text( Language::get( 'Main background color for admin pages.' ) ),

				// Text Color
				Field::make( 'color', MDS_PREFIX . 'text_color', Language::get( 'Text Color' ) )
					->set_default_value( '#333333' )
					->set_palette( [ '#333333' ] )
					->set_help_text( Language::get( 'Default text color.' ) ),

				// Custom CSS
				Field::make( 'textarea', MDS_PREFIX . 'custom-css', Language::get( 'Custom CSS' ) )
					->set_help_text( Language::get( 'Add custom CSS to override default styles. This will be saved to the bottom of the dynamically generated CSS file.' ) ),
			],

			Language::get( 'Grid' ) => [
				// Output format
				Field::make( 'select', MDS_PREFIX . 'output-jpeg', Language::get( 'Output Grid Image As' ) )
					->set_default_value( 'N' )
					->set_options( [
						'Y'   => Language::get( 'JPEG (Lossy compression)' ),
						'N'   => Language::get( 'PNG (Non-lossy compression)' ),
						'GIF' => Language::get( 'GIF (8-bit, 256 color)' ),
					] )
				     ->set_help_text( Language::get( 'Choose the output format for grid images.' ) ),

				// JPEG Quality
				Field::make( 'text', MDS_PREFIX . 'jpeg-quality', Language::get( 'JPEG Quality' ) )
					->set_conditional_logic(
						array(
							'relation' => 'AND',
							array(
								'field'   => MDS_PREFIX . 'output-jpeg',
								'compare' => '=',
								'value'   => 'Y',
							)
						)
					)
					->set_attribute( 'type', 'number' )
					->set_default_value( '75' )
					->set_help_text( Language::get( 'JPEG quality percentage (1-100).' ) ),

				// Interlace Grid
				Field::make( 'radio', MDS_PREFIX . 'interlace-switch', Language::get( 'Interlace Grid Image' ) )
					->set_conditional_logic(
						array(
							'relation' => 'AND',
							array(
								'field'   => MDS_PREFIX . 'output-jpeg',
								'compare' => '!=',
								'value'   => 'Y',
							)
						)
					)
					->set_default_value( 'YES' )
					->set_options( [
						'YES' => Language::get( 'Yes' ),
						'NO'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Enable interlaced loading preview for PNG/GIF images.' ) ),

				// Pixel Background
				Field::make( 'radio', MDS_PREFIX . 'display-pixel-background', Language::get( 'Display Pixel Background' ) )
					->set_default_value( 'NO' )
					->set_options( [
						'YES' => Language::get( 'Yes' ),
						'NO'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Show a blank pixel grid background while the grid loads.' ) ),

				// Pixel Selection Method
				Field::make( 'radio', MDS_PREFIX . 'use-ajax', Language::get( 'Pixel Selection Method' ) )
					->set_default_value( 'SIMPLE' )
					->set_options( [
						'SIMPLE' => Language::get( 'Simple (Upload image → Place image on grid)', true ),
						'YES'    => Language::get( 'Advanced (Select blocks → Upload image)', true ),
					] )
					->set_help_text( Language::get( 'Choose how users select pixels in the grid.' ) ),

				// Resize uploaded pixels automatically
				Field::make( 'radio', MDS_PREFIX . 'resize', Language::get( 'Resize Uploaded Pixels Automatically' ) )
					->set_default_value( 'YES' )
					->set_options( [
						'YES' => Language::get( 'Yes' ),
						'NO'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Uploaded pixels will be resized to fit into the blocks.' ) ),

				// Manage Pixels Grid Dropdown
				Field::make( 'radio', MDS_PREFIX . 'manage-pixels-grid-dropdown', Language::get( 'Show Grid Dropdown on Manage Pixels Page?' ) )
					->set_default_value( 'yes' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'If Yes, a dropdown will appear on the user-facing Manage Pixels page allowing users to switch between available grids. If No, the page will only show pixels for the grid specified in the shortcode or URL.' ) ),

				// Invert Pixels
				Field::make( 'radio', MDS_PREFIX . 'invert-pixels', Language::get( 'Invert Pixels' ) )
					->set_default_value( 'NO' )
					->set_options( [
						'YES' => Language::get( 'Yes' ),
						'NO'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'If checked, reverses the order pixels are displayed within the grid container.' ) ),

				// Stats Display Mode
				Field::make( 'radio', MDS_PREFIX . 'stats-display-mode', Language::get( 'Stats Display Mode' ) )
					->set_default_value( 'PIXELS' )
					->set_options( [
						'BLOCKS'  => Language::get( 'Blocks - Stats box will display sold/available blocks.' ),
						'PIXELS'  => Language::get( 'Pixels - Stats box will display sold/available pixels.' ),
					] )
					->set_help_text( Language::get( 'Display stats as blocks or pixels.' ) ),
			],

			Language::get( 'Login' ) => [

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

			Language::get( 'Popup' ) => [

				// Enable Popup
				Field::make( 'radio', MDS_PREFIX . 'enable-mouseover', Language::get( 'Show a box when interacting with a block?' ) )
					->set_options( [
						'POPUP' => Language::get( 'Yes - Show a popup box' ),
						'NO'    => Language::get( 'No - No popup box' ),
					] )
					->set_default_value( 'POPUP' )
					->set_help_text( Language::get( 'Controls whether popup boxes are displayed when interacting with the grid.' ) ),
					
				// Popup Interaction Method
				Field::make( 'radio', MDS_PREFIX . 'tooltip-trigger', Language::get( 'Popup interaction method' ) )
					->set_options( [
						'click'       => Language::get( 'Click - A box will popup when blocks are clicked on the grid' ),
						'mouseenter'  => Language::get( 'Hover - A box will popup when blocks are hovered over on the grid' ),
					] )
					->set_default_value( 'click' )
					->set_help_text( Language::get( 'Controls how users interact with the grid to display popup boxes.' ) ),

				// Popup Template
				Field::make( 'rich_text', MDS_PREFIX . 'popup-template', Language::get( 'Popup Template' ) )
					->set_settings( array( 'media_buttons' => false ) )
					->set_default_value( '%text%<br/>
%url%<br/>
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
						'_self'   => Language::get( 'Open in Same Tab/Window' ),
						'_blank'  => Language::get( 'Open in New Tab/Window' ),
						'_parent' => Language::get( 'Open in Parent Context' ),
						'_top'    => Language::get( 'Open in Full Window' ),
					] )
					->set_help_text( Language::get( 'How to open links in the popup when they are clicked.' ) ),
			],

			Language::get( 'Orders' ) => [

				// Block Selection Mode
				Field::make( 'radio', MDS_PREFIX . 'block-selection-mode', Language::get( 'Block Selection Mode' ) )
					->set_default_value( 'YES' )
					->set_options( [
						'YES' => Language::get( 'Yes - When Pixel Selection Method is set to Advanced users will be able to change the size of their block selection area.' ),
						'NO'  => Language::get( 'No - Users will not be able to change the size of their block selection area.' ),
					] )
					->set_help_text( 
						Language::get_replace( 
							'Show block selection mode. Note that you can change the min and max block placement sizes by editing the grid under <a href="%GRIDS_URL%">Admin > Pixel Management > Grids</a>.',
							'%GRIDS_URL%',
							esc_url( admin_url( 'admin.php?page=mds-manage-grids' ) )
						)
					),

				// Expire Orders
				Field::make( 'radio', MDS_PREFIX . 'expire-orders', Language::get( 'Expire Orders' ) )
					->set_default_value( 'yes' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Enable expiration of orders.' ) ),

				// Auto-approve
				Field::make( 'radio', MDS_PREFIX . 'auto-approve', Language::get( 'Auto-approve' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Enabling this will automatically approve orders before payments are verified by an admin.' ) ),

				// Currency
				Field::make( 'text', MDS_PREFIX . 'currency', Language::get( 'Currency' ) )
					->set_conditional_logic( $woocommerce_logic )
					->set_default_value( 'USD' )
					->set_help_text( Language::get( 'The currency to use for orders and payments when WooCommerce isn\'t enabled. If WooCommerce is enabled then it will use the setting from it\'s settings. If WooCommerce Payments plugin is enabled then it will use those settings.' ) ),

				// Currency Symbol
				Field::make( 'text', MDS_PREFIX . 'currency-symbol', Language::get( 'Currency Symbol' ) )
					->set_conditional_logic( $woocommerce_logic )
					->set_default_value( '$' )
					->set_help_text( Language::get( 'The currency symbol to use for orders and payments when WooCommerce isn\'t enabled. If WooCommerce is enabled then it will use the setting from it\'s settings. If WooCommerce Payments plugin is enabled then it will use those settings.' ) ),

				// Order Locking
				Field::make( 'radio', MDS_PREFIX . 'order-locking', Language::get( 'Order Locking' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Enabling this will prevent users from editing their orders after they are approved or completed.' ) ),
			],

			Language::get( 'Order Timing' ) => [
				// Expired Orders
				Field::make( 'text', MDS_PREFIX . 'minutes-renew', Language::get( 'Minutes to Keep Expired Orders' ) )
					->set_attribute( 'type', 'number' )
					->set_default_value( '10080' )
					->set_help_text( Language::get( 'How many minutes to keep Expired orders before cancellation. Enter a number. 0 = Do not cancel. -1 = instant.' ) ),

				// Confirmed Orders
				Field::make( 'text', MDS_PREFIX . 'minutes-confirmed', Language::get( 'Minutes to Keep Confirmed Orders' ) )
					->set_attribute( 'type', 'number' )
					->set_default_value( '10080' )
					->set_help_text( Language::get( 'How many minutes to keep Confirmed (but not paid) orders before cancellation. Enter a number. 0 = Do not cancel. -1 = instant.' ) ),

				// Unconfirmed Orders
				Field::make( 'text', MDS_PREFIX . 'minutes-unconfirmed', Language::get( 'Minutes to Keep Unconfirmed Orders' ) )
					->set_attribute( 'type', 'number' )
					->set_default_value( '60' )
					->set_help_text( Language::get( 'How many minutes to keep New/Unconfirmed orders before deletion. Enter a number. 0 = never delete. -1 = instant.' ) ),

				// Cancelled Orders
				Field::make( 'text', MDS_PREFIX . 'minutes-cancel', Language::get( 'Minutes to Keep Cancelled Orders' ) )
					->set_attribute( 'type', 'number' )
					->set_default_value( '4320' )
					->set_help_text( Language::get( 'How many minutes to keep Cancelled orders before deletion. Enter a number. 0 = never delete. -1 = instant. Note: If deleted, the order will stay in the database, and only the status will simply change to deleted. The blocks will be freed.' ) ),
			],

			Language::get( 'Fields' ) => [

				// Make Popup Text Optional
				Field::make( 'radio', MDS_PREFIX . 'text-optional', Language::get( 'Make Popup Text Optional' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Setting to Yes will make the Popup Text field optional.' ) ),

				// Make URL Optional
				Field::make( 'radio', MDS_PREFIX . 'url-optional', Language::get( 'Make URL Optional' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Setting to Yes will make the URL field optional.' ) ),

				// Make Image Optional
				Field::make( 'radio', MDS_PREFIX . 'image-optional', Language::get( 'Make Image Optional' ) )
					->set_default_value( 'yes' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Setting to Yes will make the Image field optional.' ) ),
			],

			Language::get( 'Permissions' ) => [

				// Enable Permissions?
				Field::make( 'radio', MDS_PREFIX . 'permissions', Language::get( 'Enable Permissions?' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'If you want to control access to MDS user pages you can enable permissions. This will require you to use a plugin like User Role Editor or to code it yourself in order to implement the proper roles and capabilities for your users. Only enable this if you want to enable the permission system for user roles and the following capabilities: ' ) . implode( ', ', $capabilities ) ),

				// Enable Capabilities
				Field::make( 'multiselect', MDS_PREFIX . 'capabilities', Language::get( 'Enable Capabilities' ) )
					->set_default_value( $capabilities )
					->set_options( $capabilities )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'permissions',
							'value'   => 'yes',
							'compare' => '=',
						],
					] )
					->set_help_text( Language::get( 'Select any number of capabilities to enable. Once enabled you can use a plugin like User Role Editor to allow access to any of the MDS menus that appear in the Buy Pixels / users area. If deselected it will show the related MDS menu item and allow access to the page. If selected it will only show the menu item if the user belongs to a role that has the listed capability.' ) ),

			],

			Language::get( 'Analytics' ) => [
				// Advanced Click Count
				Field::make( 'radio', MDS_PREFIX . 'advanced-click-count', Language::get( 'Advanced Click Tracking' ) )
					->set_options( [
						'YES' => Language::get( 'Yes - Clicks (clicking the link on the popup) will be counted by day' ),
						'NO'  => Language::get( 'No' ),
					] )
					->set_default_value( 'YES' )
					->set_help_text( Language::get( 'Enables detailed click tracking statistics.' ) ),

				// Advanced View Count
				Field::make( 'radio', MDS_PREFIX . 'advanced-view-count', Language::get( 'Advanced View Tracking' ) )
					->set_options( [
						'YES' => Language::get( 'Yes - Views (clicking the block to trigger the popup) will be counted by day' ),
						'NO'  => Language::get( 'No' ),
					] )
					->set_default_value( 'YES' )
					->set_help_text( Language::get( 'Enables detailed view tracking statistics.' ) ),
			],

			Language::get( 'System' ) => [

				// Extension Server URL
				Field::make( 'text', MDS_PREFIX . 'extension_server_url', Language::get( 'Extension Server URL' ) )
					->set_default_value( 'https://extensions.milliondollarscript.com' )
					->set_help_text( Language::get( 'The URL of the extension server for automatic updates and downloads. Use http://host.docker.internal:15346 for development or your production server URL. Default: https://extensions.milliondollarscript.com' ) ),

				// License Key
				Field::make( 'text', MDS_PREFIX . 'license_key', Language::get( 'License Key' ) )
					->set_default_value( '' )
					->set_help_text( Language::get( 'Your license key for accessing premium extensions and updates from the extension server.' ) ),

				// Delete data on uninstall?
				Field::make( 'radio', MDS_PREFIX . 'delete-data', Language::get( 'Delete data on uninstall?' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'If yes then all database tables created by this plugin will be completely deleted when the plugin is uninstalled.' ) ),

				// Plugin updates
				Field::make( 'select', MDS_PREFIX . 'updates', Language::get( 'Plugin updates' ) )
					->set_default_value( 'yes' )
					->set_options( [
						'yes'      => Language::get( 'Update' ),
						'no'       => Language::get( 'Don\'t update' ),
						'snapshot' => Language::get( 'Snapshot' ),
						'dev'      => Language::get( 'Development' )
					] )
					->set_help_text( Language::get( '<strong>Update</strong> - Updates will be done normally like all other plugins. An update will be found when a new stable version is released on the "main" branch. Code from the other branches will eventually make it\'s way here when it\'s deemed to be stable enough.<br /><br /><strong>Don\'t update</strong> - No updates will be searched for or installed for this plugin at all. Use this if custom changes have been made to the plugin code. Updates should be merged in manually.<br /><br /><strong>Snapshot</strong> - Updates will be checked for in the "snapshot" branch. These are lightly tested development snapshots. These snapshots are created periodically at points in the "dev" branch while working towards the next release when it\'s thought to be somewhat more stable and new features are finished and ready for testing.<br /><br /><strong>Development</strong> - This is the "dev" branch. This is where the development happens. This branch contains new and untested code for the next release. There are almost certainly bugs, and it may not work at all and could completely break your site. Only use this if you know what you\'re doing. You should never use this branch on a live site.<br /><br /><span style="color: red">As with any update there could be undiscovered bugs so please backup your files and database before updating anything. Please test thoroughly and report any issues you find.</span>' ) ),

				// Enable Logging
				Field::make( 'radio', MDS_PREFIX . 'log-enable', Language::get( 'Enable Logging' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Enable this to record debug information to the log file. Useful for troubleshooting.' ) ),
			],

			Language::get( 'Language' ) => [

				// Update Language
				Field::make( 'html', MDS_PREFIX . 'update-language', Language::get( 'Update Language' ) )
					->set_html( '<div class="button button-primary" id="mds_update_language" style="margin-top: 10px;">' . Language::get( 'Update Language' ) . '</div/>' )
					->set_help_text( Language::get( 'This will automatically add any missing entries to the plugin language file.' ) ),

				// Transliterate Slugs
				Field::make( 'radio', MDS_PREFIX . 'transliterate-slugs', Language::get( 'Transliterate Cyrillic Slugs' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'If enabled, attempts to convert Cyrillic characters in ad titles to Latin equivalents for cleaner URL slugs (post names). If disabled, WordPress default slug generation is used.' ) ),
			],
		];

		self::$tabs = apply_filters( 'mds_options', self::$tabs, MDS_PREFIX );

		$container = Container::make( 'theme_options', MDS_PREFIX . 'options', Language::get( 'Million Dollar Script Options' ) )
									->set_page_parent( 'milliondollarscript' )
									->set_page_file( 'milliondollarscript_options' )
									->set_page_menu_title( 'Options' );

		foreach ( self::$tabs as $title => $fields ) {
			$container->add_tab( Language::get( $title ), $fields );
		}
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
			case 'custom-css':
				add_filter('mds_dynamic_css', function($css) use ($field) {
					return $css . $field->get_value();
				});
				break;
			case 'path':
				// Normalize path field
				$field->set_value( wp_normalize_path( $field->get_value() ) );
				break;
			case 'popup-template':
				$text = $field->get_value();
				if ( ! str_contains( $text, '<br>' ) && ! str_contains( $text, '<br/>' ) && ! str_contains( $text, '<br />' ) ) {
					$text = nl2br( $field->get_value() );
				}
				$field->set_value( $text );
				break;
			case 'endpoint':
				if ( empty( $field->get_value() ) ) {
					$field->set_value( 'milliondollarscript' );
					flush_rewrite_rules();
				}
				if ( $field->get_value() !== 'milliondollarscript' ) {
					flush_rewrite_rules();
				}
				break;
			case 'users':
			case 'admin':
			default:
				break;
		}

		Styles::save_dynamic_css_file();

		return apply_filters( 'mds_options_save', $field, $name );
	}

	/**
	 * Get options from database
	 *
	 * @param $name
	 * @param mixed $default
	 * @param bool $carbon_fields
	 * @param string $container_id
	 *
	 * @return mixed
	 */
	public static function get_option( $name, $default = null, bool $carbon_fields = true, string $container_id = 'options' ): mixed {
		if ( $carbon_fields && function_exists( 'carbon_get_theme_option' ) ) {
			$option = carbon_get_theme_option( MDS_PREFIX . $name );
			if ( ! empty( $option ) || $option === false ) {
				return $option;
			} else {
				return $default;
			}
		}

		// Fallback for non-Carbon Fields or if function doesn't exist
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

	public static function add_tab( $title, $fields ): void {
		self::$tabs[ $title ] = $fields;
	}

	public static function enqueue_scripts(): void {

		wp_register_script(
			MDS_PREFIX . 'admin-options-js',
			MDS_BASE_URL . 'src/Assets/js/admin-options.min.js',
			[ 'jquery' ],
			filemtime( MDS_BASE_PATH . 'src/Assets/js/admin-options.min.js' ),
			true
		);

		wp_localize_script( MDS_PREFIX . 'admin-options-js', 'MDS', [
			'nonce'      => wp_create_nonce( 'mds_admin_nonce' ),
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'MDS_PREFIX' => MDS_PREFIX,
			'pages'      => json_encode( Utility::get_pages() ),
		] );

		wp_enqueue_script( MDS_PREFIX . 'admin-options-js' );
	}

	/**
	 * Update Carbon Fields options.
	 *
	 * @param string $name
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function update_option( string $name, string $value ): bool {
		// Use carbon_set_theme_option for Carbon Fields
		if ( function_exists( 'carbon_set_theme_option' ) ) {
			carbon_set_theme_option( MDS_PREFIX . $name, $value );
			// Assume success, carbon function doesn't return status
			return true;
		}

		// Fallback to standard update_option if Carbon Fields isn't available (shouldn't happen)
		$key = '_' . MDS_PREFIX . $name;
		return update_option( $key, $value );
	}

	/**
	 * Delete Carbon Fields options.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public static function delete( string $name ): bool {
		$key = '_' . MDS_PREFIX . $name;

		return delete_option( $key );
	}
	
	/**
	 * Creates the necessary pages for MDS functionality.
	 * Used by both the Options page and the Setup Wizard.
	 * 
	 * @return array|\WP_Error Array of created page IDs on success, WP_Error on failure
	 */
	public static function create_pages() {
		// Security check - this is called via AJAX
		if (wp_doing_ajax() && (!check_ajax_referer('mds_admin_nonce', 'nonce', false) && !check_ajax_referer('mds_wizard_nonce', 'nonce', false))) {
			return new \WP_Error('security_error', Language::get('Security check failed.'));
		}
		
		// Check permissions
		if (!current_user_can('manage_options')) {
			return new \WP_Error('permissions_error', Language::get('You do not have permission to create pages.'));
		}
		
		// Define the pages to create
		$pages = [
			[
				'title' => Language::get('Million Dollar Script Grid'),
				'content' => '[milliondollarscript]',
				'option_name' => 'grid-page',
			],
			[
				'title' => Language::get('Order Pixels'),
				'content' => '[milliondollarscript view="order"]',
				'option_name' => 'users-order-page',
			],
			[
				'title' => Language::get('Your Pixels'),
				'content' => '[milliondollarscript view="pixels"]',
				'option_name' => 'users-pixels-page',
			],
			[
				'title' => Language::get('Checkout'),
				'content' => '[milliondollarscript view="checkout"]',
				'option_name' => 'users-confirm-order-page',
			],
		];
		
		$created_pages = [];
		
		// Create each page
		foreach ($pages as $page) {
			$page_details = [
				'post_title'   => $page['title'],
				'post_content' => $page['content'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
			];
			
			$page_id = wp_insert_post($page_details);
			
			if (is_wp_error($page_id)) {
				return $page_id; // Return WP_Error if page creation failed
			}
			
			// Save the page ID in options
			self::update_option($page['option_name'], $page_id);
			
			$created_pages[$page['option_name']] = [
				'id' => $page_id,
				'title' => $page['title'],
				'permalink' => get_permalink($page_id)
			];
		}
		
		return $created_pages;
	}

	public static function init(): void {
		add_action( 'carbon_fields_register_fields', [ __CLASS__, 'register' ] );
		add_action( 'carbon_fields_container_saved', [ __CLASS__, 'handle_theme_options_save' ] );

		// AJAX handlers for options
		add_action( 'wp_ajax_mds_get_options_data', [ __CLASS__, 'ajax_get_options_data' ] );
	}

	public static function handle_theme_options_save(): void {
		// Verify we are on the correct container if necessary, though the hook is specific.
		// The hook 'carbon_fields_theme_options_container_saved' fires for the 'theme_options' container.
		// If there were multiple theme options containers with different IDs, we might check:
		// if ( isset($_POST['carbon_fields_container_id']) && $_POST['carbon_fields_container_id'] === 'theme_options' ) { ... }
		Styles::save_dynamic_css_file();
	}
}
