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

define( 'NO_HOUSE_KEEP', 'YES' );

require_once __DIR__ . "/include/init.php";

$block_id = $_REQUEST['block_id'];
if ( $block_id == '' ) {
	die();
}

global $f2;
$BID = $f2->bid();

$block_id = intval( $block_id );

$tag_to_field_id = get_tag_to_field_id( 1 );
$field_id        = intval( $tag_to_field_id['URL']['field_id'] );

$sql    = "SELECT t1.{$field_id}, t1.user_id FROM " . MDS_DB_PREFIX . "ads AS t1 INNER JOIN " . MDS_DB_PREFIX . "blocks AS t2 ON t1.ad_id = t2.ad_id WHERE t2.block_id={$block_id};";
$result = @mysqli_query( $GLOBALS['connection'], $sql );
$row    = @mysqli_fetch_array( $result );

// basic click count.

$sql = "UPDATE " . MDS_DB_PREFIX . "users SET click_count = click_count + 1 where ID='" . intval( $row['user_id'] ) . "'  ";

$result = @mysqli_query( $GLOBALS['connection'], $sql );

$ADVANCED_CLICK_COUNT = MDSConfig::get( 'ADVANCED_CLICK_COUNT' );
if ( $ADVANCED_CLICK_COUNT == 'YES' ) {

	$date   = gmdate( 'Y' ) . "-" . gmdate( 'm' ) . "-" . gmdate( 'd' );
	$sql    = "UPDATE " . MDS_DB_PREFIX . "clicks set clicks = clicks + 1 where banner_id='$BID' AND `date`='$date' AND `block_id`='" . $block_id . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql );
	$x      = @mysqli_affected_rows( $GLOBALS['connection'] );

	if ( ! $x ) {

		$sql    = "INSERT into " . MDS_DB_PREFIX . "clicks (`banner_id`, `date`, `clicks`, `block_id`, `user_id`) VALUES('$BID', '$date', '1', '$block_id', '" . intval( $row['user_id'] ) . "') ";
		$result = @mysqli_query( $GLOBALS['connection'], $sql );
	}
}

$sql = "UPDATE " . MDS_DB_PREFIX . "blocks SET click_count = click_count + 1 where block_id='" . $block_id . "' AND banner_id='$BID' ";
//echo $sql;
$result = mysqli_query( $GLOBALS['connection'], $sql );

header( "Location: http://" . $row[ $field_id ] );

?>