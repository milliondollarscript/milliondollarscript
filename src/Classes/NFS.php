<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.4
 * @author      Ryan Rhode
 * @copyright   (C) 2023, Ryan Rhode
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

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;

defined( 'ABSPATH' ) or exit;

class NFS {

	private static string $slug = 'mds-not-for-sale';

	/**
	 * Init NFS page.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 10 );
		add_action( 'wp_ajax_mds_nfs_save', [ __CLASS__, 'ajax_save' ] );
		add_action( 'wp_ajax_mds_nfs_reset', [ __CLASS__, 'ajax_reset' ] );
		add_action( 'wp_ajax_mds_nfs_covered', [ __CLASS__, 'ajax_covered' ] );
		add_action( 'wp_ajax_mds_nfs_covered_image', [ __CLASS__, 'ajax_covered_image' ] );
		add_action( 'admin_post_mds_nfs_grid', [ __CLASS__, 'form_response' ] );

		if ( isset( $_GET['page'] ) && $_GET['page'] == 'mds-not-for-sale' ) {
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		}
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public static function menu(): void {
		$handle = \add_submenu_page( 'MillionDollarScript_Admin', 'Million Dollar Script NFS', 'Not For Sale', 'manage_options', self::$slug, array( __CLASS__, 'html' ), 2 );

		// Add styles for admin page
		add_action( 'admin_print_styles-' . $handle, [ __CLASS__, 'enqueue_styles' ] );
	}

	/**
	 * Load styles.
	 *
	 * @return void
	 */
	public static function enqueue_styles(): void {
		global $wp_scripts;
		$ui = $wp_scripts->query( 'jquery-ui-core' );
		wp_enqueue_style( 'jquery-ui-theme-smoothness', 'https://code.jquery.com/ui/' . $ui->ver . '/themes/smoothness/jquery-ui.min.css' );

		wp_enqueue_style(
			MDS_PREFIX . 'admin-css',
			MDS_CORE_URL . 'admin/css/admin.css',
			array(),
			filemtime( MDS_CORE_PATH . 'admin/css/admin.css' )
		);

		wp_enqueue_style( 'mds-admin-css', MDS_BASE_URL . 'src/Assets/css/admin.css' );
		wp_add_inline_style( 'mds-admin-css',
			self::get_inline_css()
		);
	}

	/**
	 * Enqueue scripts for NFS page.
	 *
	 * @return void
	 */
	public static function enqueue_scripts(): void {

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-button' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-form' );
		wp_enqueue_script( 'viselect', MDS_CORE_URL . 'js/third-party/viselect.umd.js', [], filemtime( MDS_CORE_PATH . "/js/third-party/viselect.umd.js" ) );

