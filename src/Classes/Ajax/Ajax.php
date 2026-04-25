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
use MillionDollarScript\Classes\System\Request;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\System\Url;
use function esc_attr;
use function esc_html;
use function esc_url;
use function sanitize_html_class;
use function sanitize_key;
use function sanitize_text_field;
use function wp_json_encode;
use function wp_strip_all_tags;
use function wp_unslash;

defined( 'ABSPATH' ) or exit;

class Ajax {

	private static array $request = [];
	private static array $post = [];
	private static array $query = [];

	/**
	 * Init MDS AJAX..
	 *
	 * @return void
	 */
	public static function init(): void {

		// MDS AJAX
		add_action( 'wp_ajax_mds_ajax', [ __CLASS__, 'mds_ajax' ] );
		add_action( 'wp_ajax_nopriv_mds_ajax', [ __CLASS__, 'mds_ajax' ] );
		add_action( 'wp_ajax_mds_ajax_grid', [ __CLASS__, 'mds_ajax' ] );
		add_action( 'wp_ajax_nopriv_mds_ajax_grid', [ __CLASS__, 'mds_ajax' ] );
	}

	/**
	 * Ajax response for MDS.
	 *
	 * @return void
	 */
	public static function mds_ajax(): void {
		check_ajax_referer( 'mds_nonce', 'mds_nonce' );

		self::$post    = Request::sanitize_scalar_array( is_array( $_POST ) ? $_POST : [] );
		self::$query   = Request::json_payload( self::$post, 'get_params' );
		self::$request = array_merge( self::$query, self::$post );

		$type = Request::key( self::$post, 'type' );

		switch ( $type ) {
			case "grid":
			case "show_grid":
				self::show_grid( self::$request );
				wp_die();
			case "stats":
			case "show_stats":
				self::show_stats( self::$request );
				wp_die();
			case "ga":
				$tracking = self::get_valid_tracking_data( self::$request );
				if ( empty( $tracking ) ) {
					wp_die( '', '', [ 'response' => 400 ] );
				}
				self::get_ad( $tracking['aid'] );
				self::store_view( $tracking );
				wp_die();
			case "click":
				$tracking = self::get_valid_tracking_data( self::$request );
				if ( empty( $tracking ) ) {
					wp_die( '', '', [ 'response' => 400 ] );
				}
				self::store_click( $tracking );
				wp_die();
			case "list":
			case "show_list":
				self::show_list( self::$request );
				wp_die();
			case "confirm-order":
				self::load_legacy_user_handler( 'confirm_order.php' );
				wp_die();
			case "users":
			case "account":
			case "publish":
			case "history":
			case "manage":
				self::load_legacy_user_handler( 'manage.php' );
				wp_die();
			case "no-orders":
				Orders::no_orders();
				wp_die();
			case "order":
				Orders::order_screen();
				wp_die();
			case "checkout":
			case "payment":
				self::load_legacy_user_handler( 'payment.php' );
				wp_die();
			case "thank-you":
				self::load_legacy_user_handler( 'thanks.php' );
				wp_die();
			case "upload":
				self::load_legacy_user_handler( 'upload.php' );
				wp_die();
			case "write-ad":
				self::load_legacy_user_handler( 'write_ad.php' );
				wp_die();
			case "make-selection":
				// Logs::log( 'MDS AJAX make-selection request routed through Orders::persist_selection handler.' );
				// Logging suppressed to reduce noise while the legacy shim is still active.
				self::load_legacy_user_handler( 'make_selection.php' );
				wp_die();
			case "validate-selection":
				self::handle_validate_selection( self::$request );
				wp_die();
			default:
				break;
		}

		wp_die();
	}

	private static function load_legacy_user_handler( string $file ): void {
		$previous_get     = $_GET;
		$previous_post    = $_POST;
		$previous_request = $_REQUEST;

		try {
			$_GET     = self::$query;
			$_POST    = self::$post;
			$_REQUEST = self::$request;

			require_once MDS_CORE_PATH . 'users/' . $file;
		} finally {
			$_GET     = $previous_get;
			$_POST    = $previous_post;
			$_REQUEST = $previous_request;
		}
	}

