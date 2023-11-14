<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.5
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

defined( 'ABSPATH' ) or exit;

/**
 * Routes for Million Dollar Script
 *
 * Usage:
 * home_url( $MDS_ENDPOINT . '/pointer' )
 *
 * Wrapper function:
 * \MillionDollarScript\Classes\Functions::get_page_url('account')
 */
class Routes {
	public function __construct() {
		add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
		add_filter( 'template_include', [ __CLASS__, 'template_include' ], 99 );
		add_action( 'parse_query', [ __CLASS__, 'parse_query' ], 10, 1 );

		register_activation_hook( MDS_BASE_FILE, [ __CLASS__, 'activate' ] );
		register_deactivation_hook( MDS_BASE_FILE, [ __CLASS__, 'deactivate' ] );
	}

	/**
	 * Workaround to make sure the default header and footers load.
	 *
	 * @param $wp_query
	 *
	 * @return void
	 */
	public static function parse_query( $wp_query ): void {
		$MDS_ENDPOINT = Options::get_option( 'endpoint', 'milliondollarscript' );

		if ( isset( $wp_query->query_vars[ $MDS_ENDPOINT ] ) ) {
			$post_id = Options::get_option( 'dynamic-id' );
			if ( ! empty( $post_id ) ) {
				$post_id = intval( $post_id );
				$post    = get_post( $post_id );
				if ( $post !== null ) {
					$wp_query->queried_object    = $post;
					$wp_query->queried_object_id = $post_id;
					$wp_query->post              = $post;
					$wp_query->posts             = array( $post );
				}
			}

			$wp_query->post_count  = 1;
			$wp_query->is_singular = true;
			$wp_query->is_single   = true;
			$wp_query->is_page     = true;

			global $post;
			$post = null;
			$post = $wp_query->post;

			setup_postdata( $post );
		}
	}

	public static function template_include( $template ) {
		global $wp_query;
		$MDS_ENDPOINT = Options::get_option( 'endpoint', 'milliondollarscript' );

		if ( isset( $wp_query->query_vars[ $MDS_ENDPOINT ] ) ) {
			$page = $wp_query->query_vars[ $MDS_ENDPOINT ];

			/**
			 * requires
			 */
			if ( $page === 'display-map' ) {
				require_once MDS_CORE_PATH . 'display_map.php';
				exit;
			} else if ( $page === 'display-stats' ) {
				require_once MDS_CORE_PATH . 'display_stats.php';
				exit;
			} else if ( $page === 'ga' ) {
				require_once MDS_CORE_PATH . 'ga.php';
				exit;
				// } else if ( $page === 'click' ) {
				// 	require_once MDS_CORE_PATH . 'click.php';
				// 	exit;
			} else if ( $page === 'check-selection' ) {
				require_once MDS_CORE_PATH . 'users/check_selection.php';
				exit;
			} else if ( $page === 'make-selection' ) {
				require_once MDS_CORE_PATH . 'users/make_selection.php';
				exit;
			} else if ( $page === 'show-selection' ) {
				require_once MDS_CORE_PATH . 'users/show_selection.php';
				exit;
			} else if ( $page === 'show-map' ) {
				require_once MDS_CORE_PATH . 'users/show_map.php';
				exit;
			} else if ( $page === 'get-block-image' ) {
				require_once MDS_CORE_PATH . 'users/get_block_image.php';
				exit;
			} else if ( $page === 'get-image' ) {
				require_once MDS_CORE_PATH . 'users/get_image.php';
				exit;
			} else if ( $page === 'get-image2' ) {
				require_once MDS_CORE_PATH . 'users/get_image2.php';
				exit;
			} else if ( $page === 'get-order-image' ) {
				require_once MDS_CORE_PATH . 'users/get_order_image.php';
				exit;
			} else if ( $page === 'get-pointer-graphic' ) {
				require_once MDS_CORE_PATH . 'users/get_pointer_graphic.php';
				exit;
			} else if ( $page === 'update-order' ) {
				require_once MDS_CORE_PATH . 'users/update_order.php';
				exit;
			} else if ( $page === 'wplogout' ) {
				require_once MDS_CORE_PATH . 'users/wplogout.php';
				exit;
			} else {

				/**
				 * Templates
				 */
				$valid_pages = [
					'account',
					'checkout',
					'confirm-order',
					'history',
					'list',
					'manage',
					'no-orders',
					'order',
					'payment',
					'publish',
					'thank-you',
					'upload',
					'write-ad',
				];
				$valid_pages = apply_filters( 'mds_valid_pages', $valid_pages );

				foreach ( $valid_pages as $valid_page ) {
					if ( $page === $valid_page ) {
						return self::get_template( $valid_page );
					}
				}
			}
		}

		return $template;
	}

	public static function activate(): void {
		self::add_rewrite_rules();
		flush_rewrite_rules();
	}

	public static function add_rewrite_rules(): void {
		$MDS_ENDPOINT = Options::get_option( 'endpoint', 'milliondollarscript' );
		add_rewrite_tag( '%' . $MDS_ENDPOINT . '%', '([^&]+)' );

		$routes = [
			'account',
			'order',
			'order-pixels',
			'confirm-order',
			'checkout',
			'manage',
			'history',
			'publish',
			'display-map',
			'display-stats',
			'thank-you',
			'list',
			'ga',
			'click',
			'check-selection',
			'make-selection',
			'show-selection',
			'show-map',
			'get-block-image',
			'get-image',
			'get-image2',
			'get-order-image',
			'get-pointer-graphic',
			'update-order',
			'wplogout',
			'write-ad',
			'payment',
			'upload',
			'no-orders',
		];

		foreach ( $routes as $route ) {
			add_rewrite_rule( '^' . $MDS_ENDPOINT . '/' . $route . '/?', 'index.php?' . $MDS_ENDPOINT . '=' . $route, 'top' );
		}
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function get_template( string $page ): false|string {
		$option = Options::get_option( $page );
		if ( ! empty( $option ) && is_page( $option ) ) {
			// Get the default template used by WordPress for pages
			$default_template = locate_template( array( 'page.php', 'single.php', 'index.php' ) );

			if ( ! empty( $default_template ) ) {
				return $default_template;
			}
		}

		$default = MDS_BASE_PATH . 'templates/' . $page . '.php';
		if ( file_exists( $default ) ) {
			return $default;
		}

		return false;
	}
}