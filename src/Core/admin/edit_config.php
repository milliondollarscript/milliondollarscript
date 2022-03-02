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

require_once __DIR__ . '/admin_common.php';

if ( isset( $_REQUEST['save'] ) && $_REQUEST['save'] != '' ) {

	global $f2;

	echo "Updating config....";

	// Arrays of queries sorted by variable types for preparing for database.
	$types = [

		// strings
		's' => [
			'MDS_LOG_FILE'                => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('MDS_LOG_FILE', ?);",
			'BUILD_DATE'                  => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('BUILD_DATE', ?);",
			'VERSION_INFO'                => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('VERSION_INFO', ?);",
			'BASE_HTTP_PATH'              => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('BASE_HTTP_PATH', ?);",
			'BASE_PATH'                   => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('BASE_PATH', ?);",
			'SERVER_PATH_TO_ADMIN'        => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('SERVER_PATH_TO_ADMIN', ?);",
			'UPLOAD_PATH'                 => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('UPLOAD_PATH', ?);",
			'UPLOAD_HTTP_PATH'            => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('UPLOAD_HTTP_PATH', ?);",
			'SITE_CONTACT_EMAIL'          => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('SITE_CONTACT_EMAIL', ?);",
			'SITE_LOGO_URL'               => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('SITE_LOGO_URL', ?);",
			'SITE_NAME'                   => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('SITE_NAME', ?);",
			'SITE_SLOGAN'                 => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('SITE_SLOGAN', ?);",
			'MDS_RESIZE'                  => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('MDS_RESIZE', ?);",
			'ADMIN_PASSWORD'              => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('ADMIN_PASSWORD', ?);",
			'DATE_FORMAT'                 => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('DATE_FORMAT', ?);",
			'GMT_DIF'                     => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('GMT_DIF', ?);",
			'DATE_INPUT_SEQ'              => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('DATE_INPUT_SEQ', ?);",
			'OUTPUT_JPEG'                 => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('OUTPUT_JPEG', ?);",
			'INTERLACE_SWITCH'            => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('INTERLACE_SWITCH', ?);",
			'BANNER_DIR'                  => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('BANNER_DIR', ?);",
			'DISPLAY_PIXEL_BACKGROUND'    => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('DISPLAY_PIXEL_BACKGROUND', ?);",
			'EMAIL_USER_ORDER_CONFIRMED'  => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_USER_ORDER_CONFIRMED', ?);",
			'EMAIL_ADMIN_ORDER_CONFIRMED' => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_ADMIN_ORDER_CONFIRMED', ?);",
			'EMAIL_USER_ORDER_COMPLETED'  => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_USER_ORDER_COMPLETED', ?);",
			'EMAIL_ADMIN_ORDER_COMPLETED' => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_ADMIN_ORDER_COMPLETED', ?);",
			'EMAIL_USER_ORDER_PENDED'     => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_USER_ORDER_PENDED', ?);",
			'EMAIL_ADMIN_ORDER_PENDED'    => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_ADMIN_ORDER_PENDED', ?);",
			'EMAIL_USER_ORDER_EXPIRED'    => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_USER_ORDER_EXPIRED', ?);",
			'EMAIL_ADMIN_ORDER_EXPIRED'   => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_ADMIN_ORDER_EXPIRED', ?);",
			'EM_NEEDS_ACTIVATION'         => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EM_NEEDS_ACTIVATION', ?);",
			'EMAIL_ADMIN_ACTIVATION'      => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_ADMIN_ACTIVATION', ?);",
			'EMAIL_ADMIN_PUBLISH_NOTIFY'  => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_ADMIN_PUBLISH_NOTIFY', ?);",
			'EMAIL_USER_EXPIRE_WARNING'   => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_USER_EXPIRE_WARNING', ?);",
			'ENABLE_MOUSEOVER'            => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('ENABLE_MOUSEOVER', ?);",
			'ENABLE_CLOAKING'             => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('ENABLE_CLOAKING', ?);",
			'VALIDATE_LINK'               => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('VALIDATE_LINK', ?);",
			'ADVANCED_CLICK_COUNT'        => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('ADVANCED_CLICK_COUNT', ?);",
			'ADVANCED_VIEW_COUNT'         => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('ADVANCED_VIEW_COUNT', ?);",
			'USE_SMTP'                    => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('USE_SMTP', ?);",
			'EMAIL_SMTP_SERVER'           => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_SMTP_SERVER', ?);",
			'EMAIL_SMTP_USER'             => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_SMTP_USER', ?);",
			'EMAIL_SMTP_PASS'             => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_SMTP_PASS', ?);",
			'EMAIL_SMTP_AUTH_HOST'        => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_SMTP_AUTH_HOST', ?);",
			'EMAIL_POP_SERVER'            => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_POP_SERVER', ?);",
			'EMAIL_POP_BEFORE_SMTP'       => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_POP_BEFORE_SMTP', ?);",
			'EMAIL_DEBUG'                 => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_DEBUG', ?);",
			'USE_AJAX'                    => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('USE_AJAX', ?);",
			'MEMORY_LIMIT'                => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('MEMORY_LIMIT', ?);",
			'ERROR_REPORTING'             => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('ERROR_REPORTING', ?);",
			'REDIRECT_SWITCH'             => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('REDIRECT_SWITCH', ?);",
			'REDIRECT_URL'                => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('REDIRECT_URL', ?);",
			'MDS_AGRESSIVE_CACHE'         => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('MDS_AGRESSIVE_CACHE', ?);",
			'BLOCK_SELECTION_MODE'        => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('BLOCK_SELECTION_MODE', ?);",
			'STATS_DISPLAY_MODE'          => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('STATS_DISPLAY_MODE', ?);",
			'DISPLAY_ORDER_HISTORY'       => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('DISPLAY_ORDER_HISTORY', ?);",
			'WP_ENABLED'                  => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('WP_ENABLED', ?);",
			'WP_URL'                      => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('WP_URL', ?);",
			'WP_PATH'                     => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('WP_PATH', ?);",
			'WP_USERS_ENABLED'            => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('WP_USERS_ENABLED', ?);",
			'WP_ADMIN_ENABLED'            => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('WP_ADMIN_ENABLED', ?);",
			'WP_USE_MAIL'                 => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('WP_USE_MAIL', ?);"
		],

		// integers
		'i' => [
			'DEBUG'               => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('DEBUG', ?);",
			'MDS_LOG'             => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('MDS_LOG', ?);",
			'JPEG_QUALITY'        => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('JPEG_QUALITY', ?);",
			'EMAILS_DAYS_KEEP'    => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAILS_DAYS_KEEP', ?);",
			'DAYS_RENEW'          => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('DAYS_RENEW', ?);",
			'DAYS_CONFIRMED'      => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('DAYS_CONFIRMED', ?);",
			'MINUTES_UNCONFIRMED' => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('MINUTES_UNCONFIRMED', ?);",
			'DAYS_CANCEL'         => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('DAYS_CANCEL', ?);",
			'SMTP_PORT'           => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('SMTP_PORT', ?);",
			'POP3_PORT'           => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('POP3_PORT', ?);",
			'EMAIL_TLS'           => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAIL_TLS', ?);",
			'EMAILS_PER_BATCH'    => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAILS_PER_BATCH', ?);",
			'EMAILS_MAX_RETRY'    => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAILS_MAX_RETRY', ?);",
			'EMAILS_ERROR_WAIT'   => "REPLACE INTO `" . MDS_DB_PREFIX . "config` VALUES ('EMAILS_ERROR_WAIT', ?);",
		]

		// doubles

		// blobs
	];

	// Normalize paths
//	$_REQUEST['MDS_LOG_FILE']         = wp_normalize_path( $_REQUEST['MDS_LOG_FILE'] );
	$_REQUEST['BASE_PATH']            = wp_normalize_path( $_REQUEST['BASE_PATH'] );
	$_REQUEST['SERVER_PATH_TO_ADMIN'] = wp_normalize_path( $_REQUEST['SERVER_PATH_TO_ADMIN'] );
	$_REQUEST['UPLOAD_PATH']          = wp_normalize_path( $_REQUEST['UPLOAD_PATH'] );
	$_REQUEST['WP_PATH']              = wp_normalize_path( $_REQUEST['WP_PATH'] );

	$values = array_replace( MDSConfig::defaults(), $_REQUEST );

	foreach ( $types as $type => $queries ) {
		foreach ( $queries as $key => $query ) {
			$stmt = mysqli_stmt_init( $GLOBALS['connection'] );
			if ( ! mysqli_stmt_prepare( $stmt, $query ) ) {
				die ( mds_sql_error( $query ) );
			}

			$var = $values[ $key ];
			mysqli_stmt_bind_param( $stmt, $type, $var );

			mysqli_stmt_execute( $stmt );
			$res   = mysqli_stmt_get_result( $stmt );
			$error = mysqli_stmt_error( $stmt );
			if ( ! empty( $error ) ) {
				die ( mds_sql_error( $query ) );
			}
			mysqli_stmt_close( $stmt );
		}
	}

	?>
    <script>
		$(function () {
			$(document).scrollTop(0);
			window.location.reload();
		});
    </script>
	<?php
}

require_once __DIR__ . "/../include/init.php";

?>

<h3>Main Configuration</h3>
<p>Options on this page affect the running of the pixel advertising system.</p>
<p><b>Tip:</b> Looking for where to settings for the grid? It is set in 'Pixel Inventory' -> <a href="inventory.php">Manage Grids</a>. Click on Edit to edit the grid parameters.</p>
<p>
	<?php
	require( __DIR__ . '/config_form.php' );
	?>
</p>
