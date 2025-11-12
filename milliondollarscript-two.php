<?php

/*
  Plugin Name: Million Dollar Script Two
  Plugin URI: https://milliondollarscript.com
  Description: A WordPress plugin with Million Dollar Script Two embedded in it.
  Version: 2.6.8
  Author: Ryan Rhode
  Author URI: https://milliondollarscript.com
  Text Domain: milliondollarscript
  Domain Path: /languages
  License: GNU/GPL
 */

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

namespace MillionDollarScript;

use MillionDollarScript\Classes\Data\Database;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\System\Bootstrap;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\Web\Styles;

defined( 'ABSPATH' ) or exit;

global $wpdb;

// MDS defines
defined( 'MDS_BASE_FILE' ) or define( 'MDS_BASE_FILE', wp_normalize_path( __FILE__ ) );
defined( 'MDS_BASE_PATH' ) or define( 'MDS_BASE_PATH', wp_normalize_path( plugin_dir_path( __FILE__ ) ) );
defined( 'MDS_BASE_URL' ) or define( 'MDS_BASE_URL', plugin_dir_url( __FILE__ ) );
defined( 'MDS_LANG_DIR' ) or define( 'MDS_LANG_DIR', plugin_basename( __DIR__ ) . '/languages' );
defined( 'MDS_VENDOR_PATH' ) or define( 'MDS_VENDOR_PATH', wp_normalize_path( MDS_BASE_PATH . 'vendor/' ) );
defined( 'MDS_VENDOR_URL' ) or define( 'MDS_VENDOR_URL', MDS_BASE_URL . 'vendor/' );
defined( 'MDS_CORE_PATH' ) or define( 'MDS_CORE_PATH', wp_normalize_path( MDS_BASE_PATH . 'src/Core/' ) );
defined( 'MDS_CORE_URL' ) or define( 'MDS_CORE_URL', MDS_BASE_URL . 'src/Core/' );
defined( 'MDS_TEXT_DOMAIN' ) or define( 'MDS_TEXT_DOMAIN', 'milliondollarscript' );
defined( 'MDS_PREFIX' ) or define( 'MDS_PREFIX', 'milliondollarscript_' );
defined( 'MDS_DB_PREFIX' ) or define( 'MDS_DB_PREFIX', $wpdb->prefix . 'mds_' );
defined( 'MDS_DB_VERSION' ) or define( 'MDS_DB_VERSION', '2.6.5' );
defined( 'MDS_VERSION' ) or define( 'MDS_VERSION', '2.6.8' );

// Detect PHP version
$minimum_version = '8.1.0';
if ( version_compare( PHP_VERSION, $minimum_version, '<' ) ) {
	$error_message = wp_sprintf( __( 'Minimum PHP version requirement not met. Million Dollar Script requires at least PHP %s' ), $minimum_version );
	esc_html_e( $error_message );
	exit;
}

// WP plugin activation actions.
register_activation_hook( __FILE__, '\MillionDollarScript\milliondollarscript_two_activate' );
register_deactivation_hook( __FILE__, '\MillionDollarScript\milliondollarscript_two_deactivate' );
register_uninstall_hook( __FILE__, '\MillionDollarScript\milliondollarscript_two_uninstall' );

// Add activation hook for the wizard redirect transient
register_activation_hook( __FILE__, ['MillionDollarScript\Classes\Pages\Wizard', 'set_redirect_transient'] );

/**
 * Performs upgrade operations.
 *
 * @param bool $load_post
 *
 * @return void
 *
 * @throws \Exception if there is an error during the upgrade process.
 */
function perform_upgrade_operations( bool $load_post = true ): void {
	if ( ! isset( $mds_bootstrap ) ) {
		$mds_bootstrap = new Classes\System\Bootstrap();
	}

	// Initialize database if needed
	$mdsdb = new Classes\Data\Database();

	if ( ! $mdsdb->mds_sql_installed() ) {
		if ( ! $load_post ) {
			return;
		}
	} else {
		try {
			$result = $mdsdb->upgrade();
		} catch ( \Exception $e ) {
			if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
				$message = $e->getMessage();
				wp_die( esc_html( $message ) );
			} else {
				throw $e;
			}
		}
	}

	// Reset cron.
	Classes\System\Cron::clear_cron();
	Classes\System\Cron::schedule_cron();

	// Flush permalinks.
	if ( $load_post ) {

		// Register mds-pixel post type.
		Classes\Forms\FormFields::register_post_type();

		flush_rewrite_rules();
	}
}

/**
 * Upgrades the MillionDollarScript database.
 *
 * @param \WP_Upgrader $upgrader
 * @param array $hook_extra Extra arguments passed to hooked filters.
 *
 * @throws \Exception if there is an error during the upgrade process.
 */
