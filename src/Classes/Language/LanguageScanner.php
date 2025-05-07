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

namespace MillionDollarScript\Classes\Language;

use MillionDollarScript\Classes\System\Filesystem;
use PhpParser\Error;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

defined( 'ABSPATH' ) or exit;

// TODO: Add function to expand strings, functions or options.

class LanguageScanner {
	private string $plugin_folder;

	private array $contents;

	private string $pot_file_path;

	/**
	 * Constructs a new instance of the class.
	 *
	 * @param string $plugin_folder The folder path of the plugin.
	 */
	public function __construct( string $plugin_folder ) {
		$this->plugin_folder = $plugin_folder;
		$this->pot_file_path = $plugin_folder . 'languages/milliondollarscript.pot';

		$filesystem = new Filesystem();
		$filesystem->make_folder( dirname( $this->pot_file_path ) );
	}

	/**
	 * Scans files and generates a POT file for the Million Dollar Script plugin.
	 *
	 * @throws \Exception If there is an error accessing or manipulating the files.
	 */
	public function scan_files(): void {
		$this->contents = [];

		$header = $this->get_header();

		$file_list = $this->get_php_files( $this->plugin_folder );
		foreach ( $file_list as $file ) {
			$this->scan_file( $file );
		}

		$full_contents = $header . "\n" . implode( "\n", $this->contents );
		if ( ! Filesystem::put_contents( $this->pot_file_path, $full_contents ) ) {
			throw new \Exception( Language::get( 'Could not write to file: ' ) . $this->pot_file_path );
		}
	}

	/**
	 * Scans the given file and extracts strings that match a specific pattern.
	 *
	 * @param string $file The path to the file to be scanned.
	 *
	 * @return void
	 */
	public function scan_file( string $file ): void {
		$filesystem = new Filesystem();
		$content = $filesystem->get_contents( $file );

		$parser    = ( new ParserFactory )->createForHostVersion();
		$traverser = new NodeTraverser;

		$visitor = new LanguageFunctionVisitor();
		$traverser->addVisitor( $visitor );

		try {
			$stmts = $parser->parse( $content );
			$traverser->traverse( $stmts );

			foreach ( $visitor->strings as $string ) {
				$this->append_strings( $string['function'], $string['string'] );
			}
		} catch ( Error $error ) {
			echo "Parse error: {$error->getMessage()}\n";

			return;
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

HEADERS;
	}

	/**
	 * Formats a POT line.
	 *
	 * @param string $value The value to format.
	 *
	 * @return string The formatted POT line.
	 */
	private function format_pot_line( string $value ): string {
		$msgid  = "msgid \"$value\"\n";
		$msgstr = "msgstr \"\"\n";

		return $msgid . $msgstr;
	}

	/**
	 * Checks if a specific line exists in the contents.
	 *
	 * @param string $value The line to search for.
	 *
	 * @return bool True if the line exists, false otherwise.
	 */
	private function line_exists( string $value ): bool {
		$pot_line = $this->format_pot_line( $value );

		return in_array( $pot_line, $this->contents );
	}

	/**
	 * Extracts the string value from a given node.
	 *
	 * @param mixed $node The node to extract the value from.
	 *
	 * @return string The string value extracted from the node.
	 */
	private function extract_string_value( mixed $node ): string {
		if ( $node instanceof String_ ) {
			return $node->value;
		}

		return '';
	}

	/**
	 * Append multiple strings to the contents.
	 *
	 * @param string $function
	 * @param mixed $string
	 *
	 * @return void
	 */
	private function append_strings( string $function, mixed $string ): void {
		$value = $this->extract_string_value( $string );
		$value = $this->escape_string( $value );

		if ( ! empty( $value ) && ! $this->line_exists( $value ) ) {
			if ( Language::is_lang_function( $function ) ) {

				$msgid  = "msgid \"$value\"\n";
				$msgstr = "msgstr \"\"\n";

				$this->contents[] = $msgid .
				                    $msgstr;
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
