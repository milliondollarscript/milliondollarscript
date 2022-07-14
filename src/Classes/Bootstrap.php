<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.4
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

class Bootstrap {

	private $instance;

	function __construct() {
		if ( isset( $this->instance ) ) {
			return $this->instance;
		}

		$this->instance = $this;

		// Add main page
		add_action( 'admin_menu', [ '\MillionDollarScript\Classes\MillionDollarScript', 'menu' ], 9 );

		// Add options page
		add_action( 'carbon_fields_register_fields', [ '\MillionDollarScript\Classes\Options', 'register' ] );
		add_action( 'after_setup_theme', [ '\MillionDollarScript\Classes\Options', 'load' ] );
		add_action( 'after_setup_theme', [ '\MillionDollarScript\Classes\Block', 'load' ] );
		add_filter( 'carbon_fields_before_field_save', [ '\MillionDollarScript\Classes\Options', 'save' ] );

		// Add Admin page
		if ( Options::get_option( 'admin', false, 'options', 'no' ) == 'yes' ) {
			add_action( 'admin_menu', [ '\MillionDollarScript\Classes\Admin', 'menu' ], 10 );
		}

		// enqueue js
		add_action( 'wp_enqueue_scripts', [ '\MillionDollarScript\Classes\Functions', 'enqueue_scripts' ] );
		add_action( 'login_enqueue_scripts', [ '\MillionDollarScript\Classes\Functions', 'enqueue_scripts' ] );

		// shortcode
		add_shortcode( 'milliondollarscript', [ '\MillionDollarScript\Classes\Shortcode', 'shortcode' ] );

		// load WooCommerce integration
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && Options::get_option( 'woocommerce', false, 'options', 'no' ) == 'yes' ) {
			new WooCommerce();
		}

		// return if user integration isn't enabled
		if ( Options::get_option( 'users', false, 'options', 'no' ) == 'no' ) {
			return $this;
		}

		// user actions
		add_action( 'user_register', [ '\MillionDollarScript\Classes\Users', 'register' ], 10, 1 );
		add_action( 'wp_login', [ '\MillionDollarScript\Classes\Users', 'login' ], 10, 2 );
		add_action( 'wp_logout', [ '\MillionDollarScript\Classes\Users', 'logout' ], 10, 1 );
		add_action( 'profile_update', [ '\MillionDollarScript\Classes\Users', 'update' ], 10, 2 );
		add_action( 'delete_user', [ '\MillionDollarScript\Classes\Users', 'delete' ], 10, 3 );

		// WP login page filters
		add_filter( 'login_headerurl', [ '\MillionDollarScript\Classes\Users', 'login_headerurl' ] );
		add_filter( 'login_head', [ '\MillionDollarScript\Classes\Users', 'login_head' ] );

		// Update checker
		add_action( 'plugins_loaded', [ '\MillionDollarScript\Classes\Update', 'checker' ] );

		// Cron
		add_filter( 'cron_schedules', [ 'MillionDollarScript\Classes\Cron', 'add_minute' ] );
		add_action( 'milliondollarscript_cron_minute', [ 'MillionDollarScript\Classes\Cron', 'minute' ] );

		// Hook for when loaded
		do_action( 'mds-loaded', $this );

		return $this;
	}
}
