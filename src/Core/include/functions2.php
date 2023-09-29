<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.1
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

defined( 'ABSPATH' ) or exit;

class functions2 {

	function get_default_grid(): int {
		global $wpdb, $BID;
		$default = 1;

		// Check if first grid is enabled.
		$enabled = $wpdb->get_var( "SELECT `enabled` FROM `" . MDS_DB_PREFIX . "banners` WHERE `banner_id`=1" );
		if ( $enabled == 'N' ) {
			// First grid is not enabled, so try to find one that is.
			$banner_id = $wpdb->get_var( "SELECT `banner_id` FROM `" . MDS_DB_PREFIX . "banners` WHERE `enabled`='Y' ORDER BY `banner_id` LIMIT 1" );
			if ( $banner_id ) {
				$default = $banner_id;
				if ( ! empty( $BID ) ) {
					$BID = $default;
				}
			}
		}

		return intval( $default );
	}

	/**
	 * Get the banner id value.
	 *
	 * @param int $var
	 *
	 * @return float|int|string
	 */
	function bid( int $var = 0 ): float|int|string {
		$ret = $this->get_default_grid();

		if ( $var == 0 ) {
			global $wpdb;

			if ( isset( $_REQUEST['BID'] ) && ! empty( $_REQUEST['BID'] ) ) {
				// $_REQUEST['BID']
				$ret = $_REQUEST['BID'] == 'all' ? 'all' : intval( $_REQUEST['BID'] );
			} else if ( isset( $_REQUEST['aid'] ) && ! empty( $_REQUEST['aid'] ) ) {
				// $_REQUEST['aid']
				$mds_pixel_post = get_post( $_REQUEST['aid'] );
				if ( ! empty( $mds_pixel_post ) ) {
					$ret = intval( carbon_get_post_meta( intval( $_REQUEST['aid'] ), 'grid' ) );
				}
			} else if ( get_current_user_id() > 0 ) {
				// User is logged in
				$sql = "SELECT banner_id FROM " . MDS_DB_PREFIX . "orders WHERE user_id=%d AND (status='completed' OR status='expired') ORDER BY banner_id";
				$prepared_sql = $wpdb->prepare( $sql, get_current_user_id() );
				$result          = $wpdb->get_var( $prepared_sql );
				if ( $result !== null ) {
					$ret = $result;
				}
			}
		} else {
			if ( is_numeric( $var ) && $var > 0 ) {
				$ret = $var;
			} else if ( $var == 'all' ) {
				$ret = 'all';
			}
		}

		return $ret;
	}

	/**
	 * filters variables
	 * string:    FILTER_SANITIZE_STRING
	 * int:        FILTER_VALIDATE_INT
	 * email:    FILTER_VALIDATE_EMAIL
	 * url:        FILTER_VALIDATE_URL
	 * FILTER_SANITIZE_URL
	 * Remove all characters except letters, digits and $-_.+!*'(),{}|\\^~[]`<>#%";/?:@&=.
	 *
	 * Default:FILTER_SANITIZE_STRING
	 *
	 * example:
	 * $BID = $f2->filter($_REQUEST['BID'], "BID");
	 *
	 * more info: http://www.php.net/manual/en/filter.filters.php
	 */
	function filter( $var, $filter = null ) {

		// check for BID filter
		if ( $filter == "BID" ) {
			if ( isset( $var ) ) {
				if ( is_numeric( $var ) && $var > 0 ) {
					return $var;
				}
			}

			return "1";
		}

		// check for Y or N filter
		if ( $filter == "YN" ) {
			if ( isset( $var ) ) {
				if ( $var == strtoupper( "Y" ) || $var == strtoupper( "N" ) ) {
					return $var;
				} else {
					echo( "Invalid input" );
					die();
				}
			}
		}

		// check if var is empty first
		if ( empty( $var ) ) {
			return $var;
		}

		//echo $var . "<br />" . $filter . "<br />";

		// filter
		$var = htmlspecialchars( $var );

		// if filter_var returns false error out
		if ( $var === false ) {
			echo( "Invalid input" );
			die();
		}

		return $var;
	}

	/**
	 * Format for output.
	 *
	 * @param $value
	 * @param bool $stripslashes
	 * @param int $quotes
	 * @param string $encoding
	 *
	 * @return string
	 */
	function value( $value, $stripslashes = true, $quotes = ENT_QUOTES, $encoding = 'UTF-8' ): string {
		if ( $stripslashes ) {
			$value = stripslashes( $value );
		}

		return htmlspecialchars( $value, $quotes, $encoding );
	}

	/**
	 * Adds slashes to a string.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	function slashes( string $str ): string {
		return addcslashes( $str, "\\'" );
	}

	function write_log( $text ) {
		if ( Config::get( 'DEBUG' ) ) {
			$output_file = fopen( Config::get( 'MDS_LOG_FILE' ), 'a' );
			fwrite( $output_file, $text . "\n" );
			fclose( $output_file );
		}
	}

	/** debug */
	function debug( $line = "null", $label = "debug" ): void {

		// log file
		if ( Config::get( 'MDS_LOG' ) && file_exists( Config::get( 'MDS_LOG_FILE' ) ) ) {
			$entry_line = "[" . date( 'r' ) . "]	" . $line . "\r\n";
			$log_fp     = fopen( Config::get( 'MDS_LOG_FILE' ), "a" );
			fputs( $log_fp, $entry_line );
			fclose( $log_fp );
		}
	}

	function nl2html( $input ) {
		return str_replace( array( "\n", "\r" ), array( '<br />', '' ), htmlspecialchars( $input ) );
	}

	function nl2htmlraw( $input ) {
		return str_replace( array( "\n", "\r" ), array( '<br />', '' ), $input );
	}

	function rmnl( $input ) {
		return str_replace( array( "\n", "\r" ), '', htmlspecialchars( $input ) );
	}

	function rmnlraw( $input ) {
		return str_replace( array( "\n", "\r" ), '', $input );
	}

}

function get_banner_dir() {
	return wp_normalize_path( \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/" );
}
