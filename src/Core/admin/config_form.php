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

?>
<form method="POST" name="form1" action="index.php?page=edit-main">
    <p><input type="submit" value="Save Configuration" name="save"></p>
    <input name="build_date" type="hidden" value="<?php echo MDSConfig::get( 'BUILD_DATE', true ); ?>"/>
    <input name="version_info" type="hidden" value="<?php echo MDSConfig::get( 'VERSION_INFO', true ); ?>"/>

    <div class="admin-config">
        <div class="admin-config-heading">
            General Settings
        </div>

        <div class="admin-config-left">
            Site Name
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="SITE_NAME" size="49" value="<?php echo MDSConfig::get( 'SITE_NAME', true ); ?>"/>
            </label>
        </div>

        <div class="admin-config-left">
            Site Slogan
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="SITE_SLOGAN" size="49" value="<?php echo MDSConfig::get( 'SITE_SLOGAN', true ); ?>"/>
            </label>
        </div>

        <div class="admin-config-left">
            Site Logo URL
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="SITE_LOGO_URL" size="49" value="<?php echo MDSConfig::get( 'SITE_LOGO_URL', true ); ?>"/>
                <br/>(https://www.example.com/images/logo.gif)
            </label>
        </div>

        <div class="admin-config-left">
            Site Contact Email
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="SITE_CONTACT_EMAIL" size="49" value="<?php echo MDSConfig::get( 'SITE_CONTACT_EMAIL', true ); ?>"/>
                <br/>(Please ensure that this email address has a POP account for extra email delivery reliability.)
            </label>
        </div>

        <div class="admin-config-left">
            Admin Password
        </div>
        <div class="admin-config-right">
            <label>
                <input type="password" name="ADMIN_PASSWORD" size="49" value="<?php echo MDSConfig::get( 'ADMIN_PASSWORD', true ); ?>"/>
            </label>
        </div>

    </div>

    <div class="admin-config">
        <div class="admin-config-heading">
            Paths and Locations
        </div>

        <div class="admin-config-left">
            Site's HTTP URL (address)
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="BASE_HTTP_PATH" size="55" value="<?php echo MDSConfig::get( 'BASE_HTTP_PATH', true ); ?>"/>
            </label>
        </div>

        <div class="admin-config-left">
            Server Path to MDS Root Directory
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="BASE_PATH" size="55" value="<?php echo MDSConfig::get( 'BASE_PATH', true ); ?>"/>
            </label>
        </div>

        <div class="admin-config-left">
            Server Path to Admin
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="SERVER_PATH_TO_ADMIN" size="55" value="<?php echo MDSConfig::get( 'SERVER_PATH_TO_ADMIN', true ); ?>"/>
            </label>
        </div>

        <div class="admin-config-left">
            Path to upload directory
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="UPLOAD_PATH" size="55" value="<?php echo MDSConfig::get( 'UPLOAD_PATH', true ); ?>"/>
            </label>
        </div>

        <div class="admin-config-left">
            HTTP URL to upload directory
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="UPLOAD_HTTP_PATH" size="55" value="<?php echo MDSConfig::get( 'UPLOAD_HTTP_PATH', true ); ?>"/>
            </label>
        </div>

        <div class="admin-config-footer">
            NOTES<br/>
            - Server Path to Admin is the full path to your admin directory, <span style="color: red; ">including a slash at the end</span><br/>
            - The Site's HTTP URL must include a <span style="color: red; ">slash at the end</span><br/>
            - Use the recommended settings unless you are sure otherwise<br/>
        </div>
    </div>

    <div class="admin-config">
        <div class="admin-config-heading">
            Localization - Time and Date
        </div>

        <div class="admin-config-left">
            Display Date Format
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="DATE_FORMAT" size="49" value="<?php echo MDSConfig::get( 'DATE_FORMAT', true ); ?>"/>
                <br/> Note: See <a href="https://www.php.net/manual/en/function.date.php" target="_blank">here</a> for formatting info.
            </label>
        </div>

        <div class="admin-config-left">
            Display Time Format
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="TIME_FORMAT" size="49" value="<?php echo MDSConfig::get( 'TIME_FORMAT', true ); ?>"/>
                <br/> Note: See <a href="https://www.php.net/manual/en/function.date.php" target="_blank">here</a> for formatting info.
            </label>
        </div>

        <div class="admin-config-left">
            Input Date Sequence
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="DATE_INPUT_SEQ" size="49" value="<?php echo MDSConfig::get( 'DATE_INPUT_SEQ', true ); ?>"/>
                <br/>Eg. YMD for the international date standard (ISO 8601). The sequence should always contain one D, one M and one Y only, in any order.
            </label>
        </div>

        <div class="admin-config-left">
            GMT Difference
        </div>
        <div class="admin-config-right">
            <label>
                <select name="GMT_DIF">
					<?php
					$GMT_DIF              = MDSConfig::get( 'GMT_DIF' );
					$timezone_identifiers = DateTimeZone::listIdentifiers();
					for ( $i = 0; $i < count( $timezone_identifiers ); $i ++ ) {
						?>
                        <option value="<?php echo $timezone_identifiers[ $i ]; ?>" <?php echo( $GMT_DIF == $timezone_identifiers[ $i ] ? " selected " : '' ); ?> >
							<?php echo $timezone_identifiers[ $i ]; ?>
                        </option>
						<?php
					}
					?>
                </select>
            </label>
        </div>
    </div>

    <div class="admin-config">
        <div class="admin-config-heading">
            Grid Image Settings
        </div>

        <div class="admin-config-left">
            Output Grid Image(s) As
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$OUTPUT_JPEG = MDSConfig::get( 'OUTPUT_JPEG' );
				?>
                <input type="radio" name="OUTPUT_JPEG" value="Y" <?php echo( $OUTPUT_JPEG == 'Y' ? 'checked' : '' ); ?> /> JPEG (Lossy compression). JPEG Quality: <input type="text" name='JPEG_QUALITY' value="<?php echo MDSConfig::get( 'JPEG_QUALITY', true ); ?>" size="2" />%<br/>
                <input type="radio" name="OUTPUT_JPEG" value="N" <?php echo( $OUTPUT_JPEG == 'N' ? 'checked' : '' ); ?> /> PNG (Non-lossy compression, always highest possible quality)<br/>
                <input type="radio" name="OUTPUT_JPEG" value="GIF" <?php echo( $OUTPUT_JPEG == 'GIF' ? 'checked' : '' ); ?> /> GIF (8-bit, 256 color. Supported on newer versions of GD / PHP)
            </label>
        </div>

        <div class="admin-config-left">
            Interlace Grid Image?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$INTERLACE_SWITCH = MDSConfig::get( 'INTERLACE_SWITCH' );
				?>
                <input type="radio" name="INTERLACE_SWITCH" size="49" value="YES" <?php echo( $INTERLACE_SWITCH == 'YES' ? 'checked' : '' ); ?> /> Yes (Parts of the grid are 'previewed' while loading) Only works well for PNG or GIF. IE has a bug for JPG files)<br/>
                <input type="radio" name="INTERLACE_SWITCH" value="NO" <?php echo( $INTERLACE_SWITCH == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Display a grid in the background?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$DISPLAY_PIXEL_BACKGROUND = MDSConfig::get( 'DISPLAY_PIXEL_BACKGROUND' );
				?>
                <input type="radio" name="DISPLAY_PIXEL_BACKGROUND" size="49" value="YES" <?php echo( $DISPLAY_PIXEL_BACKGROUND == 'YES' ? 'checked' : '' ); ?> /> Yes. (A blank pixel grid is loaded almost instantly, and then the real pixel grid is loaded on top)<br/>
                <input type="radio" name="DISPLAY_PIXEL_BACKGROUND" value="NO" <?php echo( $DISPLAY_PIXEL_BACKGROUND == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Pixel Selection Method
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$USE_AJAX = MDSConfig::get( 'USE_AJAX' );
				?>
                <input type="radio" name="USE_AJAX" value="SIMPLE" <?php echo( $USE_AJAX == 'SIMPLE' ? 'checked' : '' ); ?> /> Simple (Upload whole image at a time, users can start ordering without logging in. Uses AJAX. Recommended.)<br/>
                <input type="radio" name="USE_AJAX" value="YES" <?php echo( $USE_AJAX == 'YES' ? 'checked' : '' ); ?> /> Advanced (Select individual blocks. Uses AJAX)<br/>
                <input type="radio" name="USE_AJAX" value="NO" <?php echo( $USE_AJAX == 'NO' ? 'checked' : '' ); ?> /> Advanced, no AJAX
            </label>
        </div>

        <div class="admin-config-left">
            Resize uploaded pixels automatically?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$MDS_RESIZE = MDSConfig::get( 'MDS_RESIZE' );
				?>
                <input type="radio" name="MDS_RESIZE" size="49" value="YES" <?php echo( $MDS_RESIZE == 'YES' ? 'checked' : '' ); ?> /> Yes. Uploaded pixels will be resized to fit in to the blocks<br/>
                <input type="radio" name="MDS_RESIZE" value="NO" <?php echo( $MDS_RESIZE == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Output processed images to:
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="BANNER_DIR" size="55" value="<?php echo MDSConfig::get( 'BANNER_DIR', true ); ?>"/>
                <br/>(Be aware that some AdBlocker software blindly blocks anything coming from banners/ directory. Please make sure that this directory exists in the main directory of the script and has write permissions, chmod 777)
            </label>
        </div>

        <div class="admin-config-footer">
            <p>NOTES<br/>
                If you have just installed your script or changed the above output directory, you
                will need to process your grids(s) from the Pixel Admin section or else your images will
                not appear.
            </p>
        </div>
    </div>

    <div class="admin-config">
        <div class="admin-config-heading">
            Email Settings
        </div>

        <div class="admin-config-left">
            Email Advertiser when an order is Confirmed?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_USER_ORDER_CONFIRMED = MDSConfig::get( 'EMAIL_USER_ORDER_CONFIRMED' );
				?>
                <input type="radio" name="EMAIL_USER_ORDER_CONFIRMED" value="YES" <?php echo( $EMAIL_USER_ORDER_CONFIRMED == 'YES' ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_USER_ORDER_CONFIRMED" value="NO" <?php echo( $EMAIL_USER_ORDER_CONFIRMED == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Email Admin when an order is Confirmed?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_ADMIN_ORDER_CONFIRMED = MDSConfig::get( 'EMAIL_ADMIN_ORDER_CONFIRMED' );
				?>
                <input type="radio" name="EMAIL_ADMIN_ORDER_CONFIRMED" value="YES" <?php echo( $EMAIL_ADMIN_ORDER_CONFIRMED == 'YES' ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_ADMIN_ORDER_CONFIRMED" value="NO" <?php echo( $EMAIL_ADMIN_ORDER_CONFIRMED == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Email Admin when an order is Completed?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_ADMIN_ORDER_COMPLETED = MDSConfig::get( 'EMAIL_ADMIN_ORDER_COMPLETED' );
				?>
                <input type="radio" name="EMAIL_ADMIN_ORDER_COMPLETED" value="YES" <?php echo( $EMAIL_ADMIN_ORDER_COMPLETED == 'YES' ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_ADMIN_ORDER_COMPLETED" value="NO" <?php echo( $EMAIL_ADMIN_ORDER_COMPLETED == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Email Admin when an order is Pended?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_ADMIN_ORDER_PENDED = MDSConfig::get( 'EMAIL_ADMIN_ORDER_PENDED' );
				?>
                <input type="radio" name="EMAIL_ADMIN_ORDER_PENDED" value="YES" <?php echo( $EMAIL_ADMIN_ORDER_PENDED == 'YES' ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_ADMIN_ORDER_PENDED" value="NO" <?php echo( $EMAIL_ADMIN_ORDER_PENDED == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Email Advertiser when an order is Expired?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_USER_ORDER_EXPIRED = MDSConfig::get( 'EMAIL_USER_ORDER_EXPIRED' );
				?>
                <input type="radio" name="EMAIL_USER_ORDER_EXPIRED" value="YES" <?php echo( $EMAIL_USER_ORDER_EXPIRED == 'YES' ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_USER_ORDER_EXPIRED" value="NO" <?php echo( $EMAIL_USER_ORDER_EXPIRED == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Email Admin when an order is Expired?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_ADMIN_ORDER_EXPIRED = MDSConfig::get( 'EMAIL_ADMIN_ORDER_EXPIRED' );
				?>
                <input type="radio" name="EMAIL_ADMIN_ORDER_EXPIRED" value="YES" <?php echo( $EMAIL_ADMIN_ORDER_EXPIRED == 'YES' ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_ADMIN_ORDER_EXPIRED" value="NO" <?php echo( $EMAIL_ADMIN_ORDER_EXPIRED == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Send validation email to user?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EM_NEEDS_ACTIVATION = MDSConfig::get( 'EM_NEEDS_ACTIVATION' );
				?>
                <input type="radio" name="EM_NEEDS_ACTIVATION" value="YES" <?php echo( $EM_NEEDS_ACTIVATION == 'YES' ? 'checked' : '' ); ?> /> Yes - users need to validate their account before loging in.<br/>
                <input type="radio" name="EM_NEEDS_ACTIVATION" value="AUTO" <?php echo( $EM_NEEDS_ACTIVATION == 'AUTO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Send validation email to Admin?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_ADMIN_ACTIVATION = MDSConfig::get( 'EMAIL_ADMIN_ACTIVATION' );
				?>
                <input type="radio" name="EMAIL_ADMIN_ACTIVATION" value="YES" <?php echo( $EMAIL_ADMIN_ACTIVATION == 'YES' ? 'checked' : '' ); ?> /> Yes. When a user signs up, a copy of the validation email is sent to admin.<br/>
                <input type="radio" name="EMAIL_ADMIN_ACTIVATION" value="NO" <?php echo( $EMAIL_ADMIN_ACTIVATION == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Pixels Modified: Send a notification email to Admin?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_ADMIN_PUBLISH_NOTIFY = MDSConfig::get( 'EMAIL_ADMIN_PUBLISH_NOTIFY' );
				?>
                <input type="radio" name="EMAIL_ADMIN_PUBLISH_NOTIFY" value="YES" <?php echo( $EMAIL_ADMIN_PUBLISH_NOTIFY == 'YES' ? 'checked' : '' ); ?> /> Yes. When a user modifies their pixels, a notification email is sent to admin.<br/>
                <input type="radio" name="EMAIL_ADMIN_PUBLISH_NOTIFY" value="NO" <?php echo( $EMAIL_ADMIN_PUBLISH_NOTIFY == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>
    </div>

    <div class="admin-config">
        <div class="admin-config-heading">
            SMTP Settings
        </div>

        <div class="admin-config-left">
            Email Advertiser when an order is Confirmed?
        </div>
        <div class="admin-config-right">
            <label>
                <input type="checkbox" name="USE_SMTP" value="YES" <?php echo( MDSConfig::get( 'USE_SMTP' ) == 'YES' ? 'checked' : '' ); ?>>
                Enable SMTP Server. (All outgoing email will be sent via authenticated SMTP server connection. By default, the email is sent using the PHP mail() function, and there is no need to turn this option on. Please make sure to fill in all the fields if you enable this option. POP port setting is used to verify that the script can connect to a POP account to check if the username and password was correctly filled in when the test button is clicked. )
            </label>
        </div>

        <div class="admin-config-left">
            SMTP Server address
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="EMAIL_SMTP_SERVER" size="33" value="<?php echo MDSConfig::get( 'EMAIL_SMTP_SERVER', true ); ?>">
                <br/>Eg. mail.example.com
            </label>
        </div>

        <div class="admin-config-left">
            POP3 Server address
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="EMAIL_POP_SERVER" size="33" value="<?php echo MDSConfig::get( 'EMAIL_POP_SERVER', true ); ?>"/>
                <br/>Eg. mail.example.com, usually the same as the SMTP server.
            </label>
        </div>

        <div class="admin-config-left">
            SMTP/POP3 Username
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="EMAIL_SMTP_USER" size="33" value="<?php echo MDSConfig::get( 'EMAIL_SMTP_USER', true ); ?>"/>
            </label>
        </div>

        <div class="admin-config-left">
            SMTP/POP3 Password
        </div>
        <div class="admin-config-right">
            <label>
                <input type="password" name="EMAIL_SMTP_PASS" size="33" value="<?php echo MDSConfig::get( 'EMAIL_SMTP_PASS', true ); ?>"/>
            </label>
        </div>

        <div class="admin-config-left">
            SMTP Authentication Hostname
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="EMAIL_SMTP_AUTH_HOST" size="33" value="<?php echo MDSConfig::get( 'EMAIL_SMTP_AUTH_HOST', true ); ?>"/>
                <br/>(This is usually the same as your SMTP Server address)
            </label>
        </div>

        <div class="admin-config-left">
            SMTP Port
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="SMTP_PORT" size="33" value="<?php echo intval( MDSConfig::get( 'SMTP_PORT', true ) ); ?>"/>
                <br/>(Leave blank to default to 465)
            </label>
        </div>

        <div class="admin-config-left">
            POP3 Port
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="POP3_PORT" size="33" value="<?php echo intval( MDSConfig::get( 'POP3_PORT', true ) ); ?>"/>
                <br/>(Leave blank to default to 995)
            </label>
        </div>

        <div class="admin-config-left">
            Enable email TLS/SSL
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_TLS = MDSConfig::get( 'EMAIL_TLS' );
				?>
                <input type="radio" name="EMAIL_TLS" value="1" <?php echo( $EMAIL_TLS == 1 ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_TLS" value="0" <?php echo( $EMAIL_TLS == 0 ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            My SMTP server uses the POP-before-SMTP mechanism
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_POP_BEFORE_SMTP = MDSConfig::get( 'EMAIL_POP_BEFORE_SMTP' );
				?>
                <input type="radio" name="EMAIL_POP_BEFORE_SMTP" value="YES" <?php echo( $EMAIL_POP_BEFORE_SMTP == 'YES' ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_POP_BEFORE_SMTP" value="NO" <?php echo( $EMAIL_POP_BEFORE_SMTP == 'NO' ? 'checked' : '' ); ?> /> No - Default setting, correct 99% of cases
            </label>
        </div>

        <div class="admin-config-left">
            Enable email debug (saves file in /mail/.maildebug.log)
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_DEBUG = MDSConfig::get( 'EMAIL_DEBUG' );
				?>
                <input type="radio" name="EMAIL_DEBUG" value="YES" <?php echo( $EMAIL_DEBUG == 'YES' ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_DEBUG" value="NO" <?php echo( $EMAIL_DEBUG == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Outgoing email queue settings
        </div>
        <div class="admin-config-right">
			<?php
			$new_window = "onclick=\"test_email_window(); return false;\"";
			?>
            <label>
                Send a maxiumum of <input type="text" name="EMAILS_PER_BATCH" size="3" value="<?php echo intval( MDSConfig::get( 'EMAILS_PER_BATCH' ) ); ?>">emails per batch (enter a number > 0)<br/>
                On error, retry <input type="text" name="EMAILS_MAX_RETRY" size="3" value="<?php echo intval( MDSConfig::get( 'EMAILS_MAX_RETRY' ) ); ?>"> times before giving up. (recommened: 15)<br/>
                On error, wait at least <input type="text" name="EMAILS_ERROR_WAIT" size="3" value="<?php echo intval( MDSConfig::get( 'EMAILS_ERROR_WAIT' ) ); ?>">minutes before retry. (20 minutes recommended)<br/>
                Keep sent emails for <input type="text" name="EMAILS_DAYS_KEEP" size="3" value="<?php
				echo intval( MDSConfig::get( 'EMAILS_DAYS_KEEP' ) ); ?>">days. (0 = keep forever)<br/>
                Note: You can view the outgoing queue under the 'Report' menu<br/>
            </label>
        </div>
    </div>

    <div class="admin-config">
        <div class="admin-config-heading">
            Misc Settings
        </div>

        <div class="admin-config-left">
            How many days to keep Expired orders before cancellation?
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="DAYS_RENEW" size="2" value="<?php echo intval( MDSConfig::get( 'DAYS_RENEW' ) ); ?>"/>
                <br/>(Enter a number. 0 = Do not cancel)
            </label>
        </div>

        <div class="admin-config-left">
            How many days to keep Confirmed (but not paid) orders before cancellation?
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="DAYS_CONFIRMED" size="2" value="<?php echo intval( MDSConfig::get( 'DAYS_CONFIRMED' ) ); ?>">
                <br/>(Enter a number. 0 = never cancel)
            </label>
        </div>

        <div class="admin-config-left">
            How many <b>minutes</b> to keep Unconfirmed orders before deletion?
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="MINUTES_UNCONFIRMED" size="2" value="<?php echo intval( MDSConfig::get( 'MINUTES_UNCONFIRMED' ) ); ?>">
                <br/>(Enter a number. 0 = never delete)
            </label>
        </div>

        <div class="admin-config-left">
            How many days to keep Cancelled orders before deletion?
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="DAYS_CANCEL" size="2" value="<?php echo intval( MDSConfig::get( 'DAYS_CANCEL' ) ); ?>"/>
                <br/>(Enter a number. 0 = never delete. Note: If deleted, the order will stay in the database, and only the status will simply change to 'deleted'. The blocks will be freed)
            </label>
        </div>

        <div class="admin-config-left">
            Enable URL cloaking?<br/>(Supposedly, when enabled, the advertiser's link will get a better advantage from search engines.)
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$ENABLE_CLOAKING = MDSConfig::get( 'ENABLE_CLOAKING' );
				?>
                <input type="radio" name="ENABLE_CLOAKING" value="YES" <?php echo( $ENABLE_CLOAKING == 'YES' ? 'checked' : '' ); ?> /> Yes - All links will point directly to the Advertiser's URL. Click tracking will be managed by a JavaScript.) <br/>
                <input type="radio" name="ENABLE_CLOAKING" value="NO" <?php echo( $ENABLE_CLOAKING == 'NO' ? 'checked' : '' ); ?> /> No - All links will be re-directed click.php
            </label>
        </div>

        <div class="admin-config-left">
            Validate URLs by connecting to them?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$VALIDATE_LINK = MDSConfig::get( 'VALIDATE_LINK' );
				?>
                <input type="radio" name="VALIDATE_LINK" value="YES" <?php echo( $VALIDATE_LINK == 'YES' ? 'checked' : '' ); ?> /> Yes - The script will try to connect to the Advertiser's url to make sure that the link is correct.) <br/>
                <input type="radio" name="VALIDATE_LINK" value="NO" <?php echo( $VALIDATE_LINK == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Memory Limit
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$MEMORY_LIMIT = MDSConfig::get( 'MEMORY_LIMIT' );
				?>
                <input type='radio' name='MEMORY_LIMIT' value='32M' <?php echo( $MEMORY_LIMIT == '32M' ? 'checked' : '' ); ?> /> 32MB
                | <input type='radio' name='MEMORY_LIMIT' value='64M' <?php echo( $MEMORY_LIMIT == '64M' ? 'checked' : '' ); ?> /> 64MB
                | <input type='radio' name='MEMORY_LIMIT' value='128M' <?php echo( $MEMORY_LIMIT == '128M' ? 'checked' : '' ); ?> /> 128M
                | <input type='radio' name='MEMORY_LIMIT' value='256M' <?php echo( $MEMORY_LIMIT == '256M' ? 'checked' : '' ); ?> /> 256M
                | <input type='radio' name='MEMORY_LIMIT' value='512M' <?php echo( $MEMORY_LIMIT == '512M' ? 'checked' : '' ); ?> /> 512M
                | <input type='radio' name='MEMORY_LIMIT' value='1024M' <?php echo( $MEMORY_LIMIT == '1024M' ? 'checked' : '' ); ?> /> 1024M
                <br/>(Note: If your script is reporting a 'memory exhausted' error, please check to make sure that you have currectly defined your grid size)
            </label>
        </div>

        <div class="admin-config-left">
            Error Reporting
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="ERROR_REPORTING" size="40" value="<?php echo MDSConfig::get( 'ERROR_REPORTING', true ); ?>">
                <br/>(PHP error_reporting value) <a target="_blank" href="https://php.net/manual/en/function.error-reporting.php">More information</a>
            </label>
        </div>

        <div class="admin-config-left">
            Redirect when a user clicks on available block?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$REDIRECT_SWITCH = MDSConfig::get( 'REDIRECT_SWITCH' );
				?>
                <input type="radio" name="REDIRECT_SWITCH" value="YES" <?php echo( $REDIRECT_SWITCH == 'YES' ? 'checked' : '' ); ?> /> Yes - When an available block is clicked, redirect to: <input type="text" name="REDIRECT_URL" size="30" value="<?php echo MDSConfig::get( 'REDIRECT_URL', true ); ?>">
                <br/>Note: This option will only work for grids that are on the same domain as the script (browser security). If the grid is placed on another domain, IE may report a 'permission denied' JavaScript error. <br/>
                <input type="radio" name="REDIRECT_SWITCH" value="NO" <?php echo( $REDIRECT_SWITCH == 'NO' ? 'checked' : '' ); ?> /> No (default)<br/>
                Note: You will need to process your grids after changing this option.
            </label>
        </div>

        <div class="admin-config-left">
            Advanced Click Count?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$ADVANCED_CLICK_COUNT = MDSConfig::get( 'ADVANCED_CLICK_COUNT' );
				?>
                <input type="radio" name="ADVANCED_CLICK_COUNT" value="YES" <?php echo( $ADVANCED_CLICK_COUNT == 'YES' ? 'checked' : '' ); ?> /> Yes - Clicks (clicking the link on the popup) will be counted by day <br/>
                <input type="radio" name="ADVANCED_CLICK_COUNT" value="NO" <?php echo( $ADVANCED_CLICK_COUNT == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Advanced View Count?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$ADVANCED_VIEW_COUNT = MDSConfig::get( 'ADVANCED_VIEW_COUNT' );
				?>
                <input type="radio" name="ADVANCED_VIEW_COUNT" value="YES" <?php echo( $ADVANCED_VIEW_COUNT == 'YES' ? 'checked' : '' ); ?> /> Yes - Views (clicking the block to trigger the popup) will be counted by day <br/>
                <input type="radio" name="ADVANCED_VIEW_COUNT" value="NO" <?php echo( $ADVANCED_VIEW_COUNT == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Force the browser to cache the HTML image map?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$MDS_AGRESSIVE_CACHE = MDSConfig::get( 'MDS_AGRESSIVE_CACHE' );
				?>
                <input type="radio" name="MDS_AGRESSIVE_CACHE" value="YES" <?php echo( $MDS_AGRESSIVE_CACHE == 'YES' ? 'checked' : '' ); ?> /> Yes - The script will tell the browser to cache the page to save download time. This feature may only work on Apache based servers. Disable if your grid does not refresh, even though you processed the pixels.<br/>
                <input type="radio" name="MDS_AGRESSIVE_CACHE" value="NO" <?php echo( $MDS_AGRESSIVE_CACHE == 'NO' ? 'checked' : '' ); ?> /> No (default)
            </label>
        </div>

        <div class="admin-config-left">
            Show block Selection Mode (1, 4, 6 blocks)
        </div>
        <div class="admin-config-right">
            <label>
			    <?php
			    $BLOCK_SELECTION_MODE = MDSConfig::get( 'BLOCK_SELECTION_MODE' );
			    ?>
                <input type="radio" name="BLOCK_SELECTION_MODE" value="YES" <?php echo( $BLOCK_SELECTION_MODE == 'YES' ? 'checked' : '' ); ?> /> Yes - When Pixel Selection Method is set to Advanced users will be shown an option to select blocks 1, 4 or 6 at a time.<br/>
                <input type="radio" name="BLOCK_SELECTION_MODE" value="NO" <?php echo( $BLOCK_SELECTION_MODE == 'NO' ? 'checked' : '' ); ?> /> No - They will only be able to select 1 block at a time. If you want to adjust how large of block they can select you can edit the grid settings under Manage Grids.
            </label>
        </div>

        <div class="admin-config-left">
            Display stats as blocks or pixels
        </div>
        <div class="admin-config-right">
            <label>
			    <?php
			    $STATS_DISPLAY_MODE = MDSConfig::get( 'STATS_DISPLAY_MODE' );
			    ?>
                <input type="radio" name="STATS_DISPLAY_MODE" value="BLOCKS" <?php echo( $STATS_DISPLAY_MODE == 'BLOCKS' ? 'checked' : '' ); ?> /> Blocks - Stats box will display sold/available blocks.<br/>
                <input type="radio" name="STATS_DISPLAY_MODE" value="PIXELS" <?php echo( $STATS_DISPLAY_MODE == 'PIXELS' ? 'checked' : '' ); ?> /> Pixels - Stats box will display sold/available pixels.
            </label>
        </div>
    </div>

    <div class="admin-config">
        <div class="admin-config-heading">
            Tooltip box
        </div>

        <div class="admin-config-left">
            Show a box when the clicking a block?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$ENABLE_MOUSEOVER = MDSConfig::get( 'ENABLE_MOUSEOVER' );
				?>
                <input type="radio" name="ENABLE_MOUSEOVER" value="POPUP" <?php echo( $ENABLE_MOUSEOVER == 'POPUP' ? 'checked' : '' ); ?> /> Yes - Simple popup box, with no animation<br/>
                <input type="radio" name="ENABLE_MOUSEOVER" value="NO" <?php echo( $ENABLE_MOUSEOVER == 'NO' ? 'checked' : '' ); ?> /> No, turn off
            </label>
        </div>
    </div>

    <div class="admin-config">
        <div class="admin-config-heading">
            Integrations
        </div>

        <div class="admin-config-left">
            WordPress Integration
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$WP_ENABLED = MDSConfig::get( 'WP_ENABLED' );
				?>
                <input type="radio" name="WP_ENABLED" value="YES" <?php echo( $WP_ENABLED == 'YES' ? 'checked' : '' ); ?> /> Yes - WordPress integration features will be enabled. Click here for more information.<br/>
                <input type="radio" name="WP_ENABLED" value="NO" <?php echo( $WP_ENABLED == 'NO' ? 'checked' : '' ); ?> /> No - Normal operation
            </label>
            <br/>Note: WP integration requres a separate plugin to be installed in WP that you can download <a target="_blank" href="https://milliondollarscript.com/million-dollar-script-2-1-wordpress-integration-plugin/">here</a>.
        </div>

        <div class="admin-config-left">
            WordPress Site Address (URL, no trailing slash)
        </div>
        <div class="admin-config-right">
            <label>
                <input type="url" name="WP_URL" value="<?php echo MDSConfig::get( 'WP_URL', true ); ?>" style="width:98%"/>
            </label>
        </div>

        <div class="admin-config-left">
            WordPress Site Path (absolute path to WP install, no trailing slash)
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="WP_PATH" value="<?php echo MDSConfig::get( 'WP_PATH', true ); ?>" style="width:98%"/>
            </label>
        </div>

        <div class="admin-config-left">
            WordPress Users Integration
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$WP_USERS_ENABLED = MDSConfig::get( 'WP_USERS_ENABLED' );
				?>
                <input type="radio" name="WP_USERS_ENABLED" value="YES" <?php echo( $WP_USERS_ENABLED == 'YES' ? 'checked' : '' ); ?> /> Yes - WordPress user integration features will be enabled. <a href="https://milliondollarscript.com/million-dollar-script-2-1-wordpress-integration-plugin/" target="_blank">Click here</a> for more information.<br/>
                <input type="radio" name="WP_USERS_ENABLED" value="NO" <?php echo( $WP_USERS_ENABLED == 'NO' ? 'checked' : '' ); ?> /> No - Normal operation
            </label>
        </div>

        <div class="admin-config-left">
            WordPress Admin Integration
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$WP_ADMIN_ENABLED = MDSConfig::get( 'WP_ADMIN_ENABLED' );
				?>
                <input type="radio" name="WP_ADMIN_ENABLED" value="YES" <?php echo( $WP_ADMIN_ENABLED == 'YES' ? 'checked' : '' ); ?> /> Yes - MDS admin is accessible from within the WordPress admin. <a href="https://milliondollarscript.com/million-dollar-script-2-1-wordpress-integration-plugin/" target="_blank">Click here</a> for more information.<br/>
                <input type="radio" name="WP_ADMIN_ENABLED" value="NO" <?php echo( $WP_ADMIN_ENABLED == 'NO' ? 'checked' : '' ); ?> /> No - Normal operation
            </label>
        </div>

        <div class="admin-config-left">
            WordPress Email Integration
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$WP_USE_MAIL = MDSConfig::get( 'WP_USE_MAIL' );
				?>
                <input type="radio" name="WP_USE_MAIL" value="YES" <?php echo( $WP_USE_MAIL == 'YES' ? 'checked' : '' ); ?> /> Yes - MDS will use the WP mail function instead of it's own functions.<br/>
                <input type="radio" name="WP_USE_MAIL" value="NO" <?php echo( $WP_USE_MAIL == 'NO' ? 'checked' : '' ); ?> /> No - Normal operation
            </label>
        </div>

    </div>

    <p><input type="submit" value="Save Configuration" name="save"></p>
</form>