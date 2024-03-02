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

namespace MillionDollarScript\Classes\Admin;

use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Language\LanguageScanner;
use MillionDollarScript\Classes\System\Utility;

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

	/**
	 * Creates pages for MDS.
	 *
	 * @return void
	 */
	public static function create_pages(): void {
		check_ajax_referer( 'mds_admin_nonce', 'nonce' );

		$pages = Utility::get_pages();

		$output = [];

		// Create new pages in WP.
		foreach ( $pages as $page => $data ) {
			if ( empty( $data['page_id'] ) ) {

				$id = 1;
				if ( isset( $data['id'] ) ) {
					$id = $data['id'];
				}

				$align = 'center';
				if ( isset( $data['align'] ) ) {
					$align = $data['align'];
				}

				$width = '100%';
				if ( isset( $data['width'] ) ) {
					$width = $data['width'];
				}

				$height = 'auto';
				if ( isset( $data['height'] ) ) {
					$height = $data['height'];
				}

                // TODO: Add an option to use blocks for new pages instead of shortcodes:
                // <!-- wp:carbon-fields/million-dollar-script {"data":{"milliondollarscript_preview":"\u003cdiv class=\u0022cf-preview\u0022\u003e\u003cimg src='https://mds.ddev.site/app/plugins/milliondollarscript-two/src/Core/images/bg-main.gif' /\u003e\u003c/div\u003e\u003c!\u002d\u002d /.cf-preview \u002d\u002d\u003e","milliondollarscript_id":"1","milliondollarscript_align":"center","milliondollarscript_width":"100%","milliondollarscript_height":"auto","milliondollarscript_type":"users"}} /-->

				$shortcode = '[milliondollarscript id="%d" align="%s" width="%s" height="%s" type="%s"]';
				$content   = wp_sprintf( $shortcode, $id, $align, $width, $height, $page );

				$page_id = wp_insert_post( [
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $data['title'],
					'post_content' => $content,
				] );

				if ( is_wp_error( $page_id ) ) {
					wp_die( $page_id->get_error_message() );
				}

				// Update the option for this page.
				update_option( '_' . MDS_PREFIX . $data['option'], $page_id );

				// Store post modified time for future use.
				$modified_time = get_post_modified_time( 'Y-m-d H:i:s', false, $page_id );
				update_post_meta( $page_id, '_modified_time', $modified_time );

				$output[ $data['option'] ] = $page_id;
			}
		}

		wp_send_json( $output );
	}

	/**
	 * Deletes pages created by MDS.
	 *
	 * @return void
	 */
	public static function delete_pages(): void {
		check_ajax_referer( 'mds_admin_nonce', 'nonce' );

		$pages = Utility::get_pages();

		$output = [];

		foreach ( $pages as $page => $data ) {
			$page_id = $data['page_id'];
			if ( $page_id !== false ) {

				// Delete the post from WP if it's modified time is older or equal to the stored time option.
				$modified_time          = strtotime( get_post_modified_time( 'Y-m-d H:i:s', false, $page_id ) );
				$original_modified_time = strtotime( get_post_meta( $page_id, '_modified_time', true ) );

				if ( $modified_time == $original_modified_time ) {
					wp_delete_post( $page_id );

					// Delete the options for this page.
					delete_option( '_' . MDS_PREFIX . $data['option'] );

					$output[ $data['option'] ] = '';
				}
			}
		}

		wp_send_json( $output );
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

		wp_localize_script( MDS_PREFIX . 'admin-block-js', 'MDS', [
			'admin'                => admin_url( 'admin.php' ),
			'mds_site_url'         => get_site_url(),
			'ajaxurl'              => admin_url( 'admin-ajax.php' ),
			'MDS_PREFIX'           => MDS_PREFIX,
			'mds_admin_ajax_nonce' => wp_create_nonce( 'mds_admin_ajax_nonce' ),
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

	public static function menu(): void {
		global $mds_menus;

		$handle = \add_submenu_page( 'milliondollarscript', 'Million Dollar Script Admin', 'Admin', 'manage_options', 'milliondollarscript_admin', [
			__CLASS__,
			'html'
		], 2 );

		// Add styles for admin page
		add_action( 'admin_print_styles-' . $handle, [ __CLASS__, 'styles' ] );

		// Add scripts for admin page
		add_action( 'admin_print_scripts-' . $handle, [ __CLASS__, 'scripts' ] );

		$mds_menus = [
			'hidden' => [
				'approve-pixels',
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
				'Approve Pixels',
				'Disapprove Pixels',
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
			MDS_CORE_URL . 'admin/css/admin.css',
			[],
			filemtime( MDS_CORE_PATH . 'admin/css/admin.css' )
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
		wp_enqueue_script('hoverIntent');

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
}