function milliondollarscript_two_upgrade( \WP_Upgrader $upgrader, array $hook_extra ): void {
	if ( $hook_extra['action'] == 'update' && $hook_extra['type'] == 'plugin' && isset( $hook_extra['plugins'] ) ) {
		if ( in_array( plugin_basename( __FILE__ ), $hook_extra['plugins'] ) ) {
			perform_upgrade_operations();
		}
	}
}

add_action( 'upgrader_process_complete', '\MillionDollarScript\milliondollarscript_two_upgrade', 10, 2 );

/**
 * Activation
 * @throws \Exception
 */
function milliondollarscript_two_activate(): void {
	@ini_set( 'memory_limit', '512M' );
	@ini_set( 'max_execution_time', 500 );

	// Set a flag to indicate activation is in progress.
	update_option( MDS_PREFIX . 'activation', '1' );

	// Install MDS database first to ensure tables exist
	require_once MDS_CORE_PATH . 'admin/install.php';
	install_db();

	// Run upgrade operations after tables are created
	// This ensures the config table exists before we try to read/write the dbver
	perform_upgrade_operations();

	// Load MDS core
	require_once MDS_CORE_PATH . 'include/init.php';

	// Initializes the upload folder at wp-content/uploads/milliondollarscript
	Utility::get_upload_path();

	// Generate dynamic CSS file
	Styles::save_dynamic_css_file();

	// Create default grid image
	process_image( 1 );
	publish_image( 1 );
	process_map( 1 );
}

/**
 * Deactivation
 */
function milliondollarscript_two_deactivate(): void {
	// Flush permalinks.
	flush_rewrite_rules();
}

/**
 * Uninstallation
 */
function milliondollarscript_two_uninstall(): void {
	if ( Options::get_option( 'delete-data' ) == 'yes' ) {
		global $wpdb;

		Classes\System\Utility::clear_orders();

		$tables = Database::get_mds_tables();
		$tables = array_merge( $tables, Database::get_old_mds_tables() );

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS " . MDS_DB_PREFIX . $table );
		}

		// Drop metadata system tables as well
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mds_page_metadata" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mds_page_config" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mds_detection_log" );

		// Explicitly remove wizard flags and transients
		delete_option( \MillionDollarScript\Classes\Pages\Wizard::OPTION_NAME_WIZARD_COMPLETE );
		delete_option( \MillionDollarScript\Classes\Pages\Wizard::OPTION_NAME_PAGES_CREATED );
		delete_transient( 'mds_wizard_redirect' );

		// Remove most plugin options and transients
		$prefix_like = esc_sql( MDS_PREFIX );
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '%{$prefix_like}%' 
			OR option_name = 'mds_last_order_modification_time' 
			OR option_name = 'mds_migrate_product_executed'
			OR option_name = 'mds_db_version'
			OR option_name = 'mds_use_woocommerce_integration'
			OR option_name = 'mds_migration_results'
			OR option_name = 'mds_migration_completed'
			OR option_name = 'mds_compatibility_version'
			OR option_name = 'mds_page_management_version'
			OR option_name = 'mds_pending_migrations'
			OR option_name = 'mds_db_initialized'
			OR option_name LIKE '_transient_mds_%'
			OR option_name LIKE '_transient_timeout_mds_%'
			OR option_name LIKE '_site_transient_mds_%'
			OR option_name LIKE '_site_transient_timeout_mds_%'
			OR option_name LIKE 'mds_background_opacity%'
			"
		);

		// TODO: delete privileged user meta, any other user meta

		Utility::delete_uploads_folder();
	}
}

// Load WP pluggable functions.
require_once ABSPATH . 'wp-includes/pluggable.php';

// Please wait, loading...
require_once MDS_BASE_PATH . 'vendor/autoload.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command(
		'mds extension update-snapshot',
		\MillionDollarScript\Classes\Cli\ExtensionUpdateParityCommand::class
	);
}

// Starting up...
global $mds_bootstrap;
if ( $mds_bootstrap == null ) {
	$mds_bootstrap = new Bootstrap;
}

// Registering scheduled events...
register_activation_hook( __FILE__, [ '\MillionDollarScript\Classes\System\Cron', 'schedule_cron' ] );
register_deactivation_hook( __FILE__, [ '\MillionDollarScript\Classes\System\Cron', 'clear_cron' ] );

// Cron jobs
add_action( 'mds_daily_cron_hook', [ 'MillionDollarScript\Classes\System\Cron', 'daily_tasks' ] );
register_activation_hook( __FILE__, [ '\MillionDollarScript\Classes\System\Cron', 'schedule_cron' ] );
register_deactivation_hook( __FILE__, [ '\MillionDollarScript\Classes\System\Cron', 'clear_cron' ] );

// Done!
