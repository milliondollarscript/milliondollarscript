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

use MillionDollarScript\Classes\Config;

defined( 'ABSPATH' ) or exit;

?>
<form method="POST" name="form1" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'mds-admin' ); ?>
    <input type="hidden" name="action" value="mds_admin_form_submission">
    <input type="hidden" name="mds_dest" value="main-config">

    <p><input type="submit" value="Save Configuration" name="save"></p>
    <input name="BUILD_DATE" type="hidden" value="<?php echo Config::get( 'BUILD_DATE', true ); ?>"/>
    <input name="VERSION_INFO" type="hidden" value="<?php echo Config::get( 'VERSION_INFO', true ); ?>"/>

    <div class="admin-config">
        <div class="admin-config-heading">
            Localization - Time and Date
        </div>

        <div class="admin-config-left">
            Display Date Format
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="DATE_FORMAT" size="49" value="<?php echo Config::get( 'DATE_FORMAT', true ); ?>"/>
                <br/> Note: See <a href="https://www.php.net/manual/en/function.date.php" target="_blank">here</a> for formatting info.
            </label>
        </div>

        <div class="admin-config-left">
            Display Time Format
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="TIME_FORMAT" size="49" value="<?php echo Config::get( 'TIME_FORMAT', true ); ?>"/>
                <br/> Note: See <a href="https://www.php.net/manual/en/function.date.php" target="_blank">here</a> for formatting info.
            </label>
        </div>

        <div class="admin-config-left">
            Input Date Sequence
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="DATE_INPUT_SEQ" size="49" value="<?php echo Config::get( 'DATE_INPUT_SEQ', true ); ?>"/>
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
					$GMT_DIF              = Config::get( 'GMT_DIF' );
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
				$OUTPUT_JPEG = Config::get( 'OUTPUT_JPEG' );
				?>
                <input type="radio" name="OUTPUT_JPEG" value="Y" <?php echo( $OUTPUT_JPEG == 'Y' ? 'checked' : '' ); ?> /> JPEG (Lossy compression). JPEG Quality: <input type="text" name='JPEG_QUALITY' value="<?php echo Config::get( 'JPEG_QUALITY', true ); ?>" size="2"/>%<br/>
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
				$INTERLACE_SWITCH = Config::get( 'INTERLACE_SWITCH' );
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
				$DISPLAY_PIXEL_BACKGROUND = Config::get( 'DISPLAY_PIXEL_BACKGROUND' );
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
				$USE_AJAX = Config::get( 'USE_AJAX' );
				?>
                <input type="radio" name="USE_AJAX" value="SIMPLE" <?php echo( $USE_AJAX == 'SIMPLE' ? 'checked' : '' ); ?> /> Simple (Upload image > Place image on grid)<br/>
                <input type="radio" name="USE_AJAX" value="YES" <?php echo( $USE_AJAX == 'YES' ? 'checked' : '' ); ?> /> Advanced (Select blocks > Upload image)<br/>
            </label>
        </div>

        <div class="admin-config-left">
            Resize uploaded pixels automatically?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$MDS_RESIZE = Config::get( 'MDS_RESIZE' );
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
                <input type="text" name="BANNER_DIR" size="55" value="<?php echo Config::get( 'BANNER_DIR', true ); ?>"/>
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
				$EMAIL_USER_ORDER_CONFIRMED = Config::get( 'EMAIL_USER_ORDER_CONFIRMED' );
				?>
                <input type="radio" name="EMAIL_USER_ORDER_CONFIRMED" value="YES" <?php echo( $EMAIL_USER_ORDER_CONFIRMED == 'YES' ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_USER_ORDER_CONFIRMED" value="NO" <?php echo( $EMAIL_USER_ORDER_CONFIRMED == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Email Advertiser when an order is Completed?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_USER_ORDER_COMPLETED = Config::get( 'EMAIL_USER_ORDER_COMPLETED' );
				?>
                <input type="radio" name="EMAIL_USER_ORDER_COMPLETED" value="YES" <?php echo( $EMAIL_USER_ORDER_COMPLETED == 'YES' ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_USER_ORDER_COMPLETED" value="NO" <?php echo( $EMAIL_USER_ORDER_COMPLETED == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Email Admin when an order is Confirmed?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_ADMIN_ORDER_CONFIRMED = Config::get( 'EMAIL_ADMIN_ORDER_CONFIRMED' );
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
				$EMAIL_ADMIN_ORDER_COMPLETED = Config::get( 'EMAIL_ADMIN_ORDER_COMPLETED' );
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
				$EMAIL_ADMIN_ORDER_PENDED = Config::get( 'EMAIL_ADMIN_ORDER_PENDED' );
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
				$EMAIL_USER_ORDER_EXPIRED = Config::get( 'EMAIL_USER_ORDER_EXPIRED' );
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
				$EMAIL_ADMIN_ORDER_EXPIRED = Config::get( 'EMAIL_ADMIN_ORDER_EXPIRED' );
				?>
                <input type="radio" name="EMAIL_ADMIN_ORDER_EXPIRED" value="YES" <?php echo( $EMAIL_ADMIN_ORDER_EXPIRED == 'YES' ? 'checked' : '' ); ?> /> Yes<br/>
                <input type="radio" name="EMAIL_ADMIN_ORDER_EXPIRED" value="NO" <?php echo( $EMAIL_ADMIN_ORDER_EXPIRED == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>

        <div class="admin-config-left">
            Pixels Modified: Send a notification email to Admin?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$EMAIL_ADMIN_PUBLISH_NOTIFY = Config::get( 'EMAIL_ADMIN_PUBLISH_NOTIFY' );
				?>
                <input type="radio" name="EMAIL_ADMIN_PUBLISH_NOTIFY" value="YES" <?php echo( $EMAIL_ADMIN_PUBLISH_NOTIFY == 'YES' ? 'checked' : '' ); ?> /> Yes. When a user modifies their pixels, a notification email is sent to admin.<br/>
                <input type="radio" name="EMAIL_ADMIN_PUBLISH_NOTIFY" value="NO" <?php echo( $EMAIL_ADMIN_PUBLISH_NOTIFY == 'NO' ? 'checked' : '' ); ?> /> No
            </label>
        </div>
    </div>

    <div class="admin-config">
        <div class="admin-config-heading">
            Misc Settings
        </div>

        <div class="admin-config-left">
            Outgoing email queue settings
        </div>
        <div class="admin-config-right">
            <label>
                Send a maxiumum of <input type="text" name="EMAILS_PER_BATCH" size="3" value="<?php echo intval( Config::get( 'EMAILS_PER_BATCH' ) ); ?>">emails per batch (enter a number > 0)<br/>
                On error, retry <input type="text" name="EMAILS_MAX_RETRY" size="3" value="<?php echo intval( Config::get( 'EMAILS_MAX_RETRY' ) ); ?>"> times before giving up. (recommened: 15)<br/>
                On error, wait at least <input type="text" name="EMAILS_ERROR_WAIT" size="3" value="<?php echo intval( Config::get( 'EMAILS_ERROR_WAIT' ) ); ?>">minutes before retry. (20 minutes recommended)<br/>
                Keep sent emails for <input type="text" name="EMAILS_DAYS_KEEP" size="3" value="<?php
			    echo intval( Config::get( 'EMAILS_DAYS_KEEP' ) ); ?>">days. (0 = keep forever)<br/>
                Note: You can view the outgoing queue under the 'Report' menu<br/>
            </label>
        </div>

        <div class="admin-config-left">
            How many <b>minutes</b> to keep Expired orders before cancellation?
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="MINUTES_RENEW" size="6" value="<?php echo intval( Config::get( 'MINUTES_RENEW' ) ); ?>"/>
                <br/>(Enter a number. 0 = Do not cancel. -1 = instant.)
            </label>
        </div>

        <div class="admin-config-left">
            How many <b>minutes</b> to keep Confirmed (but not paid) orders before cancellation?
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="MINUTES_CONFIRMED" size="6" value="<?php echo intval( Config::get( 'MINUTES_CONFIRMED' ) ); ?>">
                <br/>(Enter a number. 0 = never cancel. -1 = instant.)
            </label>
        </div>

        <div class="admin-config-left">
            How many <b>minutes</b> to keep New/Unconfirmed orders before deletion?
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="MINUTES_UNCONFIRMED" size="6" value="<?php echo intval( Config::get( 'MINUTES_UNCONFIRMED' ) ); ?>">
                <br/>(Enter a number. 0 = never delete. -1 = instant.)
            </label>
        </div>

        <div class="admin-config-left">
            How many <b>minutes</b> to keep Cancelled orders before deletion?
        </div>
        <div class="admin-config-right">
            <label>
                <input type="text" name="MINUTES_CANCEL" size="6" value="<?php echo intval( Config::get( 'MINUTES_CANCEL' ) ); ?>"/>
                <br/>(Enter a number. 0 = never delete. -1 = instant. Note: If deleted, the order will stay in the database, and only the status will simply change to 'deleted'. The blocks will be freed)
            </label>
        </div>

        <div class="admin-config-left">
            Enable URL cloaking?<br/>(Supposedly, when enabled, the advertiser's link will get a better advantage from search engines.)
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$ENABLE_CLOAKING = Config::get( 'ENABLE_CLOAKING' );
				?>
                <input type="radio" name="ENABLE_CLOAKING" value="YES" <?php echo( $ENABLE_CLOAKING == 'YES' ? 'checked' : '' ); ?> /> Yes - All links will point directly to the Advertiser's URL. Click tracking will be managed by a JavaScript.) <br/>
                <input type="radio" name="ENABLE_CLOAKING" value="NO" <?php echo( $ENABLE_CLOAKING == 'NO' ? 'checked' : '' ); ?> /> No - All links will be re-directed to the /milliondollarscript/click/ endpoint.
            </label>
        </div>

        <div class="admin-config-left">
            Validate URLs by connecting to them?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$VALIDATE_LINK = Config::get( 'VALIDATE_LINK' );
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
				$MEMORY_LIMIT = Config::get( 'MEMORY_LIMIT' );
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
            Redirect when a user clicks on available block?
        </div>
        <div class="admin-config-right">
            <label>
				<?php
				$REDIRECT_SWITCH = Config::get( 'REDIRECT_SWITCH' );
				?>
                <input type="radio" name="REDIRECT_SWITCH" value="YES" <?php echo( $REDIRECT_SWITCH == 'YES' ? 'checked' : '' ); ?> /> Yes - When an available block is clicked, redirect to: <input type="text" name="REDIRECT_URL" size="30" value="<?php echo Config::get( 'REDIRECT_URL', true ); ?>">
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
				$ADVANCED_CLICK_COUNT = Config::get( 'ADVANCED_CLICK_COUNT' );
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
				$ADVANCED_VIEW_COUNT = Config::get( 'ADVANCED_VIEW_COUNT' );
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
				$MDS_AGRESSIVE_CACHE = Config::get( 'MDS_AGRESSIVE_CACHE' );
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
				$BLOCK_SELECTION_MODE = Config::get( 'BLOCK_SELECTION_MODE' );
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
				$STATS_DISPLAY_MODE = Config::get( 'STATS_DISPLAY_MODE' );
				?>
                <input type="radio" name="STATS_DISPLAY_MODE" value="BLOCKS" <?php echo( $STATS_DISPLAY_MODE == 'BLOCKS' ? 'checked' : '' ); ?> /> Blocks - Stats box will display sold/available blocks.<br/>
                <input type="radio" name="STATS_DISPLAY_MODE" value="PIXELS" <?php echo( $STATS_DISPLAY_MODE == 'PIXELS' ? 'checked' : '' ); ?> /> Pixels - Stats box will display sold/available pixels.
            </label>
        </div>

        <div class="admin-config-left">
            Display Order History page?
        </div>
        <div class="admin-config-right">
            <label>
			    <?php
			    $DISPLAY_ORDER_HISTORY = Config::get( 'DISPLAY_ORDER_HISTORY' );
			    ?>
                <input type="radio" name="DISPLAY_ORDER_HISTORY" value="YES" <?php echo( $DISPLAY_ORDER_HISTORY == 'YES' ? 'checked' : '' ); ?> /> Yes - Display an Order History page for users.<br/>
                <input type="radio" name="DISPLAY_ORDER_HISTORY" value="NO" <?php echo( $DISPLAY_ORDER_HISTORY == 'NO' ? 'checked' : '' ); ?> /> No - The Order History page not be available for users.
            </label>
        </div>

        <div class="admin-config-left">
            Invert or Add when selecting pixels
        </div>
        <div class="admin-config-right">
            <label>
			    <?php
			    $INVERT_PIXELS = Config::get( 'INVERT_PIXELS' );
			    ?>
                <input type="radio" name="INVERT_PIXELS" value="YES" <?php echo( $INVERT_PIXELS == 'YES' ? 'checked' : '' ); ?> /> Invert - Pixels will be inverted when selecting them the second time, even if overlapping using multi-block selection methods.<br/>
                <input type="radio" name="INVERT_PIXELS" value="NO" <?php echo( $INVERT_PIXELS == 'NO' ? 'checked' : '' ); ?> /> Add - Pixels will be added to the selection. Clicking Reset will erase everything to start over.
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
			    $ENABLE_MOUSEOVER = Config::get( 'ENABLE_MOUSEOVER' );
			    ?>
                <input type="radio" name="ENABLE_MOUSEOVER" value="POPUP" <?php echo( $ENABLE_MOUSEOVER == 'POPUP' ? 'checked' : '' ); ?> /> Yes - Simple popup box, with no animation<br/>
                <input type="radio" name="ENABLE_MOUSEOVER" value="NO" <?php echo( $ENABLE_MOUSEOVER == 'NO' ? 'checked' : '' ); ?> /> No, turn off
            </label>
        </div>

        <div class="admin-config-left">
            Tooltip trigger
        </div>
        <div class="admin-config-right">
            <label>
			    <?php
			    $TOOLTIP_TRIGGER = Config::get( 'TOOLTIP_TRIGGER' );
			    ?>
                <input type="radio" name="TOOLTIP_TRIGGER" value="click" <?php echo( $TOOLTIP_TRIGGER == 'click' ? 'checked' : '' ); ?> /> Click - Tooltips will popup when ads are clicked on the grid.<br/>
                <input type="radio" name="TOOLTIP_TRIGGER" value="mouseenter" <?php echo( $TOOLTIP_TRIGGER == 'mouseenter' ? 'checked' : '' ); ?> /> Hover - Tooltips will popup when ads are hovered over on the grid.
            </label>
        </div>
    </div>

    <p><input type="submit" value="Save Configuration" name="save"></p>
</form>