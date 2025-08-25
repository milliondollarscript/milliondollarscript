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

namespace MillionDollarScript\Classes\User;

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Language\Language;

defined( 'ABSPATH' ) or exit;

class Users {

	public static function custom_fields(): void {
		$fields = array(
			Field::make( 'select', MDS_PREFIX . 'privileged', Language::get( 'Privileged' ) )
			     ->set_default_value( '0' )
			     ->set_options( [
				     '0' => 'Normal',
				     '1' => 'Privileged',
			     ] )
			     ->set_help_text( Language::get( 'Privileged users bypass payment for pixels.' ) ),
		);

		$fields = apply_filters( 'mds_user_fields', $fields, MDS_PREFIX );

		Container::make( 'user_meta', MDS_PREFIX . 'users', Language::get( 'Million Dollar Script' ) )
		         ->add_fields( $fields );
	}

	public static function add_custom_user_columns( $columns ) {
		$columns['view_count']  = Language::get( 'View Count' );
		$columns['click_count'] = Language::get( 'Click Count' );
		$columns['privileged']  = Language::get( 'Privileged' );

		return $columns;
	}

	public static function show_custom_user_columns_content( $value, $column_name, $user_id ) {
		if ( 'view_count' == $column_name ) {
			return get_user_meta( $user_id, MDS_PREFIX . 'view_count', true );
		}

		if ( 'click_count' == $column_name ) {
			return get_user_meta( $user_id, MDS_PREFIX . 'click_count', true );
		}

		if ( 'privileged' == $column_name ) {
			return carbon_get_user_meta( $user_id, MDS_PREFIX . 'privileged' );
		}

		return $value;
	}

	public static function make_custom_user_columns_sortable( $columns ) {
		$columns['view_count']  = MDS_PREFIX . 'view_count';
		$columns['click_count'] = MDS_PREFIX . 'click_count';
		$columns['privileged']  = MDS_PREFIX . 'privileged';

		return $columns;
	}

