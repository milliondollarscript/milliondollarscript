<?php

/*
  Plugin Name: Million Dollar Script Two
  Plugin URI: https://milliondollarscript.com
  Description: A WordPress plugin with Million Dollar Script Two embedded in it.
  Version: 2.3.5
  Author: Ryan Rhode
  Author URI: https://milliondollarscript.com
  Text Domain: milliondollarscript
  Domain Path: /Languages
  License: GNU/GPL
 */

/**
 * Million Dollar Script Two
 *
 * @version 2.3.5
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

namespace MillionDollarScript;

use MillionDollarScript\Classes\Bootstrap;
use MillionDollarScript\Classes\Database;
use MillionDollarScript\Classes\Options;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

$minimum_version = '7.4.0';
if ( version_compare( PHP_VERSION, $minimum_version, '<' ) ) {
	_e( 'Minimum PHP version requirement not met. Million Dollar Script requires at least PHP ' . $minimum_version, 'milliondollarscript' );
	exit;
}

// MDS defines
defined( 'MDS_BASE_FILE' ) or define( 'MDS_BASE_FILE', wp_normalize_path( __FILE__ ) );
defined( 'MDS_BASE_PATH' ) or define( 'MDS_BASE_PATH', wp_normalize_path( plugin_dir_path( __FILE__ ) ) );
defined( 'MDS_BASE_URL' ) or define( 'MDS_BASE_URL', plugin_dir_url( __FILE__ ) );
defined( 'MDS_VENDOR_PATH' ) or define( 'MDS_VENDOR_PATH', wp_normalize_path( MDS_BASE_PATH . 'vendor/' ) );
defined( 'MDS_VENDOR_URL' ) or define( 'MDS_VENDOR_URL', MDS_BASE_URL . 'vendor/' );
defined( 'MDS_CORE_PATH' ) or define( 'MDS_CORE_PATH', wp_normalize_path( MDS_BASE_PATH . 'src/Core/' ) );
defined( 'MDS_CORE_URL' ) or define( 'MDS_CORE_URL', MDS_BASE_URL . 'src/Core/' );

global $wpdb;
defined( 'MDS_DB_PREFIX' ) or define( 'MDS_DB_PREFIX', $wpdb->prefix . 'mds_' );
defined( 'MDS_DB_VERSION' ) or define( 'MDS_DB_VERSION', 13 );
defined( 'MDS_VERSION' ) or define( 'MDS_VERSION', '2.3.5' );

require_once ABSPATH . 'wp-includes/pluggable.php';

require_once MDS_BASE_PATH . 'vendor/autoload.php';

global $mds_bootstrap;
if ( $mds_bootstrap == null ) {
	$mds_bootstrap = new Bootstrap;
}

/**
 * Installs the MU plugin.
 */
function milliondollarscript_install_mu_plugin( $plugin ) {
	$wpdomain = parse_url( get_site_url() );

	// create mu-plugins folder if it doesn't exist yet
	if ( ! file_exists( WP_CONTENT_DIR . '/mu-plugins' ) ) {
		mkdir( WP_CONTENT_DIR . '/mu-plugins' );
	}

	if ( $plugin == "cookies" ) {
		$data = "<?php
if ( ! defined( 'MILLIONDOLLARSCRIPT_COOKIES' ) ) {
	define( 'MILLIONDOLLARSCRIPT_COOKIES', true );
} else {
	return;
}
if( ! defined( 'COOKIE_DOMAIN' ) ) {
	define( 'COOKIE_DOMAIN', '.{$wpdomain['host']}' );
}
if( ! defined( 'COOKIEPATH' ) ) {
	define( 'COOKIEPATH', '/' );
}
if( ! defined( 'COOKIEHASH' ) ) {
	define( 'COOKIEHASH', md5( '{$wpdomain['host']}' ) );
}
";
		file_put_contents( WP_CONTENT_DIR . '/mu-plugins/milliondollarscript-cookies.php', $data );

		return true;
	} else if ( $plugin == "activation" ) {
		$basename = plugin_basename( __FILE__ );
		$data     = <<<PLUGIN
<?php
if ( ! function_exists( 'untrailingslashit' ) || ! defined( 'WP_PLUGIN_DIR' ) ) {
	exit;
}
if ( in_array( "$basename", (array) get_option( 'active_plugins', array() ), true ) ) {

	// Activates the Million Dollar Script Two plugin if it's installing.
	if ( get_option( 'milliondollarscript-two-installing' ) == true ) {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		activate_plugin( "$basename" );

		// Get rid of the evidence.
		delete_option( 'milliondollarscript-two-installing' );
		unlink( __FILE__ );
	}
}
PLUGIN;
		if ( ! file_exists( WP_CONTENT_DIR . '/mu-plugins/milliondollarscript-activation.php' ) && file_put_contents( WP_CONTENT_DIR . '/mu-plugins/milliondollarscript-activation.php', $data ) !== false ) {
			return true;
		}
	}

	return false;
}

/**
 * Delete the MU plugin
 */
function milliondollarscript_delete_mu_plugin( $plugin ) {
	if ( $plugin == "cookies" && file_exists( WP_CONTENT_DIR . '/mu-plugins/milliondollarscript-cookies.php' ) ) {
		unlink( WP_CONTENT_DIR . '/mu-plugins/milliondollarscript-cookies.php' );
	} else if ( $plugin == "activation" && file_exists( WP_CONTENT_DIR . '/mu-plugins/milliondollarscript-activation.php' ) ) {
		unlink( WP_CONTENT_DIR . '/mu-plugins/milliondollarscript-activation.php' );
	}
}

