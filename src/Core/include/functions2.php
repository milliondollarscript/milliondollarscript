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

class functions2 {

	function get_doc(): string {
		return '<!DOCTYPE html>
<html>
<head>
	<title> ' . SITE_NAME . '</title>
	<meta name="Description" content="' . SITE_SLOGAN . '">
	<meta http-equiv="content-type" content="text/html; charset=utf-8"/>';
	}

	function get_default_grid(): int {
		global $wpdb, $BID;
		$default = 1;

		// Check if first grid is enabled.
		$enabled = $wpdb->get_var( "SELECT `enabled` FROM `" . MDS_DB_PREFIX . "banners` WHERE `banner_id`=1" );
		if ( $enabled == 'N' ) {
			// First grid is not enabled, so try to find one that is.
			$banner_id = $wpdb->get_var( "SELECT `banner_id` FROM `" . MDS_DB_PREFIX . "banners` WHERE `enabled`='Y' ORDER BY `banner_id` ASC LIMIT 1" );
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
	 * @return int|string
	 */
	function bid( $var = 0 ) {
		$ret = $this->get_default_grid();

		if ( $var == 0 ) {

			if ( isset( $_REQUEST['BID'] ) && ! empty( $_REQUEST['BID'] ) ) {
				// $_REQUEST['BID']
				$ret = $_REQUEST['BID'];
			} else if ( isset( $_REQUEST['aid'] ) && ! empty( $_REQUEST['aid'] ) ) {
				// $_REQUEST['aid']
				$sql = "select banner_id from " . MDS_DB_PREFIX . "ads where ad_id='" . intval( $_REQUEST['aid'] ) . "'";
				$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
				if ( mysqli_num_rows( $res ) > 0 ) {
					$row = mysqli_fetch_array( $res );
					$ret = $row['banner_id'];
				}
			} else if ( isset( $_SESSION['MDS_ID'] ) && ! empty( $_SESSION['MDS_ID'] ) ) {
				// $_SESSION['MDS_ID']
				$sql = "select *, " . MDS_DB_PREFIX . "banners.banner_id AS BID FROM " . MDS_DB_PREFIX . "orders, " . MDS_DB_PREFIX . "banners where " . MDS_DB_PREFIX . "orders.banner_id=" . MDS_DB_PREFIX . "banners.banner_id  AND user_id=" . intval( $_SESSION['MDS_ID'] ) . " and (" . MDS_DB_PREFIX . "orders.status='completed' or " . MDS_DB_PREFIX . "orders.status='expired') group by " . MDS_DB_PREFIX . "orders.banner_id order by " . MDS_DB_PREFIX . "orders.banner_id ";
				$res = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
				if ( $res !== false && mysqli_num_rows( $res ) > 0 ) {
					$row = mysqli_fetch_array( $res );
					$ret = $row['BID'];
				}
			} else {
				// temp_orders
				$sql = "select * from " . MDS_DB_PREFIX . "temp_orders where session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], get_current_order_id() ) . "' ";
				$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mds_sql_error( $sql ) );
				if ( mysqli_num_rows( $order_result ) > 0 ) {
					$order_row = mysqli_fetch_array( $order_result );

					$ret = $order_row['banner_id'];
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
		if ( MDSConfig::get( 'DEBUG' ) === true ) {
			$output_file = fopen( MDSConfig::get( 'MDS_LOG_FILE' ), 'a' );
			fwrite( $output_file, $text . "\n" );
			fclose( $output_file );
		}
	}

	/** debug */
	function debug( $line = "null", $label = "debug" ) {

		// log file
		if ( MDSConfig::get( 'MDS_LOG' ) === true && file_exists( MDSConfig::get( 'MDS_LOG_FILE' ) ) ) {
			$entry_line = "[" . date( 'r' ) . "]	" . $line . "\r\n";
			$log_fp     = fopen( MDSConfig::get( 'MDS_LOG_FILE' ), "a" );
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
	return \MillionDollarScript\Classes\Utility::get_upload_path() . "grids/";
}
