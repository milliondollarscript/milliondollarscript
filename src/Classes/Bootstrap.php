<?php

/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

defined( 'ABSPATH' ) or exit;

class Bootstrap {

	private Bootstrap $instance;

	function __construct() {
		if ( isset( $this->instance ) ) {
			return $this->instance;
		}

		$this->instance = $this;

		$page = '';
		if ( isset( $_GET['page'] ) ) {
			$page = $_GET['page'];
		}

		// Init MDS core
		add_action( 'init', function () {
			require_once MDS_CORE_PATH . 'include/init.php';
		} );

		// Load language
		add_action( 'plugins_loaded', [ '\MillionDollarScript\Classes\Language', 'load_textdomain' ] );

		// Add main page
		add_action( 'admin_menu', [ '\MillionDollarScript\Classes\MillionDollarScript', 'menu' ], 9 );

		// Add user fields
		add_action( 'carbon_fields_register_fields', [ '\MillionDollarScript\Classes\Users', 'custom_fields' ] );
		add_filter( 'manage_users_columns', [ '\MillionDollarScript\Classes\Users', 'add_custom_user_columns' ] );
		add_filter( 'manage_users_custom_column', [
			'\MillionDollarScript\Classes\Users',
			'show_custom_user_columns_content'
		], 10, 3 );
		add_filter( 'manage_users_sortable_columns', [
			'\MillionDollarScript\Classes\Users',
			'make_custom_user_columns_sortable'
		] );
		add_action( 'pre_get_users', [ '\MillionDollarScript\Classes\Users', 'sort_users_by_custom_column' ] );

		// Add Options page
		add_action( 'carbon_fields_register_fields', [ '\MillionDollarScript\Classes\Options', 'register' ] );
		add_action( 'after_setup_theme', [ '\MillionDollarScript\Classes\Options', 'load' ] );
		if ( $page == MDS_PREFIX . 'options' ) {
			add_filter( 'carbon_fields_before_field_save', [ '\MillionDollarScript\Classes\Options', 'save' ] );
		}

		// Add Block
		add_action( 'wp_enqueue_scripts', [ '\MillionDollarScript\Classes\Block', 'register_style' ] );
		add_action( 'after_setup_theme', [ '\MillionDollarScript\Classes\Block', 'load' ] );

		// Add Emails page
		add_action( 'carbon_fields_register_fields', [ '\MillionDollarScript\Classes\Emails', 'register' ] );
		add_action( 'after_setup_theme', [ '\MillionDollarScript\Classes\Emails', 'load' ] );
		if ( $page == MDS_PREFIX . 'emails' ) {
			add_filter( 'carbon_fields_before_field_save', [ '\MillionDollarScript\Classes\Emails', 'save' ] );
		}
		add_filter( 'crb_media_buttons_html', function ( $html, $field_name ) {
			if ( $field_name === 'rich_text_field_name_here' ) {
				return "";
			}

			return $html;
		}, 10, 2 );

		// Add mds-pixel post type fields
		add_action( 'carbon_fields_register_fields', [ '\MillionDollarScript\Classes\FormFields', 'register' ] );
		add_action( 'carbon_fields_post_meta_container_saved', [ '\MillionDollarScript\Classes\FormFields', 'save' ] );
		add_action( 'init', [ '\MillionDollarScript\Classes\FormFields', 'register_post_type' ] );
		add_action( 'init', [ '\MillionDollarScript\Classes\FormFields', 'register_custom_post_status' ] );
		add_action( 'admin_footer-post.php', [ '\MillionDollarScript\Classes\FormFields', 'add_custom_post_status' ] );
		add_action( 'admin_footer-post-new.php', [
			'\MillionDollarScript\Classes\FormFields',
			'add_custom_post_status'
		] );
		add_action( 'admin_footer-edit.php', [ '\MillionDollarScript\Classes\FormFields', 'add_quick_edit_status' ] );
		add_filter( 'manage_mds-pixel_posts_columns', [
			'\MillionDollarScript\Classes\FormFields',
			'add_status_column'
		] );
		add_action( 'manage_mds-pixel_posts_custom_column', [
			'\MillionDollarScript\Classes\FormFields',
			'fill_status_column'
		] );
		add_filter( 'manage_edit-mds-pixel_sortable_columns', [
			'\MillionDollarScript\Classes\FormFields',
			'sortable_columns'
		] );
		add_action( 'pre_get_posts', [ '\MillionDollarScript\Classes\FormFields', 'orderby_status' ] );
		add_action( 'pre_get_posts', [ '\MillionDollarScript\Classes\FormFields', 'modify_search_query' ] );
		add_filter( 'posts_search', [ '\MillionDollarScript\Classes\FormFields', 'posts_search' ], 10, 2 );

		// For debugging search SQL
//		add_filter( 'posts_request', function( $request, $query ) {
//			if ( $query->is_search() && $query->is_main_query() ) {
//				// Print the entire SQL query
//				var_dump( $request );
//			}
//			return $request;
//		}, 10, 2 );

		// Add Widget
		// add_action( 'widgets_init', function () {
		// 	register_widget( '\MillionDollarScript\Classes\Widget' );
		// } );

		// Add Admin page
		add_action( 'admin_menu', [ '\MillionDollarScript\Classes\Admin', 'menu' ], 10 );

		// Load Admin AJAX
		add_action( 'wp_ajax_mds_admin_ajax', [ '\MillionDollarScript\Classes\Admin', 'ajax' ] );

		add_action( 'wp_ajax_mds_update_language', [ '\MillionDollarScript\Classes\Admin', 'update_language' ] );
		add_action( 'wp_ajax_mds_create_pages', [ '\MillionDollarScript\Classes\Admin', 'create_pages' ] );
		add_action( 'wp_ajax_mds_delete_pages', [ '\MillionDollarScript\Classes\Admin', 'delete_pages' ] );

		// Load Block Editor JS
		add_action( 'enqueue_block_editor_assets', [ '\MillionDollarScript\Classes\Admin', 'block_editor_scripts' ] );

		// Add NFS page
		add_action( 'init', [ '\MillionDollarScript\Classes\NFS', 'init' ], 10 );

		// Load Frontend AJAX
		add_action( 'plugins_loaded', [ '\MillionDollarScript\Classes\Ajax', 'init' ], 10 );

		// enqueue js
		add_action( 'wp_enqueue_scripts', [ '\MillionDollarScript\Classes\Functions', 'register_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ '\MillionDollarScript\Classes\Functions', 'enqueue_scripts' ] );
		add_action( 'login_enqueue_scripts', [ '\MillionDollarScript\Classes\Functions', 'enqueue_scripts' ] );

		// enqueue js on specific admin pages
		$admin_page = is_admin() && isset( $_REQUEST['page'] );
		if ( $admin_page ) {
			$current_page = $_REQUEST['page'];
			if ( $current_page == 'mds-top-customers' ) {
				add_action( 'admin_enqueue_scripts', [ '\MillionDollarScript\Classes\Functions', 'enqueue_scripts' ] );
			} else if ( $current_page == 'milliondollarscript_options' ) {
				add_action( 'admin_enqueue_scripts', [ '\MillionDollarScript\Classes\Options', 'enqueue_scripts' ] );
			}
		}
		if ( isset( $_REQUEST['post_type'] ) && $_REQUEST['post_type'] == \MillionDollarScript\Classes\FormFields::$post_type ) {
			add_action( 'admin_enqueue_scripts', [ '\MillionDollarScript\Classes\MillionDollarScript', 'scripts' ] );
		}

		add_action( 'admin_enqueue_scripts', [ '\MillionDollarScript\Classes\Admin', 'admin_pixels' ] );

		// shortcode
		add_shortcode( 'milliondollarscript', [ '\MillionDollarScript\Classes\Shortcode', 'shortcode' ] );

		// load WooCommerce integration
		if ( WooCommerceFunctions::is_wc_active() ) {
			// Migrate product if necessary.
			add_action( 'wp_loaded', [ '\MillionDollarScript\Classes\WooCommerceFunctions', 'migrate_product' ] );

			new WooCommerceOptions();

			if ( Options::get_option( 'woocommerce', 'no', false, 'options' ) == 'yes' ) {
				new WooCommerce();
			}
		}

		// user actions
		// add_action( 'user_register', [ '\MillionDollarScript\Classes\Users', 'register' ], 10, 1 );
		// add_action( 'wp_login', [ '\MillionDollarScript\Classes\Users', 'login' ], 10, 2 );
		// add_action( 'wp_logout', [ '\MillionDollarScript\Classes\Users', 'logout' ], 10, 1 );
		// add_action( 'profile_update', [ '\MillionDollarScript\Classes\Users', 'update' ], 10, 2 );
		// add_action( 'delete_user', [ '\MillionDollarScript\Classes\Users', 'delete' ], 10, 3 );

		// WP login page filters
		add_filter( 'login_headerurl', [ '\MillionDollarScript\Classes\Users', 'login_headerurl' ] );
		add_filter( 'login_head', [ '\MillionDollarScript\Classes\Users', 'login_head' ] );
		add_filter( 'login_headertext', [ '\MillionDollarScript\Classes\Users', 'login_headertext' ] );
		add_filter( 'login_redirect', [ '\MillionDollarScript\Classes\Users', 'login_redirect' ], 10, 3 );

		// WP logout redirect filter
		add_filter( 'logout_redirect', [ '\MillionDollarScript\Classes\Users', 'logout_redirect' ], 10, 3 );

		// Add custom routes
		new Routes();

		// Add form handlers
		new Forms();

		// Update checker
		add_action( 'plugins_loaded', [ '\MillionDollarScript\Classes\Update', 'checker' ] );

		// Cron
		add_filter( 'cron_schedules', [ '\MillionDollarScript\Classes\Cron', 'add_minute' ] );
		add_action( 'milliondollarscript_cron_minute', [ '\MillionDollarScript\Classes\Cron', 'minute' ] );
		add_action( 'milliondollarscript_clean_temp_files', [ '\MillionDollarScript\Classes\Cron', 'clean_temp_files' ] );

		// Hook for when loaded
		do_action( 'mds-loaded', $this );

		return $this;
	}
}