	private static function get_valid_tracking_data( array $request ): ?array {
		global $wpdb;

		$aid      = Request::positive_int( $request, 'aid' );
		$bid      = Request::positive_int( $request, 'bid' );
		$block_id = Request::positive_int( $request, 'block_id' );

		if ( $aid <= 0 || $bid <= 0 || $block_id <= 0 ) {
			return null;
		}

		$sql = $wpdb->prepare(
			"SELECT block_id, banner_id, ad_id, user_id FROM `" . MDS_DB_PREFIX . "blocks` WHERE `ad_id` = %d AND `banner_id` = %d AND `block_id` = %d LIMIT 1",
			$aid,
			$bid,
			$block_id
		);
		$row = $wpdb->get_row( $sql, ARRAY_A );

		if ( empty( $row ) ) {
			return null;
		}

		return [
			'aid'      => $aid,
			'bid'      => $bid,
			'block_id' => $block_id,
			'user_id'  => intval( $row['user_id'] ?? 0 ),
		];
	}

	private static function resolve_bid( array $request, bool $allow_all = false ): int|string {
		global $f2;

		$raw_bid = Request::scalar( $request['BID'] ?? '' );
		if ( $allow_all && 'all' === $raw_bid ) {
			return 'all';
		}

		$bid = Request::positive_int( $request, 'BID' );
		if ( $bid > 0 ) {
			return $bid;
		}

		$aid = Request::positive_int( $request, 'aid' );
		if ( $aid > 0 ) {
			$pixel = get_post( $aid );
			if ( ! empty( $pixel ) ) {
				return intval( carbon_get_post_meta( $aid, MDS_PREFIX . 'grid' ) );
			}
		}

		return is_object( $f2 ) && method_exists( $f2, 'get_default_grid' ) ? intval( $f2->get_default_grid() ) : 0;
	}