	public static function sort_users_by_custom_column( $query ): void {
		if ( ! is_admin() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'view_count' == $orderby ) {
			$query->set( 'meta_key', MDS_PREFIX . 'view_count' );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( 'click_count' == $orderby ) {
			$query->set( 'meta_key', MDS_PREFIX . 'click_count' );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( 'privileged' == $orderby ) {
			$query->set( 'meta_key', MDS_PREFIX . 'privileged' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Login redirect
	 *
	 * @param $url
	 * @param $query
	 * @param $user
	 *
	 * @return string
	 */
	public static function login_redirect( $url, $query, $user ): string {
		return Options::get_option( 'login-redirect', home_url() );
	}

	/**
	 * Logout redirect
	 *
	 * @param $logout_url
	 * @param $requested_redirect_to
	 * @param $user
	 *
	 * @return string
	 */
	public static function logout_redirect( $logout_url, $requested_redirect_to, $user ): string {
		return Options::get_option( 'logout-redirect', home_url() );
	}

	/**
	 * Change login header link to #
	 *
	 * @param $login_header_url
	 *
	 * @return string
	 */
	public static function login_headerurl( $login_header_url ): string {
		return home_url();
	}

	/**
	 * Change logo on login page.
	 */
	public static function login_head(): void {
		echo '<style>';
		
		// Handle custom logo
		$login_header_image = Options::get_option( 'login-header-image' );
		if ( empty( $login_header_image ) ) {
			$logo = wp_get_attachment_url( get_theme_mod( 'custom_logo' ) );
			if ( empty( $logo ) ) {
				$logo = '';
			}
		} else {
			$logo = wp_get_attachment_url( $login_header_image );
		}

		$show_text = '';
		if ( empty( $logo ) && Options::get_option( 'login-header-text', get_bloginfo( 'name' ) ) != '' ) {
			$show_text = 'text-indent: 0;';
		}

		// Base login page styles
		echo '
		.login h1 a {
			background-image: url(' . esc_url( $logo ) . ') !important;
			background-size: contain;
			margin: 0 auto;
			width: auto;
			max-height: 100px;
			' . $show_text . '
		}
		#backtoblog {
			display: none;
		}';

		// Check if we're in dark mode using the correct option name
		$theme_mode = Options::get_option( 'theme_mode', 'light' );
		
		if ( $theme_mode === 'dark' ) {
			// Get the plugin's configured dark mode colors with sensible fallbacks
			$dark_main_bg = Options::get_option( 'dark_main_background', '#101216' );
			$dark_secondary_bg = Options::get_option( 'dark_secondary_background', '#14181c' );
			$dark_tertiary_bg = Options::get_option( 'dark_tertiary_background', '#1d2024' );
			$dark_text_primary = Options::get_option( 'dark_text_primary', '#ffffff' );
			$dark_button_primary_bg = Options::get_option( 'dark_button_primary_bg', '#0073aa' );
			$dark_button_primary_text = Options::get_option( 'dark_button_primary_text', '#ffffff' );
			$dark_button_danger_bg = Options::get_option( 'dark_button_danger_bg', '#f87171' );
			$dark_border_color = Options::get_option( 'dark_border_color', '#666666' );

			// Fallback to hardcoded values if options are empty (theme not configured yet)
			if ( empty( $dark_main_bg ) ) $dark_main_bg = '#1a1a1a';
			if ( empty( $dark_secondary_bg ) ) $dark_secondary_bg = '#2d2d2d';
			if ( empty( $dark_tertiary_bg ) ) $dark_tertiary_bg = '#3d3d3d';
			if ( empty( $dark_text_primary ) ) $dark_text_primary = '#ffffff';
			if ( empty( $dark_button_primary_bg ) ) $dark_button_primary_bg = '#0073aa';
			if ( empty( $dark_button_primary_text ) ) $dark_button_primary_text = '#ffffff';
			if ( empty( $dark_button_danger_bg ) ) $dark_button_danger_bg = '#dc3232';
			if ( empty( $dark_border_color ) ) $dark_border_color = '#555555';

			echo '
			/* Dark mode support for login page */
			body.login {
				background-color: ' . esc_attr( $dark_main_bg ) . ' !important;
				color: ' . esc_attr( $dark_text_primary ) . ' !important;
			}
			.login form {
				background-color: ' . esc_attr( $dark_secondary_bg ) . ' !important;
				border: 1px solid ' . esc_attr( $dark_border_color ) . ' !important;
			}
			.login form input[type="text"],
			.login form input[type="password"] {
				background-color: ' . esc_attr( $dark_tertiary_bg ) . ' !important;
				border: 1px solid ' . esc_attr( $dark_border_color ) . ' !important;
				color: ' . esc_attr( $dark_text_primary ) . ' !important;
			}
			.login form input[type="text"]:focus,
			.login form input[type="password"]:focus {
				border-color: ' . esc_attr( $dark_button_primary_bg ) . ' !important;
				box-shadow: 0 0 0 1px ' . esc_attr( $dark_button_primary_bg ) . ' !important;
			}
			.login form .forgetmenot label {
				color: ' . esc_attr( $dark_text_primary ) . ' !important;
			}
			.login #nav a,
			.login #backtoblog a {
				color: ' . esc_attr( $dark_button_primary_bg ) . ' !important;
			}
			.login #nav a:hover,
			.login #backtoblog a:hover {
				color: #005a87 !important;
			}
			.login .message {
				background-color: ' . esc_attr( $dark_secondary_bg ) . ' !important;
				border-left: 4px solid ' . esc_attr( $dark_button_primary_bg ) . ' !important;
				color: ' . esc_attr( $dark_text_primary ) . ' !important;
			}
			.login .error {
				background-color: ' . esc_attr( $dark_secondary_bg ) . ' !important;
				border-left: 4px solid ' . esc_attr( $dark_button_danger_bg ) . ' !important;
				color: ' . esc_attr( $dark_text_primary ) . ' !important;
			}
			.login .wp-core-ui .button-primary {
				background: ' . esc_attr( $dark_button_primary_bg ) . ' !important;
				border-color: ' . esc_attr( $dark_button_primary_bg ) . ' !important;
				color: ' . esc_attr( $dark_button_primary_text ) . ' !important;
			}
			.login .wp-core-ui .button-primary:hover {
				background: #005a87 !important;
				border-color: #005a87 !important;
			}
			.login #nav a,
			.login #backtoblog a,
			.login .mds-register-link {
				color: ' . esc_attr( $dark_button_primary_bg ) . ' !important;
			}
			.login #nav a:hover,
			.login #backtoblog a:hover,
			.login .mds-register-link:hover {
				color: #005a87 !important;
			}
			.login .wp-core-ui .button-secondary,
			.login .wp-core-ui .button-register,
			.login .wp-core-ui input[type="submit"].button-register {
				background: ' . esc_attr( $dark_secondary_bg ) . ' !important;
				border-color: ' . esc_attr( $dark_border_color ) . ' !important;
				color: ' . esc_attr( $dark_text_primary ) . ' !important;
			}
			.login .wp-core-ui .button-secondary:hover,
			.login .wp-core-ui .button-register:hover,
			.login .wp-core-ui input[type="submit"].button-register:hover {
				background: ' . esc_attr( $dark_tertiary_bg ) . ' !important;
				border-color: ' . esc_attr( $dark_button_primary_bg ) . ' !important;
				color: ' . esc_attr( $dark_text_primary ) . ' !important;
			}';
		}
		
		echo '</style>';
	}

	/**
	 * Change the login header text.
	 *
	 * @return string
	 */
	public static function login_headertext(): string {
		return Language::get( Options::get_option( 'login-header-text', get_bloginfo( 'name' ) ) );
	}
}
