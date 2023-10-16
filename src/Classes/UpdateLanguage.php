<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.3
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

namespace MillionDollarScript\Classes;

class UpdateLanguage {
	private static ?UpdateLanguage $instance = null;

	private static array $strings = [];

	public function __construct() {
		self::$strings = get_transient( MDS_PREFIX . 'pot_strings' ) ?: [];
	}

	/**
	 * This function will create a languages/milliondollarscript.pot file if it doesn't exist and add the headers.
	 * On each page load it will save any new strings to this file.
	 * This should only be enabled to help regenerate the language file since wp-cli and Loco Translate don't work.
	 *
	 * @param $translated_text
	 * @param $untranslated_text
	 * @param $domain
	 *
	 * @return mixed
	 */
	public static function capture_gettext_strings( $translated_text, $untranslated_text, $domain ): mixed {

		if ( MDS_TEXT_DOMAIN === $domain ) {
			self::$strings = get_transient( MDS_PREFIX . 'pot_strings' ) ?: [];

			if ( ! isset( self::$strings[ $translated_text ] ) ) {
				self::$strings[ $translated_text ] = true;
				set_transient( MDS_PREFIX . 'pot_strings', self::$strings );
				update_option( MDS_PREFIX . 'pot_needs_updating', true );
			}
		}

		return $translated_text;
	}

	public static function write_strings(): void {
		if ( ! get_option( MDS_PREFIX . 'pot_needs_updating' ) ) {
			return;
		}
		update_option( MDS_PREFIX . 'pot_needs_updating', false );

		global $wp_filesystem;

		// Use WP file system
		$wp_nonce_url = wp_nonce_url( 'wp-admin/plugins.php', 'mds_filesystem_nonce' );
		$filesystem   = new Filesystem( $wp_nonce_url );
		if ( ! $filesystem->get_filesystem() ) {
			$error = new \WP_Error( 'error_getting_wp_filesystem', Language::get( 'Error getting WP file system.' ) );
			error_log( $error->get_error_message() );
		}

		$pot_file_path = MDS_BASE_PATH . 'languages/milliondollarscript.pot';

		// Load the existing contents of the .pot file into the $strings array
		// if ( $wp_filesystem->exists( $pot_file_path ) ) {
		// 	$current_contents = $wp_filesystem->get_contents( $pot_file_path );
		// 	$matches          = [];
		// 	preg_match_all( '/msgid "(.*)"/', $current_contents, $matches );
		// 	if ( ! empty( $matches[1] ) ) {
		// 		foreach ( $matches[1] as $match ) {
		// 			self::$strings[ $match ] = true;
		// 		}
		// 	}
		// } else {
		$headers = <<<HEADERS
# Million Dollar Script.
# Copyright (C) 2023 Ryan Rhode
# This file is distributed under the GNU/GPL v3.
#
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: Million Dollar Script Two 2.5.3\\n"
"Report-Msgid-Bugs-To: https://milliondollarscript.com/\\n"
"Last-Translator: Ryan Rhode <ryan@milliondollarscript.com>\\n"
"Language-Team: Ryan Rhode <ryan@milliondollarscript.com>\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"POT-Creation-Date: 2023-06-19 09:40-0500\\n"
"PO-Revision-Date: 2023-06-19 09:40-0500\\n"
"X-Generator: Million Dollar Script Two 2.5.3\\n"
"X-Domain: milliondollarscript\\n"
"Language: English\\n"
\n
HEADERS;
		// 	if ( ! $wp_filesystem->put_contents( $pot_file_path, $headers ) ) {
		// 		$error = new \WP_Error( 'error_writing_headers_to_file', Language::get( 'Error writing headers to language file.' ) );
		// 		error_log( $error->get_error_message() );
		// 	}
		// }

		$unique_strings = array_keys( self::$strings );
		sort( $unique_strings );

		$output = '';

		foreach ( $unique_strings as $string ) {
			$string = str_replace(
				array( "\\", "\"", "\r\n", "\r", "\n", "\t" ),
				array( "\\\\", "\\\"", '\n', '\n', '\n', '\t' ),
				$string
			);
			$output .= "msgid \"$string\"\nmsgstr \"\"\n\n";
		}

		// $current_contents = $wp_filesystem->exists( $pot_file_path ) ? $wp_filesystem->get_contents( $pot_file_path ) : '';

		$new_contents = $headers . $output;

		if ( ! $wp_filesystem->put_contents( $pot_file_path, $new_contents ) ) {
			$error = new \WP_Error( 'error_writing_language_string_to_file', Language::get( 'Error writing language string to file.' ) );
			error_log( $error->get_error_message() );
		}

		set_transient( MDS_PREFIX . 'pot_strings', self::$strings );
	}
}