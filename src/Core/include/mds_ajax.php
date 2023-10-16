<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.3
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

use MillionDollarScript\Classes\Config;
use MillionDollarScript\Classes\Utility;

defined( 'ABSPATH' ) or exit;

/**
 * Class mds_ajax
 *
 * This class outputs javascript to call AJAX functions.
 */
class Mds_Ajax {

	private $banner_data;
	private $add_container;

	function __construct() {
	}

	function show( $type = null, $BID = null, $add_container = false ) {

		if ( $BID == null ) {
			if ( isset( $_REQUEST['BID'] ) && ! empty( $_REQUEST['BID'] ) ) {
				$BID = $_REQUEST['BID'];
			} else {
				$BID = 1;
			}
		}

		if ( $type == null ) {
			if ( isset( $_REQUEST['type'] ) && ! empty( $_REQUEST['type'] ) ) {
				$type = $_REQUEST['type'];
			} else {
				$type = 'grid';
			}
		}

		$BID                 = intval( $BID );
		$this->banner_data   = load_banner_constants( $BID );
		$this->add_container = $add_container;

		switch ( $type ) {
			case "grid":
				$this->grid( $BID );
				break;
			case "stats":
				$this->stats( $BID );
				break;
			case "list":
				$this->list( $BID );
				break;
			case "users":
				$this->users( $BID );
				break;
			default:
				break;
		}
	}

	private function grid( int $BID ) {
		$this->mds_js_loaded();

		$container = 'grid' . $BID;

		$width  = $this->banner_data['G_WIDTH'] * $this->banner_data['BLK_WIDTH'];
		$height = $this->banner_data['G_HEIGHT'] * $this->banner_data['BLK_HEIGHT'];

		if ( $this->add_container !== false ) {
			$container = $this->add_container . $BID;

			$bgstyle = "";
			if ( ! empty( $this->banner_data['G_BGCOLOR'] ) ) {
				$bgstyle = ' style="background-color:' . $this->banner_data['G_BGCOLOR'] . ';"';
			}
			?>
            <div class="mds-container">
                <div class="grid-container <?php echo $container; ?>"<?php echo $bgstyle ?>></div>
            </div>
			<?php
		}

		?>
        <script>
			jQuery(function () {
				let mds_grid_call = function () {
					var load_wait = setInterval(function () {
						if (typeof mds_grid == 'function') {
							mds_grid('<?php echo $container; ?>', <?php echo $BID; ?>, <?php echo $width; ?>, <?php echo $height; ?>);
							clearInterval(load_wait);
						}
					}, 100);
				}

				if (window.mds_ajax_request != null) {
					window.mds_ajax_request.done(mds_grid_call);
				} else {
					mds_grid_call();
				}
			});
        </script>
		<?php
	}

