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

use MillionDollarScript\Classes\Data\Options;

defined( 'ABSPATH' ) or exit;

/**
 * VCS API shim for core plugin updates via extension server.
 *
 * This class extends the YahnisElsts Plugin Update Checker VCS API to proxy
 * update requests to the MDS extension server instead of using GitLab directly.
 * Supports both legacy (v5.5) and modern (v5.6+) versions of the library.
 */
class CorePluginUpdateVcsApi {
	private const LEGACY_API_CLASS = 'Puc_v5p5_Vcs_Api';
	private const MODERN_API_CLASS = '\YahnisElsts\PluginUpdateChecker\v5p6\Vcs\Api';

	/**
	 * Create VCS API instance for core plugin updates.
	 *
	 * @param string $extensionServerUrl Extension server base URL
	 * @param string $channel Release channel (stable, beta, alpha)
	 * @return object|null VCS API instance or null if library unavailable
	 */
	public static function create( string $extensionServerUrl, string $channel ): ?object {
		$extensionServerUrl = rtrim( $extensionServerUrl, '/' );

		// Try legacy library first (v5.5)
		if ( class_exists( self::LEGACY_API_CLASS ) ) {
			return new class( $extensionServerUrl, $channel ) extends \Puc_v5p5_Vcs_Api {
				private $serverUrl;
				private $channel;

				public function __construct( string $serverUrl, string $channel ) {
					parent::__construct( $serverUrl );
					$this->serverUrl = $serverUrl;
					$this->channel = $channel;
				}

				public function getUpdate() {
					return $this->checkForUpdate();
				}

				public function checkForUpdate() {
					return CorePluginUpdateVcsApi::requestUpdate( $this->serverUrl, $this->channel );
				}

				protected function getUpdateDetectionStrategies( $configBranch ) {
					return [];
				}

				public function getBranch( $branchName ) {
					return null;
				}

				public function getTag( $tagName ) {
					return null;
				}

				public function getLatestTag() {
					return null;
				}

				public function getRemoteFile( $path, $ref = 'master' ) {
					return null;
				}

				public function getLatestCommitTime( $ref ) {
					return null;
				}
			};
		}

		// Try modern library (v5.6+)
		if ( class_exists( self::MODERN_API_CLASS ) ) {
			return new class( $extensionServerUrl, $channel ) extends \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\Api {
				private $serverUrl;
				private $channel;

				public function __construct( string $serverUrl, string $channel ) {
					parent::__construct( $serverUrl );
					$this->serverUrl = $serverUrl;
					$this->channel = $channel;
				}

				public function getUpdate() {
					return $this->checkForUpdate();
				}

				public function checkForUpdate() {
					return CorePluginUpdateVcsApi::requestUpdate( $this->serverUrl, $this->channel );
				}

				protected function getUpdateDetectionStrategies( $configBranch ) {
					return [];
				}

				public function getBranch( $branchName ) {
					return null;
				}

				public function getTag( $tagName ) {
					return null;
				}

				public function getLatestTag() {
					return null;
				}

				public function getRemoteFile( $path, $ref = 'master' ) {
					return null;
				}

				public function getLatestCommitTime( $ref ) {
					return null;
				}
			};
		}

		return null;
	}

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
				'Core plugin update check failed: ' . $response->get_error_message(),
				'error'
			);
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body_string   = wp_remote_retrieve_body( $response );

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
				'Core plugin update check failed: Invalid JSON response',
				'error'
			);
			return null;
		}

		// Check for API-level errors
		if ( empty( $body->success ) ) {
			Logs::log(
				'Core plugin update check failed: ' . ( $body->error ?? 'Unknown error' ),
				'error'
			);
			return null;
		}

		// No update available
		if ( empty( $body->data->update_available ) ) {
			return null;
		}

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
}