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

namespace MillionDollarScript\Classes\Admin;

use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Language\LanguageScanner;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\Data\Options;

defined( 'ABSPATH' ) or exit;

class Admin {

	/**
	 * Updates the language file.
	 *
	 * @throws \Exception
	 */
	public static function update_language(): void {
		check_ajax_referer( 'mds_admin_nonce', 'nonce' );

		$plugin_folder = plugin_dir_path( MDS_BASE_FILE );
		$scanner       = new LanguageScanner( $plugin_folder );
		$scanner->scan_files();

		wp_send_json( true );
		wp_die();
	}


	public static function block_editor_scripts(): void {
		wp_register_script(
			MDS_PREFIX . 'admin-block-js',
			MDS_BASE_URL . 'src/Assets/js/admin-block.min.js',
			[
				'jquery',
				'jquery-ui-core',
				'jquery-ui-dialog',
				'jquery-ui-button',
				'jquery-form',
				'carbon-fields-vendor'
			],
			filemtime( MDS_BASE_PATH . 'src/Assets/js/admin-block.min.js' ),
			true
		);

		// Get page-specific configuration if editing an MDS page
		$page_config = self::getCurrentPageConfiguration();

		wp_localize_script( MDS_PREFIX . 'admin-block-js', 'MDS', [
			'admin'                => admin_url( 'admin.php' ),
			'mds_site_url'         => get_site_url(),
			'ajaxurl'              => admin_url( 'admin-ajax.php' ),
			'MDS_PREFIX'           => MDS_PREFIX,
			'mds_admin_ajax_nonce' => wp_create_nonce( 'mds_admin_ajax_nonce' ),
			'page_config'          => $page_config,
		] );

		wp_enqueue_script( MDS_PREFIX . 'admin-block-js' );
	}

	public static function ajax(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( Language::get( 'Sorry, you are not allowed to access this page.' ) );
		}

		check_ajax_referer( 'mds_admin_ajax_nonce', 'mds_admin_ajax_nonce' );

		// Handle requests from admin-block.js
		if ( isset( $_POST['payload'] ) ) {
			global $wpdb;

			$response = [];
			$payload  = $_POST['payload'];
			$BID      = intval( $payload['id'] );

			switch ( $payload['type'] ) {
				case "grid":
					// Get grid data
					$sql  = $wpdb->prepare( "SELECT * FROM " . MDS_DB_PREFIX . "banners WHERE banner_id=%d", $BID );
					$grid = $wpdb->get_row( $sql, ARRAY_A );

					if ( ! $grid ) {
						wp_send_json_error( Language::get( 'Grid not found.' ) );
					}

					$width  = $grid['grid_width'] * $grid['block_width'];
					$height = $grid['grid_height'] * $grid['block_height'];

					// Send width and height for grid
					wp_send_json_success( [
						'width'  => $width . 'px',
						'height' => $height . 'px',
					] );
					break;
				case "stats":
					// Send width and height for a stats block
					wp_send_json_success( [
						'width'  => '150px',
						'height' => '60px',
					] );
					break;
				default:
					// Send width and height for users, list and anything else that happens to exist
					wp_send_json_success( [
						'width'  => '100%',
						'height' => 'auto',
					] );
					break;
			}

			wp_send_json_error( Language::get( 'Error sending data.' ) );
		}

		$requests = [
			'get-block-image',
			'get-order-image',
			'show-price-zone',
			'preview-blend',
			'show-map',
		];

		foreach ( $requests as $request ) {
			if ( isset( $_REQUEST['mds-ajax'] ) && $_REQUEST['mds-ajax'] == $request ) {
				require MDS_CORE_PATH . "admin/" . $request . ".php";
			}
		}

