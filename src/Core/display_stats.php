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
	$nfs = $row['COUNT'];
	$available = ( ( $banner_data['G_WIDTH'] * $banner_data['G_HEIGHT'] ) - $nfs ) - $sold;
} else {
	$nfs = $row['COUNT'] * ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
	$available = ( ( $banner_data['G_WIDTH'] * $banner_data['G_HEIGHT'] * ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] ) ) - $nfs ) - $sold;
}

if ( $label['sold_stats'] == '' ) {
	$label['sold_stats'] = "Sold";
}

if ( $label['available_stats'] == '' ) {
	$label['available_stats'] = "Available";
}

?><!DOCTYPE html>
<html>
<head>
    <title></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $f2->value( BASE_HTTP_PATH ); ?>css/main.css?ver=<?php echo filemtime( BASE_PATH . '/css/main.css' ); ?>">
</head>
<body class="status_body">
<div class="status">
    <b><?php echo $label['sold_stats']; ?>:</b> <span class="status_text"><?php echo number_format( $sold ); ?></span><br/>
    <b><?php echo $label['available_stats']; ?>:</b> <span class="status_text"><?php echo number_format( $available ); ?></span><br/>
</div>
</body>
</html>