		wp_register_script( 'mds-nfs-js', MDS_BASE_URL . 'src/Assets/js/nfs.min.js', [ 'jquery', 'jquery-ui-core', 'jquery-ui-button', 'jquery-ui-dialog', 'jquery-form', 'viselect' ], filemtime( MDS_BASE_PATH . 'src/Assets/js/nfs.min.js' ), true );
		wp_localize_script( 'mds-nfs-js', 'MDS', [
			'ajaxurl'       => admin_url( 'admin-ajax.php' ),
			'adminpost'     => admin_url( 'admin-post.php' ),
			'mds_nfs_nonce' => wp_create_nonce( 'mds_nfs_nonce' ),
			'MDS_BASE_URL'  => MDS_BASE_URL,
			'lang'          => [
				'success'       => Language::get( 'Success!' ),
				'error'         => Language::get( 'Error!' ),
				'reset'         => Language::get( 'Reset!' ),
				'reset_message' => Language::get( 'This will unselect all Not For Sale blocks. Are you sure you want to do this?' ),
			],
		] );
		wp_enqueue_script( 'mds-nfs-js' );
	}

	public static function ajax_covered(): void {
		check_ajax_referer( 'mds_nfs_nonce', 'mds_nfs_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::no_perms();
		}

		if ( isset( $_POST['BID'] ) && ! empty( $_POST['BID'] ) ) {
			global $f2;

			$BID = $f2->bid();

			$banner_data = load_banner_constants( $BID );

			wp_send_json_success( $banner_data['NFS_COVERED'] );
		}
	}

	/**
	 * Handle NFS AJAX requests.
	 *
	 * @return void
	 */
	public static function ajax_save(): void {
		check_ajax_referer( 'mds_nfs_nonce', 'mds_nfs_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::no_perms();
		}

		$addingnfs = false;
		if ( isset( $_POST['addnfs'] ) && ( ! empty( $_POST['addnfs'] || $_POST['addnfs'] == 0 ) ) ) {
			$addingnfs = true;
		}

		$removingnfs = false;
		if ( isset( $_POST['remnfs'] ) && ( ! empty( $_POST['remnfs'] ) || $_POST['remnfs'] == 0 ) ) {
			$removingnfs = true;
		}

		if ( ! $addingnfs && ! $removingnfs ) {
			$error = new \WP_Error( '406', Language::get( "You haven't made any changes to save!" ) );
			wp_send_json_error( $error );
		}

		@ini_set( 'max_execution_time', 10000 );
		@ini_set( 'max_input_vars', 10002 );

		global $f2;

		$BID = $f2->bid();

		$banner_data = load_banner_constants( $BID );

		$imagine = new Imagine();

		// load blocks
		$block_size  = new Box( $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );
		$palette     = new RGB();
		$color       = $palette->color( '#000', 0 );
		$zero_point  = new Point( 0, 0 );
		$blank_block = $imagine->create( $block_size, $color );

		if ( $banner_data['NFS_COVERED'] == "N" ) {
			$usr_nfs_block     = $imagine->load( $banner_data['USR_NFS_BLOCK'] );
			$default_nfs_block = $blank_block->copy();
			$usr_nfs_block->resize( $block_size );
			$default_nfs_block->paste( $usr_nfs_block, $zero_point );
			$data = base64_encode( $default_nfs_block->get( "png", array( 'png_compression_level' => 9 ) ) );
		}

		//$sql = "delete from blocks where status='nfs' AND banner_id=$BID ";
		//mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);

		if ( $addingnfs ) {
			$addnfs = json_decode( html_entity_decode( stripslashes( $_POST['addnfs'] ) ) );
		} else {
			unset( $addnfs );
		}

		if ( $removingnfs ) {
			$remnfs = json_decode( html_entity_decode( stripslashes( $_POST['remnfs'] ) ) );
		} else {
			unset( $remnfs );
		}

		// Mix the database results with $addnfs and $remnfs to get the full results to loop over.
		if ( $banner_data['NFS_COVERED'] == "Y" ) {
			global $wpdb;

			$nfs_block = $imagine->load( $banner_data['NFS_BLOCK'] );

			// Find outer bounds of NFS blocks to get the size of the cover image.
			$sql     = $wpdb->prepare( "SELECT `block_id` FROM `" . MDS_DB_PREFIX . "blocks` WHERE `status`='nfs' AND `banner_id`=%d ORDER BY `block_id`",
				$BID
			);
			$results = $wpdb->get_results( $sql, ARRAY_A );

			$topleft     = [ 'x' => PHP_INT_MIN, 'y' => PHP_INT_MIN ];
			$bottomright = [ 'x' => PHP_INT_MAX, 'y' => PHP_INT_MAX ];

			foreach ( $results as $result ) {
				// Don't process blocks being removed.
				if ( $removingnfs && in_array( $result['block_id'], $remnfs ) ) {
					continue;
				}

				$coords = get_block_position( $result['block_id'], $BID );

				$topleft['x']     = max( $topleft['x'], $coords['x'] );
				$topleft['y']     = max( $topleft['y'], $coords['y'] );
				$bottomright['x'] = min( $bottomright['x'], $coords['x'] );
				$bottomright['y'] = min( $bottomright['y'], $coords['y'] );
			}

			if ( $addingnfs ) {
				foreach ( $addnfs as $add ) {
					$coords = get_block_position( $add, $BID );

					$topleft['x']     = max( $topleft['x'], $coords['x'] );
					$topleft['y']     = max( $topleft['y'], $coords['y'] );
					$bottomright['x'] = min( $bottomright['x'], $coords['x'] );
					$bottomright['y'] = min( $bottomright['y'], $coords['y'] );
				}
			}

			$image_width  = ( $topleft['x'] - $bottomright['x'] + $banner_data['BLK_WIDTH'] );
			$image_height = ( $topleft['y'] - $bottomright['y'] + $banner_data['BLK_HEIGHT'] );

			if ( $image_width > 0 && $image_height > 0 ) {
				$image_size = new Box( $image_width, $image_height );
				$nfs_block->resize( $image_size );
			}
		}

		global $wpdb;

		$cell = $y = $nfs_y = 0;
		for ( $i = 0; $i < $banner_data['G_HEIGHT']; $i ++ ) {
			$x = $nfs_x = 0;
			for ( $j = 0; $j < $banner_data['G_WIDTH']; $j ++ ) {
				if ( $addingnfs && in_array( $cell, $addnfs ) ) {
					if ( $banner_data['NFS_COVERED'] == "Y" ) {
						// Take parts of the image and place them in the right spots.

						// @see \check_selection_main
						$dest = $imagine->create( $block_size, $color );
						imagecopy( $dest->getGdResource(), $nfs_block->getGdResource(), 0, 0, $nfs_x, $nfs_y, $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );

						$data = $dest->get( "png", array( 'png_compression_level' => 9 ) );

						unset( $dest );

						// TODO: fix this to be more robust
						$nfs_x = $nfs_x + $banner_data['BLK_WIDTH'];
						if ( $nfs_x + $banner_data['BLK_WIDTH'] > $image_width ) {
							$nfs_x = 0;
							$nfs_y = $nfs_y + $banner_data['BLK_HEIGHT'];
						}
					}

					// Check to make sure we don't overwrite any existing blocks that aren't free.
					$sql = $wpdb->prepare(
						"SELECT `block_id` FROM `" . MDS_DB_PREFIX . "blocks` WHERE `block_id`=%d AND `status`!='free' AND `banner_id`=%d ",
						$cell,
						$BID,
					);
					$wpdb->get_results( $sql );
					if ( $wpdb->num_rows == 0 ) {
						// Insert new block
						$sql = $wpdb->prepare(
							"INSERT INTO `" . MDS_DB_PREFIX . "blocks` (`block_id`, `status`, `x`, `y`, `image_data`, `banner_id`, `click_count`, `alt_text`) VALUES (%d, 'nfs', %d, %d, %s, %d, 0, '')",
							$cell,
							$x,
							$y,
							base64_encode( $data ),
							$BID
						);
					} else {
						// Update existing block only if it's free or nfs.
						$sql = $wpdb->prepare(
							"UPDATE `" . MDS_DB_PREFIX . "blocks` SET `status`='nfs', `x`=%d, `y`=%d, `image_data`=%s, `click_count`=0, `alt_text`='' WHERE `block_id`=%d AND `banner_id`=%d AND `status` IN ('free','nfs')",
							$x,
							$y,
							base64_encode( $data ),
							$cell,
							$BID,
						);
					}

					$wpdb->query( $sql );

					if ( $banner_data['NFS_COVERED'] == "Y" ) {
						unset( $data );
					}
				}

				if ( $removingnfs && in_array( $cell, $remnfs ) ) {
					$sql = "DELETE FROM " . MDS_DB_PREFIX . "blocks WHERE status='nfs' AND banner_id={$BID} AND block_id={$cell}";
					$wpdb->query( $sql );
				}
				$x = $x + $banner_data['BLK_WIDTH'];
				$cell ++;
			}

			$y = $y + $banner_data['BLK_HEIGHT'];
		}

		process_image( $BID );
		publish_image( $BID );
		process_map( $BID );

		wp_send_json_success( [ 'css' => self::get_inline_css(), 'html' => self::get_html() ] );
	}

	/**
	 * Ajax response for reset button.
	 *
	 * @return void
	 */
	public static function ajax_reset(): void {
		check_ajax_referer( 'mds_nfs_nonce', 'mds_nfs_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::no_perms();
		}

		global $wpdb, $f2;

		$BID = $f2->bid();

		@ini_set( 'max_execution_time', 10000 );
		@ini_set( 'max_input_vars', 10002 );

		$sql = $wpdb->prepare( "DELETE FROM `" . MDS_DB_PREFIX . "blocks` WHERE `status`='nfs' AND `banner_id`=%d",
			$BID
		);
		$wpdb->query( $sql );

		process_image( $BID );
		publish_image( $BID );
		process_map( $BID );

		wp_send_json_success( [ 'css' => self::get_inline_css(), 'html' => self::get_html() ] );
	}

	public static function ajax_covered_image(): void {
		check_ajax_referer( 'mds_nfs_nonce', 'mds_nfs_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::no_perms();
		}

		global $f2;

		$BID = $f2->bid();

		$banner_data = load_banner_constants( $BID );

		wp_send_json_success( $banner_data['NFS_BLOCK'] );
	}

	/**
	 * Handle NFS form responses.
	 *
	 * @return void
	 */
	public static function form_response(): void {
		check_admin_referer( 'mds_nfs_nonce', 'mds_nfs_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::no_perms();
		}

		status_header( 200 );

		wp_send_json_success( [ 'css' => self::get_inline_css(), 'html' => self::get_html() ] );
	}

	public static function no_perms(): void {
		$error = new \WP_Error( '403', Language::get( "Insufficient permissions!" ) );
		wp_send_json_error( $error );
		wp_die();
	}

	public static function get_inline_css(): string {

		global $f2;
		$BID         = $f2->bid();
		$banner_data = load_banner_constants( $BID );

		return ".grid {
                        position: relative;
                        background-size: contain;
                        z-index: 0;
                        width: " . $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] . "px;
                        height: " . $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] . "px;
                        user-select: none;
                    }
                    
                    .block_row {
                        clear: both;
                        display: block;
                    }
                    
                    .block {
                        white-space: nowrap;
                        width: " . $banner_data['BLK_WIDTH'] . "px;
                        height: " . $banner_data['BLK_HEIGHT'] . "px;
                        float: left;
                        opacity: 0.5;
                        filter: alpha(opacity=50);
                        background-size: contain !important;
                    }
                    
                    .sold {
                        background: url('" . esc_url( MDS_CORE_URL ) . "images/sold_block.png') no-repeat;
                    }
                    
                    .reserved {
                        background: url('" . esc_url( MDS_CORE_URL ) . "images/reserved_block.png') no-repeat;
                    }

                    .ordered {
                        background: url('" . esc_url( MDS_CORE_URL ) . "images/ordered_block.png') no-repeat;
                    }
                    
                    .ordered {
                        background: url('" . esc_url( MDS_CORE_URL ) . "images/ordered_block.png') no-repeat;
                    }
                    
                    .onorder {
                        background: url('" . esc_url( MDS_CORE_URL ) . "images/not_for_sale_block.png') no-repeat;
                    }
                    
                    .free {
                        background: url('" . esc_url( MDS_CORE_URL ) . "images/block.png') no-repeat;
                        cursor: pointer;
                    }
                    
                    .loading {
                        width: 32px;
                        height: 32px;
                        position: absolute;
                        top: 5%;
                        left: calc(50% - 16px);
                        z-index: 10000;
                    }
                    
                    .selection-area {
                        background: rgba(254, 202, 64, 0.33);
                        border: 1px solid rgba(135, 110, 42, 0.33);
                    }
                    
                    .selected {
                        outline: 1px solid rgba(0, 0, 0, 0.45);
                    }";
	}

	public static function get_html(): string {
		ob_start();
		self::html();
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * Output NFS page HTML.
	 */
	public static function html(): void {
		?>
        <div class="admin-container">
			<?php require_once MDS_CORE_PATH . "admin/admin_menu.php"; ?>
            <div class="admin-content">
                <div class="admin-content-inner">
					<?php require_once MDS_BASE_PATH . "src/Html/NFS.php"; ?>
                </div>
            </div>
        </div>
		<?php
	}
}
