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

	global $f2, $wpdb;

	echo "Updating config....";

	// Arrays of queries sorted by variable types for preparing for database.
	$types = [

		// strings
		's' => [
			'MDS_LOG_FILE',
			'BUILD_DATE',
			'VERSION_INFO',
			'BASE_HTTP_PATH',
			'BASE_PATH',
			'SERVER_PATH_TO_ADMIN',
			'UPLOAD_PATH',
			'UPLOAD_HTTP_PATH',
			'SITE_CONTACT_EMAIL',
			'SITE_LOGO_URL',
			'SITE_NAME',
			'SITE_SLOGAN',
			'MDS_RESIZE',
			'ADMIN_PASSWORD',
			'DATE_FORMAT',
			'GMT_DIF',
			'DATE_INPUT_SEQ',
			'OUTPUT_JPEG',
			'INTERLACE_SWITCH',
			'BANNER_DIR',
			'DISPLAY_PIXEL_BACKGROUND',
			'EMAIL_USER_ORDER_CONFIRMED',
			'EMAIL_ADMIN_ORDER_CONFIRMED',
			'EMAIL_USER_ORDER_COMPLETED',
			'EMAIL_ADMIN_ORDER_COMPLETED',
			'EMAIL_USER_ORDER_PENDED',
			'EMAIL_ADMIN_ORDER_PENDED',
			'EMAIL_USER_ORDER_EXPIRED',
			'EMAIL_ADMIN_ORDER_EXPIRED',
			'EM_NEEDS_ACTIVATION',
			'EMAIL_ADMIN_ACTIVATION',
			'EMAIL_ADMIN_PUBLISH_NOTIFY',
			'EMAIL_USER_EXPIRE_WARNING',
			'ENABLE_MOUSEOVER',
			'ENABLE_CLOAKING',
			'VALIDATE_LINK',
			'ADVANCED_CLICK_COUNT',
			'ADVANCED_VIEW_COUNT',
			'USE_SMTP',
			'EMAIL_SMTP_SERVER',
			'EMAIL_SMTP_USER',
			'EMAIL_SMTP_PASS',
			'EMAIL_SMTP_AUTH_HOST',
			'EMAIL_POP_SERVER',
			'EMAIL_POP_BEFORE_SMTP',
			'EMAIL_DEBUG',
			'USE_AJAX',
			'MEMORY_LIMIT',
			'ERROR_REPORTING',
			'REDIRECT_SWITCH',
			'REDIRECT_URL',
			'MDS_AGRESSIVE_CACHE',
			'BLOCK_SELECTION_MODE',
			'STATS_DISPLAY_MODE',
			'DISPLAY_ORDER_HISTORY',
			'INVERT_PIXELS',
			'WP_ENABLED',
			'WP_URL',
			'WP_PATH',
			'WP_USERS_ENABLED',
			'WP_ADMIN_ENABLED',
			'WP_USE_MAIL',
		],

		// integers
		'd' => [
			'DEBUG',
			'MDS_LOG',
			'JPEG_QUALITY',
			'EMAILS_DAYS_KEEP',
			'MINUTES_RENEW',
			'MINUTES_CONFIRMED',
			'MINUTES_UNCONFIRMED',
			'MINUTES_CANCEL',
			'SMTP_PORT',
			'POP3_PORT',
			'EMAIL_TLS',
			'EMAILS_PER_BATCH',
			'EMAILS_MAX_RETRY',
			'EMAILS_ERROR_WAIT',
		]

	];

	// Normalize paths
//	$_REQUEST['MDS_LOG_FILE']         = wp_normalize_path( $_REQUEST['MDS_LOG_FILE'] );
	$_REQUEST['BASE_PATH']            = wp_normalize_path( $_REQUEST['BASE_PATH'] );
	$_REQUEST['SERVER_PATH_TO_ADMIN'] = wp_normalize_path( $_REQUEST['SERVER_PATH_TO_ADMIN'] );
	$_REQUEST['UPLOAD_PATH']          = wp_normalize_path( $_REQUEST['UPLOAD_PATH'] );
	$_REQUEST['WP_PATH']              = wp_normalize_path( $_REQUEST['WP_PATH'] );

	$values = array_replace( MDSConfig::defaults(), $_REQUEST );

	foreach ( $types as $type => $keys ) {
		foreach ( $keys as $key ) {
			$wpdb->replace(
				MDS_DB_PREFIX . "config",
				[
					'key' => $key,
					'val' => $values[ $key ],
				],
				[
					'%s',
					'%' . $type,
				]
			);
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
