<?php
/**
 * Stable operational helpers shared by extensions.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Extensions;

use MillionDollarScript\V3\Extensions\ExtensionSupport;
use MillionDollarScript\V3\Extensions\ExtensionServer;
use MillionDollarScript\V3\Rest\Api;

if (!defined('ABSPATH')) {
    exit;
}

final class Support {

    public static function extension_server_url(): string {
        return (string) ExtensionServer::base_url();
    }

    public static function register_autoloader(string $namespace_prefix, string $base_path): bool {
        return (bool) ExtensionSupport::register_autoloader($namespace_prefix, $base_path);
    }

    public static function admin_parent_slug($fallback = 'options-general.php'): string {
        return (string) ExtensionSupport::admin_parent_slug($fallback);
    }

    public static function admin_url($page, array $args = []): string {
        return (string) ExtensionSupport::admin_url($page, $args);
    }

    public static function redirect_admin($page, array $args = []): void {
        ExtensionSupport::redirect_admin($page, $args);
    }

    public static function safe_redirect($url, array $args = []): void {
        ExtensionSupport::safe_redirect($url, $args);
    }

    public static function external_redirect($url, $fallback_url = ''): void {
        ExtensionSupport::external_redirect($url, $fallback_url);
    }

    public static function external_url($url): string {
        return (string) ExtensionSupport::external_url($url);
    }

    /**
     * @return bool|\WP_Error
     */
    public static function permission($request, $scope, $level) {
        return ExtensionSupport::permission($request, $scope, $level);
    }

    public static function can_manage_api(): bool {
        return (bool) ExtensionSupport::can_manage_api();
    }

    public static function remote_ip(): string {
        return (string) ExtensionSupport::remote_ip();
    }

    public static function rate_limited($key, $prefix = 'mds_ext_rate', $ttl = 45): bool {
        return (bool) ExtensionSupport::rate_limited($key, $prefix, $ttl);
    }

    public static function register_rest_route(string $route, array $args): bool {
        return (bool) register_rest_route(Api::REST_NAMESPACE, $route, $args);
    }

    public static function add_browser_config(string $handle, string $extension, string $key, array $config): bool {
        $extension = preg_replace('/[^A-Za-z0-9_-]/', '', $extension) ?: '';
        $key = preg_replace('/[^A-Za-z0-9_-]/', '', $key) ?: '';
        if ('' === $handle || '' === $extension || '' === $key) {
            return false;
        }

        $extension_json = wp_json_encode($extension);
        $key_json = wp_json_encode($key);
        $config_json = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (false === $extension_json || false === $key_json || false === $config_json) {
            return false;
        }

        $script = 'window.MillionDollarScript=window.MillionDollarScript||{};'
            . 'window.MillionDollarScript.extensions=window.MillionDollarScript.extensions||{};'
            . 'window.MillionDollarScript.extensions[' . $extension_json . ']=window.MillionDollarScript.extensions[' . $extension_json . ']||{};'
            . 'window.MillionDollarScript.extensions[' . $extension_json . '][' . $key_json . ']=' . $config_json . ';';

        return (bool) wp_add_inline_script($handle, $script, 'before');
    }
}
