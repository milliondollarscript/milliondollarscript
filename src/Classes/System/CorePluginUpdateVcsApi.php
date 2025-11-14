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

namespace MillionDollarScript\Classes\System;

defined( 'ABSPATH' ) or exit;

/**
 * Handles HTTP communication with the extension server for core plugin updates.
 */
class CorePluginUpdateVcsApi {

	/**
	 * Request update information from extension server.
	 *
	 * @param string $serverUrl Extension server base URL
	 * @param string $channel Release channel (stable, beta, alpha)
	 * @return \stdClass|null Update object or null if no update available
	 */
	public static function requestUpdate( string $serverUrl, string $channel ): ?\stdClass {
		// Determine SSL verification based on environment
		$sslverify = ! Utility::is_development_environment();

		// Build request body
		$request_body = [
			'channel'         => $channel,
			'current_version' => defined( 'MDS_VERSION' ) ? MDS_VERSION : '0.0.0',
			'site_url'        => get_site_url(),
			'php_version'     => PHP_VERSION,
			'wp_version'      => get_bloginfo( 'version' ),
		];

		// Make request to extension server
		Logs::log(
			sprintf(
				'Core plugin update request -> %s/api/public/core-plugin/v1/check-update (channel=%s, current=%s, sslverify=%s)',
				$serverUrl,
				$channel,
				$request_body['current_version'],
				$sslverify ? 'true' : 'false'
			),
			Logs::LEVEL_DEBUG
		);

		$response = wp_remote_post(
			$serverUrl . '/api/public/core-plugin/v1/check-update',
			[
				'headers'   => [
					'Content-Type' => 'application/json',
					'User-Agent'   => self::buildUserAgent(),
				],
				'body'      => wp_json_encode( $request_body ),
				'timeout'   => 15,
				'sslverify' => $sslverify,
			]
		);

		// Handle HTTP errors
		if ( is_wp_error( $response ) ) {
			Logs::log(
				'Core plugin update check failed (WP_Error): ' . $response->get_error_message(),
				Logs::LEVEL_ERROR
			);

			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body_string   = wp_remote_retrieve_body( $response );

		Logs::log(
			sprintf(
				'Core plugin update response status=%d body=%s',
				$response_code,
				self::truncate_for_log( $body_string )
			),
			Logs::LEVEL_DEBUG
		);

		// Handle non-200 responses
		if ( $response_code !== 200 ) {
			Logs::log(
				sprintf(
					'Core plugin update check failed (%d): %s',
					$response_code,
					$body_string
				),
				'error'
			);
			return null;
		}

		// Parse JSON response
		$body = json_decode( $body_string );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Logs::log(
				'Core plugin update check failed: Invalid JSON response (' . json_last_error_msg() . ')',
				Logs::LEVEL_ERROR
			);

			return null;
		}

		// Check for API-level errors
		if ( empty( $body->success ) ) {
			Logs::log(
				'Core plugin update JSON indicated failure: ' . ( $body->error ?? 'Unknown error' ),
				Logs::LEVEL_ERROR
			);

			return null;
		}

		// No update available
		if ( empty( $body->data->update_available ) ) {
			Logs::log( 'Core plugin update response indicates no update is available.', Logs::LEVEL_DEBUG );
			return null;
		}

		Logs::log(
			sprintf(
				'Core plugin update available -> version %s channel %s',
				$body->data->new_version ?? 'unknown',
				$body->data->channel ?? $channel
			),
			Logs::LEVEL_INFO
		);

		// Transform extension server response to YahnisElsts format
		return self::transformToUpdateObject( $body->data );
	}

	/**
	 * Transform extension server response to YahnisElsts update object format.
	 *
	 * @param \stdClass $data Extension server response data
	 * @return \stdClass YahnisElsts-compatible update object
	 */
	private static function transformToUpdateObject( \stdClass $data ): \stdClass {
		$update = new \stdClass();

		// Core version information
		$update->version     = $data->new_version ?? '';
		$update->new_version = $data->new_version ?? '';
		$update->slug        = 'milliondollarscript-two';

		// Download URL with token
		$update->download_url = $data->package ?? '';
		$update->package      = $update->download_url;

		// Plugin metadata
		if ( ! empty( $data->requires ) ) {
			$update->requires = $data->requires;
		}
		if ( ! empty( $data->requires_php ) ) {
			$update->requires_php = $data->requires_php;
		}
		if ( ! empty( $data->tested ) ) {
			$update->tested = $data->tested;
		}

		// Changelog (convert Markdown to HTML if provided)
		if ( ! empty( $data->changelog ) ) {
			$update->sections = [
				'changelog' => self::parseChangelog( $data->changelog ),
			];
		}

		// Plugin name
		$update->name = 'Million Dollar Script';

		return $update;
	}

	/**
	 * Parse changelog Markdown to HTML.
	 *
	 * @param string $changelog Markdown changelog content
	 * @return string HTML changelog
	 */
	private static function parseChangelog( string $changelog ): string {
		// Use Parsedown if available (included with YahnisElsts library)
		if ( class_exists( 'Parsedown' ) ) {
			return \Parsedown::instance()->text( $changelog );
		}

		// Fallback: basic conversion
		$html = wpautop( $changelog );
		$html = str_replace( "\n", '<br>', $html );
		
		return $html;
	}

	/**
	 * Build User-Agent header for extension server requests.
	 *
	 * @return string User-Agent string
	 */
	private static function buildUserAgent(): string {
		global $wp_version;
		$plugin_version = defined( 'MDS_VERSION' ) ? MDS_VERSION : '1.0.0';

		return sprintf(
			'MDS-Core/%s (WordPress/%s; %s)',
			$plugin_version,
			$wp_version,
			home_url()
		);
	}

	/**
	 * Sanitize and truncate a response body for safe logging.
	 */
	private static function truncate_for_log( string $value, int $max = 400 ): string {
		$clean = self::sanitize_tokens_for_log( $value );
		if ( strlen( $clean ) > $max ) {
			return substr( $clean, 0, $max ) . '…';
		}

		return $clean;
	}

	/**
	 * Mask signed download tokens in log output.
	 */
	private static function sanitize_tokens_for_log( string $value ): string {
		return preg_replace( '/token=[A-Za-z0-9._~-]+/', 'token=***', $value );
	}
}
