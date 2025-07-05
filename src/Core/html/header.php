<?php
/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2025, Ryan Rhode
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

use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\Data\Options;

defined( 'ABSPATH' ) or exit;

// MillionDollarScript header.php

$logged_in = is_user_logged_in() ? ' logged-in' : '';

// Get current theme mode and apply theme classes
$theme_mode = Options::get_option( 'theme_mode', 'light' );
$theme_classes = ' mds-theme-' . $theme_mode . ' mds-theme-active';

$header_container = '
<div class="mds-container' . $logged_in . $theme_classes . '">
    <div class="outer">
        <div class="inner">
';

$header_container = apply_filters( 'mds_header_container', $header_container );

echo wp_kses( $header_container, Language::allowed_html() );

$mds_error = Utility::get_error();
if ( ! empty( $mds_error ) ) {
	echo '<div class="mds-error">' . Language::get( $mds_error ) . '</div>';
}
