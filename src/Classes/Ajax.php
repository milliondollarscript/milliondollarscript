<?php

/**
 * Million Dollar Script Two
 *
 * @version 2.3.6
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

namespace MillionDollarScript\Classes;

use JetBrains\PhpStorm\NoReturn;

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
	#[NoReturn] public static function mds_ajax(): void {
		check_ajax_referer( 'mds_nonce', 'mds_nonce' );

		// Handle POST input
		if ( isset( $_POST ) ) {

			require_once MDS_CORE_PATH . "include/init.php";

			$type = $_POST['type'] ?? null;

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
						self::get_ad( $_POST );
						self::store_view( $_POST );
						wp_die();
					case "click":
						self::store_click( $_POST );
						wp_die();
					case "list":
						self::show_list();
						wp_die();
					case "users":
						self::show_users();
						wp_die();
					default:
						break;
				}

				// Handle WP integration calls
				// if ( WP_ENABLED == "YES" && ! empty( WP_URL ) ) {
				// 	if ( ! isset( $_POST['grid_id'] ) ) {
				// 		die;
				// 	}
				//
				// 	$_REQUEST['BID'] = $_POST['grid_id'];
				//
				// 	switch ( $type ) {
				// 		case "ajax_grid":
				// 			$_REQUEST['type'] = 'grid';
				// 			require_once( BASE_PATH . "/include/mds_ajax.php" );
				// 			$mds_ajax = new Mds_Ajax();
				// 			$mds_ajax->show( 'grid', $data->grid_id, 'grid' );
				// 			break;
				// 		case "ajax_stats":
				// 			$_REQUEST['type'] = 'stats';
				// 			require_once( BASE_PATH . "/include/mds_ajax.php" );
				// 			$mds_ajax = new Mds_Ajax();
				// 			$mds_ajax->show( 'stats', $data->grid_id, 'stats' );
				// 			break;
				// 		case "ajax_list":
				// 			$_REQUEST['type'] = 'list';
				// 			require_once( BASE_PATH . "/include/mds_ajax.php" );
				// 			$mds_ajax = new Mds_Ajax();
				// 			$mds_ajax->show( 'list', $data->grid_id, 'list' );
				// 			break;
				// 		case "ajax_users":
				// 			$_REQUEST['type'] = 'users';
				// 			require_once( BASE_PATH . "/include/mds_ajax.php" );
				// 			$mds_ajax = new Mds_Ajax();
				// 			$mds_ajax->show( 'users', $data->grid_id, 'users' );
				// 			break;
				// 		case "users":
				// 			require_once( BASE_PATH . "/users/index.php" );
				// 			break;
				// 		default:
				// 			break;
				// 	}
				// }

				global $ajax_call;
				$ajax_call = true;
			}
		}

		wp_die();
	}

	// #[NoReturn] public static function no_perms(): void {
	// 	$error = new \WP_Error( '403', __( "Insufficient permissions!", 'milliondollarscript' ) );
	// 	wp_send_json_error( $error );
	// 	wp_die();
	// }
	//
	// public static function get_html(): bool|string {
	// 	ob_start();
	// 	self::html();
	// 	$html = ob_get_contents();
	// 	ob_end_clean();
	//
	// 	return $html;
	// }
	//
	// /**
	//  * Output NFS page HTML.
	//  */
	// public static function html(): void {
	// 	require_once MDS_BASE_PATH . "src/Html/NFS.php";
	// }

	public static function show_grid() {
		global $f2;

		$BID = $f2->bid();

		if ( ! is_numeric( $BID ) ) {
			die;
		}

		$banner_data = load_banner_constants( $BID );

		$BANNER_PATH = \MillionDollarScript\Classes\Utility::get_upload_path() . 'grids/';
		$BANNER_URL  = \MillionDollarScript\Classes\Utility::get_upload_url() . 'grids/';

		$map_file = get_map_file_name( $BID );

		$ext = 'png';
		if ( OUTPUT_JPEG == 'Y' ) {
			$ext = "jpg";
		} else if ( OUTPUT_JPEG == 'N' ) {
			$ext = 'png';
		} else if ( OUTPUT_JPEG == 'GIF' ) {
			$ext = 'gif';
		}

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
                        <img id="theimage" src="<?php echo $BANNER_URL; ?>grid<?php echo $BID; ?>.<?php echo $ext; ?>?v=<?php echo filemtime( $BANNER_PATH . "grid" . $BID . ".$ext" ); ?>" width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>" height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>" border="0" usemap="#main" alt=""/>
                        <div class="tooltip-source">
                            <img src="<?php echo $f2->value( BASE_HTTP_PATH ); ?>images/periods.gif" alt=""/>
                        </div>
                    </div>
                </div>
            </div>

			<?php
		} else {
			echo "<b>The file: " . $BANNER_PATH . "grid" . $BID . ".$ext" . " doesn't exist.</b><br>";
			echo "<b>Please process your pixels from the Admin section (Look under 'Pixel Admin')</b>";
		}
	}

	public static function show_stats() {
		global $f2, $label;

		$BID = $f2->bid();

		$banner_data = load_banner_constants( $BID );

		$sql = "select count(*) AS COUNT FROM " . MDS_DB_PREFIX . "blocks where status='sold' and banner_id='$BID' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$row = mysqli_fetch_array( $result );

		if ( defined( 'STATS_DISPLAY_MODE' ) && STATS_DISPLAY_MODE == 'BLOCKS' ) {
			$sold = $row['COUNT'];
		} else {
			$sold = $row['COUNT'] * ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
		}

		$sql = "select count(*) AS COUNT FROM " . MDS_DB_PREFIX . "blocks where status='nfs' and banner_id='$BID' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
		$row = mysqli_fetch_array( $result );

		if ( defined( 'STATS_DISPLAY_MODE' ) && STATS_DISPLAY_MODE == 'BLOCKS' ) {
			$nfs       = $row['COUNT'];
			$available = ( ( $banner_data['G_WIDTH'] * $banner_data['G_HEIGHT'] ) - $nfs ) - $sold;
		} else {
			$nfs       = $row['COUNT'] * ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
			$available = ( ( $banner_data['G_WIDTH'] * $banner_data['G_HEIGHT'] * ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] ) ) - $nfs ) - $sold;
		}

		if ( $label['sold_stats'] == '' ) {
			$label['sold_stats'] = "Sold";
		}

		if ( $label['available_stats'] == '' ) {
			$label['available_stats'] = "Available";
		}

		?>
        <div class="mds-container stats-container stats<?php echo intval($BID); ?>">
            <div class="status_body">
                <div class="status">
                    <b><?php echo $label['sold_stats']; ?>:</b> <span class="status_text"><?php echo number_format( $sold ); ?></span><br/>
                    <b><?php echo $label['available_stats']; ?>:</b> <span class="status_text"><?php echo number_format( $available ); ?></span><br/>
                </div>
            </div>
        </div>
		<?php
	}

	public static function get_ad( $data ) {
		require_once( BASE_PATH . '/include/ads.inc.php' );

		global $prams;

		$prams = load_ad_values( $data['aid'] );

		if ( $prams !== false ) {
			echo assign_ad_template( $prams );
		}
	}

	public static function store_click( $data ) {
		$ADVANCED_CLICK_COUNT = \MDSConfig::get( 'ADVANCED_CLICK_COUNT' );
		if ( $ADVANCED_CLICK_COUNT == 'YES' ) {
			require_once BASE_PATH . '/include/ads.inc.php';
			require_once BASE_PATH . '/include/dynamic_forms.php';

			$date = gmdate( 'Y' ) . "-" . gmdate( 'm' ) . "-" . gmdate( 'd' );
			$sql  = "UPDATE `" . MDS_DB_PREFIX . "clicks` SET `clicks` = `clicks` + 1 WHERE `banner_id`=" . intval( $data['bid'] ) . " AND `date`='$date' AND `block_id`=" . intval( $data['block_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
			$x = @mysqli_affected_rows( $GLOBALS['connection'] );

			$tag_to_field_id = get_tag_to_field_id( 1 );
			$field_id        = intval( $tag_to_field_id['URL']['field_id'] );
			$sql             = "SELECT `t1`.`{$field_id}`, t1.`user_id` FROM `" . MDS_DB_PREFIX . "ads` AS `t1` INNER JOIN `" . MDS_DB_PREFIX . "blocks` AS `t2` ON `t1`.`ad_id` = `t2`.`ad_id` WHERE `t2`.`block_id`=" . intval( $data['block_id'] ) . ";";
			$result = @mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
			$row = @mysqli_fetch_array( $result );

			$sql = "UPDATE `" . MDS_DB_PREFIX . "users` SET `click_count` = `click_count` + 1 where `ID`=" . intval( $row['user_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );

			if ( ! $x ) {
				$sql = "INSERT INTO `" . MDS_DB_PREFIX . "clicks` (`banner_id`, `date`, `clicks`, `block_id`, `user_id`) VALUES(" . intval( $data['bid'] ) . ", '$date', 1, " . intval( $data['block_id'] ) . ", " . intval( $row['user_id'] ) . ") ";
				@mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
			}
		}

		$sql = "UPDATE `" . MDS_DB_PREFIX . "blocks` SET `click_count` = `click_count` + 1 where `block_id`=" . intval( $data['block_id'] ) . " AND banner_id=" . intval( $data['bid'] );
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
	}

	public static function store_view( $data ) {
		if ( ADVANCED_VIEW_COUNT == 'YES' ) {
			require_once BASE_PATH . '/include/ads.inc.php';
			require_once BASE_PATH . '/include/dynamic_forms.php';

			$date = gmdate( 'Y' ) . "-" . gmdate( 'm' ) . "-" . gmdate( 'd' );
			$sql  = "UPDATE " . MDS_DB_PREFIX . "views set views = views + 1 where banner_id='" . intval( $data['bid'] ) . "' AND `date`='$date' AND `block_id`=" . intval( $data['block_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
			$x = @mysqli_affected_rows( $GLOBALS['connection'] );

			$tag_to_field_id = get_tag_to_field_id( 1 );
			$field_id        = intval( $tag_to_field_id['URL']['field_id'] );
			$sql             = "SELECT `t1`.`{$field_id}`, `t1`.`user_id` FROM `" . MDS_DB_PREFIX . "ads` AS `t1` INNER JOIN `" . MDS_DB_PREFIX . "blocks` AS `t2` ON `t1`.`ad_id` = `t2`.`ad_id` WHERE `t2`.`block_id`=" . intval( $data['block_id'] ) . ";";
			$result = @mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
			$row = @mysqli_fetch_array( $result );

			$sql = "UPDATE `" . MDS_DB_PREFIX . "users` SET `view_count` = `view_count` + 1 where `ID`=" . intval( $row['user_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );

			if ( ! $x ) {
				$sql = "INSERT into `" . MDS_DB_PREFIX . "views` (`banner_id`, `date`, `views`, `block_id`, `user_id`) VALUES(" . intval( $data['bid'] ) . ", '$date', 1, " . intval( $data['block_id'] ) . ", " . intval( $row['user_id'] ) . ")";
				@mysqli_query( $GLOBALS['connection'], $sql );
			}
		}

		$sql = "UPDATE `" . MDS_DB_PREFIX . "blocks` SET `view_count` = `view_count` + 1 where `block_id`=" . intval( $data['block_id'] ) . " AND `banner_id`=" . intval( $data['bid'] );
		mysqli_query( $GLOBALS['connection'], $sql );
	}

	public static function show_list() {
		global $label, $purifier;

		require_once BASE_PATH . '/include/ads.inc.php';
		require_once BASE_PATH . '/include/dynamic_forms.php';

		?>
        <div class="mds-container list-container">
            <div class="list">
                <div class="table-row header">
                    <div class="list-heading"><?php echo $label['list_date_of_purchase']; ?></div>
                    <div class="list-heading"><?php echo $label['list_name']; ?></div>
                    <div class="list-heading"><?php echo $label['list_ads']; ?></div>
                    <div class="list-heading"><?php echo $label['list_pixels']; ?></div>
                </div>
				<?php
				$sql = "SELECT * FROM " . MDS_DB_PREFIX . "banners ORDER BY banner_id";
				$banners = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
				while ( $banner = mysqli_fetch_array( $banners ) ) {
					?>
                    <div class="table-row header">
                        <div class="list-heading" style="width:100%;"><?php echo $purifier->purify( $banner['name'] ); ?></div>
                    </div>
					<?php
					//TODO: add option to order by other columns
					$sql = "SELECT *, MAX(order_date) as max_date, sum(quantity) AS pixels FROM " . MDS_DB_PREFIX . "orders where status='completed' AND approved='Y' AND published='Y' AND banner_id='" . intval( $banner['banner_id'] ) . "' GROUP BY user_id, banner_id, order_id order by pixels desc ";
					$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
					while ( $row = mysqli_fetch_array( $result ) ) {
						$q = "SELECT Username FROM " . MDS_DB_PREFIX . "users WHERE ID=" . intval( $row['user_id'] );
						$q = mysqli_query( $GLOBALS['connection'], $q ) or die( mysqli_error( $GLOBALS['connection'] ) );
						$user = mysqli_fetch_row( $q );
						?>
                        <div class="table-row">
                            <div class="list-cell">
								<?php echo $purifier->purify( get_formatted_date( get_local_datetime( $row['max_date'] ) ) ); ?>
                            </div>
                            <div class="list-cell">
								<?php
								echo $purifier->purify( $user['0'] ); ?>
                            </div>
                            <div class="list-cell">
								<?php
								$br  = "";
								$sql = "Select * FROM  `" . MDS_DB_PREFIX . "ads` as t1, `" . MDS_DB_PREFIX . "orders` AS t2 WHERE t1.ad_id=t2.ad_id AND t1.banner_id='" . intval( $banner['banner_id'] ) . "' and t1.order_id='" . intval( $row['order_id'] ) . "' AND t1.user_id='" . intval( $row['user_id'] ) . "' AND status='completed' AND approved='Y' ORDER BY `ad_date`";
								$m_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
								global $prams;
								while ( $prams = mysqli_fetch_array( $m_result, MYSQLI_ASSOC ) ) {

									$blocks   = explode( ',', $prams['blocks'] );
									$block_id = $blocks[0];

									$ALT_TEXT = get_template_value( 'ALT_TEXT', 1 );
									$ALT_TEXT = str_replace( [ "'", '"' ], "", $ALT_TEXT );

									$data_values = array(
										'id'        => $prams['ad_id'],
										'block_id'  => $block_id,
										'banner_id' => $banner['banner_id'],
										'alt_text'  => $ALT_TEXT,
										'url'       => get_template_value( 'URL', 1 ),
									);

									echo $br . '<a target="_blank" data-data="' . htmlspecialchars( json_encode( $data_values, JSON_HEX_QUOT | JSON_HEX_APOS ), ENT_QUOTES, 'UTF-8' ) . '" data-alt-text="' . $ALT_TEXT . '" class="list-link" href="' . $data_values['url'] . '">' . get_template_value( 'ALT_TEXT', 1 ) . '</a>';
									$br = '<br>';
								}

								?>
                            </div>
                            <div class="list-cell">
								<?php echo $row['pixels']; ?>
                            </div>
                        </div>
						<?php
					}
				}
				?>
                <div class="table-row header">
                    <div class="list-heading"><?php echo $label['list_date_of_purchase']; ?></div>
                    <div class="list-heading"><?php echo $label['list_name']; ?></div>
                    <div class="list-heading"><?php echo $label['list_ads']; ?></div>
                    <div class="list-heading"><?php echo $label['list_pixels']; ?></div>
                </div>
            </div>
        </div>
		<?php
	}

	public static function show_users() {

		require_once BASE_PATH . "/include/login_functions.php";
		mds_start_session();
		require_once BASE_PATH . "/include/init.php";

		var_export( is_logged_in() );

		//process_login();

		// check if user has permission to access this page
		if ( ! mds_check_permission( "mds_my_account" ) ) {
			// require_once BASE_PATH . "/html/header.php";
			_e( "No Access", 'milliondollarscript' );
			// require_once BASE_PATH . "/html/footer.php";
			exit;
		}

		// require_once BASE_PATH . "/html/header.php";

		global $f2, $label;
		$BID = $f2->bid();
		$sql = "SELECT grid_width,grid_height, block_width, block_height, bgcolor, time_stamp FROM " . MDS_DB_PREFIX . "banners WHERE (banner_id = '$BID')";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$b_row = mysqli_fetch_array( $result );

		if ( ! $b_row['block_width'] ) {
			$b_row['block_width'] = 10;
		}
		if ( ! $b_row['block_height'] ) {
			$b_row['block_height'] = 10;
		}

		$sql = "select block_id from " . MDS_DB_PREFIX . "blocks where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' and status='sold' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$pixels = mysqli_num_rows( $result ) * ( $b_row['block_width'] * $b_row['block_height'] );

		$sql = "select block_id from " . MDS_DB_PREFIX . "blocks where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' and status='ordered' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$ordered = mysqli_num_rows( $result ) * ( $b_row['block_width'] * $b_row['block_height'] );

		$sql = "select * from " . MDS_DB_PREFIX . "users where ID='" . intval( $_SESSION['MDS_ID'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$user_row = mysqli_fetch_array( $result );

		if ( $user_row == null ) {
			// user might not be logged in correctly so log them out
			require_once MDS_BASE_PATH . 'src/Core/users/wplogout.php';
		}

		?>
        <h3><?php echo $label['advertiser_home_welcome']; ?></h3>
        <p>
			<?php echo $label['advertiser_home_line2'] . "<br>"; ?>
        <p>
        <p>
			<?php
			$label['advertiser_home_blkyouown'] = str_replace( "%PIXEL_COUNT%", $pixels, $label['advertiser_home_blkyouown'] );
			$label['advertiser_home_blkyouown'] = str_replace( "%BLOCK_COUNT%", ( $pixels / ( $b_row['block_width'] * $b_row['block_height'] ) ), $label['advertiser_home_blkyouown'] );
			echo $label['advertiser_home_blkyouown'] . "<br>";

			$label['advertiser_home_blkonorder'] = str_replace( "%PIXEL_ORD_COUNT%", $ordered, $label['advertiser_home_blkonorder'] );
			$label['advertiser_home_blkonorder'] = str_replace( "%BLOCK_ORD_COUNT%", ( $ordered / ( $b_row['block_width'] * $b_row['block_height'] ) ), $label['advertiser_home_blkonorder'] );

			if ( USE_AJAX == 'SIMPLE' ) {
				$label['advertiser_home_blkonorder'] = str_replace( 'select.php', 'order_pixels.php', $label['advertiser_home_blkonorder'] );
			}
			echo $label['advertiser_home_blkonorder'] . "<br>";

			$label['advertiser_home_click_count'] = str_replace( "%CLICK_COUNT%", number_format( $user_row['click_count'] ), $label['advertiser_home_click_count'] );
			echo $label['advertiser_home_click_count'] . "<br>";

			$label['advertiser_home_view_count'] = str_replace( "%VIEW_COUNT%", number_format( $user_row['view_count'] ), $label['advertiser_home_view_count'] );
			echo $label['advertiser_home_view_count'] . "<br>";
			?>
        </p>

        <h3><?php echo $label['advertiser_home_sub_head']; ?></h3>
        <p>
			<?php

			if ( USE_AJAX == 'SIMPLE' ) {
				$label['advertiser_home_selectlink'] = str_replace( 'select.php', 'order_pixels.php', $label['advertiser_home_selectlink'] );
			}
			if ( WP_ENABLED == 'YES' ) {
				$label['advertiser_home_editlink'] = str_replace( [ 'edit.php', 'href' ], [ \MillionDollarScript\Classes\Options::get_option( 'account-page' ), 'target=\'_top\' href' ], $label['advertiser_home_editlink'] );
			}

			echo $label['advertiser_home_selectlink']; ?><br>
			<?php echo $label['advertiser_home_managelink']; ?><br>
			<?php echo $label['advertiser_home_ordlink']; ?><br>
			<?php echo $label['advertiser_home_editlink']; ?><br>
        </p>
        <p>
			<?php echo $label['advertiser_home_quest']; ?>
        </p>
		<?php //require_once BASE_PATH . "/html/footer.php";
	}
}
