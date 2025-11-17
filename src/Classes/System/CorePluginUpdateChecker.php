<?php

namespace MillionDollarScript\Classes\System;

use YahnisElsts\PluginUpdateChecker\v5p6\Plugin\PluginInfo;
use YahnisElsts\PluginUpdateChecker\v5p6\Plugin\Update as PluginUpdate;
use YahnisElsts\PluginUpdateChecker\v5p6\Plugin\UpdateChecker as PluginUpdateChecker;

defined( 'ABSPATH' ) or exit;

/**
 * Custom update checker that proxies requests to the MDS extension server.
 */
class CorePluginUpdateChecker extends PluginUpdateChecker {
	private string $extensionServerUrl;
	private string $channel;
	private ?\stdClass $lastResponse = null;

	public function __construct(
		string $extensionServerUrl,
		string $pluginFile,
		string $slug,
		string $channel,
		int $checkPeriod = 12,
		string $optionName = '',
		string $muPluginFile = ''
	) {
		$this->extensionServerUrl = rtrim( $extensionServerUrl, '/' );
		$this->channel            = $channel;

		parent::__construct( $this->extensionServerUrl, $pluginFile, $slug, $checkPeriod, $optionName, $muPluginFile );

		add_filter( 'upgrader_package_options', [ $this, 'maybeRefreshPackageUrl' ], 10, 1 );
		add_filter( 'upgrader_pre_download', [ $this, 'logDownloadAttempt' ], 10, 4 );
	}

	/**
	 * Fetch update metadata from the extension server.
	 */
	public function requestUpdate() {
		$response = $this->getOrFetchUpdateData();
		if ( $response === null ) {
			return null;
		}

		$update = $this->createPluginUpdateFromStdClass( $response );
		$update->slug     = $this->slug;
		$update->filename = $this->pluginFile;

		return $this->filterUpdateResult( $update );
	}

	/**
	 * Provide plugin information for the WP "View details" modal.
	 */
	public function requestInfo( $queryArgs = array() ) {
		$response = $this->getOrFetchUpdateData();

		if ( $response === null ) {
			return $this->buildLocalPluginInfo();
		}

		$info            = $this->createPluginInfoFromStdClass( $response );
		$info->filename  = $this->pluginFile;
		$info->slug      = $this->slug;
		$info->homepage  = $info->homepage ?: 'https://milliondollarscript.com';
		$info->sections  = $info->sections ?? [];

		return $info;
	}
	private function getOrFetchUpdateData(): ?\stdClass {
		if ( $this->lastResponse === null ) {
			$this->lastResponse = CorePluginUpdateVcsApi::requestUpdate(
				$this->extensionServerUrl,
				$this->channel
			);
		}

		return $this->lastResponse;
	}

	private function buildLocalPluginInfo(): PluginInfo {
		$info           = new PluginInfo();
		$info->name     = 'Million Dollar Script';
		$info->slug     = $this->slug;
		$info->version  = defined( 'MDS_VERSION' ) ? MDS_VERSION : '0.0.0';
		$info->homepage = 'https://milliondollarscript.com';
		$info->sections = [
			'description' => __( 'Million Dollar Script core plugin.', 'milliondollarscript' ),
		];

		return $info;
	}

