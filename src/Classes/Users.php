<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.6
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

use Carbon_Fields\Container;
use Carbon_Fields\Field;

defined( 'ABSPATH' ) or exit;

class Users {

	public static function custom_fields(): void {
		$fields = array(
			Field::make( 'select', MDS_PREFIX . 'privileged', Language::get( 'Privileged' ) )
			     ->set_default_value( '0' )
			     ->set_options( [
				     '0' => 'Normal',
				     '1' => 'Privileged',
			     ] )
			     ->set_help_text( Language::get( 'Privileged users bypass payment for pixels.' ) ),
		);

		$fields = apply_filters( 'mds_user_fields', $fields, MDS_PREFIX );

		Container::make( 'user_meta', MDS_PREFIX . 'users', Language::get( 'Million Dollar Script' ) )
		         ->add_fields( $fields );
	}

	public static function add_custom_user_columns( $columns ) {
		$columns['view_count']  = Language::get( 'View Count' );
		$columns['click_count'] = Language::get( 'Click Count' );
		$columns['privileged']  = Language::get( 'Privileged' );

		return $columns;
	}

	public static function show_custom_user_columns_content( $value, $column_name, $user_id ) {
		if ( 'view_count' == $column_name ) {
			return get_user_meta( $user_id, MDS_PREFIX . 'view_count', true );
		}

		if ( 'click_count' == $column_name ) {
			return get_user_meta( $user_id, MDS_PREFIX . 'click_count', true );
		}

		if ( 'privileged' == $column_name ) {
			return carbon_get_user_meta( $user_id, MDS_PREFIX . 'privileged' );
		}

		return $value;
	}

	public static function make_custom_user_columns_sortable( $columns ) {
		$columns['view_count']  = MDS_PREFIX . 'view_count';
		$columns['click_count'] = MDS_PREFIX . 'click_count';
		$columns['privileged']  = MDS_PREFIX . 'privileged';

		return $columns;
	}

