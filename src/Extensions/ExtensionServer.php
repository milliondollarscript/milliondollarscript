<?php
/**
 * Extension server endpoint resolution.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionServer {

    public const DEFAULT_URL = 'https://milliondollarscript.com';
    public const LOCAL_URL = 'http://host.docker.internal:3030';
    public const LOCAL_PUBLIC_URL = 'http://localhost:3030';

    public static function base_url(array $settings = null) {
        if (null === $settings) {
            $settings = get_option('mds3_settings', []);
            $settings = is_array($settings) ? $settings : [];
        }

        $mode = self::mode();
        $configured = (string) ($settings['extension_server_url'] ?? self::DEFAULT_URL);
        $explicit = self::config('MDS3_EXTENSION_SERVER_URL');
        $url = '' !== $explicit ? $explicit : $configured;

        if ('' === $explicit && self::is_local_mode($mode)) {
            $url = self::config('MDS3_EXTENSION_SERVER_LOCAL_URL') ?: self::LOCAL_URL;
        }

        $url = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/server/base/url', $url, $settings, $mode);

        return self::normalize_url($url);
    }

    public static function mode() {
        $mode = self::config('MDS3_EXTENSION_SERVER_MODE');

        return strtolower(trim($mode ?: 'production'));
    }

    public static function public_url(array $settings = null, $base_url = null) {
        $base_url = $base_url ?: self::base_url($settings);
        $explicit = self::config('MDS3_EXTENSION_SERVER_PUBLIC_URL');
        $url = '' !== $explicit ? $explicit : $base_url;

        if ('' === $explicit && self::is_local()) {
            $url = self::LOCAL_PUBLIC_URL;
        }

        $url = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/server/public/url', $url, $settings, self::mode(), $base_url);

        return self::normalize_url($url);
    }

    public static function is_local() {
        return self::is_local_mode(self::mode());
    }

    public static function installation_id() {
        $id = (string) get_option('mds3_installation_id', '');
        if ('' === $id) {
            $id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : md5(wp_rand() . microtime(true));
            update_option('mds3_installation_id', $id, false);
        }

        return $id;
    }

    public static function compatibility_args() {
        return [
            'platform' => 'wordpress',
            'mds_generation' => '3',
            'core' => 'million-dollar-script',
            'product_family' => defined('MILLION_DOLLAR_SCRIPT_PRODUCT_FAMILY') ? MILLION_DOLLAR_SCRIPT_PRODUCT_FAMILY : 'modern',
            'core_version' => defined('MILLION_DOLLAR_SCRIPT_VERSION') ? MILLION_DOLLAR_SCRIPT_VERSION : '3.0.0',
            'core_api_version' => defined('MILLION_DOLLAR_SCRIPT_CORE_API_VERSION') ? (int) MILLION_DOLLAR_SCRIPT_CORE_API_VERSION : 1,
        ];
    }

    private static function is_local_mode($mode) {
        return in_array(strtolower(trim((string) $mode)), ['local', 'dev', 'development'], true);
    }

    private static function config($key) {
        if (defined($key)) {
            return trim((string) constant($key));
        }

        $value = getenv($key);
        if (false === $value && isset($_ENV[$key])) {
            $value = sanitize_text_field(wp_unslash((string) $_ENV[$key]));
        }
        if (false === $value && isset($_SERVER[$key])) {
            $value = sanitize_text_field(wp_unslash((string) $_SERVER[$key]));
        }

        return false === $value ? '' : trim((string) $value);
    }

    private static function normalize_url($url) {
        if (!is_scalar($url)) {
            return '';
        }

        $url = rtrim(esc_url_raw((string) $url, ['http', 'https']), '/');
        $parts = wp_parse_url($url);

        if (empty($parts['scheme']) || empty($parts['host']) || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return '';
        }

        return $url;
    }
}
