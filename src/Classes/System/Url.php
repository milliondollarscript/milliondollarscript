<?php

namespace MillionDollarScript\Classes\System;

defined( 'ABSPATH' ) or exit;

class Url {

	public static function advertiser_url( $value ): string {
		$value = Request::scalar( $value );
		$value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
		$value = str_replace( [ "\r", "\n", "\t" ], '', $value );
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( str_starts_with( $value, '//' ) ) {
			$value = 'https:' . $value;
		} else if ( ! preg_match( '/^[a-z][a-z0-9+\-.]*:/i', $value ) ) {
			$value = 'https://' . ltrim( $value, '/' );
		}

		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $value ) : parse_url( $value );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$scheme = strtolower( $parts['scheme'] ?? '' );
		$host   = $parts['host'] ?? '';

		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) || '' === $host ) {
			return '';
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}

		if ( preg_match( '/[\s\x00-\x1F\x7F]/', $host ) ) {
			return '';
		}

		if ( function_exists( 'wp_http_validate_url' ) ) {
			$validated = wp_http_validate_url( $value );
			if ( ! $validated ) {
				return '';
			}
			$value = $validated;
		} else if ( false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return '';
		}

		if ( function_exists( 'esc_url_raw' ) ) {
			$value = esc_url_raw( $value, [ 'http', 'https' ] );
		}

		return is_string( $value ) ? $value : '';
	}
}