	/**
	 * Ensures the mds.js is loaded only once.
	 */
	private function mds_js_loaded() {
		if ( ! isset( $GLOBALS['mds_js_loaded'] ) ) {
			$GLOBALS['mds_js_loaded'] = true;

			global $f2;
			$BID         = $f2->bid();
			$banner_data = load_banner_constants( $BID );

			$tooltips = Config::get( 'ENABLE_MOUSEOVER' );

			?>
            <script>
	            jQuery(document).ready(function(){
					if (window.mds_js_loaded !== true) {
						window.mds_js_loaded = true;

						<?php if($tooltips == 'POPUP') { ?>
						jQuery('<link/>', {rel: 'stylesheet', href: '<?php echo MDS_CORE_URL; ?>css/tippy/light.css'}).appendTo('head');
						<?php } ?>
						jQuery('<link/>', {rel: 'stylesheet', href: '<?php echo MDS_BASE_URL; ?>src/Assets/css/mds.css?ver=<?php echo filemtime( MDS_BASE_PATH . "src/Assets/css/mds.css" ); ?>'}).appendTo('head');

						<?php if($tooltips == 'POPUP') { ?>
						jQuery.getScript('<?php echo MDS_CORE_URL; ?>js/third-party/popper.min.js', function () {
							jQuery.getScript('<?php echo MDS_CORE_URL; ?>js/third-party/tippy-bundle.umd.min.js', function () {
								<?php } ?>
								jQuery.getScript('<?php echo MDS_CORE_URL; ?>js/third-party/image-scale.min.js', function () {
									jQuery.getScript('<?php echo MDS_CORE_URL; ?>js/third-party/image-map.min.js', function () {
										jQuery.getScript('<?php echo MDS_CORE_URL; ?>js/third-party/contact.nomodule.min.js', function () {
											window.mds_data = {
												ajax: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
												publishurl: '<?php echo esc_js( Utility::get_page_url( 'manage' ) ); ?>',
												paymenturl: '<?php echo esc_js( Utility::get_page_url( 'payment' ) ); ?>',
												wp: '<?php echo esc_js( get_site_url() ); ?>',
												winWidth: parseInt('<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>', 10),
												winHeight: parseInt('<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>', 10),
												time: '<?php echo esc_js( time() ); ?>',
												MDS_CORE_URL: '<?php echo esc_js( MDS_CORE_URL );?>',
												REDIRECT_SWITCH: '<?php echo esc_js( REDIRECT_SWITCH ); ?>',
												REDIRECT_URL: '<?php echo esc_js( REDIRECT_URL ); ?>',
												ENABLE_MOUSEOVER: '<?php echo esc_js( ENABLE_MOUSEOVER ); ?>',
												BID: parseInt('<?php echo $BID; ?>', 10),
												link_target: '<?php echo esc_js( \MillionDollarScript\Classes\Options::get_option( 'link-target' ) ); ?>'
											};
											jQuery.getScript('<?php echo MDS_BASE_URL; ?>src/Assets/js/mds.min.js?ver=<?php echo filemtime( MDS_BASE_PATH . 'src/Assets/js/mds.min.js' ); ?>', function () {
											});
										});
									});
								});
								<?php if($tooltips == 'POPUP') { ?>
							});
						});
						<?php } ?>
					}
				});
            </script>
			<?php
		}
	}

	private function stats( int $BID ) {
		$this->mds_js_loaded();

		$container = 'stats' . $BID;

		if ( $this->add_container !== false ) {
			$container = $this->add_container . $BID;
			?>
            <div class="mds-container stats-container <?php echo $container; ?>"></div>
			<?php
		}

		?>
        <script>
			jQuery(function () {
				let mds_stats_call = function () {
					var load_wait = setInterval(function () {
						if (typeof mds_stats == 'function') {
							mds_stats('<?php echo $container; ?>', <?php echo $BID; ?>);
							clearInterval(load_wait);
						}
					}, 100);
				}

				if (window.mds_ajax_request != null) {
					window.mds_ajax_request.done(mds_stats_call);
				} else {
					mds_stats_call();
				}
			});
        </script>
		<?php
	}

	private function list( int $BID ) {
		$this->mds_js_loaded();

		$container = 'list' . $BID;

		if ( $this->add_container !== false ) {
			$container = $this->add_container . $BID;
			?>
            <div class="mds-container list-container <?php echo $container; ?>"></div>
			<?php
		}

		?>
        <script>
			jQuery(function () {
				let mds_list_call = function () {
					var load_wait = setInterval(function () {
						if (typeof mds_list == 'function') {
							mds_list('<?php echo $container; ?>', <?php echo $BID; ?>);
							clearInterval(load_wait);
						}
					}, 100);
				}

				if (window.mds_ajax_request != null) {
					window.mds_ajax_request.done(mds_list_call);
				} else {
					mds_list_call();
				}
			});
        </script>
		<?php
	}

	private function users( int $BID ) {
		$this->mds_js_loaded();

		$container = 'users' . $BID;

		if ( $this->add_container !== false ) {
			$container = $this->add_container . $BID;
			?>
            <div class="mds-container users-container <?php echo $container; ?>"></div>
			<?php
		}

		?>
        <script>
			jQuery(function () {
				let mds_users_call = function () {
					var load_wait = setInterval(function () {
						if (typeof mds_users == 'function') {
							mds_users('<?php echo $container; ?>', <?php echo $BID; ?>);
							clearInterval(load_wait);
						}
					}, 100);
				}

				if (window.mds_ajax_request != null) {
					window.mds_ajax_request.done(mds_users_call);
				} else {
					mds_users_call();
				}
			});
        </script>
		<?php
	}
}