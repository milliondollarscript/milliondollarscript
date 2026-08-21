<?php
/**
 * ImageGrid account and configuration helpers.
 *
 * @package MillionDollarScript\V3\Rendering
 */

namespace MillionDollarScript\V3\Rendering\Concerns;

if (!defined('ABSPATH')) {
    exit;
}

trait ManagesImageGridAccount {

    public function settings() {
        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];

        return [
            'app_url' => self::ACCOUNT_URL,
            'api_url' => $this->api_url($settings),
            'api_key_configured' => '' !== $this->api_key($settings),
            'remote_enabled' => $this->remote_enabled($settings),
            'account' => $this->account_status($settings),
            'quota' => $this->quota($settings),
        ];
    }

    public function test_connection() {
        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $filtered = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/test/connection', null, $settings);
        if (is_wp_error($filtered) || is_array($filtered)) {
            return $filtered;
        }

        $api_url = $this->api_url($settings);
        $api_key = $this->api_key($settings);

        if (!$api_url || !$api_key) {
            return new \WP_Error('mds3_imagegrid_not_configured', __('ImageGrid API URL and API key are required.', 'million-dollar-script'));
        }

        return new \WP_Error(
            'mds3_imagegrid_extension_unavailable',
            __('The ImageGrid extension did not provide a connection test handler.', 'million-dollar-script'),
            ['api_url' => $api_url]
        );
    }

    private function quota(array $settings = null) {
        if (null === $settings) {
            $settings = get_option('mds3_settings', []);
            $settings = is_array($settings) ? $settings : [];
        }

        $quota = [
            'grid_megapixels' => 100.0,
            'source_megapixels' => 250.0,
            'tile_estimate' => 12000.0,
            'storage_estimate_bytes' => (float) (2 * GB_IN_BYTES),
            'processing_credits' => 5000.0,
        ];

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/quota', $quota);
    }

    private function should_use_remote(array $estimate) {
        if (!$this->remote_enabled()) {
            return false;
        }

        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $threshold = 25.0;
        $threshold = (float) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/local/render/threshold/megapixels', $threshold, $settings, $estimate);

        return (float) ($estimate['grid_megapixels'] ?? 0) > $threshold;
    }

    private function remote_enabled(array $settings = null) {
        if (null === $settings) {
            $settings = get_option('mds3_settings', []);
            $settings = is_array($settings) ? $settings : [];
        }

        $enabled = (bool) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/remote/enabled', false, $settings);

        return $enabled && '' !== $this->api_url($settings) && '' !== $this->api_key($settings);
    }

    private function account_status(array $settings) {
        $remote_enabled = $this->remote_enabled($settings);
        $api_configured = '' !== $this->api_url($settings) && '' !== $this->api_key($settings);
        $site = rawurlencode(home_url('/'));
        $account = [
            'status' => $api_configured ? 'configured' : 'disconnected',
            'label' => $api_configured ? __('Configured', 'million-dollar-script') : __('Local fallback', 'million-dollar-script'),
            'plan' => $api_configured ? __('ImageGrid account configured', 'million-dollar-script') : __('Local rendering only', 'million-dollar-script'),
            'usage' => [],
            'account_url' => self::ACCOUNT_URL . '/account',
            'billing_portal_url' => self::ACCOUNT_URL . '/portal/billing',
            'connect_url' => self::ACCOUNT_URL . '/connect?site=' . $site,
            'reconnect_url' => self::ACCOUNT_URL . '/connect?mode=reconnect&site=' . $site,
            'fallback_mode' => $remote_enabled ? 'remote_when_needed' : 'local',
        ];

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/account/status', $account, $settings);
    }

    private function api_url(array $settings = null) {
        if (null === $settings) {
            $settings = get_option('mds3_settings', []);
            $settings = is_array($settings) ? $settings : [];
        }

        $url = '';
        $url = rtrim((string) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/api/url', $url, $settings), '/');
        $parts = wp_parse_url($url);

        if (empty($parts['scheme']) || empty($parts['host']) || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return '';
        }

        return $url;
    }

    private function api_key(array $settings = null) {
        if (null === $settings) {
            $settings = get_option('mds3_settings', []);
            $settings = is_array($settings) ? $settings : [];
        }

        return sanitize_text_field((string) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/imagegrid/api/key', '', $settings));
    }
}
