<?php
/*
 * @package       mds
 * @copyright     (C) Copyright 2022 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @version       2022-01-30 17:07:25 EST
 * @license       This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 *  * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */

require_once __DIR__ . "/../include/init.php";

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
			default:
				break;
		}
	}

	/**
	 * Ensures the mds.js is loaded only once.
	 */
	private function mds_js_loaded() {
		if ( ! isset( $GLOBALS['mds_js_loaded'] ) ) {
			$GLOBALS['mds_js_loaded'] = true;

			global $f2, $load_mds_js;
			$BID         = $f2->bid();
			$banner_data = load_banner_constants( $BID );

			$wp_url = '';
			if ( WP_ENABLED == "YES" && ! empty( WP_URL ) ) {
				$wp_url = WP_URL;
			}

			if ( WP_ENABLED == "YES" ) {
				?>
                <script>
					if (window.mds_js_loaded !== true) {
						window.mds_js_loaded = true;

						jQuery('<link/>', {rel: 'stylesheet', href: '<?php echo BASE_HTTP_PATH; ?>css/tippy/light.css'}).appendTo('head');
						jQuery('<link/>', {rel: 'stylesheet', href: '<?php echo BASE_HTTP_PATH; ?>css/main.css?ver=<?php echo filemtime( BASE_PATH . "/css/main.css" ); ?>'}).appendTo('head');

						jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/third-party/popper.js', function () {
							jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/third-party/tippy-bundle.umd.js', function () {
								jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/third-party/image-scale.min.js', function () {
									jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/third-party/image-map.js', function () {
										jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/third-party/hammer.min.js', function () {
											window.mds_data = {
												ajax: '<?php echo BASE_HTTP_PATH; ?>ajax.php',
												wp: '<?php echo $wp_url; ?>',
												winWidth: parseInt('<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>'),
												winHeight: parseInt('<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>'),
												time: '<?php echo time(); ?>',
												BASE_HTTP_PATH: '<?php echo BASE_HTTP_PATH;?>',
												REDIRECT_SWITCH: '<?php echo REDIRECT_SWITCH; ?>',
												REDIRECT_URL: '<?php echo REDIRECT_URL; ?>',
												ENABLE_MOUSEOVER: '<?php echo ENABLE_MOUSEOVER; ?>',
												BID: parseInt('<?php echo $BID; ?>')
											};
											jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/mds.js?ver=<?php echo filemtime( BASE_PATH . '/js/mds.js' ); ?>', function () {
											});
										});
									});
								});
							});
						});
					}
                </script>
				<?php
			} else if ( $load_mds_js != null ) {
				?>
                <script>
					if (window.mds_js_loaded !== true) {
						window.mds_js_loaded = true;
						jQuery('<link/>', {rel: 'stylesheet', href: '<?php echo BASE_HTTP_PATH; ?>css/tippy/light.css'}).appendTo('head');
						jQuery('<link/>', {rel: 'stylesheet', href: '<?php echo BASE_HTTP_PATH; ?>css/main.css?ver=<?php echo filemtime( BASE_PATH . "/css/main.css" ); ?>'}).appendTo('head');

						jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/third-party/popper.js', function () {
							jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/third-party/tippy-bundle.umd.js', function () {
								jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/third-party/image-scale.min.js', function () {
									jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/third-party/image-map.js', function () {
										jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/third-party/hammer.min.js', function () {
											window.mds_data = {
												ajax: '<?php echo BASE_HTTP_PATH; ?>ajax.php',
												time: '<?php echo time(); ?>',
												BASE_HTTP_PATH: '<?php echo BASE_HTTP_PATH;?>',
												REDIRECT_SWITCH: '<?php echo REDIRECT_SWITCH; ?>',
												REDIRECT_URL: '<?php echo REDIRECT_URL; ?>',
												ENABLE_MOUSEOVER: '<?php echo ENABLE_MOUSEOVER; ?>',
												BID: parseInt('<?php echo $BID; ?>')
											};
											jQuery.getScript('<?php echo BASE_HTTP_PATH; ?>js/mds.js?ver=<?php echo filemtime( BASE_PATH . '/js/mds.js' ); ?>', function () {
											});
										});
									});
								});
							});
						});
					}
                </script>
				<?php
			}
		}
	}

	private function grid( int $BID ) {
		$this->mds_js_loaded();

		$container = 'grid' . $BID;

		$width  = $this->banner_data['G_WIDTH'] * $this->banner_data['BLK_WIDTH'];
		$height = $this->banner_data['G_HEIGHT'] * $this->banner_data['BLK_HEIGHT'];

		if ( $this->add_container !== false ) {
			$container = $this->add_container . $BID;
			?>
            <div class="mds-container">
                <div class="grid-container <?php echo $container; ?>"></div>
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
}