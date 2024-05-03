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

/** @noinspection PhpUndefinedConstantInspection */

namespace MillionDollarScript\Classes\Ajax;

use MillionDollarScript\Classes\Data\Config;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\Forms\FormFields;
use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Orders\Orders;
use MillionDollarScript\Classes\System\Utility;

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
						require_once MDS_CORE_PATH . 'users/make_selection.php';
						wp_die();
					default:
						break;
				}
			}
		}

		wp_die();
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

			$bgstyle = "";
			if ( ! empty( $banner_data['G_BGCOLOR'] ) ) {
				$bgstyle = ' style="background-color:' . $banner_data['G_BGCOLOR'] . ';"';
			}
			?>
            <div class="mds-container">
                <div class="grid-container <?php echo $container; ?>"<?php echo $bgstyle ?>>
                    <div class='grid-inner' id='<?php echo $container; ?>'>
						<?php include_once( $map_file ); ?>
                        <img id="theimage" src="<?php echo $BANNER_URL; ?>grid<?php echo $BID; ?>.<?php echo $ext; ?>?v=<?php echo filemtime( $BANNER_PATH . "grid" . $BID . ".$ext" ); ?>"
                             width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>" height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>" border="0"
                             usemap="#main" alt=""/>
                        <div class="tooltip-source">
                            <img src="<?php echo esc_url( MDS_CORE_URL ); ?>images/periods.gif" alt=""/>
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
		global $f2;

		$BID = $f2->bid();

		$banner_data = load_banner_constants( $BID );

		$sql = "select count(*) AS COUNT FROM " . MDS_DB_PREFIX . "blocks where status='sold' and banner_id='$BID' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$row = mysqli_fetch_array( $result );

		$STATS_DISPLAY_MODE = Config::get( 'STATS_DISPLAY_MODE' );

		if ( $STATS_DISPLAY_MODE == 'BLOCKS' ) {
			$sold = $row['COUNT'];
		} else {
			$sold = $row['COUNT'] * ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
		}

		$sql = "select count(*) AS COUNT FROM " . MDS_DB_PREFIX . "blocks where status='nfs' and banner_id='$BID' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$row = mysqli_fetch_array( $result );

		$STATS_DISPLAY_MODE = Config::get( 'STATS_DISPLAY_MODE' );

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
				$value     = '<img class="mds-popup-image" src="' . esc_url( $image_url ) . '" alt="">';
			}

			$output = str_replace( '%' . $field_name . '%', $value, $output );

            $allowed_tags = Language::allowed_html();
            $allowed_tags['div']['data-mds-params'] = true;
			$output = wp_kses( $output, $allowed_tags, Language::allowed_protocols() );

			$output = apply_filters( 'mds_field_output_after', $output, $field, $post_id );
		}

		if ( $return ) {
			return $output;
		}

		echo $output;

		return null;
	}

	public static function store_view( $data ): void {
		$ADVANCED_VIEW_COUNT = Config::get( 'ADVANCED_VIEW_COUNT' );
		if ( $ADVANCED_VIEW_COUNT == 'YES' ) {
			require_once MDS_CORE_PATH . 'include/ads.inc.php';

			$date = current_time( 'Y-m-d' );
			$sql  = "UPDATE " . MDS_DB_PREFIX . "views set views = views + 1 where banner_id='" . intval( $data['bid'] ) . "' AND `date`='$date' AND `block_id`=" . intval( $data['block_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
			$x = @mysqli_affected_rows( $GLOBALS['connection'] );

			$mds_pixel_post = get_post( $_POST['aid'] );

			if ( ! empty( $mds_pixel_post ) ) {
				$user_id            = $mds_pixel_post->post_author;
				$view_count         = intval( get_user_meta( $user_id, MDS_PREFIX . 'view_count', true ) );
				$update_click_count = $view_count + 1;
				update_user_meta( $user_id, MDS_PREFIX . 'view_count', $update_click_count );

				if ( ! $x ) {
					$sql = "INSERT INTO `" . MDS_DB_PREFIX . "views` (`banner_id`, `date`, `views`, `block_id`, `user_id`) VALUES(" . intval( $data['bid'] ) . ", '$date', 1, " . intval( $data['block_id'] ) . ", " . intval( $user_id ) . ")";
					@mysqli_query( $GLOBALS['connection'], $sql );
				}
			}
		}

		$sql = "UPDATE `" . MDS_DB_PREFIX . "blocks` SET `view_count` = `view_count` + 1 where `block_id`=" . intval( $data['block_id'] ) . " AND `banner_id`=" . intval( $data['bid'] );
		mysqli_query( $GLOBALS['connection'], $sql );
	}

	public static function store_click( $data ): void {
		$ADVANCED_CLICK_COUNT = Config::get( 'ADVANCED_CLICK_COUNT' );
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
				$sql = "INSERT INTO `" . MDS_DB_PREFIX . "clicks` (`banner_id`, `date`, `clicks`, `block_id`, `user_id`) VALUES(" . intval( $data['bid'] ) . ", '$date', 1, " . intval( $data['block_id'] ) . ", " . intval( $row['user_id'] ) . ") ";
				@mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
			}
		}

		$sql = "UPDATE `" . MDS_DB_PREFIX . "blocks` SET `click_count` = `click_count` + 1 where `block_id`=" . intval( $data['block_id'] ) . " AND banner_id=" . intval( $data['bid'] );
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	}

	public
	static function show_list(): void {
		require_once MDS_CORE_PATH . 'include/ads.inc.php';

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
				$banners = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
				while ( $banner = mysqli_fetch_array( $banners ) ) {
					?>
                    <div class="table-row header">
                        <div class="list-heading" style="width:100%;"><?php echo esc_html( $banner['name'] ); ?></div>
                    </div>
					<?php
					//TODO: add option to order by other columns
					$sql = "SELECT *, MAX(order_date) as max_date, sum(quantity) AS pixels FROM " . MDS_DB_PREFIX . "orders where status='completed' AND approved='Y' AND published='Y' AND banner_id='" . intval( $banner['banner_id'] ) . "' GROUP BY user_id, banner_id, order_id order by pixels desc ";
					$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
					while ( $row = mysqli_fetch_array( $result ) ) {
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
								$ALT_TEXT = str_replace( [ "'", '"' ], "", $ALT_TEXT );

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
