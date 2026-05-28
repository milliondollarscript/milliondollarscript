<?php

namespace MillionDollarScript\Classes\System;

defined( 'ABSPATH' ) or exit;

class Request {

	public static function scalar( $value, string $default = '' ): string {
		if ( is_array( $value ) || is_object( $value ) || is_resource( $value ) || null === $value ) {
			return $default;
		}

		$value = self::unslash( (string) $value );
		$value = trim( $value );

		if ( preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value ) ) {
			$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
		}

		return is_string( $value ) ? $value : $default;
	}

	public static function text( array $source, string $key, string $default = '' ): string {
		$value = self::scalar( $source[ $key ] ?? null, $default );

		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return trim( wp_strip_all_tags( $value ) );
		}

		return trim( strip_tags( $value ) );
	}

	public static function key( array $source, string $key, string $default = '' ): string {
		$value = strtolower( self::scalar( $source[ $key ] ?? null, $default ) );

		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return preg_replace( '/[^a-z0-9_\-]/', '', $value );
	}

	public static function int( array $source, string $key, int $default = 0 ): int {
		$value = self::scalar( $source[ $key ] ?? null, '' );

		if ( '' === $value || ! preg_match( '/^-?\d+$/', $value ) ) {
			return $default;
		}

		return (int) $value;
	}

	public static function positive_int( array $source, string $key, int $default = 0 ): int {
		$value = self::int( $source, $key, $default );

		return $value > 0 ? $value : $default;
	}

	public static function bool( array $source, string $key, bool $default = false ): bool {
		if ( ! array_key_exists( $key, $source ) ) {
			return $default;
		}

		$value = strtolower( self::scalar( $source[ $key ], '' ) );

		if ( in_array( $value, [ '1', 'true', 'yes', 'on', 'y' ], true ) ) {
			return true;
		}

		if ( in_array( $value, [ '0', 'false', 'no', 'off', 'n' ], true ) ) {
			return false;
		}

		return $default;
	}

	public static function mode( array $source, string $key, array $allowed, ?string $default = null ): ?string {
		$value = strtoupper( self::scalar( $source[ $key ] ?? null, '' ) );

		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	public static function advertiser_url( array $source, string $key, string $default = '' ): string {
		$value = Url::advertiser_url( $source[ $key ] ?? null );

		return '' !== $value ? $value : $default;
	}

	public static function json_payload( array $source, string $key ): array {
		$json = self::scalar( $source[ $key ] ?? null, '' );

		if ( '' === $json ) {
			return [];
		}

		$decoded = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return [];
		}

		return self::sanitize_scalar_array( $decoded );
	}

	public static function sanitize_scalar_array( array $source ): array {
		$sanitized = [];

		foreach ( $source as $key => $value ) {
			$clean_key = self::sanitize_array_key( $key );
			if ( '' === $clean_key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$nested = self::sanitize_scalar_array( $value );
				if ( ! empty( $nested ) ) {
					$sanitized[ $clean_key ] = $nested;
				}
				continue;
			}

			if ( is_object( $value ) || is_resource( $value ) ) {
				continue;
			}

			$sanitized[ $clean_key ] = self::scalar( $value );
		}

		return $sanitized;
	}

	private static function sanitize_array_key( $key ): string {
		$key = self::scalar( $key );

		return preg_replace( '/[^A-Za-z0-9_\-]/', '', $key );
	}

	private static function unslash( string $value ): string {
		if ( function_exists( 'wp_unslash' ) ) {
			return wp_unslash( $value );
		}

		return stripslashes( $value );
	}
}
