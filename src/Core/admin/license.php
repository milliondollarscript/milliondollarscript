<?php
/*
 * Million Dollar Script Two
 *
 * @author      Ryan Rhode
 * @copyright   (C) 2024, Ryan Rhode
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

defined( 'ABSPATH' ) or exit;

$license_txt = MDS_BASE_PATH . 'license.txt';
if ( file_exists( $license_txt ) ) {
	$file_content = file_get_contents( $license_txt );
	echo nl2br( esc_html( $file_content ) );
} else {
	$plugin_data = get_file_data( MDS_BASE_FILE, [ 'License' => 'License' ] );
	if ( ! empty( $plugin_data['License'] ) ) {
		echo esc_html( $plugin_data['License'] );
	} else {
		Language::out( 'License not found' );
	}
}