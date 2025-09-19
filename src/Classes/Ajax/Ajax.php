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

/** @noinspection PhpUndefinedConstantInspection */

namespace MillionDollarScript\Classes\Ajax;

use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\Orders\Blocks;
use MillionDollarScript\Classes\System\Logs;
use MillionDollarScript\Classes\System\Utility;
use function esc_attr;
use function wp_unslash;
use function sanitize_text_field;

defined( 'ABSPATH' ) or exit;

class Ajax {

	/**
	 * Init NFS page.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_ajax_mds_ajax', [ __CLASS__, 'mds_ajax' ] );
		add_action( 'wp_ajax_nopriv_mds_ajax', [ __CLASS__, 'mds_ajax' ] );
	}

	/**
	 * Ajax response for MDS.
	 *
	 * @return void
	 */
	public static function mds_ajax(): void {
		check_ajax_referer( 'mds_nonce', 'mds_nonce' );

		// Handle POST input
		if ( isset( $_POST ) ) {

			$type = $_POST['type'] ?? null;
			$_GET = [];

			// Check if 'get_params' is set
			if ( isset( $_POST['get_params'] ) ) {
				$json = json_decode( stripslashes( $_POST['get_params'] ), true );

				// Catch broken JSON in get_params
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$_GET = $json;
				}
			}

			// Make sure $_POST and $_REQUEST are arrays
			$_POST    = is_array( $_POST ) ? $_POST : [];
			$_REQUEST = is_array( $_REQUEST ) ? $_REQUEST : [];

			// Merge arrays
			$_REQUEST = array_merge( $_GET, $_POST, $_REQUEST );

			// Handle core MDS AJAX calls
			if ( isset( $type ) ) {
				switch ( $type ) {
					case "grid":
						self::show_grid();
						wp_die();
					case "stats":
						self::show_stats();
						wp_die();
					case "ga":
						self::get_ad();
						self::store_view( $_POST );
						wp_die();
					case "click":
						self::store_click( $_POST );
						wp_die();
					case "list":
						self::show_list();
						wp_die();
					case "confirm-order":
						require_once MDS_CORE_PATH . 'users/confirm_order.php';
						wp_die();
					case "users":
					case "account":
					case "publish":
					case "history":
					case "manage":
						require_once MDS_CORE_PATH . 'users/manage.php';
						wp_die();
					case "no-orders":
						Orders::no_orders();
						wp_die();
					case "order":
						Orders::order_screen();
						wp_die();
					case "checkout":
					case "payment":
						require_once MDS_CORE_PATH . 'users/payment.php';
						wp_die();
					case "thank-you":
						require_once MDS_CORE_PATH . 'users/thanks.php';
						wp_die();
					case "upload":
						require_once MDS_CORE_PATH . 'users/upload.php';
						wp_die();
					case "write-ad":
						require_once MDS_CORE_PATH . 'users/write_ad.php';
						wp_die();
					case "make-selection":
						// Logs::log( 'MDS AJAX make-selection request routed through Orders::persist_selection handler.' );
						// Logging suppressed to reduce noise while the legacy shim is still active.
						require_once MDS_CORE_PATH . 'users/make_selection.php';
						wp_die();
					case "validate-selection":
						self::handle_validate_selection();
						wp_die();
					default:
						break;
				}
			}
		}

