<?php

/*
 * Million Dollar Script Two
 *
 * @version     2.5.4
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

defined( 'ABSPATH' ) or exit;

class Language {

	/**
	 * Returns the output of the given content. Filters for valid HTML or escapes HTML. Can optionally output the result (default).
	 *
	 * @param $content string The content to filter or escape.
	 * @param bool $html If true, filters valid HTML tags. If false, escapes HTML.
	 *
	 * @return string
	 */
	public static function get( string $content, bool $html = true ): string {
		$content = __( $content, MDS_TEXT_DOMAIN );

		if ( $html ) {
			$content = wp_kses( $content, Language::allowed_html(), Language::allowed_protocols() );
		} else {
			$content = esc_html( $content );
		}

		return $content;
	}

	/**
	 * Output the given content. Filters for valid HTML or escapes HTML. Can optionally output the result (default).
	 *
	 * @param $content string The content to filter or escape.
	 * @param bool $html If true, filters valid HTML tags. If false, escapes HTML.
	 *
	 */
	public static function out( string $content, bool $html = true ): void {
		self::get( $content, $html );

		echo $content;
	}

	/**
	 * Get the output of the given content with translation, search and replace, and sanitization.
	 *
	 * @param string $content
	 * @param string|array $search
	 * @param string|array $replace
	 * @param bool $html
	 *
	 * @return string
	 */
	public static function get_replace( string $content, string|array $search, string|array $replace, bool $html = true ): string {
		$content = __( $content, MDS_TEXT_DOMAIN );

		$content = str_replace( $search, $replace, $content );

		if ( $html ) {
			$content = wp_kses( $content, Language::allowed_html(), Language::allowed_protocols() );
		} else {
			$content = esc_html( $content );
		}

		return $content;
	}

	/**
	 * Output the given content with translation, search and replace, and sanitization.
	 *
	 * @param $content string The content to filter or escape.
	 * @param bool $html If true, filters valid HTML tags. If false, escapes HTML.
	 *
	 */
	public static function out_replace( string $content, string|array $search, string|array $replace, bool $html = true ): void {
		echo self::get_replace( $content, $search, $replace, $html );
	}

	/**
	 * Return array of allowed HTML tags.
	 *
	 * @return array[]
	 */
	public static function allowed_html(): array {
		static $allowed_html = null;

		if ( $allowed_html === null ) {
			$allowed_html = [
				'a'      => [
					'class'  => [],
					'href'   => [],
					'id'     => [],
					'style'  => [],
					'target' => []
				],
				'b'      => [
					'class' => [],
					'id'    => [],
					'style' => []
				],
				'br'     => [
					'class' => [],
					'id'    => [],
					'style' => []
				],
				'div'    => [
					'align' => [],
					'class' => [],
					'dir'   => [],
					'id'    => [],
					'style' => []
				],
				'font'   => [
					'class' => [],
					'color' => [],
					'face'  => [],
					'id'    => [],
					'size'  => [],
					'style' => []
				],
				'h1'     => [
					'align' => [],
					'class' => [],
					'dir'   => [],
					'id'    => [],
					'style' => []
				],
				'h2'     => [
					'align' => [],
					'class' => [],
					'dir'   => [],
					'id'    => [],
					'style' => []
				],
				'h3'     => [
					'align' => [],
					'class' => [],
					'dir'   => [],
					'id'    => [],
					'style' => []
				],
				'h4'     => [
					'align' => [],
					'class' => [],
					'dir'   => [],
					'id'    => [],
					'style' => []
				],
				'h5'     => [
					'align' => [],
					'class' => [],
					'dir'   => [],
					'id'    => [],
					'style' => []
				],
				'h6'     => [
					'align' => [],
					'class' => [],
					'dir'   => [],
					'id'    => [],
					'style' => []
				],
				'head'   => [
					'dir'  => [],
					'lang' => []
				],
				'hr'     => [
					'align' => [],
					'size'  => [],
					'width' => []
				],
				// ...
				'img'    => [
					'align'  => [],
					'border' => [],
					'class'  => [],
					'height' => [],
					'hspace' => [],
					'id'     => [],
					'src'    => [],
					'style'  => [],
					'usemap' => [],
					'vspace' => [],
					'width'  => []
				],
				'label'  => [
					'class' => [],
					'id'    => [],
					'style' => []
				],
				'li'     => [
					'class' => [],
					'dir'   => [],
					'id'    => [],
					'style' => [],
					'type'  => []
				],
				'ol'     => [
					'class' => [],
					'dir'   => [],
					'id'    => [],
					'style' => [],
					'type'  => []
				],
				'p'      => [
					'align' => [],
					'class' => [],
					'dir'   => [],
					'id'    => [],
					'style' => []
				],
				'span'   => [
					'class' => [],
					'id'    => [],
					'style' => []
				],
				'strong' => [
					'class' => [],
					'id'    => [],
					'style' => []
				],
				'table'  => [
					'align'       => [],
					'bgcolor'     => [],
					'border'      => [],
					'cellpadding' => [],
					'cellspacing' => [],
					'class'       => [],
					'dir'         => [],
					'frame'       => [],
					'id'          => [],
					'rules'       => [],
					'style'       => [],
					'width'       => []
				],
				'td'     => [
					'abbr'    => [],
					'align'   => [],
					'bgcolor' => [],
					'class'   => [],
					'colspan' => [],
					'dir'     => [],
					'height'  => [],
					'id'      => [],
					'lang'    => [],
					'rowspan' => [],
					'scope'   => [],
					'style'   => [],
					'valign'  => [],
					'width'   => []
				],
				'th'     => [
					'abbr'       => [],
					'align'      => [],
					'background' => [],
					'bgcolor'    => [],
					'class'      => [],
					'colspan'    => [],
					'dir'        => [],
					'height'     => [],
					'id'         => [],
					'lang'       => [],
					'scope'      => [],
					'style'      => [],
					'valign'     => [],
					'width'      => []
				],
				'tr'     => [
					'align'   => [],
					'bgcolor' => [],
					'class'   => [],
					'dir'     => [],
					'id'      => [],
					'style'   => [],
					'valign'  => []
				],
				'u'      => [
					'class' => [],
					'id'    => [],
					'style' => []
				],
				'ul'     => [
					'class' => [],
					'dir'   => [],
					'id'    => [],
					'style' => []
				]
			];

			apply_filters( 'mds_allowed_html', $allowed_html );
		}

		return $allowed_html;
	}

	/**
	 * Return array of allowed protocols.
	 *
	 * @return string[]
	 */
	public static function allowed_protocols(): array {
		static $allowed_protocols = null;

		if ( $allowed_protocols === null ) {
			$allowed_protocols = [
				'http',
				'https',
				'mailto',
			];

			apply_filters( 'mds_allowed_protocols', $allowed_protocols );
		}

		return $allowed_protocols;
	}

	/**
	 * Translate and replace string.
	 *
	 * @param $string string String to translate and replace.
	 * @param $search array|string String or array to search for.
	 * @param $replace array|string String or array to replace with.
	 *
	 * @return array|string|null
	 */
	public static function replace( string $string, array|string $search, array|string $replace ): array|string|null {
		$translatable = __( $string, MDS_TEXT_DOMAIN );

		return str_replace( $search, $replace, $translatable );
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain( 'milliondollarscript', false, MDS_LANG_DIR );
	}

	public static function upgrade(): void {
		if ( ! is_writable( \MillionDollarScript\Classes\Utility::get_upload_path() ) ) {
			Language::out_replace( '%UPLOAD_PATH% directory is not writable. Give write permissions (777) to the directory.<br>', '%UPLOAD_PATH%', \MillionDollarScript\Classes\Utility::get_upload_path() );
		}

		if ( ! is_writable( \MillionDollarScript\Classes\Utility::get_upload_path() . "/images/" ) ) {
			Language::out_replace( '%UPLOAD_PATH%/images/ directory is not writable. Give write permissions (777) to the directory.<br>', '%UPLOAD_PATH%', \MillionDollarScript\Classes\Utility::get_upload_path() );
		}
	}
}