	private static function handle_validate_selection( array $request ): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [
				'messages' => [ Language::get( 'You must be logged in to continue.' ) ],
			], 401 );
		}

		$BID = Request::positive_int( $request, 'BID' );
		if ( $BID <= 0 ) {
			wp_send_json_error( [ 'messages' => [ Language::get( 'Invalid grid.' ) ] ], 400 );
		}

		$banner_data = load_banner_constants( $BID );
		if ( empty( $banner_data ) ) {
			wp_send_json_error( [ 'messages' => [ Language::get( 'Invalid grid configuration.' ) ] ], 400 );
		}

		$mode       = Request::mode( $request, 'mode', [ 'ADJACENT', 'RECTANGLE', 'NONE' ] );
		$raw_blocks = Request::scalar( $request['blocks'] ?? '' );
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

	public static function show_grid( ?array $request = null ): void {
		$BID = self::resolve_bid( $request ?? self::$request );

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

	public static function show_stats( ?array $request = null ): void {
		global $wpdb;

		$BID = self::resolve_bid( $request ?? self::$request );

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
			$post_id = Request::positive_int( self::$request, 'aid' );
		}
		$post_id = intval( $post_id );
		if ( $post_id <= 0 ) {
			return null;
		}
		$fields = FormFields::get_fields();

		add_filter( 'wp_kses_allowed_html', [ '\MillionDollarScript\Classes\System\Functions', 'mds_allowed_mds_params' ] );
		$output = apply_filters( 'the_content', Options::get_option( 'popup-template' ) );
		remove_filter( 'wp_kses_allowed_html', [ '\MillionDollarScript\Classes\System\Functions', 'mds_allowed_mds_params' ] );

		// Allow extensions to add their own custom placeholders and values
		$custom_replacements = [];
		$custom_replacements = apply_filters( 'mds_popup_custom_replacements', $custom_replacements, $post_id );

		if ( ! empty( $custom_replacements ) && is_array( $custom_replacements ) ) {
			foreach ( $custom_replacements as $placeholder => $value ) {
				$output = str_replace( (string) $placeholder, (string) $value, $output );
			}
		}

		foreach ( $fields as $field ) {
			$field_name = str_replace( MDS_PREFIX, '', $field->get_base_name() );
			$value      = carbon_get_post_meta( $post_id, $field->get_base_name() );

			$value = apply_filters( 'mds_field_output_before', $value, $field, $post_id );

			if ( $field_name === 'text' ) {
				if ( $field->get_type() === 'rich_text' ) {
					$value = FormFields::sanitize_rich_text_value( (string) $value );
					$value = '<div class="mds-popup-text mds-popup-text--rich">' . $value . '</div>';
				} else {
					$value = FormFields::sanitize_plain_text_value( (string) $value, $field->get_base_name() );
					$value = '<span class="mds-popup-text">' . esc_html( $value ) . '</span>';
				}
			} else if ( $field_name === 'url' ) {
				$value = Url::advertiser_url( $value );
				$value = '' !== $value ? '<a class="mds-popup-url" target="_blank" href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>' : '';
			} else if ( $field_name === 'image' ) {
				$image_id  = carbon_get_post_meta( $post_id, $field->get_base_name() );
				$image_url = wp_get_attachment_url( $image_id );
				$max_image_size = Options::get_option( 'max-image-size', 332 );
				$value     = '<img class="mds-popup-image" src="' . esc_url( $image_url ) . '" alt="" style="max-width:' . $max_image_size . 'px;max-height:' . $max_image_size . 'px;">';
			}

			$custom_replacements['%' . $field_name . '%'] = $value;
			$output = str_replace( '%' . $field_name . '%', $value, $output );

			$allowed_tags                           = Language::allowed_html();
			$allowed_tags['div']['data-mds-params'] = true;
			$output                                 = wp_kses( $output, $allowed_tags, Language::allowed_protocols() );

			$output = apply_filters( 'mds_field_output_after', $output, $field, $post_id );
		}

		if ( $return ) {
			return $output;
		}

		echo $output;

		return null;
	}

	public static function store_view( $data ): void {
		global $wpdb;

		$bid      = intval( $data['bid'] ?? 0 );
		$block_id = intval( $data['block_id'] ?? 0 );
		$aid      = intval( $data['aid'] ?? 0 );
		$user_id  = intval( $data['user_id'] ?? 0 );

		if ( $bid <= 0 || $block_id <= 0 || $aid <= 0 ) {
			return;
		}
		
		$ADVANCED_VIEW_COUNT = Options::get_option( 'advanced-view-count' );
		if ( $ADVANCED_VIEW_COUNT == 'YES' ) {
			require_once MDS_CORE_PATH . 'include/ads.inc.php';

			$date = current_time( 'Y-m-d' );
			$sql  = $wpdb->prepare( "UPDATE " . MDS_DB_PREFIX . "views SET views = views + 1 WHERE banner_id = %d AND `date` = %s AND `block_id` = %d", $bid, $date, $block_id );
			$wpdb->query( $sql );
			$x = $wpdb->rows_affected;

			$mds_pixel_post = get_post( $aid );

			if ( ! empty( $mds_pixel_post ) ) {
				$user_id = $user_id > 0 ? $user_id : intval( $mds_pixel_post->post_author );
				if ( $user_id > 0 ) {
					$view_count         = intval( get_user_meta( $user_id, MDS_PREFIX . 'view_count', true ) );
					$update_click_count = $view_count + 1;
					update_user_meta( $user_id, MDS_PREFIX . 'view_count', $update_click_count );
				}

				if ( ! $x ) {
					$wpdb->insert(
					MDS_DB_PREFIX . 'views',
					array(
							'banner_id' => $bid,
							'date'      => $date,
							'views'     => 1,
							'block_id'  => $block_id,
							'user_id'   => intval( $user_id )
						),
						array( '%d', '%s', '%d', '%d', '%d' )
					);
				}
			}
		}

		$sql = $wpdb->prepare( "UPDATE `" . MDS_DB_PREFIX . "blocks` SET `view_count` = `view_count` + 1 WHERE `block_id` = %d AND `banner_id` = %d AND `ad_id` = %d", $block_id, $bid, $aid );
		$wpdb->query( $sql );
	}

	public static function store_click( $data ): void {
		global $wpdb;

		$bid      = intval( $data['bid'] ?? 0 );
		$block_id = intval( $data['block_id'] ?? 0 );
		$aid      = intval( $data['aid'] ?? 0 );
		$user_id  = intval( $data['user_id'] ?? 0 );

		if ( $bid <= 0 || $block_id <= 0 || $aid <= 0 ) {
			return;
		}

		$ADVANCED_CLICK_COUNT = Options::get_option( 'advanced-click-count' );
		if ( $ADVANCED_CLICK_COUNT == 'YES' ) {
			require_once MDS_CORE_PATH . 'include/ads.inc.php';

			$date         = current_time( 'Y-m-d' );
			$sql          = "UPDATE `" . MDS_DB_PREFIX . "clicks` SET `clicks` = `clicks` + 1 WHERE `banner_id`=%d AND `date`=%s AND `block_id`=%d";
			$prepared_sql = $wpdb->prepare( $sql, $bid, $date, $block_id );
			$wpdb->query( $prepared_sql );
			$x = $wpdb->rows_affected;

			$sql          = "SELECT * FROM `" . MDS_DB_PREFIX . "blocks` WHERE `ad_id`=%d AND `banner_id`=%d AND `block_id`=%d;";
			$prepared_sql = $wpdb->prepare( $sql, $aid, $bid, $block_id );
			$row          = $wpdb->get_row( $prepared_sql, ARRAY_A );

			$user_id = $user_id > 0 ? $user_id : intval( $row['user_id'] ?? 0 );
			if ( $user_id > 0 ) {
				$click_count        = intval( get_user_meta( $user_id, MDS_PREFIX . 'click_count', true ) );
				$update_click_count = $click_count + 1;
				update_user_meta( $user_id, MDS_PREFIX . 'click_count', $update_click_count );
			}

			if ( ! $x ) {
				$wpdb->insert(
					MDS_DB_PREFIX . 'clicks',
					array(
						'banner_id' => $bid,
						'date'      => $date,
						'clicks'    => 1,
						'block_id'  => $block_id,
						'user_id'   => $user_id
					),
					array( '%d', '%s', '%d', '%d', '%d' )
				);
			}
		}

		$sql = $wpdb->prepare( "UPDATE `" . MDS_DB_PREFIX . "blocks` SET `click_count` = `click_count` + 1 WHERE `block_id` = %d AND banner_id = %d AND `ad_id` = %d", $block_id, $bid, $aid );
		$wpdb->query( $sql );
	}

	public static function show_list( ?array $request = null ): void {
		require_once MDS_CORE_PATH . 'include/ads.inc.php';
		global $wpdb;

		$request              = $request ?? self::$request;
		$requested_bid        = Request::positive_int( $request, 'BID' );
		$is_ajax_request      = wp_doing_ajax();
		$explicit_bid_request = isset( self::$query['BID'] ) || isset( self::$post['BID'] ) || isset( $request['BID'] );
		$list_show_all        = Request::bool( $request, 'mds_list_show_all' );
		$explicit_id          = Request::bool( $request, 'mds_explicit_id' );
		$shortcode_id         = Request::positive_int( $request, 'mds_shortcode_id' );
		$requested_banner_ids = [];

		if ( $requested_bid > 0 && ( $is_ajax_request || $explicit_bid_request ) && ! $list_show_all ) {
			$requested_banner_ids[] = $requested_bid;
		}

		$context = [
			'requested_bid'         => $requested_bid,
			'request'               => $request,
			'is_ajax_request'       => $is_ajax_request,
			'explicit_bid_request'  => $explicit_bid_request,
			'list_show_all'         => $list_show_all,
			'explicit_id'           => $explicit_id,
			'shortcode_id'          => $shortcode_id,
		];

		// Allow extensions to adjust which banners appear before the query runs.
		$requested_banner_ids = apply_filters( 'mds_list_requested_banner_ids', $requested_banner_ids, $context );
		$requested_banner_ids = array_filter( array_map( 'absint', (array) $requested_banner_ids ) );
		$requested_banner_ids = array_values( array_unique( $requested_banner_ids ) );

		$context['requested_banner_ids'] = $requested_banner_ids;

		$container_attributes = [
			'class' => 'mds-container list-container',
		];
		if ( $requested_bid > 0 && ( $is_ajax_request || $explicit_bid_request ) && ! $list_show_all ) {
			$container_attributes['data-bid'] = (string) $requested_bid;
		}
		if ( ! empty( $requested_banner_ids ) ) {
			$container_attributes['data-requested-bids'] = implode( ',', $requested_banner_ids );
		}
		if ( $list_show_all ) {
			$container_attributes['data-list-show-all'] = '1';
		}
		$container_attributes = apply_filters( 'mds_list_container_attributes', $container_attributes, $context );

		$list_attributes = apply_filters( 'mds_list_wrapper_attributes', [ 'class' => 'list' ], $context );

		$columns = apply_filters( 'mds_list_columns', self::get_default_list_columns(), $context );
		if ( empty( $columns ) || ! is_array( $columns ) ) {
			$columns = self::get_default_list_columns();
		}

		$column_count = self::count_list_columns( $columns );
		$context['column_count'] = $column_count;

		$grid_tracks = self::build_list_grid_tracks( $columns, $context );
		$grid_tracks = apply_filters( 'mds_list_grid_tracks', $grid_tracks, $columns, $context );
		$grid_template = implode( ' ', array_filter( array_map( 'trim', $grid_tracks ) ) );
		$grid_template = apply_filters( 'mds_list_grid_template', $grid_template, $columns, $context );

		if ( '' !== $grid_template ) {
			$list_attributes = self::append_inline_style_declaration( $list_attributes, '--mds-list-grid-template:' . $grid_template . ';' );
		}

		$list_attributes['data-column-count'] = (string) $column_count;
		$context['grid_tracks']             = $grid_tracks;
		$context['grid_template']           = $grid_template;

		$heading_cells = [];
		foreach ( $columns as $column ) {
			$heading_cells[] = apply_filters(
				'mds_list_heading_cell',
				[
					'content'    => $column['label'],
					'attributes' => $column['heading_attributes'] ?? [ 'class' => 'list-heading' ],
					'column'     => $column,
				],
				$column,
				$context
			);
		}
		$heading_cells          = apply_filters( 'mds_list_heading_cells', $heading_cells, $columns, $context );
		$heading_row_attributes = apply_filters(
			'mds_list_heading_row_attributes',
			[ 'class' => 'table-row header' ],
			$columns,
			$context
		);

		$banners_sql = 'SELECT * FROM ' . MDS_DB_PREFIX . 'banners ORDER BY banner_id';
		if ( ! empty( $requested_banner_ids ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $requested_banner_ids ), '%d' ) );
			$sql          = 'SELECT * FROM ' . MDS_DB_PREFIX . 'banners WHERE banner_id IN ( ' . $placeholders . ' ) ORDER BY banner_id';
			$banners_sql  = $wpdb->prepare( $sql, $requested_banner_ids );
		}
		$banners_sql = apply_filters( 'mds_list_banners_sql', $banners_sql, $context );

		$banners = $wpdb->get_results( $banners_sql, ARRAY_A );
		$banners = apply_filters( 'mds_list_banners', $banners, $context );

		$override_markup = apply_filters(
			'mds_list_markup',
			null,
			[
				'context'              => $context,
				'columns'              => $columns,
				'banners'              => $banners,
				'heading_cells'        => $heading_cells,
				'container_attributes' => $container_attributes,
				'list_attributes'      => $list_attributes,
			]
		);

		if ( null !== $override_markup ) {
			echo $override_markup;
			return;
		}

		do_action( 'mds_before_list', $context, $columns, $banners );

		echo '<div' . self::stringify_attributes( $container_attributes ) . '>';
		do_action( 'mds_before_list_inner', $context, $columns, $banners );

		echo '<div class="mds-list-wrapper">';
		echo '<div' . self::stringify_attributes( $list_attributes ) . '>';

		self::render_row( $heading_cells, $heading_row_attributes );

		foreach ( $banners as $banner ) {
			$banner_context = array_merge( $context, [ 'banner' => $banner ] );

			$banner_markup = apply_filters( 'mds_list_banner_markup', null, $banner_context, $columns );
			if ( null !== $banner_markup ) {
				echo $banner_markup;
				continue;
			}

			$banner_heading_row_attributes = apply_filters(
				'mds_list_banner_heading_row_attributes',
				[ 'class' => [ 'header', 'mds-banner-row' ] ],
				$banner_context,
				$columns
			);

			if ( $column_count > 0 && ! isset( $banner_heading_row_attributes['data-column-count'] ) ) {
				$banner_heading_row_attributes['data-column-count'] = (string) $column_count;
			}

			$banner_heading_cell = apply_filters(
				'mds_list_banner_heading_cell',
				[
					'content'    => esc_html( $banner['name'] ),
					'attributes' => [
						'class' => [ 'list-heading', 'mds-banner-heading' ],
					],
				],
				$banner_context,
				$columns
			);

			$banner_heading_markup = apply_filters( 'mds_list_banner_heading_markup', null, $banner_heading_row_attributes, $banner_heading_cell, $banner_context, $columns );
			if ( null !== $banner_heading_markup ) {
				echo $banner_heading_markup;
			} else {
				echo '<div' . self::stringify_attributes( $banner_heading_row_attributes ) . '>';
				echo '<div' . self::stringify_attributes( $banner_heading_cell['attributes'] ?? [] ) . '>' . $banner_heading_cell['content'] . '</div>';
				echo '</div>';
			}

			$orders_sql = $wpdb->prepare(
				"SELECT *, MAX(order_date) AS max_date, SUM(quantity) AS pixels FROM " . MDS_DB_PREFIX . "orders WHERE status = 'completed' AND approved = 'Y' AND published = 'Y' AND banner_id = %d GROUP BY user_id, banner_id, order_id ORDER BY pixels DESC",
				intval( $banner['banner_id'] )
			);
			$orders_sql = apply_filters( 'mds_list_orders_sql', $orders_sql, $banner_context, $columns );

			$rows = $wpdb->get_results( $orders_sql, ARRAY_A );
			$rows = apply_filters( 'mds_list_rows', $rows, $banner_context, $columns );

			if ( empty( $rows ) ) {
				do_action( 'mds_list_no_rows', $banner_context, $columns );
				continue;
			}

			foreach ( $rows as $row ) {
				$row_context = self::build_list_row_context( $row, $banner, $columns, $context );
				$row_context = apply_filters( 'mds_list_row_context', $row_context, $row, $banner, $columns );

				if ( ! empty( $row_context['skip'] ) ) {
					continue;
				}

				$row_cells = self::build_list_row_cells( $columns, $row_context );
				$row_cells = apply_filters( 'mds_list_row_cells', $row_cells, $row_context, $columns );

				$row_attributes = apply_filters(
					'mds_list_row_attributes',
					[ 'class' => 'table-row' ],
					$row_context,
					$columns
				);

				$row_markup = apply_filters(
					'mds_list_row_markup',
						null,
						$row_context,
						$columns,
						$row_cells,
						$row_attributes
				);

				if ( null !== $row_markup ) {
					echo $row_markup;
					continue;
				}

				self::render_row( $row_cells, $row_attributes );
			}

			do_action( 'mds_after_list_banner_rows', $banner_context, $columns );
		}

		$render_footer_heading = apply_filters( 'mds_list_render_footer_heading', true, $context, $columns );

		if ( $render_footer_heading ) {
			self::render_row( $heading_cells, $heading_row_attributes );
		}

		echo '</div>';
		echo '</div>';
		do_action( 'mds_after_list_inner', $context, $columns, $banners );

		echo '</div>';
		do_action( 'mds_after_list', $context, $columns, $banners );
	}

	private static function get_default_list_columns(): array {
		return [
			[
				'key'               => 'date',
				'label'             => Language::get( 'Date of Purchase' ),
				'render_callback'   => [ __CLASS__, 'render_list_date_cell' ],
				'cell_attributes'   => [ 'class' => 'list-cell' ],
				'heading_attributes' => [ 'class' => 'list-heading' ],
			],
			[
				'key'               => 'ads',
				'label'             => Language::get( 'Popup Text' ),
				'render_callback'   => [ __CLASS__, 'render_list_ads_cell' ],
				'cell_attributes'   => [ 'class' => 'list-cell' ],
				'heading_attributes' => [ 'class' => 'list-heading' ],
			],
			[
				'key'               => 'pixels',
				'label'             => Language::get( 'Pixels' ),
				'render_callback'   => [ __CLASS__, 'render_list_pixels_cell' ],
				'cell_attributes'   => [ 'class' => 'list-cell' ],
				'heading_attributes' => [ 'class' => 'list-heading' ],
			],
		];
	}

	private static function build_list_row_context( array $row, array $banner, array $columns, array $context ): array {
		$ad_id = isset( $row['ad_id'] ) ? intval( $row['ad_id'] ) : 0;

		if ( $ad_id <= 0 ) {
			return [
				'row'     => $row,
				'banner'  => $banner,
				'columns' => $columns,
				'context' => $context,
				'skip'    => true,
			];
		}

		$blocks = [];
		if ( ! empty( $row['blocks'] ) ) {
			$blocks = array_filter(
				array_map( 'trim', explode( ',', (string) $row['blocks'] ) ),
				static function ( $value ) {
					return $value !== '';
				}
			);
		}
		$first_block = ! empty( $blocks ) ? intval( reset( $blocks ) ) : 0;

		$alt_text      = carbon_get_post_meta( $ad_id, MDS_PREFIX . 'text' ) ?? '';
		$alt_text_trim = wp_strip_all_tags( $alt_text );
		$alt_text_trim = str_replace( [ "'", '"' ], '', $alt_text_trim );

		$url = Url::advertiser_url( carbon_get_post_meta( $ad_id, MDS_PREFIX . 'url' ) ?: '' );

		$data_values = [
			'aid'       => $ad_id,
			'block_id'  => $first_block,
			'banner_id' => intval( $banner['banner_id'] ?? 0 ),
			'alt_text'  => $alt_text_trim,
			'url'       => $url,
		];

		$data_values = apply_filters( 'mds_list_link_data', $data_values, $row, $banner, $context );

		return [
			'row'            => $row,
			'banner'         => $banner,
			'columns'        => $columns,
			'context'        => $context,
			'skip'           => false,
			'ad_id'          => $ad_id,
			'blocks'         => $blocks,
			'first_block'    => $first_block,
			'alt_text_raw'   => $alt_text,
			'alt_text_clean' => $alt_text_trim,
			'url'            => $url,
			'pixels'         => intval( $row['pixels'] ?? 0 ),
			'data_values'   => $data_values,
		];
	}

	private static function build_list_row_cells( array $columns, array $row_context ): array {
		$cells = [];

		foreach ( $columns as $column ) {
			$key = $column['key'] ?? '';

			if ( '' === $key ) {
				continue;
			}

			$cell = [
				'content'    => '',
				'attributes' => $column['cell_attributes'] ?? [ 'class' => 'list-cell' ],
				'column'     => $column,
			];

			if ( isset( $column['render_callback'] ) && is_callable( $column['render_callback'] ) ) {
				$cell['content'] = call_user_func( $column['render_callback'], $row_context, $column );
			} else {
				$value_key       = $column['value_key'] ?? $key;
				$value           = $row_context['row'][ $value_key ] ?? '';
				$cell['content'] = esc_html( (string) $value );
			}

			$cell = apply_filters( 'mds_list_cell', $cell, $column, $row_context );

			$filter_key = sanitize_key( $key );
			$cell       = apply_filters( 'mds_list_cell_' . $filter_key, $cell, $column, $row_context );

			$cells[ $key ] = $cell;
		}

		return $cells;
	}

	private static function render_list_date_cell( array $row_context, array $column ): string {
		$date = $row_context['row']['max_date'] ?? '';

		if ( empty( $date ) ) {
			return '';
		}

		return esc_html( get_date_from_gmt( $date ) );
	}

	private static function render_list_ads_cell( array $row_context, array $column ): string {
		$data_values = $row_context['data_values'] ?? [];
		$json        = wp_json_encode( $data_values, JSON_HEX_QUOT | JSON_HEX_APOS );

		if ( false === $json ) {
			$json = '[]';
		}

		$href = trim( (string) ( $row_context['url'] ?? '' ) );
		if ( '' === $href ) {
			$href = '#';
		}

		$attributes = [
			'target'        => '_blank',
			'class'         => 'list-link',
			'href'          => esc_url( $href ),
			'data-data'     => htmlspecialchars( $json, ENT_QUOTES, 'UTF-8' ),
			'data-alt-text' => $row_context['alt_text_clean'] ?? '',
		];

		$attributes = apply_filters( 'mds_list_link_attributes', $attributes, $row_context, $column );

		if ( empty( $attributes['href'] ) ) {
			$attributes['href'] = '#';
		}

		$link_text = $row_context['alt_text_raw'] ?? '';
		if ( '' === $link_text ) {
			$link_text = $row_context['url'] ?? '';
		}
		$link_text = wp_strip_all_tags( $link_text );

		$link_text = apply_filters( 'mds_list_link_text', $link_text, $row_context, $column );

		return '<br /><a' . self::stringify_attributes( $attributes ) . '>' . esc_html( $link_text ) . '</a>';
	}

	private static function render_list_pixels_cell( array $row_context, array $column ): string {
		return esc_html( (string) intval( $row_context['pixels'] ?? 0 ) );
	}

	private static function count_list_columns( $columns ): int {
		if ( empty( $columns ) || ! is_iterable( $columns ) ) {
			return 1;
		}

		$count = 0;
		foreach ( $columns as $column ) {
			if ( is_array( $column ) ) {
				$count++;
			}
		}

		return max( 1, $count );
	}

	private static function build_list_grid_tracks( array $columns, array $context ): array {
		$tracks = [];

		foreach ( array_values( $columns ) as $index => $column ) {
			$tracks[] = self::resolve_grid_track_value( $column, $index, $columns, $context );
		}

		return $tracks;
	}

	private static function resolve_grid_track_value( array $column, int $index, array $columns, array $context ): string {
		if ( isset( $column['grid_track'] ) ) {
			$track = trim( (string) $column['grid_track'] );
			if ( '' !== $track ) {
				return $track;
			}
		}

		$default_tracks = apply_filters(
			'mds_list_default_grid_tracks',
			[
				0 => 'minmax(180px, 0.25fr)',
				1 => 'minmax(0, 0.55fr)',
			],
			$columns,
			$context
		);

		if ( isset( $default_tracks[ $index ] ) ) {
			return (string) $default_tracks[ $index ];
		}

		return apply_filters( 'mds_list_fallback_grid_track', 'minmax(0, 0.25fr)', $column, $index, $columns, $context );
	}

	private static function append_inline_style_declaration( array $attributes, string $declaration ): array {
		$declaration = trim( $declaration );
		if ( '' === $declaration ) {
			return $attributes;
		}

		if ( isset( $attributes['style'] ) ) {
			if ( is_array( $attributes['style'] ) ) {
				$attributes['style'][] = $declaration;
			} else {
				$attributes['style'] = trim( (string) $attributes['style'] );
				if ( '' !== $attributes['style'] ) {
					$attributes['style'] .= ' ' . $declaration;
				} else {
					$attributes['style'] = $declaration;
				}
			}
		} else {
			$attributes['style'] = $declaration;
		}

		return $attributes;
	}

	private static function render_row( array $cells, array $row_attributes ): void {
		echo '<div' . self::stringify_attributes( $row_attributes ) . '>';

		foreach ( $cells as $cell ) {
			$attributes = [];
			if ( isset( $cell['attributes'] ) && is_array( $cell['attributes'] ) ) {
				$attributes = $cell['attributes'];
			}

			if ( ! isset( $attributes['data-label'] ) && isset( $cell['column']['label'] ) ) {
				$attributes['data-label'] = wp_strip_all_tags( (string) $cell['column']['label'] );
			}

			echo '<div' . self::stringify_attributes( $attributes ) . '>';
			echo $cell['content'];
			echo '</div>';
		}

		echo '</div>';
	}

	private static function stringify_attributes( array $attributes ): string {
		if ( empty( $attributes ) ) {
			return '';
		}

		$parts = [];

		foreach ( $attributes as $key => $value ) {
			if ( null === $value ) {
				continue;
			}

			if ( is_bool( $value ) ) {
				if ( $value ) {
					$parts[] = esc_attr( $key );
				}
				continue;
			}

			if ( 'class' === $key ) {
				if ( is_array( $value ) ) {
					$value = array_filter( array_map( 'sanitize_html_class', $value ) );
					if ( empty( $value ) ) {
						continue;
					}
					$value = implode( ' ', $value );
				} else {
					$raw_classes = preg_split( '/\s+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
					$value       = array_filter( array_map( 'sanitize_html_class', $raw_classes ) );
					if ( empty( $value ) ) {
						continue;
					}
					$value = implode( ' ', $value );
				}
			} elseif ( is_array( $value ) ) {
				$value = implode( ' ', array_map( 'strval', $value ) );
			}

			if ( '' === $value ) {
				continue;
			}

			$parts[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
		}

		if ( empty( $parts ) ) {
			return '';
		}

		return ' ' . implode( ' ', $parts );
	}
}
