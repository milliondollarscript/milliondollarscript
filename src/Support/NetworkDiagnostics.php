<?php
/**
 * On-demand, privacy-minimized network diagnostics.
 *
 * @package MillionDollarScript\V3\Support
 */

namespace MillionDollarScript\V3\Support;

use MillionDollarScript\V3\Extensions\ExtensionServer;

if (!defined('ABSPATH')) {
    exit;
}

final class NetworkDiagnostics implements Component {

    private const TRANSIENT_PREFIX = 'mds3_network_diagnostics_';

    public function register() {
        add_action('admin_post_mds3_run_network_diagnostics', [$this, 'handle']);
    }

    public function handle() {
        check_admin_referer('mds3_run_network_diagnostics');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        set_transient(self::TRANSIENT_PREFIX . get_current_user_id(), self::run(), 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=mds3-system-status&network_checked=1'));
        exit;
    }

    public static function latest() {
        $value = get_transient(self::TRANSIENT_PREFIX . get_current_user_id());

        return is_array($value) ? $value : [];
    }

    public static function run(array $endpoints = null) {
        if (null === $endpoints) {
            $base_url = ExtensionServer::base_url();
            $endpoints = $base_url ? [
                [
                    'id' => 'extension-server-health',
                    'label' => __('Extension server health', 'million-dollar-script'),
                    'url' => $base_url . '/health',
                    'allow_private' => ExtensionServer::is_local(),
                ],
                [
                    'id' => 'extension-catalog',
                    'label' => __('Extension catalog', 'million-dollar-script'),
                    'url' => $base_url . '/api/public/extensions',
                    'allow_private' => ExtensionServer::is_local(),
                ],
            ] : [];
            $endpoints = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/network/diagnostic/endpoints', $endpoints);
        }

        $checks = [];
        foreach (array_slice(is_array($endpoints) ? $endpoints : [], 0, 12) as $endpoint) {
            if (!is_array($endpoint)) {
                continue;
            }
            $url = esc_url_raw((string) ($endpoint['url'] ?? ''), ['http', 'https']);
            $parts = wp_parse_url($url);
            if (!$url || empty($parts['scheme']) || empty($parts['host'])) {
                continue;
            }

            $started = microtime(true);
            $args = [
                'redirection' => 0,
                'timeout' => 8,
                'limit_response_size' => 4096,
                'headers' => ['Accept' => 'application/json, text/plain;q=0.8, */*;q=0.1'],
            ];
            $response = !empty($endpoint['allow_private'])
                ? wp_remote_get($url, $args)
                : wp_safe_remote_get($url, $args);
            $duration_ms = max(0, (int) round((microtime(true) - $started) * 1000));

            $checks[] = self::result($endpoint, $url, $response, $duration_ms);
        }

        return [
            'checked_at' => gmdate('c'),
            'checks' => $checks,
        ];
    }

    private static function result(array $endpoint, $url, $response, $duration_ms) {
        $base = [
            'id' => sanitize_key((string) ($endpoint['id'] ?? 'endpoint')),
            'label' => sanitize_text_field((string) ($endpoint['label'] ?? __('Service endpoint', 'million-dollar-script'))),
            'url' => self::display_url($url),
            'host' => sanitize_text_field((string) wp_parse_url($url, PHP_URL_HOST)),
            'duration_ms' => absint($duration_ms),
            'status' => 0,
            'outcome' => 'network_error',
            'request_id' => '',
            'message' => '',
        ];

        if (is_wp_error($response)) {
            $base['message'] = sanitize_text_field($response->get_error_message());
            return $base;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $base['status'] = $status;
        $base['outcome'] = self::classify($status);
        foreach (['cf-ray', 'cdn-requestid', 'cdn-request-id', 'x-request-id', 'x-amz-cf-id'] as $header) {
            $value = wp_remote_retrieve_header($response, $header);
            if ($value) {
                $base['request_id'] = sanitize_text_field((string) $value);
                break;
            }
        }

        return $base;
    }

    private static function classify($status) {
        if ($status >= 200 && $status < 300) {
            return 'reachable';
        }
        if ($status >= 300 && $status < 400) {
            return 'redirect';
        }
        if (in_array($status, [401, 403], true)) {
            return 'access_denied';
        }
        if (429 === $status) {
            return 'rate_limited';
        }
        if ($status >= 500) {
            return 'server_error';
        }

        return 'http_error';
    }

    private static function display_url($url) {
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $port = !empty($parts['port']) ? ':' . absint($parts['port']) : '';
        return esc_url_raw((string) ($parts['scheme'] ?? '') . '://' . (string) ($parts['host'] ?? '') . $port . (string) ($parts['path'] ?? '/'));
    }
}
