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
					->set_help_text( Language::get( 'The URL to your checkout page. If left empty and WooCommerce integration is enabled (that option only appears if WooCommerce is installed), your customers will be automatically redirected to the WooCommerce checkout page. If you don\'t specify a URL and WooCommerce integration is disabled, a default message will be displayed. When “Auto-complete manual payments” is disabled the message will ask customers to wait for approval. When it\'s enabled, the message will inform them their order has been successfully submitted. Placeholders: %AMOUNT%, %CURRENCY%, %QUANTITY%, %ORDERID, %USERID%, %GRID%, %PIXELID%' ) ),

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

				// MDS Pixels base (CPT rewrite base)
				Field::make( 'text', MDS_PREFIX . 'mds-pixel-base', Language::get( 'MDS Pixels base' ) )
					->set_default_value( 'mds-pixel' )
					->set_help_text( Language::get( 'Base segment for MDS Pixels URLs. Example: /mds-pixel/sample-slug. Changing this will flush permalinks and add the previous base to a redirect list.' ) ),

				// MDS Pixels slug pattern
				Field::make( 'text', MDS_PREFIX . 'mds-pixel-slug-structure', Language::get( 'MDS Pixels slug pattern' ) )
					->set_default_value( '%username%-%order_id%' )
					->set_help_text( Language::get( 'Pattern for MDS Pixels slugs. Supported tokens: %username%, %display_name%, %order_id%, %grid%, %pixel_id%, %text%, and %meta:your_field%.' ) ),

				// Migrate slugs (button)
                Field::make( 'html', MDS_PREFIX . 'mds-pixel-slug-migrate', Language::get( 'Migrate MDS Pixels slugs' ) )
					->set_html(
'<div style="margin:8px 0;">'
						. '<p class="description" style="margin-bottom:6px;">'
						. 'Updates all existing MDS Pixels to use the current slug pattern and stores previous slugs for automatic 301 redirects. '
						. 'After changing the base or slug pattern, click <strong>Save Changes</strong> first, then run this migration. May take time on large sites.'
						. '</p>'
						. '<button type="button" id="mds_migrate_slugs_btn" class="button button-secondary">Run migration</button> '
						. '<span id="mds_migrate_slugs_status"></span>'
						. '</div>'
					),

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

				// Dark Mode Toggle
				Field::make( 'radio', MDS_PREFIX . 'theme_mode', Language::get( 'Theme Mode' ) )
					->set_default_value( 'light' )
					->set_options( [
						'light' => Language::get( 'Light Mode' ),
						'dark'  => Language::get( 'Dark Mode' ),
					] )
					->set_help_text( Language::get( 'Choose between light and dark theme for the frontend display. Changing this will affect all color settings below. Your current settings will be backed up before switching.' ) ),

				// Export/Import Colors Section
				Field::make( 'html', MDS_PREFIX . 'export-import-section', Language::get( 'Backup & Restore' ) )
					->set_html( '
						<h3>' . Language::get( 'Export & Import Color Settings' ) . '</h3>
						<p>' . Language::get( 'Export your current color settings as a backup file or import settings from a previously saved file.' ) . '</p>
						<div style="margin: 15px 0;">
							<button type="button" id="mds_export_colors" class="button button-secondary" style="margin-right: 10px;">
								<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> ' . Language::get( 'Export Colors' ) . '
							</button>
							<button type="button" id="mds_reset_colors" class="button button-secondary" style="margin-right: 10px;">
								<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> ' . Language::get( 'Reset to Defaults' ) . '
							</button>
						</div>
						<div style="margin: 15px 0;">
							<label for="mds_import_file" style="display: block; margin-bottom: 5px; font-weight: bold;">' . Language::get( 'Import Colors:' ) . '</label>
							<input type="file" id="mds_import_file" accept=".json" style="margin-right: 10px;">
							<button type="button" id="mds_import_colors" class="button button-secondary" disabled>
								<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span> ' . Language::get( 'Import Colors' ) . '
							</button>
						</div>
						<p style="font-style: italic; color: #666;">' . Language::get( 'Note: Import will replace all current color settings. Make sure to export your current settings first as a backup.' ) . '</p>
					' ),

				// ============================================================================
				// BACKGROUND COLORS
				// ============================================================================

				// Main Background Colors
				Field::make( 'html', MDS_PREFIX . 'main-background-section', Language::get( 'Main Background Colors' ) )
					->set_html( '<h3>' . Language::get( 'Main Background Colors' ) . '</h3><p>' . Language::get( 'Primary background colors for the main frontend interface including grid display, user pages, and content areas.' ) . '</p>' ),

				// Light Mode - Main Background
				Field::make( 'color', MDS_PREFIX . 'background_color', Language::get( 'Light Mode - Primary Background' ) )
					->set_default_value( '#ffffff' )
					->set_palette( [ '#ffffff' ] )
					->set_help_text( Language::get( 'Primary background color for the main frontend interface including grid display, user pages, and content areas in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Main Background
				Field::make( 'color', MDS_PREFIX . 'dark_main_background', Language::get( 'Dark Mode - Primary Background' ) )
					->set_default_value( '#101216' )
					->set_palette( [ '#101216', '#14181c', '#1d2024', '#181a20', '#1a1c21' ] )
					->set_help_text( Language::get( 'Primary background color for the main frontend interface including grid display, user pages, and content areas in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Secondary Background Colors
				Field::make( 'html', MDS_PREFIX . 'secondary-background-section', Language::get( 'Secondary Background Colors' ) )
					->set_html( '<h3>' . Language::get( 'Secondary Background Colors' ) . '</h3><p>' . Language::get( 'Background colors for cards, menus, navigation bars, and elevated interface elements.' ) . '</p>' ),

				// Light Mode - Secondary Background (using existing primary_color as base)
				Field::make( 'color', MDS_PREFIX . 'primary_color', Language::get( 'Light Mode - Secondary Background' ) )
					->set_default_value( '#ff0000' )
					->set_palette( [ '#ff0000' ] )
					->set_help_text( Language::get( 'Secondary background color for cards, menus, navigation bars, and elevated interface elements in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Secondary Background
				Field::make( 'color', MDS_PREFIX . 'dark_secondary_background', Language::get( 'Dark Mode - Secondary Background' ) )
					->set_default_value( '#14181c' )
					->set_palette( [ '#101216', '#14181c', '#1d2024', '#181a20', '#1a1c21' ] )
					->set_help_text( Language::get( 'Secondary background color for cards, menus, navigation bars, and elevated interface elements in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Tertiary Background Colors
				Field::make( 'html', MDS_PREFIX . 'tertiary-background-section', Language::get( 'Tertiary Background Colors' ) )
					->set_html( '<h3>' . Language::get( 'Tertiary Background Colors' ) . '</h3><p>' . Language::get( 'Background colors for subtle overlays, hover states, and supporting interface elements.' ) . '</p>' ),

				// Light Mode - Tertiary Background
				Field::make( 'color', MDS_PREFIX . 'light_tertiary_background', Language::get( 'Light Mode - Tertiary Background' ) )
					->set_default_value( '#efefef' )
					->set_palette( [ '#efefef', '#f0f0f0', '#f5f5f5', '#f8f8f8' ] )
					->set_help_text( Language::get( 'Tertiary background color for subtle overlays, hover states, and supporting interface elements in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Tertiary Background
				Field::make( 'color', MDS_PREFIX . 'dark_tertiary_background', Language::get( 'Dark Mode - Tertiary Background' ) )
					->set_default_value( '#1d2024' )
					->set_palette( [ '#101216', '#14181c', '#1d2024', '#181a20', '#1a1c21' ] )
					->set_help_text( Language::get( 'Tertiary background color for subtle overlays, hover states, and supporting interface elements in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// ============================================================================
				// TEXT COLORS
				// ============================================================================

				// Primary Text Colors
				Field::make( 'html', MDS_PREFIX . 'primary-text-section', Language::get( 'Primary Text Colors' ) )
					->set_html( '<h3>' . Language::get( 'Primary Text Colors' ) . '</h3><p>' . Language::get( 'Primary text colors for all content, headings, and interface labels with high contrast readability.' ) . '</p>' ),

				// Light Mode - Primary Text
				Field::make( 'color', MDS_PREFIX . 'text_color', Language::get( 'Light Mode - Primary Text' ) )
					->set_default_value( '#333333' )
					->set_palette( [ '#333333' ] )
					->set_help_text( Language::get( 'Primary text color for all content, headings, and interface labels throughout the frontend in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Primary Text
				Field::make( 'color', MDS_PREFIX . 'dark_text_primary', Language::get( 'Dark Mode - Primary Text' ) )
					->set_default_value( '#ffffff' )
					->set_palette( [ '#ffffff', '#e0e0e0', '#cccccc', '#b0b0b0' ] )
					->set_help_text( Language::get( 'Primary text color for all content, headings, and interface labels throughout the frontend in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Secondary Text Colors
				Field::make( 'html', MDS_PREFIX . 'secondary-text-section', Language::get( 'Secondary Text Colors' ) )
					->set_html( '<h3>' . Language::get( 'Secondary Text Colors' ) . '</h3><p>' . Language::get( 'Text colors for supporting information, metadata, and less prominent interface elements.' ) . '</p>' ),

				// Light Mode - Secondary Text
				Field::make( 'color', MDS_PREFIX . 'light_text_secondary', Language::get( 'Light Mode - Secondary Text' ) )
					->set_default_value( '#666666' )
					->set_palette( [ '#666666', '#999999', '#777777' ] )
					->set_help_text( Language::get( 'Secondary text color for supporting information, metadata, and less prominent interface elements in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Secondary Text
				Field::make( 'color', MDS_PREFIX . 'dark_text_secondary', Language::get( 'Dark Mode - Secondary Text' ) )
					->set_default_value( '#cccccc' )
					->set_palette( [ '#ffffff', '#e0e0e0', '#cccccc', '#b0b0b0' ] )
					->set_help_text( Language::get( 'Secondary text color for supporting information, metadata, and less prominent interface elements in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Accent Colors
				Field::make( 'html', MDS_PREFIX . 'accent-colors-section', Language::get( 'Accent Colors' ) )
					->set_html( '<h3>' . Language::get( 'Accent Colors' ) . '</h3><p>' . Language::get( 'Accent colors for highlights, links, and special emphasis elements.' ) . '</p>' ),

				// Light Mode - Secondary Accent Color
				Field::make( 'color', MDS_PREFIX . 'secondary_color', Language::get( 'Light Mode - Secondary Accent' ) )
					->set_default_value( '#000000' )
					->set_palette( [ '#000000' ] )
					->set_help_text( Language::get( 'Secondary accent color for highlights, special emphasis, and decorative elements in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Secondary Accent Color
				Field::make( 'color', MDS_PREFIX . 'dark_secondary_accent', Language::get( 'Dark Mode - Secondary Accent' ) )
					->set_default_value( '#ffffff' )
					->set_palette( [ '#ffffff', '#e0e0e0', '#cccccc', '#b0b0b0', '#999999' ] )
					->set_help_text( Language::get( 'Secondary accent color for highlights, special emphasis, and decorative elements in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// ============================================================================
				// BUTTON COLORS
				// ============================================================================

				// Primary Button Colors
				Field::make( 'html', MDS_PREFIX . 'primary-button-section', Language::get( 'Primary Button Colors' ) )
					->set_html( '<h3>' . Language::get( 'Primary Button Colors' ) . '</h3><p>' . Language::get( 'Colors for primary action buttons including submit, login, and main navigation actions.' ) . '</p>' ),

				// Light Mode - Primary Button Background
				Field::make( 'color', MDS_PREFIX . 'button-color', Language::get( 'Light Mode - Primary Button Background' ) )
					->set_default_value( '#0073aa' )
					->set_palette( [ '#0073aa' ] )
					->set_help_text( Language::get( 'Background color for primary action buttons including submit, login, and main navigation actions in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Light Mode - Primary Button Text
				Field::make( 'color', MDS_PREFIX . 'button_text_color', Language::get( 'Light Mode - Primary Button Text' ) )
					->set_default_value( '#ffffff' )
					->set_palette( [ '#ffffff', '#000000' ] )
					->set_help_text( Language::get( 'Text color for primary action buttons including submit, login, and main navigation actions in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Primary Button Text
				Field::make( 'color', MDS_PREFIX . 'dark_button_primary_text', Language::get( 'Dark Mode - Primary Button Text' ) )
					->set_default_value( '#ffffff' )
					->set_palette( [ '#ffffff', '#000000', '#e0e0e0' ] )
					->set_help_text( Language::get( 'Text color for primary action buttons including submit, login, and main navigation actions in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Dark Mode - Primary Button Background
				Field::make( 'color', MDS_PREFIX . 'dark_button_primary_bg', Language::get( 'Dark Mode - Primary Button Background' ) )
					->set_default_value( '#0073aa' )
					->set_palette( [ '#0073aa', '#005a87', '#004666', '#003344', '#002233' ] )
					->set_help_text( Language::get( 'Background color for primary action buttons including submit, login, and main navigation actions in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Secondary Button Colors
				Field::make( 'html', MDS_PREFIX . 'secondary-button-section', Language::get( 'Secondary Button Colors' ) )
					->set_html( '<h3>' . Language::get( 'Secondary Button Colors' ) . '</h3><p>' . Language::get( 'Colors for secondary action buttons including edit, manage, and alternative actions.' ) . '</p>' ),

				// Light Mode - Secondary Button Background
				Field::make( 'color', MDS_PREFIX . 'button_secondary_bg', Language::get( 'Light Mode - Secondary Button Background' ) )
					->set_default_value( '#91877D' )
					->set_palette( [ '#91877D' ] )
					->set_help_text( Language::get( 'Background color for secondary action buttons including edit, manage, and alternative actions in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Light Mode - Secondary Button Text
				Field::make( 'color', MDS_PREFIX . 'button_secondary_text', Language::get( 'Light Mode - Secondary Button Text' ) )
					->set_default_value( '#ffffff' )
					->set_palette( [ '#ffffff', '#000000' ] )
					->set_help_text( Language::get( 'Text color for secondary action buttons including edit, manage, and alternative actions in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Secondary Button Background
				Field::make( 'color', MDS_PREFIX . 'dark_button_secondary_bg', Language::get( 'Dark Mode - Secondary Button Background' ) )
					->set_default_value( '#6b6155' )
					->set_palette( [ '#6b6155', '#5a5045', '#494035', '#383025', '#272015' ] )
					->set_help_text( Language::get( 'Background color for secondary action buttons including edit, manage, and alternative actions in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Dark Mode - Secondary Button Text
				Field::make( 'color', MDS_PREFIX . 'dark_button_secondary_text', Language::get( 'Dark Mode - Secondary Button Text' ) )
					->set_default_value( '#ffffff' )
					->set_palette( [ '#ffffff', '#000000', '#e0e0e0', '#cccccc', '#b0b0b0' ] )
					->set_help_text( Language::get( 'Text color for secondary action buttons including edit, manage, and alternative actions in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Success Button Colors
				Field::make( 'html', MDS_PREFIX . 'success-button-section', Language::get( 'Success Button Colors' ) )
					->set_html( '<h3>' . Language::get( 'Success Button Colors' ) . '</h3><p>' . Language::get( 'Colors for success and confirmation buttons including confirm, upload, pay, and complete actions.' ) . '</p>' ),

				// Light Mode - Success Button Background
				Field::make( 'color', MDS_PREFIX . 'button_success_bg', Language::get( 'Light Mode - Success Button Background' ) )
					->set_default_value( '#28a745' )
					->set_palette( [ '#28a745' ] )
					->set_help_text( Language::get( 'Background color for success and confirmation buttons including confirm, upload, pay, and complete actions in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Light Mode - Success Button Text
				Field::make( 'color', MDS_PREFIX . 'button_success_text', Language::get( 'Light Mode - Success Button Text' ) )
					->set_default_value( '#ffffff' )
					->set_palette( [ '#ffffff', '#000000' ] )
					->set_help_text( Language::get( 'Text color for success and confirmation buttons including confirm, upload, pay, and complete actions in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Success Button Background
				Field::make( 'color', MDS_PREFIX . 'dark_button_success_bg', Language::get( 'Dark Mode - Success Button Background' ) )
					->set_default_value( '#4ade80' )
					->set_palette( [ '#4ade80', '#22c55e', '#16a34a', '#15803d' ] )
					->set_help_text( Language::get( 'Background color for success and confirmation buttons including confirm, upload, pay, and complete actions in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Dark Mode - Success Button Text
				Field::make( 'color', MDS_PREFIX . 'dark_button_success_text', Language::get( 'Dark Mode - Success Button Text' ) )
					->set_default_value( '#000000' )
					->set_palette( [ '#000000', '#ffffff', '#1f2937' ] )
					->set_help_text( Language::get( 'Text color for success and confirmation buttons including confirm, upload, pay, and complete actions in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Danger Button Colors
				Field::make( 'html', MDS_PREFIX . 'danger-button-section', Language::get( 'Danger Button Colors' ) )
					->set_html( '<h3>' . Language::get( 'Danger Button Colors' ) . '</h3><p>' . Language::get( 'Colors for danger and destructive buttons including cancel, delete, and reset actions.' ) . '</p>' ),

				// Light Mode - Danger Button Background
				Field::make( 'color', MDS_PREFIX . 'button_danger_bg', Language::get( 'Light Mode - Danger Button Background' ) )
					->set_default_value( '#f44336' )
					->set_palette( [ '#f44336' ] )
					->set_help_text( Language::get( 'Background color for danger and destructive buttons including cancel, delete, and reset actions in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Light Mode - Danger Button Text
				Field::make( 'color', MDS_PREFIX . 'button_danger_text', Language::get( 'Light Mode - Danger Button Text' ) )
					->set_default_value( '#ffffff' )
					->set_palette( [ '#ffffff', '#000000' ] )
					->set_help_text( Language::get( 'Text color for danger and destructive buttons including cancel, delete, and reset actions in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Danger Button Background
				Field::make( 'color', MDS_PREFIX . 'dark_button_danger_bg', Language::get( 'Dark Mode - Danger Button Background' ) )
					->set_default_value( '#f87171' )
					->set_palette( [ '#f87171', '#ef4444', '#dc2626', '#b91c1c' ] )
					->set_help_text( Language::get( 'Background color for danger and destructive buttons including cancel, delete, and reset actions in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Dark Mode - Danger Button Text
				Field::make( 'color', MDS_PREFIX . 'dark_button_danger_text', Language::get( 'Dark Mode - Danger Button Text' ) )
					->set_default_value( '#000000' )
					->set_palette( [ '#000000', '#ffffff', '#1f2937' ] )
					->set_help_text( Language::get( 'Text color for danger and destructive buttons including cancel, delete, and reset actions in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// ============================================================================
				// INTERFACE ELEMENTS
				// ============================================================================

				// Border Colors
				Field::make( 'html', MDS_PREFIX . 'border-colors-section', Language::get( 'Border Colors' ) )
					->set_html( '<h3>' . Language::get( 'Border Colors' ) . '</h3><p>' . Language::get( 'Colors for borders, dividers, input outlines, and interface element separators.' ) . '</p>' ),

				// Light Mode - Border Color
				Field::make( 'color', MDS_PREFIX . 'light_border_color', Language::get( 'Light Mode - Border Color' ) )
					->set_default_value( '#dddddd' )
					->set_palette( [ '#dddddd', '#e0e0e0', '#d0d0d0', '#cccccc' ] )
					->set_help_text( Language::get( 'Border color for form inputs, cards, dividers, and interface element outlines in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Border Color
				Field::make( 'color', MDS_PREFIX . 'dark_border_color', Language::get( 'Dark Mode - Border Color' ) )
					->set_default_value( '#1a1c21' )
					->set_palette( [ '#101216', '#14181c', '#1d2024', '#181a20', '#1a1c21' ] )
					->set_help_text( Language::get( 'Border color for form inputs, cards, dividers, and interface element outlines in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

				// Tooltip Colors
				Field::make( 'html', MDS_PREFIX . 'tooltip-colors-section', Language::get( 'Tooltip Colors' ) )
					->set_html( '<h3>' . Language::get( 'Tooltip Colors' ) . '</h3><p>' . Language::get( 'Colors for tooltips, popups, informational overlays, and help elements.' ) . '</p>' ),

				// Light Mode - Tooltip Background
				Field::make( 'color', MDS_PREFIX . 'light_tooltip_background', Language::get( 'Light Mode - Tooltip Background' ) )
					->set_default_value( '#ffffff' )
					->set_palette( [ '#ffffff', '#f5f5f5', '#efefef', '#e9ecef' ] )
					->set_help_text( Language::get( 'Background color for tooltips, popups, informational overlays, and help elements in light mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'light',
						],
					] ),

				// Dark Mode - Tooltip Background
				Field::make( 'color', MDS_PREFIX . 'dark_tooltip_background', Language::get( 'Dark Mode - Tooltip Background' ) )
					->set_default_value( '#101216' )
					->set_palette( [ '#101216', '#14181c', '#1d2024', '#181a20', '#1a1c21' ] )
					->set_help_text( Language::get( 'Background color for tooltips, popups, informational overlays, and help elements in dark mode.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'theme_mode',
							'compare' => '=',
							'value'   => 'dark',
						],
					] ),

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

				// Show Uploaded Image in Advanced Mode
				Field::make( 'radio', MDS_PREFIX . 'show_uploaded_image_in_advanced_mode', Language::get( 'Show Uploaded Image in Advanced Mode' ) )
					->set_default_value( 'yes' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'use-ajax',
							'compare' => '=',
							'value'   => 'YES',
						],
					] )
					->set_help_text( Language::get( 'If Yes, the uploaded image and its dimensions will be shown in advanced mode. If No, only the image within the grid will be visible.' ) ),

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
				Field::make( 'radio', MDS_PREFIX . 'invert-pixels', Language::get( 'Invert Selection' ) )
					->set_default_value( 'NO' )
					->set_options( [
						'YES' => Language::get( 'Yes' ),
						'NO'  => Language::get( 'No' ),
					] )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'use-ajax',
							'compare' => '=',
							'value'   => 'YES',
						],
					] )
					->set_help_text( Language::get( 'If enabled, clicking a selected block will deselect it (inverting the selection). This only applies to the Advanced selection method.' ) ),

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

				// WooCommerce Login Redirect
				Field::make( 'text', MDS_PREFIX . 'woocommerce-login-redirect', Language::get( 'WooCommerce Login Redirect' ) )
					->set_default_value( '' )
					->set_help_text( Language::get( 'When WooCommerce integration is enabled, redirect users to this URL after a successful WooCommerce login. Leave empty to use WooCommerce\'s default behavior.' ) )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'woocommerce',
							'compare' => '=',
							'value'   => 'yes',
						],
					] ),

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
					->set_default_value( '332' )
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

				// Auto-advance after upload (Advanced mode)
				Field::make( 'radio', MDS_PREFIX . 'auto-advance-after-upload', Language::get( 'Auto-advance after Upload (Advanced)' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'use-ajax',
							'compare' => '=',
							'value'   => 'YES',
						],
					] )
					->set_help_text( Language::get( 'When enabled (Advanced selection method only), after a user uploads an image the flow skips the “Your Uploaded Pixels” preview and continues automatically. If the Confirm Orders Page is enabled, it goes to Confirm Order; if Confirm Orders is disabled, it goes directly to Payment. Default is No to show the preview screen as usual.' ) ),

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

				// Selection Adjacency Mode (Advanced selection)
				Field::make( 'radio', MDS_PREFIX . 'selection-adjacency-mode', Language::get( 'Selection Adjacency Mode' ) )
					->set_default_value( 'ADJACENT' )
					->set_options( [
						'ADJACENT'  => Language::get( 'Adjacent (any contiguous shape)' ),
						'RECTANGLE' => Language::get( 'Rectangle only' ),
						'NONE'      => Language::get( 'No adjacency enforcement' ),
					] )
					->set_conditional_logic( [
						'relation' => 'AND',
						[
							'field'   => MDS_PREFIX . 'use-ajax',
							'compare' => '=',
							'value'   => 'YES',
						],
					] )
					->set_help_text( Language::get( 'Controls how adjacency is enforced in Advanced selection: any contiguous shape, strict rectangle, or no enforcement.' ) ),

				// Expire Orders
				Field::make( 'radio', MDS_PREFIX . 'expire-orders', Language::get( 'Expire Orders' ) )
					->set_default_value( 'yes' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Enable expiration of orders.' ) ),

				// Auto-complete offline/manual payments
				Field::make( 'radio', MDS_PREFIX . 'auto-approve', Language::get( 'Auto-complete manual payments' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_conditional_logic( $woocommerce_logic )
					->set_help_text(
						Language::get_replace(
							'Automatically mark manual/offline MDS checkout flows as completed so buyers see the thank-you page immediately. Pixel approval still follows each grid\'s “Approve Automatically?” setting and may require review on the <a href="%APPROVAL_URL%">Pixels Awaiting Approval</a> screen. Leave this disabled when WooCommerce integration is handling payments.',
							'%APPROVAL_URL%',
							esc_url( admin_url( 'admin.php?page=mds-approve-pixels&app=N' ) )
						)
					),

				// Currency
				Field::make( 'text', MDS_PREFIX . 'currency', Language::get( 'Currency' ) )
					->set_conditional_logic( $woocommerce_logic )
					->set_default_value( 'CAD' )
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

				// Use Rich Text Popup Field
				Field::make( 'radio', MDS_PREFIX . 'popup-rich-text', Language::get( 'Use Rich Text Popup Field?' ) )
					->set_default_value( 'no' )
					->set_options( [
						'yes' => Language::get( 'Yes' ),
						'no'  => Language::get( 'No' ),
					] )
					->set_help_text( Language::get( 'Switch to a compact WYSIWYG editor for Popup Text. Output is limited to paragraphs, line breaks, bold, and italics.' ) ),

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

				// Max Upload Dimensions
				Field::make( 'text', MDS_PREFIX . 'max-upload-width', Language::get( 'Max Upload Width' ) )
					->set_default_value( '2000' )
					->set_attribute( 'type', 'number' )
					->set_attribute( 'min', '1' )
					->set_help_text( Language::get( 'Maximum width (in pixels) allowed when users upload ad images. Applies to both the grid image and tooltip image fields. Does not change display sizing; see Popup > Max Image Size.' ) ),

				Field::make( 'text', MDS_PREFIX . 'max-upload-height', Language::get( 'Max Upload Height' ) )
					->set_default_value( '2000' )
					->set_attribute( 'type', 'number' )
					->set_attribute( 'min', '1' )
					->set_help_text( Language::get( 'Maximum height (in pixels) allowed when users upload ad images. Applies to both the grid image and tooltip image fields. Does not change display sizing; see Popup > Max Image Size.' ) ),
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
					->set_help_text( Language::get( 'The URL of the extension server for automatic updates and downloads. Use http://extension-server-go:3030 or http://localhost:3030 for development or your production server URL. Default: https://extensions.milliondollarscript.com' ) ),

				// Portal auto-account creation
				Field::make( 'radio', MDS_PREFIX . 'extension_portal_auto_accounts', Language::get( 'Automatically create client portal accounts for extension purchases?' ) )
					->set_default_value( 'yes' )
					->set_options( [
						'yes' => Language::get( 'Yes - store buyer emails, create portal accounts, and sync licenses' ),
						'no'  => Language::get( 'No - do not store buyer emails or create client portal accounts' ),
					] )
					->set_help_text( Language::get( 'Recommended: keep this enabled so license purchases automatically create a client portal account tied to the buyer\'s email and display that email in the Extension Server. Disabling this means emails will not be stored with licenses and portal accounts will not be created. If you disable it and later lose site access, you must cancel auto-renewals directly in Stripe or contact support.' ) ),


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
			case 'woocommerce-login-redirect':
				// Sanitize URL for WooCommerce login redirect
				$field->set_value( esc_url_raw( (string) $field->get_value() ) );
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
            case 'mds-pixel-base':
				$old = self::get_option( 'mds-pixel-base', 'mds-pixel' );
				$new = sanitize_title( (string) $field->get_value() );
				if ( empty( $new ) ) { $new = 'mds-pixel'; }
				if ( $new !== $old ) {
					$hist_key = '_' . MDS_PREFIX . 'mds-pixel-base-history';
					$history = get_option( $hist_key, [] );
					if ( ! is_array( $history ) ) { $history = []; }
					if ( ! in_array( $old, $history, true ) ) { $history[] = $old; }
					update_option( $hist_key, $history );
					$field->set_value( $new );
					flush_rewrite_rules();
				}
				break;

			case 'mds-pixel-slug-structure':
				$old = self::get_option( 'mds-pixel-slug-structure', '%username%-%order_id%' );
				$new = (string) $field->get_value();
				if ( $new !== $old ) {
					// No rewrite structure change, but flush for good measure after pattern updates
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
		// If no explicit default provided, try to get it from field configuration
		if ( $default === null ) {
			$default = self::get_field_default_value( $name );
		}
		
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
	 * Returns the configured maximum upload dimensions for ad images.
	 *
	 * @return array{width:int|null,height:int|null}
	 */
	public static function get_upload_dimension_limits(): array {
		$width_option  = absint( self::get_option( 'max-upload-width', 2000 ) );
		$height_option = absint( self::get_option( 'max-upload-height', 2000 ) );

		$width_limit  = $width_option > 0 ? $width_option : null;
		$height_limit = $height_option > 0 ? $height_option : null;

		return [
			'width'  => $width_limit,
			'height' => $height_limit,
		];
	}

	/**
	 * Extract default value for a field from the Carbon Fields configuration
	 *
	 * @param string $field_name The field name to find default for
	 * @return mixed|null The default value or null if not found
	 */
	private static function get_field_default_value( string $field_name ) {
		// Check if $tabs is initialized before accessing it
		if ( ! isset( self::$tabs ) || empty( self::$tabs ) ) {
			return null;
		}
		
		foreach ( self::$tabs as $tab_fields ) {
			if ( ! is_array( $tab_fields ) ) {
				continue;
			}
			
			foreach ( $tab_fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				
				// Look for fields with matching name
				if ( isset( $field['name'] ) && $field['name'] === $field_name ) {
					return $field['default'] ?? null;
				}
			}
		}
		
		return null;
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
		if ( ! is_admin() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( $page !== 'milliondollarscript_options' ) {
			return;
		}

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
				'content' => '[milliondollarscript type="order"]',
				'option_name' => 'users-order-page',
			],
			[
				'title' => Language::get('Your Pixels'),
				'content' => '[milliondollarscript type="manage"]',
				'option_name' => 'users-pixels-page',
			],
			[
				'title' => Language::get('Checkout'),
				'content' => '[milliondollarscript type="confirm-order"]',
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
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );

		// AJAX handlers
		add_action( 'wp_ajax_mds_handle_theme_change', [ __CLASS__, 'ajax_handle_theme_change' ] );
		add_action( 'wp_ajax_mds_migrate_slugs', [ __CLASS__, 'ajax_migrate_slugs' ] );
	}

	public static function handle_theme_options_save(): void {
		// Regenerate dynamic styles after theme options are saved
		Styles::save_dynamic_css_file();
	}

	/**
	 * AJAX handler for theme switching
	 */
	public static function ajax_handle_theme_change(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'mds_admin_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$new_theme = sanitize_text_field( $_POST['theme'] ?? 'light' );
		$result = self::handle_theme_change( $new_theme );

        wp_send_json( $result );
	}

	/**
	 * AJAX: Migrate mds-pixel slugs to the current pattern.
	 */
    public static function ajax_migrate_slugs(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'mds_admin_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'bad_nonce' ], 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$page = max( 1, intval( $_POST['page'] ?? 1 ) );
		$batch = intval( $_POST['batch_size'] ?? 100 );
		if ( $batch < 1 ) { $batch = 100; }
		if ( $batch > 500 ) { $batch = 500; }

		$lock_key = '_' . MDS_PREFIX . 'slug_migration_lock';
		$lock = get_transient( $lock_key );
		if ( $page === 1 ) {
			if ( $lock ) {
				wp_send_json_error( [ 'message' => 'already_running' ], 409 );
			}
			set_transient( $lock_key, 1, 15 * MINUTE_IN_SECONDS );
		} else {
			if ( ! $lock ) { set_transient( $lock_key, 1, 15 * MINUTE_IN_SECONDS ); }
			else { set_transient( $lock_key, 1, 15 * MINUTE_IN_SECONDS ); }
		}

		$q = new \WP_Query( [
			'post_type' => 'mds-pixel',
			'post_status' => 'any',
			'posts_per_page' => $batch,
			'paged' => $page,
			'fields' => 'ids',
			'no_found_rows' => false,
		] );

		$updated_batch = 0;
		if ( $q->have_posts() ) {
			foreach ( $q->posts as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) { continue; }
				$old = $post->post_name;
				$new = \MillionDollarScript\Classes\Web\Permalinks::build_slug_for_post( (int) $post_id );
				if ( ! empty( $new ) && $new !== $old ) {
					add_post_meta( $post_id, '_wp_old_slug', $old );
					wp_update_post( [ 'ID' => $post_id, 'post_name' => $new ] );
					$updated_batch++;
				}
			}
		}

		$total_pages = intval( $q->max_num_pages );
        $done = ( $page >= max( 1, $total_pages ) ) || ! $q->have_posts();
		if ( $done ) { 
			delete_transient( $lock_key );
			// Flush permalinks once at the end of migration
			flush_rewrite_rules();
		}

		wp_send_json_success( [
			'updated' => $updated_batch,
			'page' => $page,
			'total_pages' => max( 1, $total_pages ),
			'done' => $done,
		] );
	}

	/**
	 * Check if theme mode is changing and handle backup/confirmation
	 *
	 * @param string $new_theme
	 * @return array
	 */
	public static function handle_theme_change( string $new_theme ): array {
		$current_theme = self::get_option( 'theme_mode', 'light' );
		
		if ( $current_theme === $new_theme ) {
			return [ 'status' => 'no_change', 'message' => 'Theme is already set to ' . $new_theme ];
		}

		// Backup current theme settings
		$backup_created = self::backup_current_theme_settings( $current_theme );
		
		if ( ! $backup_created ) {
			return [ 'status' => 'error', 'message' => 'Failed to backup current theme settings' ];
		}

		// Apply new theme colors
		$applied = self::apply_theme_colors( $new_theme );
		
		if ( ! $applied ) {
			return [ 'status' => 'error', 'message' => 'Failed to apply new theme colors' ];
		}



		// Fire action for other systems to hook into
		do_action( 'mds_theme_changed', $new_theme, $current_theme );

		$response = [ 
			'status' => 'success', 
			'message' => 'Theme changed from ' . $current_theme . ' to ' . $new_theme,
			'backup_created' => $backup_created
		];

		return $response;
	}

	/**
	 * Backup current theme settings before switching
	 *
	 * @param string $theme
	 * @return bool
	 */
	public static function backup_current_theme_settings( string $theme ): bool {
		$backup_key = 'theme_backup_' . $theme . '_' . time();
		
		// Get comprehensive list of variables to backup using unified system
		$settings_to_backup = self::get_theme_backup_variables( $theme );

		$backup = [];
		foreach ( $settings_to_backup as $setting ) {
			$backup[ $setting ] = self::get_option( $setting );
		}

		return update_option( $backup_key, $backup );
	}

	/**
	 * Get complete list of variables to backup for theme
	 *
	 * @param string $theme
	 * @return array
	 */
	private static function get_theme_backup_variables( string $theme ): array {
		// Shared variables (backed up for both themes)
		$shared_variables = [
			'primary_color',
			'secondary_color',
		];

		// Dark mode accent variables (backed up separately)
		if ( $theme === 'dark' ) {
			$shared_variables[] = 'dark_secondary_accent';
		}

		// Theme-specific variables
		if ( $theme === 'dark' ) {
			$theme_variables = [
				// Dark mode foundation variables
				'dark_main_background',
				'dark_secondary_background',
				'dark_tertiary_background',
				'dark_text_primary',
				'dark_text_secondary',
				'dark_border_color',
				'dark_tooltip_background',
				
				// Dark mode button variables
				'dark_button_primary_bg',
				'dark_button_primary_text',
				'dark_button_secondary_bg',
				'dark_button_secondary_text',
				'dark_button_success_bg',
				'dark_button_success_text',
				'dark_button_danger_bg',
				'dark_button_danger_text',
			];
		} else {
			$theme_variables = [
				// Light mode foundation variables
				'background_color',
				'text_color',
				'light_text_secondary',
				'light_tertiary_background',
				'light_tooltip_background',
				'light_border_color',
				
				// Light mode button variables
				'button-color',
				'button_text_color',
				'button_secondary_bg',
				'button_secondary_text',
				'button_success_bg',
				'button_success_text',
				'button_danger_bg',
				'button_danger_text',
			];
		}

		return array_merge( $shared_variables, $theme_variables );
	}

	/**
	 * Apply theme-specific color settings
	 *
	 * @param string $theme
	 * @return bool
	 */
	public static function apply_theme_colors( string $theme ): bool {
		if ( $theme === 'dark' ) {
			return self::apply_dark_theme_colors();
		} else {
			return self::apply_light_theme_colors();
		}
	}

	/**
	 * Apply dark theme color scheme
	 *
	 * @return bool
	 */
	public static function apply_dark_theme_colors(): bool {
		$defaults = self::get_theme_variable_defaults();
		$dark_defaults = $defaults['dark'];
		
		// Foundation variables (mapped to shared options for compatibility)
		$foundation_colors = [
			'background_color' => self::get_option( 'dark_main_background', $dark_defaults['bg_primary'] ),
			'text_color' => self::get_option( 'dark_text_primary', $dark_defaults['text_primary'] ),
			'secondary_color' => self::get_option( 'dark_secondary_accent', '#ffffff' ),
			'primary_color' => $dark_defaults['btn_primary_bg'], // Dark mode optimized blue
		];

		// Button variables (complete set with new dark mode fields)
		$button_colors = [
			'button-color' => self::get_option( 'dark_button_primary_bg', $dark_defaults['btn_primary_bg'] ),
			'button_text_color' => self::get_option( 'dark_button_primary_text', $dark_defaults['btn_primary_text'] ),
			'button_secondary_bg' => self::get_option( 'dark_button_secondary_bg', $dark_defaults['btn_secondary_bg'] ),
			'button_secondary_text' => self::get_option( 'dark_button_secondary_text', $dark_defaults['btn_secondary_text'] ),
			'button_success_bg' => self::get_option( 'dark_button_success_bg', $dark_defaults['btn_success_bg'] ),
			'button_success_text' => self::get_option( 'dark_button_success_text', $dark_defaults['btn_success_text'] ),
			'button_danger_bg' => self::get_option( 'dark_button_danger_bg', $dark_defaults['btn_danger_bg'] ),
			'button_danger_text' => self::get_option( 'dark_button_danger_text', $dark_defaults['btn_danger_text'] ),
		];

		// Apply all color updates atomically
		$all_colors = array_merge( $foundation_colors, $button_colors );
		
		foreach ( $all_colors as $option => $value ) {
			if ( ! self::update_option( $option, $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Apply light theme color scheme (restore defaults)
	 *
	 * @return bool
	 */
	public static function apply_light_theme_colors(): bool {
		$defaults = self::get_theme_variable_defaults();
		$light_defaults = $defaults['light'];
		
		// Foundation variables (using light mode defaults)
		$foundation_colors = [
			'background_color' => $light_defaults['bg_primary'],
			'text_color' => $light_defaults['text_primary'],
			'secondary_color' => '#000000', // Keep existing secondary color logic
			'primary_color' => '#ff0000', // Keep existing primary color logic
		];

		// Button variables (complete set with light mode defaults)
		$button_colors = [
			'button-color' => $light_defaults['btn_primary_bg'],
			'button_text_color' => $light_defaults['btn_primary_text'],
			'button_secondary_bg' => $light_defaults['btn_secondary_bg'],
			'button_secondary_text' => $light_defaults['btn_secondary_text'],
			'button_success_bg' => $light_defaults['btn_success_bg'],
			'button_success_text' => $light_defaults['btn_success_text'],
			'button_danger_bg' => $light_defaults['btn_danger_bg'],
			'button_danger_text' => $light_defaults['btn_danger_text'],
		];

		// Apply all color updates atomically
		$all_colors = array_merge( $foundation_colors, $button_colors );
		
		foreach ( $all_colors as $option => $value ) {
			if ( ! self::update_option( $option, $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get theme-aware CSS variables
	 *
	 * @return array
	 */
	public static function get_theme_css_variables(): array {
		$theme = self::get_option( 'theme_mode', 'light' );
		$defaults = self::get_theme_variable_defaults();
		
		$variables = [
			// Common variables (shared across themes)
			'--mds-theme-mode' => $theme,
			'--mds-primary-color' => self::get_option( 'primary_color', '#ff0000' ),
			'--mds-secondary-color' => $theme === 'dark' 
				? self::get_option( 'dark_secondary_accent', '#ffffff' )
				: self::get_option( 'secondary_color', '#000000' ),
		];

		// Get theme-specific variable mappings
		$theme_variables = self::get_theme_variable_mappings( $theme );
		
		// Generate CSS variables using unified approach
		foreach ( $theme_variables as $css_var => $config ) {
			$option_name = $config['option'];
			$default_value = $defaults[ $theme ][ $config['key'] ] ?? $config['fallback'];
			$variables[ $css_var ] = self::get_option( $option_name, $default_value );
		}

		// Add button variables using unified approach
		$button_variables = self::get_button_css_variables( $theme );
		$variables = array_merge( $variables, $button_variables );

		return $variables;
	}

	/**
	 * Get theme variable mappings for CSS generation
	 *
	 * @param string $theme
	 * @return array
	 */
	private static function get_theme_variable_mappings( string $theme ): array {
		$prefix = $theme === 'dark' ? 'dark_' : 'light_';
		$bg_primary_option = $theme === 'dark' ? 'dark_main_background' : 'background_color';
		$text_primary_option = $theme === 'dark' ? 'dark_text_primary' : 'text_color';
		
		return [
			// Foundation variables
			'--mds-bg-primary' => [
				'option' => $bg_primary_option,
				'key' => 'bg_primary',
				'fallback' => $theme === 'dark' ? '#101216' : '#ffffff'
			],
			'--mds-bg-secondary' => [
				'option' => $theme === 'dark' ? 'dark_secondary_background' : 'light_secondary_background',
				'key' => 'bg_secondary', 
				'fallback' => $theme === 'dark' ? '#14181c' : '#f4f2f1'
			],
			'--mds-bg-tertiary' => [
				'option' => $prefix . 'tertiary_background',
				'key' => 'bg_tertiary',
				'fallback' => $theme === 'dark' ? '#1d2024' : '#efefef'
			],
			'--mds-text-primary' => [
				'option' => $text_primary_option,
				'key' => 'text_primary',
				'fallback' => $theme === 'dark' ? '#ffffff' : '#333333'
			],
			'--mds-text-secondary' => [
				'option' => $prefix . 'text_secondary',
				'key' => 'text_secondary',
				'fallback' => $theme === 'dark' ? '#cccccc' : '#666666'
			],
			'--mds-border-color' => [
				'option' => $prefix . 'border_color',
				'key' => 'border_primary',
				'fallback' => $theme === 'dark' ? '#666666' : '#dddddd'
			],
			'--mds-tooltip-bg' => [
				'option' => $prefix . 'tooltip_background',
				'key' => 'tooltip_bg',
				'fallback' => $theme === 'dark' ? '#101216' : '#ffffff'
			],
			
			// Component variables
			'--mds-menu-bg' => [
				'option' => $prefix . 'menu_background',
				'key' => 'menu_bg',
				'fallback' => $theme === 'dark' ? '#14181c' : '#F4F2F1'
			],
			'--mds-menu-text' => [
				'option' => $prefix . 'menu_text',
				'key' => 'menu_text',
				'fallback' => $theme === 'dark' ? '#ffffff' : '#333333'
			],
			'--mds-menu-hover-bg' => [
				'option' => $prefix . 'menu_hover_background',
				'key' => 'menu_hover_bg',
				'fallback' => $theme === 'dark' ? '#1d2024' : '#dddddd'
			],
			'--mds-input-bg' => [
				'option' => $prefix . 'input_background',
				'key' => 'input_bg',
				'fallback' => $theme === 'dark' ? '#14181c' : '#ffffff'
			],
			'--mds-input-border' => [
				'option' => $prefix . 'input_border',
				'key' => 'input_border',
				'fallback' => $theme === 'dark' ? '#666666' : '#cccccc'
			],
			'--mds-card-bg' => [
				'option' => $prefix . 'card_background',
				'key' => 'card_bg',
				'fallback' => $theme === 'dark' ? '#14181c' : '#ffffff'
			],
			'--mds-shadow' => [
				'option' => $prefix . 'shadow_color',
				'key' => 'shadow',
				'fallback' => $theme === 'dark' ? 'rgba(0, 0, 0, 0.5)' : 'rgba(0, 0, 0, 0.1)'
			],
		];
	}

	/**
	 * Get button CSS variables for theme
	 *
	 * @param string $theme
	 * @return array
	 */
	private static function get_button_css_variables( string $theme ): array {
		$defaults = self::get_theme_variable_defaults();
		$theme_defaults = $defaults[ $theme ];
		
		if ( $theme === 'dark' ) {
			return [
				'--mds-btn-primary-bg' => self::get_option( 'dark_button_primary_bg', $theme_defaults['btn_primary_bg'] ),
				'--mds-btn-primary-text' => self::get_option( 'dark_button_primary_text', $theme_defaults['btn_primary_text'] ),
				'--mds-btn-secondary-bg' => self::get_option( 'dark_button_secondary_bg', $theme_defaults['btn_secondary_bg'] ),
				'--mds-btn-secondary-text' => self::get_option( 'dark_button_secondary_text', $theme_defaults['btn_secondary_text'] ),
				'--mds-btn-success-bg' => self::get_option( 'dark_button_success_bg', $theme_defaults['btn_success_bg'] ),
				'--mds-btn-success-text' => self::get_option( 'dark_button_success_text', $theme_defaults['btn_success_text'] ),
				'--mds-btn-danger-bg' => self::get_option( 'dark_button_danger_bg', $theme_defaults['btn_danger_bg'] ),
				'--mds-btn-danger-text' => self::get_option( 'dark_button_danger_text', $theme_defaults['btn_danger_text'] ),
			];
		} else {
			return [
				'--mds-btn-primary-bg' => self::get_option( 'button-color', $theme_defaults['btn_primary_bg'] ),
				'--mds-btn-primary-text' => self::get_option( 'button_text_color', $theme_defaults['btn_primary_text'] ),
				'--mds-btn-secondary-bg' => self::get_option( 'button_secondary_bg', $theme_defaults['btn_secondary_bg'] ),
				'--mds-btn-secondary-text' => self::get_option( 'button_secondary_text', $theme_defaults['btn_secondary_text'] ),
				'--mds-btn-success-bg' => self::get_option( 'button_success_bg', $theme_defaults['btn_success_bg'] ),
				'--mds-btn-success-text' => self::get_option( 'button_success_text', $theme_defaults['btn_success_text'] ),
				'--mds-btn-danger-bg' => self::get_option( 'button_danger_bg', $theme_defaults['btn_danger_bg'] ),
				'--mds-btn-danger-text' => self::get_option( 'button_danger_text', $theme_defaults['btn_danger_text'] ),
			];
		}
	}

	/**
	 * Get default values for theme variables
	 *
	 * @return array
	 */
	private static function get_theme_variable_defaults(): array {
		return [
			'light' => [
				// Foundation
				'bg_primary' => '#ffffff',
				'bg_secondary' => '#f4f2f1',
				'bg_tertiary' => '#efefef',
				'text_primary' => '#333333',
				'text_secondary' => '#666666',
				'border_primary' => '#dddddd',
				'tooltip_bg' => '#ffffff',
				
				// Components
				'menu_bg' => '#F4F2F1',
				'menu_text' => '#333333',
				'menu_hover_bg' => '#dddddd',
				'input_bg' => '#ffffff',
				'input_border' => '#cccccc',
				'card_bg' => '#ffffff',
				'shadow' => 'rgba(0, 0, 0, 0.1)',
				
				// Buttons
				'btn_primary_bg' => '#0073aa',
				'btn_primary_text' => '#ffffff',
				'btn_secondary_bg' => '#91877D',
				'btn_secondary_text' => '#ffffff',
				'btn_success_bg' => '#28a745',
				'btn_success_text' => '#ffffff',
				'btn_danger_bg' => '#f44336',
				'btn_danger_text' => '#ffffff',
			],
			'dark' => [
				// Foundation
				'bg_primary' => '#101216',
				'bg_secondary' => '#14181c',
				'bg_tertiary' => '#1d2024',
				'text_primary' => '#ffffff',
				'text_secondary' => '#cccccc',
				'border_primary' => '#666666',
				'tooltip_bg' => '#101216',
				
				// Components
				'menu_bg' => '#14181c',
				'menu_text' => '#ffffff',
				'menu_hover_bg' => '#1d2024',
				'input_bg' => '#14181c',
				'input_border' => '#666666',
				'card_bg' => '#14181c',
				'shadow' => 'rgba(0, 0, 0, 0.5)',
				
				// Buttons
				'btn_primary_bg' => '#0073aa',
				'btn_primary_text' => '#ffffff',
				'btn_secondary_bg' => '#6b6155',
				'btn_secondary_text' => '#ffffff',
				'btn_success_bg' => '#4ade80',
				'btn_success_text' => '#000000',
				'btn_danger_bg' => '#f87171',
				'btn_danger_text' => '#000000',
			]
		];
	}

	/**
	 * Get accessible color contrasts for theme
	 *
	 * @param string $theme
	 * @return array
	 */
	public static function get_accessible_colors( string $theme = null ): array {
		if ( $theme === null ) {
			$theme = self::get_option( 'theme_mode', 'light' );
		}

		if ( $theme === 'dark' ) {
			return [
				'bg_primary' => '#101216',
				'text_primary' => '#ffffff',
				'text_secondary' => '#cccccc',
				'link_color' => '#66b3ff', // High contrast blue for dark backgrounds
				'success_color' => '#4ade80',
				'error_color' => '#f87171',
				'warning_color' => '#fbbf24',
			];
		} else {
			return [
				'bg_primary' => '#ffffff',
				'text_primary' => '#000000',
				'text_secondary' => '#333333',
				'link_color' => '#0066cc', // High contrast blue for light backgrounds
				'success_color' => '#16a34a',
				'error_color' => '#dc2626',
				'warning_color' => '#d97706',
			];
		}
	}
}