/**
 * Activation
 */
function milliondollarscript_two_activate() {
	@ini_set( 'memory_limit', '512M' );

	// Deactivates old MDS plugins to prevent conflicts.
	$found_old_plugin = false;
	$plugins          = get_plugins();
	foreach ( $plugins as $file => $plugin ) {
		if ( $plugin['Name'] == "Million Dollar Script Embedded" || $plugin['Name'] == "Million Dollar Script Old" ) {
			deactivate_plugins( $file );
			$found_old_plugin = true;
		}
	}
	unset( $plugins );

	if ( get_option( 'milliondollarscript-two-installed', false ) === false ) {
		add_option( 'milliondollarscript-two-installing', true );
	}

	// Install MU Activation plugin to reactivate this plugin.
	if ( $found_old_plugin && milliondollarscript_install_mu_plugin( 'activation' ) ) {

		// Deactivate this plugin to prevent conflicts with old versions of the plugin.
		deactivate_plugins( plugin_basename( __FILE__ ) );

		// Set as installed here to prevent rampant redirects.
		add_option( 'milliondollarscript-two-installed', true );

		$text = __( "Installing Million Dollar Script Two, this should only take 5-10 seconds or so...", 'milliondollarscript' );
		echo esc_html( $text ) . '
		<script>
		document.title = "' . esc_js( $text ) . '";
		window.onload = function() {
			window.location.reload();
		}
		</script>
		';

		wp_die();
	}

	// Initializes the upload folder at wp-content/uploads/milliondollarscript
	Utility::get_upload_path();

	// Check if database requires upgrades and do them before install deltas happen.
	$mdsdb = new Database();
	if ( $mdsdb->upgrade() ) {
		add_option( 'milliondollarscript_redirect1', wp_get_current_user()->ID );
	}
}

function milliondollarscript_two_activate_step_2() {
	@ini_set( 'memory_limit', '512M' );

	// Install MDS database
	require_once MDS_CORE_PATH . 'admin/install.php';

	install_db();

	// Create default grid image
	@ini_set( 'max_execution_time', 500 );

	require_once MDS_CORE_PATH . 'include/init.php';
	process_image( 1 );
	publish_image( 1 );
	process_map( 1 );

	// Install MU plugin if admin integration is enabled
	if ( Options::get_option( 'admin' ) == 'yes' ) {
		milliondollarscript_install_mu_plugin( 'cookies' );
	}

	// Update user session
	$user_id = get_current_user_id();
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id );
}

if ( is_admin() ) {

	add_action( 'activated_plugin', function ( $filename ) {
		if ( $filename != str_replace( trailingslashit( WP_PLUGIN_DIR ), '', MDS_BASE_FILE ) ) {
			return;
		}
		if ( intval( get_option( 'milliondollarscript_redirect1', false ) ) === wp_get_current_user()->ID ) {
			delete_option( 'milliondollarscript_redirect1' );
			add_option( 'milliondollarscript_redirect2', wp_get_current_user()->ID );
			wp_safe_redirect( admin_url( '/admin.php?page=MillionDollarScript&step=2' ) );
			exit;
		}
	} );

	if ( isset( $_GET['page'] ) && $_GET['page'] == 'MillionDollarScript' && intval( get_option( 'milliondollarscript_redirect2', false ) ) === wp_get_current_user()->ID ) {
		delete_option( 'milliondollarscript_redirect2' );
		add_option( 'milliondollarscript_redirect3', wp_get_current_user()->ID );
		milliondollarscript_two_activate_step_2();
		wp_safe_redirect( admin_url( '/admin.php?page=MillionDollarScript&step=3' ) );
		exit;
	}
}

/**
 * Deactivation
 */
function milliondollarscript_two_deactivate() {
}

/**
 * Uninstallation
 */
function milliondollarscript_two_uninstall() {
	if ( Options::get_option( 'delete-data' ) == 'yes' ) {
		global $wpdb;

		$tables = Database::get_mds_tables();

		foreach ( $tables as $table ) {
			$wpdb->query(
				"DROP TABLE IF EXISTS " . MDS_DB_PREFIX . $table,
			);
		}

		$wpdb->query(
			"DELETE FROM " . $wpdb->prefix . "options WHERE `option_name` LIKE '%milliondollarscript%'",
		);

		Utility::delete_uploads_folder();

		milliondollarscript_delete_mu_plugin( 'activation' );
		milliondollarscript_delete_mu_plugin( 'cookies' );

		delete_option( 'milliondollarscript-two-installed' );
	}
}

function milliondollarscript_two_update() {
	// Check if database requires upgrades and do them before install deltas happen.
	$mdsdb = new Database();
	$mdsdb->upgrade();
}

// WP plugin activation actions
register_activation_hook( __FILE__, '\MillionDollarScript\milliondollarscript_two_activate' );
register_deactivation_hook( __FILE__, '\MillionDollarScript\milliondollarscript_two_deactivate' );
register_uninstall_hook( __FILE__, '\MillionDollarScript\milliondollarscript_two_uninstall' );

// Update plugin
add_action( 'plugins_loaded', '\MillionDollarScript\milliondollarscript_two_update' );

// Cron
register_activation_hook( __FILE__, [ '\MillionDollarScript\Classes\Cron', 'schedule_cron' ] );
register_deactivation_hook( __FILE__,  [ '\MillionDollarScript\Classes\Cron', 'clear_cron' ] );
