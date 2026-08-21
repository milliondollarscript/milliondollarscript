<?php
/**
 * Shared helpers for extension admin and request plumbing.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

use MillionDollarScript\V3\Rest\ApiGovernance;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionSupport {

    /**
     * Registered extension namespace and source-directory pairs.
     *
     * @var array<string, bool>
     */
    private static $autoloaders = [];

    public static function register_autoloader($namespace_prefix, $base_path) {
        $namespace_prefix = trim((string) $namespace_prefix, '\\') . '\\';
        $base_path = realpath((string) $base_path);

        if ('\\' === $namespace_prefix || false === $base_path || !is_dir($base_path)) {
            return false;
        }

        $base_path = rtrim($base_path, '/\\') . DIRECTORY_SEPARATOR;
        $registration_key = $namespace_prefix . '|' . $base_path;
        if (isset(self::$autoloaders[$registration_key])) {
            return true;
        }

        spl_autoload_register(static function($class) use ($namespace_prefix, $base_path) {
            if (0 !== strpos($class, $namespace_prefix)) {
                return;
            }

            $relative_class = substr($class, strlen($namespace_prefix));
            if ('' === $relative_class || false !== strpos($relative_class, '..')) {
                return;
            }

            $relative_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class);
            $files = [
                $base_path . $relative_path . '.php',
                $base_path . str_replace('_', '', $relative_path) . '.php',
            ];

            foreach (array_unique($files) as $file) {
                if (is_readable($file)) {
                    require_once $file;
                    return;
                }
            }
        });

        self::$autoloaders[$registration_key] = true;

        return true;
    }

    public static function admin_parent_slug($fallback = 'options-general.php') {
        global $admin_page_hooks;

        if (isset($admin_page_hooks['mds3'])) {
            return 'mds3';
        }
        if (isset($admin_page_hooks['million-dollar-script'])) {
            return 'million-dollar-script';
        }
        if (isset($admin_page_hooks['milliondollarscript'])) {
            return 'milliondollarscript';
        }
        if (defined('MILLION_DOLLAR_SCRIPT_VERSION') || class_exists('\\MillionDollarScript\V3\\Plugin')) {
            return 'mds3';
        }

        return (string) $fallback;
    }

    public static function admin_url($page, array $args = []) {
        return add_query_arg(array_merge(['page' => sanitize_key((string) $page)], $args), admin_url('admin.php'));
    }

    public static function redirect_admin($page, array $args = []) {
        wp_safe_redirect(self::admin_url($page, $args));
        exit;
    }

    public static function safe_redirect($url, array $args = []) {
        $url = $args ? add_query_arg($args, $url) : $url;
        wp_safe_redirect(esc_url_raw($url));
        exit;
    }

    public static function external_redirect($url, $fallback_url = '') {
        $target = self::external_url($url);
        if ('' === $target) {
            $fallback = esc_url_raw((string) $fallback_url);
            wp_safe_redirect($fallback ?: home_url('/'));
            exit;
        }

        $target_host = strtolower((string) wp_parse_url($target, PHP_URL_HOST));
        $allow_target = static function ($hosts) use ($target_host) {
            $hosts = is_array($hosts) ? $hosts : [];
            if ($target_host) {
                $hosts[] = $target_host;
            }

            return array_values(array_unique($hosts));
        };
        add_filter('allowed_redirect_hosts', $allow_target);
        wp_safe_redirect($target, 302, 'Million Dollar Script');
        remove_filter('allowed_redirect_hosts', $allow_target);
        exit;
    }

    public static function external_url($url) {
        $target = esc_url_raw((string) $url, ['http', 'https']);
        if (!$target) {
            return '';
        }

        $parts = wp_parse_url($target);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        return in_array($scheme, ['http', 'https'], true) ? $target : '';
    }

    public static function permission($request, $scope, $level) {
        if (class_exists(ApiGovernance::class)) {
            return (new ApiGovernance())->authorize($request, $scope, $level);
        }

        return current_user_can('manage_options');
    }

    public static function can_manage_api() {
        return current_user_can('manage_options');
    }

    public static function remote_ip() {
        return sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')));
    }

    public static function rate_limited($key, $prefix = 'mds_ext_rate', $ttl = 45) {
        $key = sanitize_key((string) $key);
        $prefix = sanitize_key((string) $prefix) ?: 'mds_ext_rate';
        $ttl = max(1, absint($ttl));
        $hash = md5(self::remote_ip() . '|' . $key);
        $transient = substr($prefix . '_' . $hash, 0, 172);

        if (get_transient($transient)) {
            return true;
        }

        set_transient($transient, 1, $ttl);

        return false;
    }
}
