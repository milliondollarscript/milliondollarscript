<?php

namespace MillionDollarScript\Classes\Extension;

use MillionDollarScript\Classes\Data\Options;

class API {

    /**
     * Get the base URL for the extension server.
     *
     * @return string
     */
    private static function get_base_url(): string {
        return defined( 'MDS_EXTENSION_SERVER_URL' ) ? MDS_EXTENSION_SERVER_URL : 'https://api.milliondollarscript.com';
    }

    /**
     * Activate a license key for an extension.
     *
     * @param string $license_key
     * @param string $extension_slug
     * @return array|mixed
     */
    public static function activate_license( string $license_key, string $extension_slug ) {
        $url = self::get_base_url() . '/api/licenses/activate';

        $response = wp_remote_post( $url, [
            'body' => json_encode( [
                'license_key'    => $license_key,
                'extension_slug' => $extension_slug,
            ] ),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
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
     * @return array|mixed
     */
    public static function validate_license( string $license_key, string $extension_slug ) {
        $url = self::get_base_url() . '/api/licenses/validate';

        $response = wp_remote_post( $url, [
            'body' => json_encode( [
                'license_key'    => $license_key,
                'extension_slug' => $extension_slug,
            ] ),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
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
     * Deactivate a license key for an extension.
     *
     * @param string $license_key
     * @param string $extension_slug
     * @return array|mixed
     */
    public static function deactivate_license( string $license_key, string $extension_slug ) {
        $url = self::get_base_url() . '/api/licenses/deactivate';

        $response = wp_remote_post( $url, [
            'body' => json_encode( [
                'license_key'    => $license_key,
                'extension_slug' => $extension_slug,
            ] ),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
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