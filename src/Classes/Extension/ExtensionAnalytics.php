<?php

namespace MillionDollarScript\Classes\Extension;

final class ExtensionAnalytics {

	private const MAX_EXTENSIONS = 64;

	public static function snapshot(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return self::snapshot_from_plugins( get_plugins(), (array) get_option( 'active_plugins', array() ) );
	}

	public static function snapshot_from_plugins( array $plugins, array $active_files ): array {
		$active   = array_fill_keys( array_map( 'plugin_basename', $active_files ), true );
		$snapshot = array();

		foreach ( $plugins as $file => $plugin ) {
			$file = plugin_basename( (string) $file );
			$slug = sanitize_key( '.' === dirname( $file ) ? basename( $file, '.php' ) : dirname( $file ) );
			if ( 0 !== strpos( $slug, 'mds-' ) || ! self::is_official( (array) $plugin ) ) {
				continue;
			}

			$snapshot[] = array(
				'slug'    => $slug,
				'version' => sanitize_text_field( (string) ( $plugin['Version'] ?? '' ) ),
				'active'  => isset( $active[ $file ] ) || ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $file ) ),
			);
			if ( count( $snapshot ) >= self::MAX_EXTENSIONS ) {
				break;
			}
		}

		usort(
			$snapshot,
			static function ( array $left, array $right ): int {
				return strcmp( (string) ( $left['slug'] ?? '' ), (string) ( $right['slug'] ?? '' ) );
			}
		);

		return $snapshot;
	}

	private static function is_official( array $plugin ): bool {
		foreach ( array( 'PluginURI', 'Plugin URI', 'AuthorURI', 'Author URI' ) as $key ) {
			$host = strtolower( (string) wp_parse_url( (string) ( $plugin[ $key ] ?? '' ), PHP_URL_HOST ) );
			if ( 'milliondollarscript.com' === $host || 'www.milliondollarscript.com' === $host ) {
				return true;
			}
		}

		return false;
	}
}
