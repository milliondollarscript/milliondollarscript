<?php
/**
 * Core plugin update integration.
 *
 * @package MillionDollarScript\V3\Updates
 */

namespace MillionDollarScript\V3\Updates;

use MillionDollarScript\V3\Extensions\ExtensionAnalytics;
use MillionDollarScript\V3\Extensions\ExtensionServer;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\Component;
use MillionDollarScript\V3\Support\ReleaseProfile;

if (!defined('ABSPATH')) {
    exit;
}

final class CorePluginUpdater implements Component {

    /**
     * Register WordPress update hooks.
     *
     * @return void
     */
    public function register() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'plugin_information'], 20, 3);
        add_filter('http_request_host_is_external', [$this, 'allow_update_host'], 10, 3);
        add_filter('http_allowed_safe_ports', [$this, 'allow_update_port'], 10, 3);
    }

    /**
     * Add Million Dollar Script updates to the normal WordPress plugin updater.
     *
     * @param mixed $transient WordPress update transient.
     * @return mixed
     */
    public function inject_update($transient) {
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }

        if (empty($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        if (!is_array($transient->response ?? null)) {
            $transient->response = [];
        }
        if (!is_array($transient->no_update ?? null)) {
            $transient->no_update = [];
        }

        $current_version = $this->current_version($transient);
        $update = $this->request_update($current_version);
        if (!$update) {
            unset($transient->response[MILLION_DOLLAR_SCRIPT_BASENAME]);
            $transient->no_update[MILLION_DOLLAR_SCRIPT_BASENAME] = $this->plugin_update_object($current_version);
            return $transient;
        }

        unset($transient->no_update[MILLION_DOLLAR_SCRIPT_BASENAME]);
        $transient->response[MILLION_DOLLAR_SCRIPT_BASENAME] = $update;

        return $transient;
    }

    /**
     * Populate the WordPress plugin details modal.
     *
     * @param mixed  $result Existing result.
     * @param string $action Requested API action.
     * @param object $args   Plugin API arguments.
     * @return mixed
     */
    public function plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action || !is_object($args)) {
            return $result;
        }

        $slug = sanitize_key((string) ($args->slug ?? ''));
        if ('million-dollar-script' !== $slug) {
            return $result;
        }

        $current_version = $this->installed_version();
        $update = $this->request_update($current_version);
        $version = $update ? (string) ($update->new_version ?? MILLION_DOLLAR_SCRIPT_VERSION) : MILLION_DOLLAR_SCRIPT_VERSION;
        $sections = [
            'description' => esc_html__('WordPress-first pixel grid advertising plugin.', 'million-dollar-script'),
        ];

        if ($update && !empty($update->sections['changelog'])) {
            $sections['changelog'] = $update->sections['changelog'];
        }

        return (object) [
            'name' => 'Million Dollar Script',
            'slug' => 'million-dollar-script',
            'version' => $version,
            'author' => 'Million Dollar Script',
            'homepage' => 'https://milliondollarscript.com',
            'requires' => (string) ($update->requires ?? '6.0'),
            'requires_php' => (string) ($update->requires_php ?? '8.1'),
            'tested' => (string) ($update->tested ?? ''),
            'sections' => $sections,
            'download_link' => (string) ($update->package ?? ''),
        ];
    }

    /**
     * Permit WordPress' safe HTTP layer to download local extension-server packages.
     *
     * @param bool   $external Whether the host is external.
     * @param string $host     Requested host.
     * @param string $url      Requested URL.
     * @return bool
     */
    public function allow_update_host($external, $host, $url) {
        if (!$this->is_allowed_update_host($host, $url)) {
            return $external;
        }

        return true;
    }

    /**
     * Permit local extension-server ports for WordPress safe downloads.
     *
     * @param int[]  $ports Allowed safe ports.
     * @param string $host  Requested host.
     * @param string $url   Requested URL.
     * @return int[]
     */
    public function allow_update_port($ports, $host, $url) {
        $ports = is_array($ports) ? array_map('intval', $ports) : [80, 443, 8080];
        if (!$this->is_allowed_update_host($host, $url)) {
            return $ports;
        }

        $settings = $this->settings();
        $server_url = (string) ExtensionServer::base_url($settings);
        $server_port = (int) wp_parse_url($server_url, PHP_URL_PORT);
        $url_port = (int) wp_parse_url((string) $url, PHP_URL_PORT);

        foreach ([$server_port, $url_port, 3030] as $port) {
            if ($port > 0) {
                $ports[] = $port;
            }
        }

        return array_values(array_unique(array_filter($ports)));
    }

    /**
     * Return whether the request targets the configured extension server.
     *
     * @param string $host Requested host.
     * @param string $url  Requested URL.
     * @return bool
     */
    private function is_allowed_update_host($host, $url) {
        $settings = $this->settings();
        $server_url = (string) ExtensionServer::base_url($settings);
        $server_host = (string) wp_parse_url($server_url, PHP_URL_HOST);
        $request_host = strtolower((string) $host);
        $url_host = strtolower((string) wp_parse_url((string) $url, PHP_URL_HOST));

        $allowed_hosts = array_filter(array_map('strtolower', [
            $server_host,
            'host.docker.internal',
            'extension-server-go',
            'extension-server',
            'localhost',
            '127.0.0.1',
        ]));

        return in_array($request_host, $allowed_hosts, true) || in_array($url_host, $allowed_hosts, true);
    }

    /**
     * Request update metadata from the configured extension server.
     *
     * @return object|null
     */
    private function request_update($current_version = null) {
        $settings = $this->settings();
        $server_url = rtrim(esc_url_raw((string) ExtensionServer::base_url($settings)), '/');
        if (!$server_url) {
            return null;
        }

        $current_version = sanitize_text_field((string) ($current_version ?: MILLION_DOLLAR_SCRIPT_VERSION));
        if ('' === $current_version) {
            $current_version = MILLION_DOLLAR_SCRIPT_VERSION;
        }

        $channel = $this->normalize_channel(ReleaseProfile::update_channel((string) ($settings['updates'] ?? 'main')));
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => $this->user_agent(),
        ];

        $analytics_disabled = 'yes' === strtolower((string) ($settings['disable_version_analytics'] ?? 'no'));
        if ($analytics_disabled) {
            $headers['X-MDS-Analytics-Opt-Out'] = '1';
        }


        $request_body = array_merge(ExtensionServer::compatibility_args(), [
            'channel' => $channel,
            'current_version' => $current_version,
            'core_slug' => 'million-dollar-script',
            'plugin_slug' => 'million-dollar-script',
            'site_url' => home_url('/'),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
        ]);
        if (!$analytics_disabled) {
            $request_body['analytics_payload_version'] = 1;
            $request_body['extensions'] = ExtensionAnalytics::snapshot();
        }

        $response = wp_remote_post($server_url . '/api/public/core-plugin/v1/check-update', [
            'headers' => $headers,
            'body' => wp_json_encode($request_body),
            'timeout' => 15,
            'sslverify' => $this->ssl_verify($server_url),
        ]);

        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return null;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response));
        if (!$body || empty($body->success) || empty($body->data) || empty($body->data->update_available)) {
            return null;
        }

        $version = sanitize_text_field((string) ($body->data->new_version ?? $body->data->latest_version ?? ''));
        $package = esc_url_raw((string) ($body->data->package ?? $body->data->download_url ?? ''));
        if (!$version || !$package || !version_compare($version, $current_version, '>')) {
            return null;
        }

        $update = $this->plugin_update_object($version);
        $update->package = $package;
        $update->download_url = $package;
        $update->requires = sanitize_text_field((string) ($body->data->requires ?? $update->requires));
        $update->requires_php = sanitize_text_field((string) ($body->data->requires_php ?? $update->requires_php));
        $update->tested = sanitize_text_field((string) ($body->data->tested ?? ''));

        $changelog = trim((string) ($body->data->changelog ?? ''));
        if ($changelog) {
            $update->sections = [
                'changelog' => wpautop(esc_html($changelog)),
            ];
        }

        return $update;
    }

    /**
     * Build a WordPress update object.
     *
     * @param string $version Plugin version.
     * @return object
     */
    private function plugin_update_object($version) {
        return (object) [
            'id' => MILLION_DOLLAR_SCRIPT_BASENAME,
            'slug' => 'million-dollar-script',
            'plugin' => MILLION_DOLLAR_SCRIPT_BASENAME,
            'new_version' => (string) $version,
            'url' => 'https://milliondollarscript.com',
            'package' => '',
            'requires' => '6.0',
            'requires_php' => '8.1',
            'tested' => '',
        ];
    }

    /**
     * Return the version WordPress believes is installed.
     *
     * @param object|null $transient Update transient.
     * @return string
     */
    private function current_version($transient = null) {
        if (is_object($transient) && is_array($transient->checked ?? null) && !empty($transient->checked[MILLION_DOLLAR_SCRIPT_BASENAME])) {
            return sanitize_text_field((string) $transient->checked[MILLION_DOLLAR_SCRIPT_BASENAME]);
        }

        return $this->installed_version();
    }

    /**
     * Return the installed plugin header version.
     *
     * @return string
     */
    private function installed_version() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = function_exists('get_plugins') ? get_plugins() : [];
        if (is_array($plugins) && !empty($plugins[MILLION_DOLLAR_SCRIPT_BASENAME]['Version'])) {
            return sanitize_text_field((string) $plugins[MILLION_DOLLAR_SCRIPT_BASENAME]['Version']);
        }

        return MILLION_DOLLAR_SCRIPT_VERSION;
    }

    /**
     * Return normalized settings.
     *
     * @return array
     */
    private function settings() {
        $settings = get_option('mds3_settings', []);

        return wp_parse_args(is_array($settings) ? $settings : [], SettingsSchema::defaults());
    }

    /**
     * Normalize update channel for the extension server.
     *
     * @param string $channel Stored channel setting.
     * @return string
     */
    private function normalize_channel($channel) {
        $channel = strtolower(trim($channel));
        if (in_array($channel, ['alpha', 'dev', 'development', 'nightly'], true)) {
            return 'alpha';
        }
        if ('beta' === $channel) {
            return 'beta';
        }

        return 'stable';
    }

    /**
     * Return whether SSL should be verified for a server URL.
     *
     * @param string $server_url Extension server URL.
     * @return bool
     */
    private function ssl_verify($server_url) {
        $host = strtolower((string) wp_parse_url($server_url, PHP_URL_HOST));

        return !in_array($host, ['localhost', '127.0.0.1', 'host.docker.internal'], true);
    }

    /**
     * Build a concise update check user agent.
     *
     * @return string
     */
    private function user_agent() {
        global $wp_version;

        return sprintf(
            'MDS-Core/%s (WordPress/%s; %s)',
            MILLION_DOLLAR_SCRIPT_VERSION,
            (string) $wp_version,
            home_url('/')
        );
    }
}
