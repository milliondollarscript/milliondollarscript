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

use MillionDollarScript\Classes\Language;

defined( 'ABSPATH' ) or exit;

?>
<div class="system-info">
    <h1><?php Language::out( 'System Information' ); ?></h1>
    <p><?php Language::out_replace( '%UPLOAD_PATH%', \MillionDollarScript\Classes\Utility::get_upload_path(), 'The path to your uploads directory: %UPLOAD_PATH%' ); ?></p>
    <hr>
    <div class="phpinfo-container">
		<?php
		// @link https://www.mainelydesign.com/blog/view/displaying-phpinfo-without-css-styles
		ob_start();
		phpinfo();
		$pinfo = ob_get_contents();
		ob_end_clean();

		$pinfo = preg_replace( '%^.*<body>(.*)</body>.*$%ms', '$1', $pinfo );
		echo $pinfo;
		?>
    </div>
</div>