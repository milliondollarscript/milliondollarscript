<?php
/*
 * Million Dollar Script Two
 *
 * @version     2.5.2
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

use MillionDollarScript\Classes\Language;

defined( 'ABSPATH' ) or exit;

global $f2;
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

?><!DOCTYPE html>
<html>
<head>
    <title></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $f2->value( MDS_CORE_URL ); ?>css/main.css?ver=<?php echo filemtime( MDS_CORE_PATH . 'css/main.css' ); ?>">
</head>
<body class="status_body">
<div class="status">
	<?php
	Language::out_replace( '%SOLD%', number_format( $sold ), '<b>Sold:</b> <span class="status_text">%SOLD%</span><br/>' );
	Language::out_replace( '%AVAILABLE%', number_format( $available ), '<b>Available:</b> <span class="status_text">%AVAILABLE%</span><br/>' );
	?>
</div>
</body>
</html>