	private function createPluginInfoFromStdClass( \stdClass $data ): PluginInfo {
		Logs::log( 'Creating PluginInfo from stdClass payload.', Logs::LEVEL_DEBUG );

		$info              = new PluginInfo();
		$info->name        = $data->name ?? 'Million Dollar Script';
		$info->slug        = $this->slug;
		$info->version     = $data->version ?? ( $data->new_version ?? '' );
		$info->homepage    = $data->homepage ?? 'https://milliondollarscript.com';
		$info->download_url = $data->download_url ?? $data->package ?? '';
		$info->sections    = isset( $data->sections ) ? (array) $data->sections : [];
		$info->requires    = $data->requires ?? null;
		$info->tested      = $data->tested ?? null;
		$info->requires_php = $data->requires_php ?? null;
		$info->upgrade_notice = $data->upgrade_notice ?? null;
		$info->icons       = isset( $data->icons ) ? (array) $data->icons : [];
		$info->last_updated = $data->last_updated ?? null;
		$info->author      = $data->author ?? 'Million Dollar Script';
		$info->author_homepage = $data->author_homepage ?? 'https://milliondollarscript.com';

		if ( empty( $info->sections['changelog'] ) ) {
			$changelog_html = $this->render_changelog( $data->changelog ?? '' );
			if ( $changelog_html ) {
				$info->sections['changelog'] = $changelog_html;
			}
		}

		return $info;
	}

	private function createPluginUpdateFromStdClass( \stdClass $data ): PluginUpdate {
		Logs::log( 'Creating PluginUpdate from stdClass payload.', Logs::LEVEL_DEBUG );

		$update = new PluginUpdate();
		$update->slug = $this->slug;
		$update->new_version = $data->new_version ?? ($data->version ?? '');
		$update->version = $update->new_version;
		$update->package = $data->package ?? $data->download_url ?? '';
		$update->download_url = $update->package;
		$update->requires = $data->requires ?? null;
		$update->tested = $data->tested ?? null;
		$update->requires_php = $data->requires_php ?? null;
		$update->upgrade_notice = $data->upgrade_notice ?? null;
		$update->sections = isset( $data->sections ) ? (array) $data->sections : [];
		$update->homepage = $data->homepage ?? 'https://milliondollarscript.com';
		$update->name = $data->name ?? 'Million Dollar Script';

		if ( empty( $update->sections['changelog'] ) ) {
			$changelog_html = $this->render_changelog( $data->changelog ?? '' );
			if ( $changelog_html ) {
				$update->sections['changelog'] = $changelog_html;
			}
		}
		if ( empty( $update->changelog ) && ! empty( $update->sections['changelog'] ) ) {
			$update->changelog = $update->sections['changelog'];
		}

		return $update;
	}

	public function maybeRefreshPackageUrl( array $options ): array {
		$plugin_basename = plugin_basename( MDS_BASE_FILE );
		$target_plugin   = $options['hook_extra']['plugin'] ?? '';

		if ( $target_plugin !== $plugin_basename ) {
			return $options;
		}

		Logs::log( 'Refreshing core plugin download URL before upgrade.', Logs::LEVEL_DEBUG );

		$latest = CorePluginUpdateVcsApi::requestUpdate( $this->extensionServerUrl, $this->channel );
		if ( $latest && ! empty( $latest->download_url ) ) {
			$options['package'] = $latest->download_url;
			Logs::log( 'Core plugin package URL refreshed with new token.', Logs::LEVEL_DEBUG );
		} else {
			Logs::log( 'Failed to refresh core plugin package URL; using cached value.', Logs::LEVEL_WARNING );
		}

		return $options;
	}

	public function logDownloadAttempt( $reply, $package, $upgrader, $hook_extra ) {
		$plugin_basename = plugin_basename( MDS_BASE_FILE );
		$target_plugin   = $hook_extra['plugin'] ?? '';

		if ( $target_plugin !== $plugin_basename ) {
			return $reply;
		}

		Logs::log( sprintf( 'Core plugin download starting: %s', $package ), Logs::LEVEL_DEBUG );

		return $reply;
	}

	/**
	 * Render changelog Markdown (or plain text) to HTML for the admin UI.
	 */
	private function render_changelog( string $changelog ): string {
		$trimmed = trim( $changelog );
		if ( $trimmed === '' ) {
			return '';
		}

		if ( class_exists( 'Parsedown' ) ) {
			return \Parsedown::instance()->text( $trimmed );
		}

		$html = wpautop( $trimmed );

		return str_replace( "\n", '<br>', $html );
	}
}