		wp_die();
	}

	private static function handle_validate_selection(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [
				'messages' => [ Language::get( 'You must be logged in to continue.' ) ],
			], 401 );
		}

		$BID = isset( $_REQUEST['BID'] ) ? intval( $_REQUEST['BID'] ) : 0;
		if ( $BID <= 0 ) {
			wp_send_json_error( [ 'messages' => [ Language::get( 'Invalid grid.' ) ] ], 400 );
		}

		$banner_data = load_banner_constants( $BID );
		if ( empty( $banner_data ) ) {
			wp_send_json_error( [ 'messages' => [ Language::get( 'Invalid grid configuration.' ) ] ], 400 );
		}

		$mode       = isset( $_REQUEST['mode'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_REQUEST['mode'] ) ) ) : null;
		$raw_blocks = $_REQUEST['blocks'] ?? '';
		$block_ids  = Blocks::parse_block_ids( $raw_blocks );

		$errors = Blocks::validate_selection( $block_ids, $banner_data, $mode );
		if ( ! empty( $errors ) ) {
			wp_send_json_error( [ 'messages' => $errors ] );
		}

		wp_send_json_success( [
			'messages'   => [],
			'normalized' => implode( ',', $block_ids ),
			'count'      => count( $block_ids ),
		] );
	}

	public static function show_grid(): void {
		global $f2;

		$BID = $f2->bid();

		if ( ! is_numeric( $BID ) ) {
			die;
		}

		$banner_data = load_banner_constants( $BID );

		$BANNER_PATH = Utility::get_upload_path() . 'grids/';
		$BANNER_URL  = Utility::get_upload_url() . 'grids/';

		$map_file = get_map_file_name( $BID );

		$ext = Utility::get_file_extension();

		if ( ! file_exists( $BANNER_PATH . "grid" . $BID . ".$ext" ) ) {
			process_image( $BID );
			publish_image( $BID );
			process_map( $BID, $map_file );
		}

		header( 'Expires: Sat, 28 Sept 2005 00:00:00 GMT' );

		if ( file_exists( $BANNER_PATH . "grid" . $BID . ".$ext" ) ) {
			$container = 'grid' . $BID;

			$background_color = mds_get_grid_background_color( $BID, $banner_data );
			$bgstyle          = $background_color ? ' style="background-color:' . esc_attr( $background_color ) . ';"' : '';
			$grid_src = $BANNER_URL . 'grid' . $BID . '.' . $ext . '?v=' . filemtime( $BANNER_PATH . "grid" . $BID . ".$ext" );
			?>
            <div class="mds-container">
                <div class="grid-container <?php echo $container; ?>"<?php echo $bgstyle ?>>
                    <div class='grid-inner' id='<?php echo $container; ?>'>
						<?php include_once( $map_file ); ?>
                        <div class="mds-grid-frame">
                            <div class="mds-grid-status">
                                <div class="mds-grid-preloader"
                                    data-loader-src="<?php echo esc_url( MDS_BASE_URL . 'src/Assets/images/ajax-loader.gif' ); ?>"
                                    data-original-width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>"
                                    data-original-height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>"
                                    hidden>
                                    <span class="mds-grid-preloader__spinner" aria-hidden="true"></span>
                                </div>
                                <div class="mds-grid-feedback" role="alert" aria-live="polite" hidden>
                                    <p class="mds-grid-feedback__message"><?php Language::out( "We're having trouble loading the grid image right now. This can happen if the grid is very large or the server needs more time." ); ?></p>
                                    <button type="button" class="mds-grid-feedback__retry"><?php Language::out( 'Retry loading image' ); ?></button>
                                </div>
                            </div>
                            <div class="mds-grid-canvas">
                                <img id="theimage" src="<?php echo esc_url( $grid_src ); ?>" data-grid-src="<?php echo esc_url( $grid_src ); ?>"
                                    width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>" height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>" border="0"
                                    usemap="#map-grid-<?php echo $BID; ?>" alt=""/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

			<?php
		} else {

			Language::out_replace( '<b>The file: %FILE% doesn\'t exist.</b><br>', '%FILE%', $BANNER_PATH . 'grid' . $BID . '.' . $ext );
			Language::out( "<b>Please process your pixels from the Admin section (Look under 'Pixel Admin')</b>" );
		}
	}

	public static function show_stats(): void {
		global $f2, $wpdb;

		$BID = $f2->bid();

		$banner_data = load_banner_constants( $BID );

		$sql = $wpdb->prepare( "SELECT COUNT(*) AS COUNT FROM " . MDS_DB_PREFIX . "blocks WHERE status='sold' AND banner_id=%d", $BID );
		$row = $wpdb->get_row( $sql, ARRAY_A );

		$STATS_DISPLAY_MODE = Options::get_option( 'stats-display-mode' );

		if ( $STATS_DISPLAY_MODE == 'BLOCKS' ) {
			$sold = $row['COUNT'];
		} else {
			$sold = $row['COUNT'] * ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
		}

		$sql = $wpdb->prepare( "SELECT COUNT(*) AS COUNT FROM " . MDS_DB_PREFIX . "blocks WHERE status='nfs' AND banner_id=%d", $BID );
		$row = $wpdb->get_row( $sql, ARRAY_A );

		$STATS_DISPLAY_MODE = Options::get_option( 'stats-display-mode' );

		if ( $STATS_DISPLAY_MODE == 'BLOCKS' ) {
			$nfs       = $row['COUNT'];
			$available = ( ( $banner_data['G_WIDTH'] * $banner_data['G_HEIGHT'] ) - $nfs ) - $sold;
		} else {
			$nfs       = $row['COUNT'] * ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
			$available = ( ( $banner_data['G_WIDTH'] * $banner_data['G_HEIGHT'] * ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] ) ) - $nfs ) - $sold;
		}

		?>
        <div class="mds-container stats-container stats<?php echo intval( $BID ); ?>">
            <div class="status_body">
                <div class="status">
					<?php
					Language::out_replace( '<b>Sold:</b> <span class="status_text">%SOLD%</span><br/>', '%SOLD%', number_format( $sold ) );
					Language::out_replace( '<b>Available:</b> <span class="status_text">%AVAILABLE%</span><br/>', '%AVAILABLE%', number_format( $available ) );
					?>
                </div>
            </div>
        </div>
		<?php
	}

	public static function get_ad( $post_id = null, $return = false ): string|null {
		if ( $post_id == null ) {
			$post_id = intval( $_POST['aid'] );
		}
		$fields = FormFields::get_fields();

		add_filter( 'wp_kses_allowed_html', [ '\MillionDollarScript\Classes\System\Functions', 'mds_allowed_mds_params' ] );
		$output = apply_filters( 'the_content', Options::get_option( 'popup-template' ) );
		remove_filter( 'wp_kses_allowed_html', [ '\MillionDollarScript\Classes\System\Functions', 'mds_allowed_mds_params' ] );

		// Create replacements array for custom template variables
		$replacements = [];
		
		foreach ( $fields as $field ) {
			$field_name = str_replace( MDS_PREFIX, '', $field->get_base_name() );
			$value      = carbon_get_post_meta( $post_id, $field->get_base_name() );

			$value = apply_filters( 'mds_field_output_before', $value, $field, $post_id );

			if ( $field_name === 'text' ) {
				$value = '<span class="mds-popup-text">' . esc_html( $value ) . '</span>';
			} else if ( $field_name === 'url' ) {
				$value = '<a class="mds-popup-url" target="_blank" href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>';
			} else if ( $field_name === 'image' ) {
				$image_id  = carbon_get_post_meta( $post_id, $field->get_base_name() );
				$image_url = wp_get_attachment_url( $image_id );
				$max_image_size = Options::get_option( 'max-image-size', 332 );
				$value     = '<img class="mds-popup-image" src="' . esc_url( $image_url ) . '" alt="" style="max-width:' . $max_image_size . 'px;max-height:' . $max_image_size . 'px;">';
			}

			$replacements['%' . $field_name . '%'] = $value;
			$output = str_replace( '%' . $field_name . '%', $value, $output );

			$allowed_tags                           = Language::allowed_html();
			$allowed_tags['div']['data-mds-params'] = true;
			$output                                 = wp_kses( $output, $allowed_tags, Language::allowed_protocols() );

			$output = apply_filters( 'mds_field_output_after', $output, $field, $post_id );
		}
		
		// Apply custom popup replacements filter for extensions
		$replacements = apply_filters( 'mds_popup_custom_replacements', $replacements, $post_id );
		
		// Apply the additional replacements
		foreach ( $replacements as $search => $replace ) {
			$output = str_replace( $search, $replace, $output );
		}

		if ( $return ) {
			return $output;
		}

		echo $output;

		return null;
	}

	public static function store_view( $data ): void {
		global $wpdb;
		
		$ADVANCED_VIEW_COUNT = Options::get_option( 'advanced-view-count' );
		if ( $ADVANCED_VIEW_COUNT == 'YES' ) {
			require_once MDS_CORE_PATH . 'include/ads.inc.php';

			$date = current_time( 'Y-m-d' );
			$sql  = $wpdb->prepare( "UPDATE " . MDS_DB_PREFIX . "views SET views = views + 1 WHERE banner_id = %d AND `date` = %s AND `block_id` = %d", intval( $data['bid'] ), $date, intval( $data['block_id'] ) );
			$wpdb->query( $sql );
			$x = $wpdb->rows_affected;

			$mds_pixel_post = get_post( $_POST['aid'] );

			if ( ! empty( $mds_pixel_post ) ) {
				$user_id            = $mds_pixel_post->post_author;
				$view_count         = intval( get_user_meta( $user_id, MDS_PREFIX . 'view_count', true ) );
				$update_click_count = $view_count + 1;
				update_user_meta( $user_id, MDS_PREFIX . 'view_count', $update_click_count );

				if ( ! $x ) {
					$wpdb->insert(
						MDS_DB_PREFIX . 'views',
						array(
							'banner_id' => intval( $data['bid'] ),
							'date'      => $date,
							'views'     => 1,
							'block_id'  => intval( $data['block_id'] ),
							'user_id'   => intval( $user_id )
						),
						array( '%d', '%s', '%d', '%d', '%d' )
					);
				}
			}
		}

		$sql = $wpdb->prepare( "UPDATE `" . MDS_DB_PREFIX . "blocks` SET `view_count` = `view_count` + 1 WHERE `block_id` = %d AND `banner_id` = %d", intval( $data['block_id'] ), intval( $data['bid'] ) );
		$wpdb->query( $sql );
	}

	public static function store_click( $data ): void {
		$ADVANCED_CLICK_COUNT = Options::get_option( 'advanced-click-count' );
		if ( $ADVANCED_CLICK_COUNT == 'YES' ) {
			require_once MDS_CORE_PATH . 'include/ads.inc.php';

			global $wpdb;

			$date         = current_time( 'Y-m-d' );
			$sql          = "UPDATE `" . MDS_DB_PREFIX . "clicks` SET `clicks` = `clicks` + 1 WHERE `banner_id`=%d AND `date`=%s AND `block_id`=%d";
			$prepared_sql = $wpdb->prepare( $sql, intval( $data['bid'] ), $date, intval( $data['block_id'] ) );
			$wpdb->query( $prepared_sql );
			$x = $wpdb->rows_affected;

			$sql          = "SELECT * FROM `" . MDS_DB_PREFIX . "blocks` WHERE `ad_id`=%d AND `block_id`=%d;";
			$prepared_sql = $wpdb->prepare( $sql, intval( $_POST['aid'] ), intval( $data['block_id'] ) );
			$row          = $wpdb->get_row( $prepared_sql, ARRAY_A );

			$user_id            = intval( $row['user_id'] );
			$click_count        = intval( get_user_meta( $user_id, MDS_PREFIX . 'click_count', true ) );
			$update_click_count = $click_count + 1;
			update_user_meta( $user_id, MDS_PREFIX . 'click_count', $update_click_count );

			if ( ! $x ) {
				$wpdb->insert(
					MDS_DB_PREFIX . 'clicks',
					array(
						'banner_id' => intval( $data['bid'] ),
						'date'      => $date,
						'clicks'    => 1,
						'block_id'  => intval( $data['block_id'] ),
						'user_id'   => intval( $row['user_id'] )
					),
					array( '%d', '%s', '%d', '%d', '%d' )
				);
			}
		}

		$sql = $wpdb->prepare( "UPDATE `" . MDS_DB_PREFIX . "blocks` SET `click_count` = `click_count` + 1 WHERE `block_id` = %d AND banner_id = %d", intval( $data['block_id'] ), intval( $data['bid'] ) );
		$wpdb->query( $sql );
	}

	public static function show_list(): void {
		require_once MDS_CORE_PATH . 'include/ads.inc.php';
		global $wpdb;

		?>
        <div class="mds-container list-container">
            <div class="list">
                <div class="table-row header">
                    <div class="list-heading"><?php Language::out( 'Date of Purchase' ); ?></div>
                    <div class="list-heading"><?php Language::out( 'Ads(s)' ); ?></div>
                    <div class="list-heading"><?php Language::out( 'Pixels' ); ?></div>
                </div>
				<?php
				$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners ORDER BY banner_id";
				$banners = $wpdb->get_results( $sql, ARRAY_A );
				foreach ( $banners as $banner ) {
					?>
                    <div class="table-row header">
                        <div class="list-heading" style="width:100%;"><?php echo esc_html( $banner['name'] ); ?></div>
                    </div>
					<?php
					//TODO: add option to order by other columns
					$sql = $wpdb->prepare( "SELECT *, MAX(order_date) as max_date, sum(quantity) AS pixels FROM " . MDS_DB_PREFIX . "orders WHERE status='completed' AND approved='Y' AND published='Y' AND banner_id=%d GROUP BY user_id, banner_id, order_id ORDER BY pixels DESC", intval( $banner['banner_id'] ) );
					$results = $wpdb->get_results( $sql, ARRAY_A );
					foreach ( $results as $row ) {
						?>
                        <div class="table-row">
                            <div class="list-cell">
								<?php echo esc_html( get_date_from_gmt( $row['max_date'] ) ); ?>
                            </div>
                            <div class="list-cell">
								<?php

								if ( ! isset( $row['ad_id'] ) ) {
									continue;
								}

								$blocks   = explode( ',', $row['blocks'] );
								$block_id = $blocks[0];

								$ALT_TEXT = carbon_get_post_meta( $row['ad_id'], MDS_PREFIX . 'text' );
								$ALT_TEXT = str_replace( [ "'", '"' ], "", $ALT_TEXT ?? '' );

								$url = carbon_get_post_meta( $row['ad_id'], MDS_PREFIX . 'url' );

								$data_values = array(
									'aid'       => $row['ad_id'],
									'block_id'  => $block_id,
									'banner_id' => $banner['banner_id'],
									'alt_text'  => $ALT_TEXT,
									'url'       => $url ?: "",
								);

								echo '<br /><a target="_blank" data-data="' . htmlspecialchars( json_encode( $data_values, JSON_HEX_QUOT | JSON_HEX_APOS ), ENT_QUOTES, 'UTF-8' ) . '" data-alt-text="' . esc_attr( $ALT_TEXT ) . '" class="list-link" href="' . esc_url( $data_values['url'] ) . '">' . esc_html( $ALT_TEXT ) . '</a>';

								?>
                            </div>
                            <div class="list-cell">
								<?php echo intval( $row['pixels'] ); ?>
                            </div>
                        </div>
						<?php
					}
				}
				?>
                <div class="table-row header">
                    <div class="list-heading"><?php Language::out( 'Date of Purchase' ); ?></div>
                    <div class="list-heading"><?php Language::out( 'Ads(s)' ); ?></div>
                    <div class="list-heading"><?php Language::out( 'Pixels' ); ?></div>
                </div>
            </div>
        </div>
		<?php
	}
}
