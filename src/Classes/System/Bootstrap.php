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

namespace MillionDollarScript\Classes\System;

use MillionDollarScript\Classes\Admin\Admin;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Forms\Forms;
use MillionDollarScript\Classes\Web\Routes;
use MillionDollarScript\Classes\WooCommerce\WooCommerce;
use MillionDollarScript\Classes\WooCommerce\WooCommerceFunctions;
use MillionDollarScript\Classes\WooCommerce\WooCommerceOptions;
use MillionDollarScript\Classes\Pages\ApprovePixels;
use MillionDollarScript\Classes\Pages\NFS;

defined( 'ABSPATH' ) or exit;

class Bootstrap {

	private Bootstrap $instance;

	function __construct() {
		if ( isset( $this->instance ) ) {
			// phpcs:disable
			return $this->instance;
			// phpcs:enable
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
		add_action( 'plugins_loaded', [ '\MillionDollarScript\Classes\Language\Language', 'load_textdomain' ] );

		// Initialize theme hooks for dark mode support
		add_action( 'init', [ '\MillionDollarScript\Classes\Web\Styles', 'init_theme_hooks' ] );

		// Register dashboard menu items
		add_action( 'mds_register_dashboard_menu', [ '\MillionDollarScript\Classes\Admin\Core_Dashboard_Menu', 'register' ] );

		// Register admin pages
		add_action( 'admin_menu', [ '\MillionDollarScript\Classes\Pages\MillionDollarScript', 'menu' ], 9 );
		add_action( 'admin_menu', [ '\MillionDollarScript\Classes\Pages\Logs', 'menu' ], 11 );
		add_action( 'admin_menu', [ '\MillionDollarScript\Classes\Pages\Wizard', 'menu' ], 12 );
		add_action( 'admin_menu', [ '\MillionDollarScript\Classes\Pages\Extensions', 'menu' ], 13 );
		add_action( 'admin_menu', [ '\MillionDollarScript\Classes\Pages\Account', 'menu' ], 14 );

		// Add server test page (only in development or when WP_DEBUG is enabled)
		if (defined('WP_DEBUG') && WP_DEBUG) {
			add_action( 'admin_menu', [ '\MillionDollarScript\Classes\System\ExtensionServerTest', 'add_admin_menu' ], 14 );
		}
		
		// Register setup wizard redirect
		add_action( 'admin_init', [ '\MillionDollarScript\Classes\Pages\Wizard', 'maybe_redirect_to_wizard' ] );
		
		// Handle background image delete action before any output
		add_action( 'admin_init', function() {
			if ( isset( $_GET['page'] ) && $_GET['page'] === 'mds-backgrounds' &&
				 isset( $_GET['mds-action'] ) && $_GET['mds-action'] === 'delete' &&
				 isset( $_GET['BID'] ) ) {
				
				$BID = intval( $_GET['BID'] );
				
				// Verify nonce
				if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'mds_delete_background_' . $BID ) ) {
					wp_die( 'Security check failed.' );
				}

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( 'Sorry, you are not allowed to perform this action.' );
				}

				$params = ['page' => 'mds-backgrounds', 'BID' => $BID];

				if ( $BID > 0 ) {
					// Use the correct upload path from Utility class
					$upload_path = \MillionDollarScript\Classes\System\Utility::get_upload_path();
					if ( $upload_path ) {
						$upload_path = $upload_path . 'grids/';
						$files_to_delete = glob( $upload_path . "background{$BID}.*" );
						$deleted = false;
						$found = false;

						if ( $files_to_delete ) {
							$found = true;
							foreach ( $files_to_delete as $file_to_delete ) {
								if ( is_writable( $file_to_delete ) && unlink( $file_to_delete ) ) {
									$deleted = true;
								} else {
									$params['delete_error'] = 'failed_unlink';
									break;
								}
							}
						}

						// Also check legacy paths for backward compatibility
						if ( ! $found ) {
							// Check old mds path
							$legacy_path1 = WP_CONTENT_DIR . '/uploads/mds/grids/';
							if ( is_dir( $legacy_path1 ) ) {
								$legacy_files = glob( $legacy_path1 . "background{$BID}.*" );
								if ( $legacy_files ) {
									$found = true;
									foreach ( $legacy_files as $file_to_delete ) {
										if ( is_writable( $file_to_delete ) && unlink( $file_to_delete ) ) {
											$deleted = true;
										} else {
											$params['delete_error'] = 'failed_unlink';
											break;
										}
									}
								}
							}
						}

						if ( ! $found ) {
							// Check direct wp_upload_dir path
							$upload_dir = wp_upload_dir();
							$legacy_path2 = $upload_dir['basedir'] . '/mds/grids/';
							if ( is_dir( $legacy_path2 ) ) {
								$legacy_files = glob( $legacy_path2 . "background{$BID}.*" );
								if ( $legacy_files ) {
									$found = true;
									foreach ( $legacy_files as $file_to_delete ) {
										if ( is_writable( $file_to_delete ) && unlink( $file_to_delete ) ) {
											$deleted = true;
										} else {
											$params['delete_error'] = 'failed_unlink';
											break;
										}
									}
								}
							}
						}
					} else {
						$params['delete_error'] = 'upload_path_not_found';
					}

					if ( $found && $deleted && ! isset( $params['delete_error'] ) ) {
						$params['delete_success'] = 'true';
						delete_option( 'mds_background_opacity_' . $BID );
					} else if ( ! $found ) {
						$params['delete_error'] = 'not_found';
					}
				} else {
					$params['delete_error'] = 'invalid_bid';
				}

				// Redirect back
				$redirect_url = remove_query_arg( array( 'mds-action', '_wpnonce' ), admin_url( 'admin.php' ) );
				wp_safe_redirect( add_query_arg( $params, $redirect_url ) );
				exit;
			}
		} );
		
		// Register AJAX handlers for the wizard
		add_action( 'wp_ajax_mds_wizard_create_pages', [ '\MillionDollarScript\Classes\Pages\Wizard', 'ajax_create_pages' ] );
		add_action( 'wp_ajax_mds_wizard_save_settings', [ '\MillionDollarScript\Classes\Pages\Wizard', 'ajax_save_settings' ] );
		add_action( 'wp_ajax_mds_wizard_complete', [ '\MillionDollarScript\Classes\Pages\Wizard', 'ajax_mark_complete' ] );
		
		// Register AJAX handlers for extensions
		add_action( 'wp_ajax_mds_extensions_refresh', [ '\MillionDollarScript\Classes\Pages\Extensions', 'ajax_refresh_extensions' ] );
		add_action( 'wp_ajax_mds_extensions_install', [ '\MillionDollarScript\Classes\Pages\Extensions', 'ajax_install_extension' ] );
		add_action( 'wp_ajax_mds_extensions_activate', [ '\MillionDollarScript\Classes\Pages\Extensions', 'ajax_activate_extension' ] );
		add_action( 'wp_ajax_mds_extensions_deactivate', [ '\MillionDollarScript\Classes\Pages\Extensions', 'ajax_deactivate_extension' ] );
		add_action( 'wp_ajax_mds_extensions_check_updates', [ '\MillionDollarScript\Classes\Pages\Extensions', 'ajax_check_updates' ] );
		add_action( 'wp_ajax_mds_extensions_update', [ '\MillionDollarScript\Classes\Pages\Extensions', 'ajax_update_extension' ] );

		// Add user fields
		add_action( 'carbon_fields_register_fields', [ '\MillionDollarScript\Classes\User\Users', 'custom_fields' ] );
		add_filter( 'manage_users_columns', [ '\MillionDollarScript\Classes\User\Users', 'add_custom_user_columns' ] );
		add_filter( 'manage_users_custom_column', [
			'\MillionDollarScript\Classes\User\Users',
			'show_custom_user_columns_content'
		], 10, 3 );
		add_filter( 'manage_users_sortable_columns', [
			'\MillionDollarScript\Classes\User\Users',
			'make_custom_user_columns_sortable'
		] );
		add_action( 'pre_get_users', [ '\MillionDollarScript\Classes\User\Users', 'sort_users_by_custom_column' ] );

		// Add Options page
		add_action( 'carbon_fields_register_fields', [ '\MillionDollarScript\Classes\Data\Options', 'register' ] );
		add_action( 'after_setup_theme', [ '\MillionDollarScript\Classes\Data\Options', 'load' ] );
		if ( $page == MDS_PREFIX . 'options' ) {
			add_filter( 'carbon_fields_before_field_save', [ '\MillionDollarScript\Classes\Data\Options', 'save' ] );
		}

		// Initialize metadata system and WordPress integration
		add_action( 'init', [ '\MillionDollarScript\Classes\Data\MDSPageWordPressIntegration', 'initializeStatic' ] );

		// Initialize Grid Image Switcher for theme mode automation
		add_action( 'init', [ '\\MillionDollarScript\\Classes\\Services\\GridImageSwitcher', 'init' ] );

		// Initialize MDS Pixels Permalinks and old-base redirects
		add_action( 'init', [ '\\MillionDollarScript\\Classes\\Web\\Permalinks', 'init' ] );

		// Add Block
		add_action( 'wp_enqueue_scripts', [ '\MillionDollarScript\Classes\Blocks\Block', 'register_style' ] );
		add_action( 'carbon_fields_register_fields', [ '\MillionDollarScript\Classes\Blocks\Block', 'load' ] );
		
		// Initialize block fallback system to ensure Carbon Fields blocks work correctly
		add_action( 'init', [ '\MillionDollarScript\Classes\Admin\MDSEnhancedPageCreator', 'initBlockFallback' ] );

		// Add Emails page
		add_action( 'carbon_fields_register_fields', [ '\MillionDollarScript\Classes\Email\Emails', 'register' ] );
		add_action( 'after_setup_theme', [ '\MillionDollarScript\Classes\Email\Emails', 'load' ] );
		if ( $page == MDS_PREFIX . 'emails' ) {
			add_filter( 'carbon_fields_before_field_save', [ '\MillionDollarScript\Classes\Email\Emails', 'save' ] );
		}
		add_filter( 'crb_media_buttons_html', function ( $html, $field_name ) {
			if ( $field_name === 'rich_text_field_name_here' ) {
				return "";
			}

			return $html;
		}, 10, 2 );

		// Add Changelog page
		add_action( 'carbon_fields_register_fields', [ '\MillionDollarScript\Classes\Pages\Changelog', 'register' ] );

		// Add mds-pixel post type fields
		add_action( 'carbon_fields_register_fields', [ '\MillionDollarScript\Classes\Forms\FormFields', 'register' ] );
		add_action( 'carbon_fields_post_meta_container_saved', [ '\MillionDollarScript\Classes\Forms\FormFields', 'save' ] );
		add_action( 'init', [ '\MillionDollarScript\Classes\Forms\FormFields', 'register_post_type' ] );
		add_action( 'init', [ '\MillionDollarScript\Classes\Forms\FormFields', 'register_custom_post_status' ] );
		add_action( 'admin_footer-post.php', [ '\MillionDollarScript\Classes\Forms\FormFields', 'add_custom_post_status' ] );
		add_action( 'admin_footer-post-new.php', [
			'\MillionDollarScript\Classes\Forms\FormFields',
			'add_custom_post_status'
		] );
		add_action( 'admin_footer-edit.php', [ '\MillionDollarScript\Classes\Forms\FormFields', 'add_quick_edit_status' ] );

		// Add custom columns
		add_filter( 'manage_mds-pixel_posts_columns', [
			'\MillionDollarScript\Classes\Forms\FormFields',
			'add_custom_columns'
		] );
		add_action( 'manage_mds-pixel_posts_custom_column', [
			'\MillionDollarScript\Classes\Forms\FormFields',
			'fill_custom_columns'
		] );
		add_filter( 'manage_edit-mds-pixel_sortable_columns', [
			'\MillionDollarScript\Classes\Forms\FormFields',
			'sortable_columns'
		] );
		add_action( 'pre_get_posts', [ '\MillionDollarScript\Classes\Forms\FormFields', 'orderby_grid' ] );
		add_action( 'pre_get_posts', [ '\MillionDollarScript\Classes\Forms\FormFields', 'orderby_expiry' ] );
		add_action( 'pre_get_posts', [ '\MillionDollarScript\Classes\Forms\FormFields', 'orderby_approved' ] );
		add_action( 'pre_get_posts', [ '\MillionDollarScript\Classes\Forms\FormFields', 'orderby_published' ] );
		add_action( 'pre_get_posts', [ '\MillionDollarScript\Classes\Forms\FormFields', 'orderby_status' ] );

		add_action( 'pre_get_posts', [ '\MillionDollarScript\Classes\Forms\FormFields', 'modify_search_query' ] );
		add_filter( 'posts_search', [ '\MillionDollarScript\Classes\Forms\FormFields', 'posts_search' ], 10, 2 );

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
		// 	register_widget( '\MillionDollarScript\Classes\Web\Widget' );
		// } );

		// Register AJAX handlers for Logs
		add_action( 'init', [ '\MillionDollarScript\Classes\Pages\Logs', 'register_ajax_handlers' ], 10 );

		// Add Admin page
		add_action( 'admin_menu', [ '\MillionDollarScript\Classes\Admin\Admin', 'menu' ], 10 );

		// Initialize WooCommerce integrations
		add_action( 'plugins_loaded', [ '\MillionDollarScript\Classes\WooCommerce\Refunds', 'init' ] );

		// Load Admin AJAX
		add_action( 'wp_ajax_mds_admin_ajax', [ Admin::class, 'ajax' ] );

		add_action( 'wp_ajax_mds_update_language', [ '\\MillionDollarScript\\Classes\\Admin\\Admin', 'update_language' ] );

		// Register slug migration AJAX explicitly so it works on Options page
		add_action( 'wp_ajax_mds_migrate_slugs', [ '\\MillionDollarScript\\Classes\\Data\\Options', 'ajax_migrate_slugs' ] );

		// AJAX handlers for Click Reports View Type preference
		add_action( 'wp_ajax_mds_load_click_report_view_type', [ '\MillionDollarScript\Classes\Admin\Admin', 'load_click_report_view_type' ] );
		add_action( 'wp_ajax_mds_save_click_report_view_type', [ '\MillionDollarScript\Classes\Admin\Admin', 'save_click_report_view_type' ] );

		// Disable admin email (admin-only)
		add_action( 'wp_ajax_mds_disable_admin_email', [ 'MillionDollarScript\Classes\Email\Mail', 'handle_disable_admin_email' ] );

		// Load Block Editor JS
		add_action( 'enqueue_block_editor_assets', [ '\MillionDollarScript\Classes\Admin\Admin', 'block_editor_scripts' ] );

		// Add Approve Pixels page
		add_action( 'init', [ $this, 'init_admin_pages' ] );

		// Initialize MDS Page Metadata System
		add_action( 'init', [ $this, 'init_metadata_system' ], 5 );

		// Initialize MDS Backward Compatibility System
		add_action( 'init', [ $this, 'init_backward_compatibility_system' ], 6 );

		// Initialize MDS Page Management Interface
		add_action( 'init', [ $this, 'init_page_management_interface' ], 7 );

		// Add Extensions page
		add_action( 'init', [ '\MillionDollarScript\Classes\Pages\Extensions', 'init' ], 10 );

		// Load Frontend AJAX
		add_action( 'plugins_loaded', [ '\MillionDollarScript\Classes\Ajax\Ajax', 'init' ], 10 );

		// enqueue js
		add_action( 'wp_enqueue_scripts', [ '\MillionDollarScript\Classes\System\Functions', 'register_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ '\MillionDollarScript\Classes\System\Functions', 'enqueue_scripts' ] );
		add_action( 'login_enqueue_scripts', [ '\MillionDollarScript\Classes\System\Functions', 'enqueue_scripts' ] );

		// Enqueue dynamic CSS for frontend and login pages (late to win tie-specificity)
		add_action( 'wp_enqueue_scripts', [ '\\MillionDollarScript\\Classes\\Web\\Styles', 'enqueue_dynamic_styles' ], 100 );
		add_action( 'login_enqueue_scripts', [ '\\MillionDollarScript\\Classes\\Web\\Styles', 'enqueue_dynamic_styles' ], 100 );

		// enqueue js on specific admin pages
		$admin_page = is_admin() && isset( $_REQUEST['page'] );
		if ( $admin_page ) {
			$current_page = $_REQUEST['page'];
			
			// Load admin.js on all MDS admin pages for confirmLink functionality
			if ( str_starts_with( $current_page, 'mds-' ) ) {
				add_action( 'admin_enqueue_scripts', [ '\MillionDollarScript\Classes\Admin\Admin', 'enqueue_admin_scripts' ] );
			}
			
			if ( $current_page == 'mds-top-customers' ) {
				add_action( 'admin_enqueue_scripts', [ '\MillionDollarScript\Classes\System\Functions', 'enqueue_scripts' ] );
			} else if ( $current_page == 'milliondollarscript_options' ) {
				add_action( 'admin_enqueue_scripts', [ '\MillionDollarScript\Classes\Data\Options', 'enqueue_scripts' ] );
			}
		}
		if ( isset( $_REQUEST['post_type'] ) && $_REQUEST['post_type'] == FormFields::$post_type ) {
			add_action( 'admin_enqueue_scripts', [ '\MillionDollarScript\Classes\Pages\MillionDollarScript', 'scripts' ] );
		}

		add_action( 'admin_enqueue_scripts', [ '\MillionDollarScript\Classes\Admin\Admin', 'admin_pixels' ] );

		// shortcode
		add_shortcode( 'milliondollarscript', [ '\MillionDollarScript\Classes\Web\Shortcode', 'shortcode' ] );
		add_action( 'mds_shortcode', [ '\MillionDollarScript\Classes\Web\Shortcode', 'extension' ], 10, 3 );

		// load WooCommerce integration
		if ( WooCommerceFunctions::is_wc_active() ) {
			// Migrate product if necessary.
			add_action( 'wp_loaded', [ '\MillionDollarScript\Classes\WooCommerce\WooCommerceFunctions', 'migrate_product' ] );

			new WooCommerceOptions();

			// Check if WooCommerce integration is enabled
			if ( Options::get_option( 'woocommerce', 'no', false, 'options' ) ) {
				new WooCommerce();
			}
		}

		// user actions
		// add_action( 'user_register', [ '\MillionDollarScript\Classes\User\Users', 'register' ], 10, 1 );
		// add_action( 'wp_login', [ '\MillionDollarScript\Classes\User\Users', 'login' ], 10, 2 );
		// add_action( 'wp_logout', [ '\MillionDollarScript\Classes\User\Users', 'logout' ], 10, 1 );
		// add_action( 'profile_update', [ '\MillionDollarScript\Classes\User\Users', 'update' ], 10, 2 );
		// add_action( 'delete_user', [ '\MillionDollarScript\Classes\User\Users', 'delete' ], 10, 3 );

		// WP login page filters
		add_filter( 'login_headerurl', [ '\MillionDollarScript\Classes\User\Users', 'login_headerurl' ] );
		add_filter( 'login_head', [ '\MillionDollarScript\Classes\User\Users', 'login_head' ] );
		add_filter( 'login_headertext', [ '\MillionDollarScript\Classes\User\Users', 'login_headertext' ] );
		add_filter( 'login_redirect', [ '\MillionDollarScript\Classes\User\Users', 'login_redirect' ], 10, 3 );

		// WP logout redirect filter
		add_filter( 'logout_redirect', [ '\MillionDollarScript\Classes\User\Users', 'logout_redirect' ], 10, 3 );

		// Add custom routes
		new Routes();

		// Add form handlers
		new Forms();

		// Register admin-post handlers early to ensure actions run before any theme output
		add_action( 'init', [ 'MillionDollarScript\Classes\Admin\AdminPostHandlers', 'register' ], 1 );

		// Update checker
		add_action( 'plugins_loaded', [ '\MillionDollarScript\Classes\System\Update', 'checker' ] );

		// Cron
		add_filter( 'cron_schedules', [ '\MillionDollarScript\Classes\System\Cron', 'add_minute' ] );
		add_action( 'milliondollarscript_cron_minute', [ '\MillionDollarScript\Classes\System\Cron', 'minute' ] );
		add_action( 'milliondollarscript_clean_temp_files', [ '\MillionDollarScript\Classes\System\Cron', 'clean_temp_files' ] );
		add_action( 'milliondollarscript_clean_old_logs', [ '\MillionDollarScript\Classes\System\Cron', 'clean_old_log_files' ] );
		
		// Schedule daily renewal reminder emails
		if ( ! wp_next_scheduled( 'mds_renewal_reminder_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'mds_renewal_reminder_cron' );
		}
		add_action( 'mds_renewal_reminder_cron', [ '\MillionDollarScript\Classes\Email\Emails', 'send_renewal_reminders' ] );

		// Hook for when loaded
		do_action( 'mds-loaded', $this );

		// phpcs:disable
		return $this;
		// phpcs:enable
	}

	public function init_admin_pages(): void {
		NFS::init();
		new ApprovePixels();
	}

	/**
	 * Initialize the MDS Page Metadata System
	 *
	 * @return void
	 */
	public function init_metadata_system(): void {
		try {
			$metadata_manager = \MillionDollarScript\Classes\Data\MDSPageMetadataManager::getInstance();
			$result = $metadata_manager->initialize();
			
			if ( is_wp_error( $result ) ) {
				Logs::log( 'MDS Metadata System initialization failed: ' . $result->get_error_message() );
			}
			
			// Initialize WordPress integration (includes post state hooks)
			$wp_integration = \MillionDollarScript\Classes\Data\MDSPageWordPressIntegration::getInstance();
			$wp_integration->initialize();
		} catch ( \Exception $e ) {
			Logs::log( 'MDS Metadata System initialization exception: ' . $e->getMessage() );
		}
	}

	/**
	 * Initialize the MDS Backward Compatibility System
	 *
	 * @return void
	 */
	public function init_backward_compatibility_system(): void {
		try {
			// Initialize backward compatibility manager
			$compatibility_manager = \MillionDollarScript\Classes\Data\MDSBackwardCompatibilityManager::getInstance();
			
			// Initialize legacy shortcode handler
			$legacy_handler = \MillionDollarScript\Classes\Data\MDSLegacyShortcodeHandler::getInstance();
		} catch ( \Exception $e ) {
			Logs::log( 'MDS Backward Compatibility System initialization exception: ' . $e->getMessage() );
		}
	}

	/**
	 * Initialize the MDS Page Management Interface
	 *
	 * @return void
	 */
	public function init_page_management_interface(): void {
		try {
			// Only initialize in admin
			if ( ! is_admin() ) {
				return;
			}
			
			// Initialize page management interface
			new \MillionDollarScript\Classes\Admin\MDSPageManagementInterface();
			
			// Initialize page creator interface
			new \MillionDollarScript\Classes\Admin\MDSPageCreatorInterface();
		} catch ( \Exception $e ) {
			Logs::log( 'MDS Page Management Interface initialization exception: ' . $e->getMessage() );
		}
	}
}
