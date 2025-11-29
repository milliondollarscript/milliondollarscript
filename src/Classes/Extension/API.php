<?php

namespace MillionDollarScript\Classes\Extension;

use MillionDollarScript\Classes\System\Utility;

use MillionDollarScript\Classes\Data\Options;

class API {

    /**
     * Check if version analytics tracking is disabled.
     *
     * @return bool True if analytics is disabled, false if enabled
     */
    private static function is_version_analytics_disabled(): bool {
        if ( ! is_callable( [ Options::class, 'get_option' ] ) ) {
            return false;
        }
        return Options::get_option( 'disable_version_analytics', 'no' ) === 'yes';
    }

    /**
     * Get the base URL for the extension server.
     *
     * @return string
     */
    private static function get_base_url(): string {
        // Prefer option set in MDS settings; fallback to constant; then to production default
        $opt = is_callable([Options::class, 'get_option']) ? Options::get_option('extension_server_url', '') : '';
        if (is_string($opt) && $opt !== '') {
            return rtrim($opt, '/');
        }
        if (defined('MDS_EXTENSION_SERVER_URL')) {
            return rtrim((string) MDS_EXTENSION_SERVER_URL, '/');
        }
        // Default to production URL; override with MDS_EXTENSION_SERVER_URL constant or wp-config for dev
        return 'https://milliondollarscript.com';
    }

    /**
     * Activate a license key for an extension.
     *
     * @param string $license_key
     * @param string $extension_slug
     * @param string $version Optional version string to track
     * @return array|mixed
     */
    public static function activate_license( string $license_key, string $extension_slug, string $version = '' ) {
        // Use public API for activation; pass productIdentifier as slug for now
        $url = self::get_base_url() . '/api/public/activate';

        $body = [
            'licenseKey'        => $license_key,
            'productIdentifier' => $extension_slug,
            'deviceId'          => md5( site_url() ), // Required: unique site identifier for activation tracking
        ];

        // Only send version if analytics not disabled
        if ( $version !== '' && ! self::is_version_analytics_disabled() ) {
            $body['version'] = $version;
        }

        $response = wp_remote_post( $url, [
            'body' => json_encode( $body ),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 20,
            'sslverify' => ! Utility::is_development_environment(),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Validate a license key for an extension.
     *
     * @param string $license_key
     * @param string $extension_slug
     * @param string $version Optional version string to track
     * @return array|mixed
     */
    public static function validate_license( string $license_key, string $extension_slug, string $version = '' ) {
        $url = self::get_base_url() . '/api/public/validate';

        $body = [
            'licenseKey'        => $license_key,
            'productIdentifier' => $extension_slug,
            'deviceId'          => md5( site_url() ), // Required for version tracking
        ];

        // Only send version if analytics not disabled
        if ( $version !== '' && ! self::is_version_analytics_disabled() ) {
            $body['version'] = $version;
        }

        $response = wp_remote_post( $url, [
            'body' => json_encode( $body ),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 20,
            'sslverify' => ! Utility::is_development_environment(),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'valid'   => false,
                'message' => $response->get_error_message(),
            ];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if (!is_array($data)) {
            return [ 'success' => false, 'valid' => false, 'message' => 'Invalid response' ];
        }
        // Normalize to expected shape
        $valid = !empty($data['valid']);
        return [
            'success' => true,
            'valid'   => $valid,
            'license' => $data['license'] ?? null,
            'message' => $data['message'] ?? ($valid ? 'Valid' : 'Invalid'),
        ];
    }

    /**
     * Deactivate a license key for an extension.
     *
     * @param string $license_key
     * @param string $extension_slug
     * @param string $version Optional version string to track
     * @return array|mixed
     */
    public static function deactivate_license( string $license_key, string $extension_slug, string $version = '' ) {
        // Use public API for deactivation; pass productIdentifier as slug for consistency
        $url = self::get_base_url() . '/api/public/deactivate';

        $body = [
            'licenseKey'        => $license_key,
            'productIdentifier' => $extension_slug,
            'deviceId'          => md5( site_url() ), // Required: unique site identifier for deactivation tracking
        ];

        // Only send version if analytics not disabled
        if ( $version !== '' && ! self::is_version_analytics_disabled() ) {
            $body['version'] = $version;
        }

        $response = wp_remote_post( $url, [
            'body' => json_encode( $body ),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 20,
            'sslverify' => ! Utility::is_development_environment(),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
}