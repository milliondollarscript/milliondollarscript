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

defined( 'ABSPATH' ) or exit;

class Mail {
	protected static int $last_insert_id;

	/**
	 * Used for the safe_style_css in the sanitize_email_content function to sanitize CSS.
	 *
	 * @param $styles
	 *
	 * @return string[]
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function safe_style_css( $styles ): array {
		return [
			'background',
			'background-color',
			'border',
			'border-bottom',
			'border-bottom-color',
			'border-bottom-style',
			'border-bottom-width',
			'border-color',
			'border-left',
			'border-left-color',
			'border-left-style',
			'border-left-width',
			'border-right',
			'border-right-color',
			'border-right-style',
			'border-right-width',
			'border-style',
			'border-top',
			'border-top-color',
			'border-width',
			'color',
			'display',
			'font',
			'font-family',
			'font-size',
			'font-style',
			'font-variant',
			'font-weight',
			'height',
			'letter-spacing',
			'line-height',
			'list-style-type',
			'padding',
			'padding-bottom',
			'padding-left',
			'padding-right',
			'padding-top',
			'table-layout',
			'text-align',
			'text-decoration',
			'text-indent',
			'text-transform',
			'vertical-align'
		];
	}

	/**
	 * This function will sanitize email content.
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public static function sanitize_email_content( $content ): string {

		add_filter( 'safe_style_css', [ __CLASS__, 'safe_style_css' ], 10, 1 );

		$allowed_html = Language::allowed_html();

		// Use safecss_filter_attr() to sanitize inline CSS
		foreach ( $allowed_html as $tag => $attributes ) {
			if ( isset( $attributes['style'] ) ) {
				$content = preg_replace_callback(
					'/(<' . $tag . '[^>]*style=["\'])([^"\']*["\'])/i',
					function ( $matches ) {
						return $matches[1] . wp_kses_no_null( safecss_filter_attr( $matches[2] ) );
					},
					$content
				);
			}
		}

		remove_filter( 'safe_style_css', [ __CLASS__, 'safe_style_css' ] );

		return wp_kses( $content, $allowed_html, Language::allowed_protocols() );
	}

	/**
	 * Handle failure of wp_mail and update the error_msg and status for MDS.
	 *
	 * @param $wp_error
	 *
	 * @return void
	 */
	public static function catch_mail_failure( $wp_error ): void {
		global $wpdb;

		// Extract error message from WP_Error object
		$error_msg = $wp_error->get_error_message();

		// The table name
		$table_name = MDS_DB_PREFIX . "mail_queue";

		// Update the error_msg field with the error message
		$wpdb->update(
			$table_name,
			array( 'error_msg' => $error_msg ),
			array( 'mail_id' => self::$last_insert_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Wrapper function for wp_mail that sanitizes emails and stores them in the database.
	 *
	 * @param string $to_address
	 * @param string $subject
	 * @param string $message
	 * @param string $to_name
	 * @param string $from_address
	 * @param string $from_name
	 * @param int $template_id
	 *
	 * @return bool
	 */
	public static function send( string $to_address, string $subject, string $message = '', string $to_name = '', string $from_address = '', string $from_name = '', int $template_id = 0 ): bool {

		// detect mail injection
		if ( is_email( $to_address ) && strpos( strtolower( $to_address ), strtolower( 'Content-type' ) ) > 0 ) {
			return false;
		}

		if ( strpos( strtolower( $subject ), strtolower( 'Content-type' ) ) > 0 ) {
			return false;
		}

		if ( ! empty( $to_name ) && strpos( strtolower( $to_name ), strtolower( 'Content-type' ) ) > 0 ) {
			return false;
		}

		if ( ! empty( $from_address ) && is_email( $from_address ) && strpos( strtolower( $from_address ), strtolower( 'Content-type' ) ) > 0 ) {
			return false;
		}

		if ( ! empty( $from_name ) && strpos( strtolower( $from_name ), strtolower( 'Content-type' ) ) > 0 ) {
			return false;
		}

		// Sanitize the address
		add_filter( 'wp_mail_from', function () use ( $from_address ) {
			return $from_address;
		} );

		// Sanitize the name
		add_filter( 'wp_mail_from_name', function () use ( $from_name ) {
			return sanitize_text_field( $from_name );
		} );

		// Set the content type to text/html
		add_filter( 'wp_mail_content_type', function () {
			return 'text/html';
		} );

		// Save to the database
		global $wpdb;

		$attachments = 'N';
		$now         = current_time( 'mysql' );
		$table_name  = MDS_DB_PREFIX . "mail_queue";

		$data = array(
			'mail_date'    => $now,
			'to_address'   => $to_address,
			'to_name'      => $to_name,
			'from_address' => $from_address,
			'from_name'    => $from_name,
			'subject'      => $subject,
			'message'      => $message,
			'attachments'  => $attachments,
			'status'       => 'sent',
			'error_msg'    => '',
			'retry_count'  => 0,
			'template_id'  => $template_id,
			'date_stamp'   => $now
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );

		$result = $wpdb->insert( $table_name, $data, $formats );

		if ( false === $result ) {
			wp_die( $wpdb->last_error );
		}

		self::$last_insert_id = $wpdb->insert_id;

		add_action( 'wp_mail_failed', [ __CLASS__, 'catch_mail_failure' ], 10, 1 );

		// Send the email
		$mail_result = wp_mail( $to_address, sanitize_text_field( $subject ), self::sanitize_email_content( $message ) );

		remove_action( 'wp_mail_failed', [ __CLASS__, 'catch_mail_failure' ] );

		return $mail_result !== false;
	}
}