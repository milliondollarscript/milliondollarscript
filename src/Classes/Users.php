<?php
/**
 * Million Dollar Script Two
 *
 * @version 2.3.4
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

defined( 'ABSPATH' ) or exit;

class Users {

	/**
	 * Register MDS user.
	 *
	 * @param $user_id int WP user id
	 */
	public static function register( int $user_id ) {
		global $wpdb;

		// WP_User
		$wp_user = get_userdata( $user_id );

		// check if user exists already
		$mds_user = $wpdb->get_row(
			$wpdb->prepare( "SELECT ID FROM " . MDS_DB_PREFIX . "users WHERE Username = %s",
				array( $wp_user->user_login )
			)
		);

		// TODO: if user exists then do something useful
		if ( $mds_user !== null ) {
			return;
		}

		// username doesn't exist in MDS yet so create it
		self::create( $wp_user->first_name, $wp_user->last_name, $wp_user->user_login, $wp_user->user_pass, $wp_user->user_email );
	}

	/**
	 * Create MDS user.
	 *
	 * @param $FirstName string
	 * @param $LastName string
	 * @param $Username string
	 * @param $pass string
	 * @param $Email string
	 *
	 * @return bool
	 */
	public static function create( string $FirstName, string $LastName, string $Username, string $pass, string $Email ): bool {
		global $wpdb;

		$EM_NEEDS_ACTIVATION = Config::get( 'EM_NEEDS_ACTIVATION' );

		$Password = wp_hash_password( $pass );

		$validated = 0;

		if ( ( $EM_NEEDS_ACTIVATION == "AUTO" ) ) {
			$validated = 1;
		}

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$res = $wpdb->insert(
			MDS_DB_PREFIX . 'users',
			array(
				'SignupDate' => $now,
				'FirstName'  => $FirstName,
				'LastName'   => $LastName,
				'Username'   => $Username,
				'Password'   => $Password,
				'Email'      => $Email,
				'Validated'  => $validated,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s'
			)
		);

		return $res > 0;
	}

	/**
	 * Validate MDS user with the given MDS user id.
	 *
	 * @param $id int
	 */
	public static function validate( int $id ) {
		global $wpdb;

		$wpdb->update(
			MDS_DB_PREFIX . 'users',
			array( 'Validated' => 1 ),
			array( 'ID' => $id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Login the given user.
	 *
	 * @param $user_login
	 * @param $user \WP_User
	 */
	public static function login( $user_login, \WP_User $user ) {
		global $wpdb;

		// Check if there is a valid MDS user
		$valid_user = false;
		$mds_user   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `" . MDS_DB_PREFIX . "users` Where Username = %s",
				$user_login
			)
		);
		if ( $mds_user === null ) {
			// No MDS user found so check if they already exist in WP
			if ( username_exists( $user_login ) ) {
				// User exists in WP so create it in MDS
				if ( self::create( $user->first_name, $user->last_name, $user->user_login, $user->user_pass, $user->user_email ) > 0 ) {
					$mds_user = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT * FROM `" . MDS_DB_PREFIX . "users` Where Username = %s",
							$user_login
						)
					);
				}
			}
		}

		if ( $mds_user !== null ) {
			// Now we have a valid user
			$valid_user = true;
		}

		// Unable to get a valid MDS user so don't login
		if ( ! $valid_user ) {
			return;
		}

		// validate account if it isn't already
		if ( $mds_user->Validated == "0" ) {
			self::validate( (int) $mds_user->ID );
		}

		// post to MDS to log the user in
		$login    = Config::get( 'BASE_HTTP_PATH' ) . 'users/wplogin.php';
		$response = wp_remote_post( $login, array(
			'method'  => 'POST',
			'timeout' => 30,
			'body'    => array(
				'ID'       => $user->ID,
				'Username' => $_POST['log'],
				'Password' => $_POST['pwd']
			)
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'Error sending login request to ' . $login );
		}
	}

	/**
	 * Logout the MDS user.
	 *
	 * @param $user_id
	 */
	public static function logout( $user_id ) {
		$logout   = Config::get( 'BASE_HTTP_PATH' ) . 'users/wplogout.php';
		$response = wp_remote_post( $logout );

		if ( is_wp_error( $response ) ) {
			error_log( 'Error sending logout request to ' . $logout );
		}

		$user = get_userdata( $user_id );
		if ( $user !== false && $user->has_cap( 'manage_options' ) ) {
			if ( isset( $_COOKIE['MDSADMIN_PHPSESSID'] ) ) {
				unset( $_COOKIE['MDSADMIN_PHPSESSID'] );
				setcookie( 'MDSADMIN_PHPSESSID', null, - 1 );
			}
		}
	}

	/**
	 * Update the MDS user matching the given WP user id.
	 *
	 * @param $user_id int
	 * @param $old_user_data \WP_User
	 */
	public static function update( int $user_id, \WP_User $old_user_data ) {
		global $wpdb;

		// WP_User
		$wp_user = get_userdata( $user_id );

		$now = gmdate( "Y-m-d H:i:s" );

		$wpdb->update(
			MDS_DB_PREFIX . 'users',
			array(
				'SignupDate' => $now,
				'FirstName'  => $wp_user->first_name,
				'LastName'   => $wp_user->last_name,
				'Username'   => $wp_user->user_login,
				'Password'   => $wp_user->user_pass,
				'Email'      => $wp_user->user_email
			),
			array( 'username' => $wp_user->user_login ),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * Delete the MDS user matching the given WP user id.
	 *
	 * @param $id int
	 */
	public static function delete( int $id ) {
		global $wpdb;

		// WP_User
		$wp_user = get_userdata( $id );

		$wpdb->delete(
			MDS_DB_PREFIX . 'users',
			array(
				'Username' => $wp_user->user_login
			),
			array(
				'%s'
			)
		);
	}

	/**
	 * Change login header link to #
	 *
	 * @param $login_header_url
	 *
	 * @return string
	 */
	public static function login_headerurl( $login_header_url ): string {
		return "#";
	}

	/**
	 * Change logo on login page.
	 */
	public static function login_head() {
		echo '
	<style type="text/css">
    	.login h1 a {
    		background-image:url(' . Config::get( 'SITE_LOGO_URL' ) . ') !important;
    		background-size: contain;
    		margin:0 auto;
    		width:auto;
    		height:100px;
    	}
    	#backtoblog {
    		display:none;
    	}
    </style>
';
	}
}