	public static function sort_users_by_custom_column( $query ): void {
		if ( ! is_admin() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'view_count' == $orderby ) {
			$query->set( 'meta_key', MDS_PREFIX . 'view_count' );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( 'click_count' == $orderby ) {
			$query->set( 'meta_key', MDS_PREFIX . 'click_count' );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( 'privileged' == $orderby ) {
			$query->set( 'meta_key', MDS_PREFIX . 'privileged' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Login redirect
	 *
	 * @param $url
	 * @param $query
	 * @param $user
	 *
	 * @return string
	 */
	public static function login_redirect( $url, $query, $user ): string {
		return \MillionDollarScript\Classes\Options::get_option( 'login-redirect', home_url() );
	}

	/**
	 * Logout redirect
	 *
	 * @param $logout_url
	 * @param $requested_redirect_to
	 * @param $user
	 *
	 * @return string
	 */
	public static function logout_redirect( $logout_url, $requested_redirect_to, $user ): string {
		return \MillionDollarScript\Classes\Options::get_option( 'logout-redirect', home_url() );
	}

	/**
	 * Change login header link to #
	 *
	 * @param $login_header_url
	 *
	 * @return string
	 */
	public static function login_headerurl( $login_header_url ): string {
		return home_url();
	}

	/**
	 * Change logo on login page.
	 */
	public static function login_head(): void {
		$login_header_image = Options::get_option( 'login-header-image' );
		if ( empty( $login_header_image ) ) {
			$logo = wp_get_attachment_url( get_theme_mod( 'custom_logo' ) );
			if ( empty( $logo ) ) {
				$logo = '';
			}
		} else {
			$logo = wp_get_attachment_url( $login_header_image );
		}

		$show_text = '';
		if ( empty( $logo ) && Options::get_option( 'login-header-text', '' ) != '' ) {
			$show_text = 'text-indent: 0;';
		}

		echo '
	<style type="text/css">
    	.login h1 a {
    		background-image:url(' . esc_url( $logo ) . ') !important;
    		background-size: contain;
    		margin:0 auto;
    		width:auto;
    		height:100px;
    		' . $show_text . '
    	}
    	#backtoblog {
    		display:none;
    	}
    </style>
';
	}

	/**
	 * Change the login header text.
	 *
	 * @return string
	 */
	public static function login_headertext(): string {
		return Language::get( Options::get_option( 'login-header-text', '' ) );
	}

	public static function my_account( $header_footer = true ): void {

		mds_wp_login_check();

		// check if user has permission to access this page
		if ( ! mds_check_permission( "mds_my_account" ) ) {
			require_once MDS_CORE_PATH . "html/header.php";
			_e( "No Access" );
			require_once MDS_CORE_PATH . "html/footer.php";
			exit;
		}

		if ( $header_footer ) {
			require_once MDS_CORE_PATH . "html/header.php";
		}

		global $f2;
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

		$user_id = get_current_user_id();

		$sql = "select block_id from " . MDS_DB_PREFIX . "blocks where user_id='" . $user_id . "' and status='sold' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$pixels = mysqli_num_rows( $result ) * ( $b_row['block_width'] * $b_row['block_height'] );

		$sql = "select block_id from " . MDS_DB_PREFIX . "blocks where user_id='" . $user_id . "' and status='ordered' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$ordered = mysqli_num_rows( $result ) * ( $b_row['block_width'] * $b_row['block_height'] );

		// Replacements for custom page links in WP
		$order_page   = Utility::get_page_url( 'order' );
		$manage_page  = Utility::get_page_url( 'manage' );
		$history_page = Utility::get_page_url( 'history' );
		$account_page = \MillionDollarScript\Classes\Options::get_option( 'account-page' );

		Language::out( '<div class="fancy-heading">Welcome to your account</div>' );

		Language::out_replace(
			'<p>Here you can manage your pixels. <a href="%MANAGE_URL%">Manage Pixels</a><p>',
			[
				'%PIXEL_COUNT%',
				'%BLOCK_COUNT%',
				'%MANAGE_URL%',
			],
			[
				$pixels,
				( $pixels / ( $b_row['block_width'] * $b_row['block_height'] ) ),
				Utility::get_page_url( 'manage' ),
			]
		);

		Language::out_replace(
			'<p>You own %PIXEL_COUNT% blocks.</p>',
			[
				'%PIXEL_COUNT%',
				'%BLOCK_COUNT%',
				'%MANAGE_URL%',
			],
			[
				$pixels,
				( $pixels / ( $b_row['block_width'] * $b_row['block_height'] ) ),
				Utility::get_page_url( 'manage' ),
			]
		);

		Language::out_replace(
			'<p>You have %PIXEL_ORD_COUNT% pixels on order (%BLOCK_ORD_COUNT% blocks). <a href="%ORDER_URL%">Order Pixels</a> / <a href="%HISTORY_URL%">View Order History</a></p>',
			[
				'%PIXEL_ORD_COUNT%',
				'%BLOCK_ORD_COUNT%',
				'%ORDER_URL%',
				'%HISTORY_URL%',
			],
			[
				$ordered,
				( $ordered / ( $b_row['block_width'] * $b_row['block_height'] ) ),
				$order_page,
				$history_page,
			]
		);

		Language::out_replace( '<p>Your pixels were clicked %CLICK_COUNT% times.</p>', '%CLICK_COUNT%', number_format( intval( get_user_meta( $user_id, MDS_PREFIX . 'click_count', true ) ) ) );
		Language::out_replace( '<p>Your pixels were viewed %VIEW_COUNT% times.</p>', '%VIEW_COUNT%', number_format( intval( get_user_meta( $user_id, MDS_PREFIX . 'view_count', true ) ) ) );

		Language::out( '<div class="fancy-heading">Here is what you can do</div>' );

		Language::out_replace( '- <a href="%ORDER_URL%">Order</a>: Choose and order new pixels.<br />', '%ORDER_URL%', $order_page );
		Language::out_replace( '- <a href="%MANAGE_URL%">Manage</a>: Manage pixels owned by you.<br />', '%MANAGE_URL%', $manage_page );
		Language::out_replace( '- <a href="%HISTORY_URL%">View Orders</a>: View your order history, and status of each order.<br />', '%HISTORY_URL%', $history_page );
		Language::out_replace( '- <a href="%ACCOUNT_URL%">Edit Account Details</a>: Edit your personal details, change your password.<br />', '%ACCOUNT_URL%', $account_page );

		Language::out( '<p>Questions? Contact Us: <span id="emailPlaceholder"></span></p>' );

		// encode email from spam bots
		$email         = get_option( 'admin_email' );
		$encoded_email = '';
		for ( $i = 0; $i < strlen( $email ); $i ++ ) {
			$encoded_email .= dechex( ord( $email[ $i ] ) ) . ',';
		}
		$encoded_email = rtrim( $encoded_email, ',' );

		?>
        <script>
			const encodedEmail = '<?php echo $encoded_email; ?>';
			let decodedEmail = '';
			const hexValues = encodedEmail.split(',');
			for (let i = 0; i < hexValues.length; i++) {
				decodedEmail += String.fromCharCode(parseInt(hexValues[i], 16));
			}

			const a = document.createElement('a');
			a.href = 'mailto:' + decodedEmail;
			a.textContent = decodedEmail;

			const placeholder = document.getElementById('emailPlaceholder');
			placeholder.parentNode.replaceChild(a, placeholder);

        </script>
		<?php

		if ( $header_footer ) {
			require_once MDS_CORE_PATH . "html/footer.php";
		}
	}
}