		wp_die();
	}

	public static function load_click_report_view_type(): void {
		// No nonce check needed for loading data generally, but ensure user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}
		$user_id = get_current_user_id();
		$view_type = get_user_meta( $user_id, 'mds_click_report_view_type', true );

		// Default to 'order' if not set or invalid
		if ( empty( $view_type ) || ! in_array( $view_type, [ 'order', 'block', 'date' ] ) ) {
			$view_type = 'order';
		}

		wp_send_json_success( [ 'view_type' => $view_type ] );
	}

	public static function save_click_report_view_type(): void {
		// Check nonce first for security
		check_ajax_referer( 'mds_save_view_type_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}
		$user_id = get_current_user_id();
		$view_type = isset( $_POST['view_type'] ) ? sanitize_text_field( $_POST['view_type'] ) : '';

		// Validate view_type
		if ( ! in_array( $view_type, [ 'order', 'block', 'date' ] ) ) {
			wp_send_json_error( 'Invalid view type' );
		}

		update_user_meta( $user_id, 'mds_click_report_view_type', $view_type );
		wp_send_json_success();
	}

	public static function menu(): void {
		global $mds_menus;

		$handle = \add_submenu_page(
			'milliondollarscript', 'Million Dollar Script Admin', 'Admin', 'manage_options', 'milliondollarscript_admin',
			[
				__CLASS__,
				'html'
			],
			2
		);

		// Add styles for admin page
		add_action( 'admin_print_styles-' . $handle, [ __CLASS__, 'styles' ] );

		// Add scripts for admin page
		add_action( 'admin_print_scripts-' . $handle, [ __CLASS__, 'scripts' ] );

		$mds_menus = [
			'hidden' => [
				'check',
				'edit-template',
				'map-iframe',
				'orders',
				'show-email',
				'outgoing-email',
				'get-order-image',
				'show-map',
				'Manage Grids',
				'Packages',
				'Price Zones',
				'Not For Sale',
				'Backgrounds',
				'Get Shortcodes',
				'List Customers',
				'Orders: Waiting',
				'Orders: Completed',
				'Orders: Reserved',
				'Orders: Expired',
				'Orders: Denied',
				'Orders: Cancelled',
				'Orders: Deleted',
				'Map of Orders',
				'Transaction Log',
				'Clear Orders',
				'Process Pixels',
				'List',
				'Top Customers',
				'Outgoing Email',
				'Top Clicks',
				'Click Reports',
				'Main Config',
				'System Information',
				'License',
				'Script Homepage',
			],
		];

		$parent_slug = 'milliondollarscript';
		$capability  = 'manage_options';

		foreach ( $mds_menus as $parent_title => $submenu_items ) {
			// if ( $parent_title == 'hidden' ) {
			// 	continue;
			// }

			if ( $parent_title != 'hidden' ) {
				// Format parent slug and callback
				$parent_slug_formatted = sanitize_title( $parent_title );
				$parent_callback       = sanitize_title( $parent_title );
				$parent_menu_slug      = 'mds-' . $parent_slug_formatted;

				$parent_handle = add_submenu_page(
					$parent_slug,
					$parent_title,
					$parent_title,
					$capability,
					$parent_menu_slug,
					[ "\\MillionDollarScript\\Classes\\Admin\\Admin", $parent_callback ]
				);

				add_action( 'admin_print_styles-' . $parent_handle, [ __CLASS__, 'styles' ] );
				add_action( 'admin_print_scripts-' . $parent_handle, [ __CLASS__, 'scripts' ] );
			}

			foreach ( $submenu_items as $submenu_title ) {
				// Format submenu slug and callback
				$submenu_slug_formatted = sanitize_title( $submenu_title );
				$submenu_callback       = sanitize_title( $submenu_title );
				$submenu_menu_slug      = 'mds-' . $submenu_slug_formatted;

				if ( $submenu_slug_formatted != 'not-for-sale' ) {
					$submenu_handle = add_submenu_page(
						'milliondollarscript_admin',
						$submenu_title,
						$submenu_title,
						$capability,
						$submenu_menu_slug,
						[ "\\MillionDollarScript\\Classes\\Admin\\Admin", $submenu_callback ]
					);

					add_action( 'admin_print_styles-' . $submenu_handle, [ __CLASS__, 'styles' ] );
					add_action( 'admin_print_scripts-' . $submenu_handle, [ __CLASS__, 'scripts' ] );
				}
			}
		}
	}

	/**
	 * Dynamic functions for menus and links.
	 *
	 * @throws \Exception
	 */
	public static function __callStatic( $name, $arguments ) {
		global $mds_menus;

		foreach ( $mds_menus as $parent_title => $submenu_items ) {
			$parent_slug_formatted = sanitize_title( $parent_title );
			$parent_callback       = sanitize_title( $parent_title );

			if ( $name == $parent_callback ) {
				echo '<h1>' . Language::get( $parent_title ) . '</h1>';
				echo '<ul>';
				foreach ( $submenu_items as $submenu_title ) {
					$submenu_slug_formatted = sanitize_title( $submenu_title );
					echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=mds-' . $submenu_slug_formatted ) ) . '">' . Language::get( $submenu_title ) . '</a></li>';
				}
				echo '</ul>';

				return;
			}

			foreach ( $submenu_items as $submenu_title ) {
				$submenu_slug_formatted = sanitize_title( $submenu_title );
				$submenu_callback       = sanitize_title( $submenu_title );

				if ( $name == $submenu_callback ) {
					?>
                    <div id="mds-top"></div>
                    <div class="admin-container">
						<?php require MDS_CORE_PATH . "admin/admin_menu.php"; ?>
                        <div class="admin-content">
                            <div class="admin-content-inner">
                                <?php \MillionDollarScript\Classes\Admin\Notices::display_notices(); ?>
                                <?php require MDS_CORE_PATH . "admin/" . $submenu_slug_formatted . ".php"; ?>
                            </div>
                        </div>
                    </div>
					<?php

					return;
				}
			}
		}

		throw new \Exception( wp_sprintf( Language::get( 'Invalid callback: %s' ), $name ) );
	}

	public static function html(): void {
		require_once MDS_CORE_PATH . 'admin/index.php';
	}

	public static function styles(): void {
		// CSS
		wp_enqueue_style(
			MDS_PREFIX . 'admin-css',
			MDS_BASE_URL . 'src/Assets/css/admin.css',
			[],
			filemtime( MDS_BASE_PATH . 'src/Assets/css/admin.css' )
		);

		global $wp_scripts;
		$ui = $wp_scripts->query( 'jquery-ui-core' );
		wp_enqueue_style(
			MDS_PREFIX . 'jquery-ui-css',
			'https://code.jquery.com/ui/' . $ui->ver . '/themes/smoothness/jquery-ui.min.css',
			[],
			$ui->ver
		);
	}

	public static function scripts(): void {
		wp_enqueue_script( 'hoverIntent' );

		// JS
		wp_enqueue_script(
			MDS_PREFIX . 'viselect',
			MDS_CORE_URL . 'js/third-party/viselect.umd.js',
			[],
			filemtime( MDS_CORE_PATH . 'js/third-party/viselect.umd.js' ),
			true
		);

		wp_register_script(
			MDS_PREFIX . 'admin-core-js',
			MDS_CORE_URL . 'admin/js/admin.min.js',
			[ 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-ui-button', 'jquery-form', 'hoverIntent' ],
			filemtime( MDS_CORE_PATH . 'admin/js/admin.min.js' ),
			true
		);

		wp_enqueue_script( MDS_PREFIX . 'admin-core-js' );
		
		// Enqueue error resolution script on compatibility page
		$current_screen = get_current_screen();
		if ( $current_screen && strpos( $current_screen->id, 'mds-compatibility' ) !== false ) {
			wp_enqueue_script(
				MDS_PREFIX . 'error-resolution',
				MDS_BASE_URL . 'src/Assets/js/admin/error-resolution.js',
				[ 'jquery', 'wp-api' ],
				filemtime( MDS_BASE_PATH . 'src/Assets/js/admin/error-resolution.js' ),
				true
			);
		}
	}

	public static function admin_pixels(): void {
		$post_type = get_post_type();

		if ( $post_type === FormFields::$post_type && is_admin() ) {
			wp_enqueue_script(
				MDS_PREFIX . 'admin-pixels',
				MDS_BASE_URL . 'src/Assets/js/admin-pixels.min.js',
				[ 'jquery' ],
				filemtime( MDS_BASE_PATH . 'src/Assets/js/admin-pixels.min.js' ),
				true
			);
		}
	}

	/**
	 * Enqueue admin scripts for MDS admin pages.
	 *
	 * @return void
	 */
	public static function enqueue_admin_scripts(): void {
		if ( is_admin() ) {
			// Use the existing registered script from Admin::scripts()
			wp_enqueue_script( 'hoverIntent' );
			
			// Register the script if not already registered
			if ( ! wp_script_is( MDS_PREFIX . 'admin-core-js', 'registered' ) ) {
				wp_register_script(
					MDS_PREFIX . 'admin-core-js',
					MDS_CORE_URL . 'admin/js/admin.min.js',
					[ 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-ui-button', 'jquery-form', 'hoverIntent' ],
					filemtime( MDS_CORE_PATH . 'admin/js/admin.min.js' ),
					true
				);
			}
			
			// Enqueue the registered script
			wp_enqueue_script( MDS_PREFIX . 'admin-core-js' );
		}
	}

	/**
	 * Get current page configuration for block editor
	 *
	 * @return array Page configuration or null if not an MDS page
	 */
	private static function getCurrentPageConfiguration(): ?array {
		global $post;
		
		// Only process when editing a page
		if ( !$post || $post->post_type !== 'page' || !is_admin() ) {
			return null;
		}
		
		// Get current screen to verify we're in the editor
		$screen = get_current_screen();
		if ( !$screen || ( $screen->id !== 'page' && $screen->base !== 'post' ) ) {
			return null;
		}
		
		// Try to get page metadata first
		$metadata_manager = \MillionDollarScript\Classes\Data\MDSPageMetadataManager::getInstance();
		$metadata = $metadata_manager->getMetadata( $post->ID );
		
		if ( $metadata ) {
			return [
				'is_mds_page' => true,
				'page_type' => $metadata->page_type,
				'grid_id' => $metadata->grid_id,
				'configuration' => $metadata->configuration,
				'source' => 'metadata'
			];
		}
		
		// Fallback: Analyze page content directly
		$detection_engine = new \MillionDollarScript\Classes\Data\MDSPageDetectionEngine();
		$detection_result = $detection_engine->detectMDSPage( $post->ID );
		
		if ( $detection_result['is_mds_page'] ) {
			// Extract configuration from detected patterns
			$config = self::extractConfigurationFromDetection( $detection_result );
			return [
				'is_mds_page' => true,
				'page_type' => $detection_result['page_type'],
				'grid_id' => $config['grid_id'] ?? 1,
				'configuration' => $config,
				'source' => 'detection'
			];
		}
		
		// Parse MDS Configuration comments as final fallback
		$comment_config = self::parseMDSConfigurationComments( $post->post_content );
		if ( $comment_config ) {
			return [
				'is_mds_page' => true,
				'page_type' => $comment_config['type'] ?? 'grid',
				'grid_id' => $comment_config['id'] ?? 1,
				'configuration' => $comment_config,
				'source' => 'comments'
			];
		}
		
		return null;
	}
	
	/**
	 * Extract configuration from detection engine results
	 *
	 * @param array $detection_result Detection engine results
	 * @return array Configuration array
	 */
	private static function extractConfigurationFromDetection( array $detection_result ): array {
		$config = [
			'id' => 1,
			'align' => 'center',
			'width' => '1000px',
			'height' => '1000px',
			'type' => $detection_result['page_type']
		];
		
		// Extract from patterns if available
		if ( isset( $detection_result['patterns'] ) && is_array( $detection_result['patterns'] ) ) {
			foreach ( $detection_result['patterns'] as $pattern ) {
				if ( isset( $pattern['attributes'] ) && is_array( $pattern['attributes'] ) ) {
					$attrs = $pattern['attributes'];
					
					// Map common attributes
					if ( isset( $attrs['id'] ) ) $config['id'] = intval( $attrs['id'] );
					if ( isset( $attrs['width'] ) ) $config['width'] = $attrs['width'];
					if ( isset( $attrs['height'] ) ) $config['height'] = $attrs['height'];
					if ( isset( $attrs['align'] ) ) $config['align'] = $attrs['align'];
					if ( isset( $attrs['type'] ) ) $config['type'] = $attrs['type'];
					
					// Handle Carbon Fields attributes
					if ( isset( $attrs['milliondollarscript_id'] ) ) $config['id'] = intval( $attrs['milliondollarscript_id'] );
					if ( isset( $attrs['milliondollarscript_width'] ) ) $config['width'] = $attrs['milliondollarscript_width'];
					if ( isset( $attrs['milliondollarscript_height'] ) ) $config['height'] = $attrs['milliondollarscript_height'];
					if ( isset( $attrs['milliondollarscript_align'] ) ) $config['align'] = $attrs['milliondollarscript_align'];
					if ( isset( $attrs['milliondollarscript_type'] ) ) $config['type'] = $attrs['milliondollarscript_type'];
				}
			}
		}
		
		return $config;
	}
	
	/**
	 * Parse MDS Configuration comments from page content
	 *
	 * @param string $content Page content
	 * @return array|null Configuration array or null if not found
	 */
	private static function parseMDSConfigurationComments( string $content ): ?array {
		// Look for MDS Configuration comment pattern
		$pattern = '/<!--\s*MDS Configuration:\s*([^-]+)-->/i';
		if ( preg_match( $pattern, $content, $matches ) ) {
			$config_text = trim( $matches[1] );
			
			// Parse key-value pairs
			$config = [];
			$lines = explode( ',', $config_text );
			
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( strpos( $line, ':' ) !== false ) {
					list( $key, $value ) = explode( ':', $line, 2 );
					$config[trim( $key )] = trim( $value );
				}
			}
			
			return $config;
		}
		
		return null;
	}

}