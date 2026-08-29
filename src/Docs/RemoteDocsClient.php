<?php
/**
 * License-gated extension documentation client.
 *
 * @package MillionDollarScript\V3\Docs
 */

namespace MillionDollarScript\V3\Docs;

use MillionDollarScript\V3\Extensions\ExtensionCatalog;
use MillionDollarScript\V3\Extensions\ExtensionLicenseManager;
use MillionDollarScript\V3\Extensions\ExtensionServer;
use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class RemoteDocsClient implements Component {

    private const CACHE_INDEX_OPTION = 'mds3_remote_docs_cache_keys';
    private const CACHE_SCHEMA_VERSION = 2;
    private const DEFAULT_CACHE_TTL = 300;
    private const MANUAL_REFRESH_COOLDOWN = 60;
    private const MANUAL_REFRESH_OPTION = 'million_dollar_script_remote_docs_last_refresh_at';
    private const MAX_MARKDOWN_BYTES = 524288;
    private const UNAVAILABLE_DOC_ID = 'documentation-unavailable';

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register() {
        add_filter('million-dollar-script/docs/packages', [$this, 'add_remote_packages'], 20);
    }

    /**
     * Replace bundled paid-extension docs with license-gated remote docs.
     *
     * @param array<string,array> $packages Existing local packages.
     * @return array<string,array>
     */
    public function add_remote_packages($packages) {
        $packages = is_array($packages) ? $packages : [];
        $license_manager = new ExtensionLicenseManager();

        $public_core_enabled = (bool) \MillionDollarScript\Core\Hooks::apply(
            'million-dollar-script/remote/docs/public-core/enabled',
            true
        );
        if ($public_core_enabled) {
            $remote_core = $this->fetch_public_core_package($packages['million-dollar-script'] ?? []);
            if ($remote_core) {
                $packages['million-dollar-script'] = $remote_core;
            }
        }

        foreach ((new ExtensionCatalog())->installed() as $item) {
            if (empty($item['active'])) {
                continue;
            }

            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            if (!$slug || 'mds-grid' === $slug) {
                continue;
            }

            $license_required = !empty($item['license_required']) || $license_manager->is_active($slug);
            if (!$license_required) {
                continue;
            }

            unset($packages[$slug]);

            if (!$license_manager->has_access($slug)) {
                continue;
            }

            $remote_package = $this->fetch_manifest_package($item, $license_manager);
            if ($remote_package) {
                $packages[$slug] = $remote_package;
            }
        }

        return $packages;
    }

    /**
     * Read a remote Markdown document with entitlement checks.
     *
     * @param array $doc Remote docs registry item.
     * @return string
     */
    public function read_document(array $doc) {
        if (!empty($doc['content_markdown']) && is_scalar($doc['content_markdown'])) {
            return (string) $doc['content_markdown'];
        }

        $slug = sanitize_key((string) ($doc['package'] ?? ''));
        $doc_slug = sanitize_key((string) ($doc['remote_doc_slug'] ?? $doc['id'] ?? ''));
        $version = sanitize_text_field((string) ($doc['version'] ?? 'current'));
        $channel = sanitize_key((string) ($doc['channel'] ?? 'main')) ?: 'main';
        if (!$slug || !$doc_slug) {
            return '';
        }

        $license_required = !empty($doc['license_required']);
        if ($license_required) {
            $license_manager = new ExtensionLicenseManager();
            $access_candidates = $license_manager->access_candidates($slug);
            if (!$access_candidates) {
                self::purge_extension($slug);
                return $this->license_unavailable_markdown();
            }
        } else {
            $access_candidates = [['license_key' => '']];
        }

        foreach ($access_candidates as $candidate) {
            $license_key = (string) ($candidate['license_key'] ?? '');
            $cache_key = $this->cache_key('document', $slug, $version, $channel, $license_key, $doc_slug);
            $cached = $this->cache_get($cache_key);
            if (is_array($cached) && !empty($cached['content_markdown']) && is_scalar($cached['content_markdown'])) {
                return (string) $cached['content_markdown'];
            }

            $payload = $this->request_package_endpoint($slug, 'documents/' . rawurlencode($doc_slug), [
                'licenseKey' => $license_key,
                'instanceId' => ExtensionServer::installation_id(),
                'version' => $version ?: 'current',
                'channel' => $channel,
            ]);
            if ($this->is_entitlement_failure($payload)) {
                continue;
            }
            if (empty($payload['success']) || empty($payload['document']) || !is_array($payload['document'])) {
                return $this->temporary_unavailable_markdown();
            }

            $raw_markdown = $payload['document']['content_markdown'] ?? '';
            if (!is_scalar($raw_markdown)) {
                return $this->temporary_unavailable_markdown();
            }
            $expected_hash = strtolower(sanitize_text_field((string) ($doc['body_hash'] ?? '')));
            $response_hash = strtolower(sanitize_text_field((string) ($payload['document']['body_hash'] ?? '')));
            $actual_hash = hash('sha256', (string) $raw_markdown);
            if (($expected_hash && !hash_equals($expected_hash, $actual_hash))
                || ($response_hash && !hash_equals($response_hash, $actual_hash))) {
                return $this->temporary_unavailable_markdown();
            }
            $markdown = $this->sanitize_remote_markdown($raw_markdown);
            if ('' === $markdown) {
                return $this->temporary_unavailable_markdown();
            }
            $this->cache_set($slug, $cache_key, ['content_markdown' => $markdown]);
            return $markdown;
        }

        self::purge_extension($slug);
        return $this->license_unavailable_markdown();
    }

    /**
     * Delete all remembered remote-docs transients for an extension.
     *
     * @param string $slug Extension slug.
     * @return void
     */
    public static function purge_extension($slug) {
        $slug = sanitize_key((string) $slug);
        if (!$slug) {
            return;
        }

        $index = get_option(self::CACHE_INDEX_OPTION, []);
        $index = is_array($index) ? $index : [];
        foreach ((array) ($index[$slug] ?? []) as $cache_key) {
            $cache_key = sanitize_key((string) $cache_key);
            if ($cache_key) {
                delete_transient($cache_key);
            }
        }
        unset($index[$slug]);
        update_option(self::CACHE_INDEX_OPTION, $index, false);
    }

    /**
     * Delete every remembered remote documentation transient.
     *
     * @return void
     */
    public static function purge_all() {
        $index = get_option(self::CACHE_INDEX_OPTION, []);
        $index = is_array($index) ? $index : [];
        foreach ($index as $cache_keys) {
            foreach ((array) $cache_keys as $cache_key) {
                $cache_key = sanitize_key((string) $cache_key);
                if ($cache_key) {
                    delete_transient($cache_key);
                }
            }
        }
        delete_option(self::CACHE_INDEX_OPTION);
    }

    /**
     * Clear this site's remote documentation cache when the cooldown allows it.
     *
     * @return int|\WP_Error Refresh timestamp or a cooldown error.
     */
    public static function manual_refresh() {
        $now = time();
        $retry_after = self::manual_refresh_retry_after($now);
        if ($retry_after > 0) {
            return new \WP_Error(
                'million_dollar_script_docs_refresh_cooldown',
                __('Documentation was refreshed recently.', 'million-dollar-script'),
                ['retry_after' => $retry_after]
            );
        }

        delete_option(self::MANUAL_REFRESH_OPTION);
        if (!add_option(self::MANUAL_REFRESH_OPTION, $now, '', false)) {
            return new \WP_Error(
                'million_dollar_script_docs_refresh_cooldown',
                __('Another documentation refresh is already in progress.', 'million-dollar-script'),
                ['retry_after' => self::MANUAL_REFRESH_COOLDOWN]
            );
        }

        self::purge_all();

        return $now;
    }

    /**
     * Return the last successful manual cache refresh timestamp.
     *
     * @return int
     */
    public static function last_manual_refresh_at() {
        return absint(get_option(self::MANUAL_REFRESH_OPTION, 0));
    }

    /**
     * Return the remaining manual refresh cooldown in seconds.
     *
     * @param int|null $now Current timestamp for deterministic checks.
     * @return int
     */
    public static function manual_refresh_retry_after($now = null) {
        $now = null === $now ? time() : absint($now);
        $last_refresh = self::last_manual_refresh_at();
        if (!$last_refresh) {
            return 0;
        }

        return max(0, self::MANUAL_REFRESH_COOLDOWN - max(0, $now - $last_refresh));
    }

    /**
     * @param array $item Installed extension catalog item.
     * @param ExtensionLicenseManager $license_manager License manager.
     * @return array|null
     */
    private function fetch_manifest_package(array $item, ExtensionLicenseManager $license_manager) {
        $slug = sanitize_key((string) ($item['slug'] ?? ''));
        $access_candidates = $license_manager->access_candidates($slug);
        if (!$slug || !$access_candidates) {
            return null;
        }

        $version = sanitize_text_field((string) ($item['version'] ?? 'current')) ?: 'current';
        $channel = sanitize_key((string) ($item['channel'] ?? 'main')) ?: 'main';
        foreach ($access_candidates as $candidate) {
            $license_key = (string) ($candidate['license_key'] ?? '');
            $candidate_version = $version;
            $payload = $this->fetch_manifest_payload($slug, $license_key, $candidate_version, $channel);
            if ('version_not_found' === sanitize_key((string) ($payload['state'] ?? '')) && 'current' !== $candidate_version) {
                $candidate_version = 'current';
                $payload = $this->fetch_manifest_payload($slug, $license_key, $candidate_version, $channel);
            }
            if ($this->is_entitlement_failure($payload)) {
                continue;
            }
            if (empty($payload['success']) || empty($payload['manifest']) || !is_array($payload['manifest'])) {
                return $this->unavailable_package($slug, $item, $candidate_version, $channel);
            }
            return $this->manifest_to_package($payload['manifest'], $item, $candidate_version, $channel);
        }

        self::purge_extension($slug);
        return null;
    }

    private function fetch_public_core_package($local_package) {
        $local_package = is_array($local_package) ? $local_package : [];
        $version = defined('MILLION_DOLLAR_SCRIPT_VERSION') ? MILLION_DOLLAR_SCRIPT_VERSION : (string) ($local_package['version'] ?? 'current');
        $version = sanitize_text_field((string) $version) ?: 'current';
        $channel = sanitize_key((string) ($local_package['channel'] ?? 'main')) ?: 'main';
        $payload = $this->fetch_manifest_payload('million-dollar-script', '', $version, $channel);
        if (empty($payload['success']) || empty($payload['manifest']) || !is_array($payload['manifest'])) {
            return null;
        }

        return $this->manifest_to_package($payload['manifest'], [
            'slug' => 'million-dollar-script',
            'name' => 'Million Dollar Script',
        ], $version, $channel, false);
    }

    /**
     * @return array
     */
    private function fetch_manifest_payload($slug, $license_key, $version, $channel) {
        $cache_key = $this->cache_key('manifest', $slug, $version, $channel, $license_key);
        $cached = $this->cache_get($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $payload = $this->request_package_endpoint($slug, 'manifest', [
            'licenseKey' => $license_key,
            'instanceId' => ExtensionServer::installation_id(),
            'version' => $version ?: 'current',
            'channel' => $channel ?: 'main',
        ]);

        if (!empty($payload['success'])) {
            $this->cache_set($slug, $cache_key, $payload);
        }

        return $payload;
    }

    /**
     * @param array $manifest Remote package manifest.
     * @param array $item Installed extension catalog item.
     * @return array|null
     */
    private function manifest_to_package(array $manifest, array $item, $fallback_version, $fallback_channel, $license_required = true) {
        $slug = sanitize_key((string) ($manifest['package'] ?? $item['slug'] ?? ''));
        $title = sanitize_text_field((string) ($manifest['title'] ?? ''));
        if ('' === $title) {
            $title = sanitize_text_field((string) ($item['name'] ?? $slug));
        }
        if (!$slug || empty($manifest['docs']) || !is_array($manifest['docs'])) {
            return null;
        }

        $version = sanitize_text_field((string) ($manifest['version'] ?? $fallback_version ?: 'current'));
        $channel = sanitize_key((string) ($manifest['channel'] ?? $fallback_channel ?: 'main')) ?: 'main';
        $package = [
            'package' => $slug,
            'type' => sanitize_key((string) ($manifest['type'] ?? 'extension')) ?: 'extension',
            'version' => $version,
            'channel' => $channel,
            'title' => $title,
            'remote' => true,
            'license_required' => (bool) $license_required,
            'docs' => [],
        ];

        foreach ($manifest['docs'] as $doc) {
            if (!is_array($doc)) {
                continue;
            }

            $id = sanitize_key((string) ($doc['slug'] ?? $doc['id'] ?? ''));
            $doc_title = sanitize_text_field((string) ($doc['title'] ?? ''));
            if (!$id || !$doc_title) {
                continue;
            }

            $package['docs'][] = [
                'id' => $id,
                'key' => $slug . ':' . $id,
                'title' => $doc_title,
                'nav_title' => $this->navigation_title($doc_title, $title),
                'summary' => sanitize_text_field((string) ($doc['summary'] ?? '')),
                'audience' => ['admin'],
                'topics' => $this->sanitize_topics($doc['tags'] ?? $doc['topics'] ?? ['extensions']),
                'package' => $slug,
                'package_title' => $title,
                'package_type' => sanitize_key((string) ($manifest['type'] ?? 'extension')) ?: 'extension',
                'version' => $version,
                'channel' => $channel,
                'remote' => true,
                'license_required' => (bool) $license_required,
                'remote_doc_slug' => $id,
                'content_hash' => strtolower(sanitize_text_field((string) ($doc['content_hash'] ?? ''))),
                'body_hash' => strtolower(sanitize_text_field((string) ($doc['body_hash'] ?? ''))),
                'position' => intval($doc['position'] ?? 0),
                'access_level' => sanitize_key((string) ($doc['access_level'] ?? ($license_required ? 'product' : 'public'))),
            ];
        }

        return $package['docs'] ? $package : null;
    }

    /**
     * @return array
     */
    private function unavailable_package($slug, array $item, $version, $channel) {
        $title = sanitize_text_field((string) ($item['name'] ?? $slug));

        return [
            'package' => $slug,
            'type' => 'extension',
            'version' => sanitize_text_field((string) ($version ?: 'current')),
            'channel' => sanitize_key((string) ($channel ?: 'main')),
            'title' => $title,
            'remote' => true,
            'license_required' => true,
            'docs' => [
                [
                    'id' => self::UNAVAILABLE_DOC_ID,
                    'key' => $slug . ':' . self::UNAVAILABLE_DOC_ID,
                    'title' => __('Documentation unavailable', 'million-dollar-script'),
                    'nav_title' => __('Unavailable', 'million-dollar-script'),
                    'audience' => ['admin'],
                    'topics' => ['troubleshooting'],
                    'package' => $slug,
                    'package_title' => $title,
                    'package_type' => 'extension',
                    'version' => sanitize_text_field((string) ($version ?: 'current')),
                    'channel' => sanitize_key((string) ($channel ?: 'main')),
                    'remote' => true,
                    'license_required' => true,
                    'content_markdown' => $this->temporary_unavailable_markdown(),
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    private function request_package_endpoint($slug, $suffix, array $payload) {
        $base_url = ExtensionServer::base_url();
        if (!$base_url) {
            return [
                'success' => false,
                'state' => 'temporarily_unavailable',
                'message' => __('Extension server URL is not configured.', 'million-dollar-script'),
            ];
        }

        if (ExtensionCatalog::server_down_fresh()) {
            return [
                'success' => false,
                'state' => 'temporarily_unavailable',
                'message' => __('Extension server temporarily unreachable.', 'million-dollar-script'),
            ];
        }

        $payload = array_merge(ExtensionServer::compatibility_args(), $payload);

        $url = $base_url . '/api/public/docs/packages/' . rawurlencode($slug) . '/' . ltrim((string) $suffix, '/');
        $response = wp_remote_post($url, [
            'timeout' => 2,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            ExtensionCatalog::record_reachability(false);
            return [
                'success' => false,
                'state' => 'temporarily_unavailable',
                'message' => $response->get_error_message(),
            ];
        }

        ExtensionCatalog::record_reachability(true, $base_url);

        $code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        $decoded = is_array($decoded) ? $decoded : [];
        $decoded['success'] = !empty($decoded['success']) && $code >= 200 && $code < 300;
        if (!empty($decoded['success']) && !empty($decoded['data']) && is_array($decoded['data'])) {
            if ('manifest' === trim((string) $suffix)) {
                $decoded['manifest'] = $this->normalize_manifest_response($decoded['data']);
            } elseif (0 === strpos(trim((string) $suffix), 'documents/')) {
                $decoded['document'] = $decoded['data'];
            }
        }
        if (empty($decoded['state']) && ($code < 200 || $code >= 300)) {
            $decoded['state'] = 401 === $code ? 'license_required' : (403 === $code ? 'not_entitled' : 'temporarily_unavailable');
        }

        return $decoded;
    }

    private function normalize_manifest_response(array $data) {
        return [
            'package' => sanitize_key((string) ($data['package_slug'] ?? $data['package'] ?? '')),
            'type' => sanitize_key((string) ($data['package_type'] ?? $data['type'] ?? 'extension')) ?: 'extension',
            'version' => sanitize_text_field((string) ($data['package_version'] ?? $data['version'] ?? 'current')),
            'channel' => sanitize_key((string) ($data['channel'] ?? 'main')) ?: 'main',
            'title' => sanitize_text_field((string) ($data['title'] ?? '')),
            'docs' => is_array($data['documents'] ?? null) ? $data['documents'] : (is_array($data['docs'] ?? null) ? $data['docs'] : []),
        ];
    }

    private function cache_key($kind, $slug, $version, $channel, $license_key, $doc_slug = '') {
        $hash = md5(implode('|', [
            (string) self::CACHE_SCHEMA_VERSION,
            sanitize_key((string) $kind),
            sanitize_key((string) $slug),
            sanitize_text_field((string) $version),
            sanitize_key((string) $channel),
            md5((string) $license_key),
            sanitize_key((string) $doc_slug),
        ]));

        return 'mds3_remote_docs_' . $hash;
    }

    /**
     * @return mixed
     */
    private function cache_get($cache_key) {
        $cache_key = sanitize_key((string) $cache_key);
        if (!$cache_key) {
            return false;
        }

        return get_transient($cache_key);
    }

    private function cache_set($slug, $cache_key, array $payload) {
        $slug = sanitize_key((string) $slug);
        $cache_key = sanitize_key((string) $cache_key);
        if (!$slug || !$cache_key) {
            return;
        }

        $ttl = (int) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/remote/docs/cache/ttl', self::DEFAULT_CACHE_TTL, $slug, $payload);
        set_transient($cache_key, $payload, max(60, $ttl));

        $index = get_option(self::CACHE_INDEX_OPTION, []);
        $index = is_array($index) ? $index : [];
        $index[$slug] = array_values(array_unique(array_merge((array) ($index[$slug] ?? []), [$cache_key])));
        update_option(self::CACHE_INDEX_OPTION, $index, false);
    }

    private function is_entitlement_failure(array $payload) {
        $state = sanitize_key((string) ($payload['state'] ?? ''));

        return in_array($state, ['license_required', 'license_expired', 'not_entitled'], true);
    }

    private function sanitize_remote_markdown($markdown) {
        if (!is_scalar($markdown)) {
            return '';
        }

        $markdown = wp_check_invalid_utf8((string) $markdown, true);
        if (strlen($markdown) > self::MAX_MARKDOWN_BYTES) {
            $markdown = substr($markdown, 0, self::MAX_MARKDOWN_BYTES);
        }

        return trim((string) $markdown);
    }

    private function sanitize_topics($topics) {
        $topics = is_array($topics) ? $topics : [];
        $clean = [];
        foreach ($topics as $topic) {
            $topic = sanitize_key((string) $topic);
            if ($topic) {
                $clean[] = $topic;
            }
        }

        return array_values(array_unique($clean ?: ['extensions']));
    }

    private function navigation_title($title, $package_title) {
        $title = trim(sanitize_text_field((string) $title));
        $package_title = trim(sanitize_text_field((string) $package_title));
        foreach (array_filter([$package_title, 'Million Dollar Script']) as $prefix) {
            if (0 !== stripos($title, $prefix)) {
                continue;
            }

            $candidate = trim((string) substr($title, strlen($prefix)));
            $candidate = ltrim($candidate, " \t\n\r\0\x0B:-");
            if ('' !== $candidate) {
                return $candidate;
            }
        }

        return $title;
    }

    private function temporary_unavailable_markdown() {
        return __('Documentation could not be retrieved from the extension server. Confirm the extension server URL is reachable and try again.', 'million-dollar-script');
    }

    private function license_unavailable_markdown() {
        return __('Documentation is available only while this extension has an active license with support access.', 'million-dollar-script');
    }
}
