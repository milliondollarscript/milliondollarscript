<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.5
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

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

defined( 'ABSPATH' ) or exit;

// TODO: Add function to trim non-existing strings.
// TODO: Add function to expand strings, functions or options.

class MDS_Language_Scanner {
	private string $plugin_folder;

	private string $contents;

	/** @var $wp_filesystem \WP_Filesystem_Direct */
	private $wp_filesystem;

	/**
	 * Constructs a new instance of the class.
	 *
	 * @param string $plugin_folder The folder path of the plugin.
	 */
	public function __construct( string $plugin_folder ) {
		$this->plugin_folder = $plugin_folder;

		$url        = wp_nonce_url( 'wp-admin/plugins.php', 'mds_filesystem_nonce' );
		$filesystem = new Filesystem( $url );
		if ( $filesystem->get_filesystem() ) {
			global $wp_filesystem;
			$this->wp_filesystem = $wp_filesystem;
		}
	}

	/**
	 * Scans files and generates a POT file for the Million Dollar Script plugin.
	 *
	 * @throws \Exception If there is an error accessing or manipulating the files.
	 */
	public function scan_files(): void {
		$pot_file_path = $this->plugin_folder . 'languages/milliondollarscript.pot';

		$file_exists = $this->wp_filesystem->exists( $pot_file_path );

		if ( $file_exists && filesize( $pot_file_path ) > 0 ) {
			$this->contents = $this->wp_filesystem->get_contents( $pot_file_path );
		} else if ( ! $file_exists || filesize( $pot_file_path ) == 0 ) {
			$this->contents = $this->get_header();
		}

		$file_list = $this->get_php_files( $this->plugin_folder );
		foreach ( $file_list as $file ) {
			$this->scan_file( $file );
		}

		if ( ! $this->wp_filesystem->put_contents( $pot_file_path, $this->contents ) ) {
			throw new \Exception( Language::get( 'Could not write to file: ' ) . $pot_file_path );
		}
	}

	/**
	 * Retrieves an array of PHP files from a given folder.
	 *
	 * @param string $folder The path to the folder to search for PHP files.
	 *
	 * @return array An array of file paths for PHP files found in the folder.
	 */
	private function get_php_files( string $folder ): array {
		$file_list = [];
		$files     = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $folder )
		);

		foreach ( $files as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$file_list[] = $file->getPathname();
			}
		}

		return $file_list;
	}

	/**
	 * Scans the given file and extracts strings that match a specific pattern.
	 *
	 * @param string $file The path to the file to be scanned.
	 *
	 * @return void
	 */
	private function scan_file( string $file ): void {

		$content = $this->wp_filesystem->get_contents( $file );
		$strings = [];

		preg_match_all( '/Language::(?:get_replace|out_replace|get|out)\(\s*([\'"])(.*?)\\1(?:\s*,\s*(?:\\\\?(?:\w+\\\\)*\w+(?:::\w+)*(?:\(\s*\))?|\$[\w-]+\[[^]]+]|\$\w+|\w+|\'.*?\'|".*?"))*\s*\)/', $content, $matches );

		foreach ( $matches[2] as $string ) {
			$string    = preg_replace( '/\\\\([\'"])/', '$1', $string );
			$string    = preg_replace( '/\.\$\w+/', '', $string );
			$string    = preg_replace( '/\\\\\\\\(?:\w+\\\\)*(\w+::\w+)\(/', '$1(', $string );
			$strings[] = $string;
		}

		if ( ! empty( $strings ) ) {
			$this->append_strings( $strings );
		}
	}

	/**
	 * A function that returns the header for the language file.
	 *
	 * @return string The header for the language file.
	 */
	private function get_header(): string {
		$version = MDS_VERSION;
		$date    = current_time( 'mysql' );
		$year    = date( 'Y' );

		return <<<HEADERS
# Million Dollar Script.
# Copyright (C) $year Ryan Rhode
# This file is distributed under the GNU/GPL v3.
#
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: Million Dollar Script Two $version\\n"
"Report-Msgid-Bugs-To: https://milliondollarscript.com/\\n"
"Last-Translator: Ryan Rhode <ryan@milliondollarscript.com>\\n"
"Language-Team: Ryan Rhode <ryan@milliondollarscript.com>\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"POT-Creation-Date: $date\\n"
"PO-Revision-Date: $date\\n"
"X-Generator: Million Dollar Script Two $version\\n"
"X-Domain: milliondollarscript\\n"
"Language: English\\n"
\n
HEADERS;
	}

	/**
	 * Checks if a specific line exists in the contents.
	 *
	 * @param mixed $line The line to search for.
	 *
	 * @return bool True if the line exists, false otherwise.
	 */
	private function line_exists( mixed $line ): bool {
		$lines = explode( "\n", $this->contents );

		foreach ( $lines as $l ) {
			if ( trim( $l ) === trim( $line ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Append multiple strings to the contents.
	 *
	 * @param array $strings The array of strings to append.
	 *
	 * @return void
	 */
	private function append_strings( array $strings ): void {
		foreach ( $strings as $string ) {
			$msgid  = $this->escape_string( $string );
			$msgid  = "msgid \"$msgid\"\n";
			$msgstr = "msgstr \"\"\n";
			$break  = "\n";

			if ( ! $this->line_exists( $msgid ) ) {
				$this->contents .=
					$msgid .
					$msgstr .
					$break;
			}
		}
	}

	/**
	 * Escapes double quotes in a given string.
	 *
	 * @param array|string $string The string to be escaped.
	 *
	 * @return string|array The escaped string or array.
	 */
	private function escape_string( array|string $string ): array|string {
		// Escape double quotes

		return str_replace(
			array( "\\", "\"", "\r\n", "\r", "\n", "\t" ),
			array( "\\\\", "\\\"", '\n', '\n', '\n', '\t' ),
			$string
		);
	}
}
