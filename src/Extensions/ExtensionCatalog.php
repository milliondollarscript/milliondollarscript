<?php
/**
 * First-party extension catalog.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

use MillionDollarScript\V3\Rest\Api;
use MillionDollarScript\V3\Support\Distribution;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionCatalog {

    private const HARD_HIDDEN_SLUGS = [
        'mds-creator-platforms',
    ];

    private const DEVELOPER_SLUGS = [
        'mds-license-test',
        'mds-premium-test',
        'mds-hello-world',
        'mds-sample-greeter',
        'mds-skeleton',
        'mds-test-extension',
    ];

    /**
     * @var string
     */
    private $last_available_base_url = '';

    /**
     * @var array|null
     */
    private $last_server_notice = null;

    /**
     * @var array|null
     */
    private $last_bundle_notice = null;

    const SERVER_PROBE_TRANSIENT = 'mds3_extsrv_probe';
    const SERVER_PROBE_UP_TTL = 300;
    const SERVER_PROBE_DOWN_TTL = 60;

    public function catalog($force_update_checks = false) {
        $installed = array_merge($this->bundled(), $this->installed());
        $available = $this->available();
        $bundles = $this->bundles();
        $base_url = $this->last_available_base_url ?: ExtensionServer::base_url();
        $available_by_slug = [];
        foreach ($available as $item) {
            $available_by_slug[(string) ($item['slug'] ?? '')] = $item;
        }

        foreach ($installed as $index => $item) {
            $slug = (string) ($item['slug'] ?? '');
            if (!$slug) {
                continue;
            }

            $remote = $available_by_slug[$slug] ?? [];
            $installed[$index]['id'] = (string) ($remote['id'] ?? $installed[$index]['id'] ?? '');
            $installed[$index]['catalog_version'] = (string) ($remote['version'] ?? '');
            $installed[$index]['download_url'] = (string) ($remote['download_url'] ?? '');
            $installed[$index]['purchase_url'] = (string) ($remote['purchase_url'] ?? '');
            $installed[$index]['license_url'] = (string) ($remote['license_url'] ?? '');
            $installed[$index]['license_required'] = !empty($remote['license_required']) || !empty($installed[$index]['license_required']);
            $is_bundled = !empty($item['bundled']) || 'core' === ($item['source'] ?? '');
            $update = !$is_bundled && $base_url && $force_update_checks
                ? $this->check_update($base_url, $slug, (string) ($item['version'] ?? ''), $item, (bool) $force_update_checks)
                : null;
            if ($update && !empty($update['update_available'])) {
                $installed[$index]['update_available'] = true;
                $installed[$index]['update_version'] = (string) ($update['new_version'] ?? $update['latestVersion'] ?? '');
                $installed[$index]['download_url'] = (string) ($update['package_url'] ?? $update['downloadUrl'] ?? $installed[$index]['download_url']);
            } elseif (!empty($remote['version']) && version_compare((string) ($remote['version'] ?? ''), (string) ($item['version'] ?? ''), '>')) {
                $installed[$index]['update_available'] = true;
                $installed[$index]['update_version'] = (string) $remote['version'];
            }
        }
        $installed_by_slug = [];
        foreach ($installed as $item) {
            $installed_by_slug[(string) ($item['slug'] ?? '')] = $item;
        }
        foreach ($available as $index => $item) {
            $slug = (string) ($item['slug'] ?? '');
            if (!$slug || empty($installed_by_slug[$slug])) {
                continue;
            }

            $available[$index]['installed'] = true;
            $available[$index]['active'] = !empty($installed_by_slug[$slug]['active']);
            $available[$index]['plugin_file'] = (string) ($installed_by_slug[$slug]['plugin_file'] ?? '');
        }
        $all_remote_by_slug = [];
        foreach ($available as $item) {
            $all_remote_by_slug[(string) ($item['slug'] ?? '')] = $item;
        }
        $bundles = $this->hydrate_bundle_members($bundles, $all_remote_by_slug, $installed_by_slug);
        $bundle_names_by_extension = [];
        foreach ($bundles as $bundle) {
            foreach ((array) ($bundle['included_extensions'] ?? []) as $member) {
                $member_slug = sanitize_key((string) ($member['slug'] ?? ''));
                if ($member_slug) {
                    $bundle_names_by_extension[$member_slug][] = sanitize_text_field((string) ($bundle['name'] ?? $bundle['slug'] ?? ''));
                }
            }
        }
        $license_manager = new ExtensionLicenseManager();
        foreach ($installed as $index => $item) {
            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            $installed[$index]['included_in_packs'] = array_values(array_unique($bundle_names_by_extension[$slug] ?? []));
            $installed[$index]['access_source'] = $license_manager->access_source($slug);
            $installed[$index]['access_product'] = $license_manager->access_product($slug);
        }
        foreach ($available as $index => $item) {
            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            $available[$index]['included_in_packs'] = array_values(array_unique($bundle_names_by_extension[$slug] ?? []));
            $available[$index]['access_source'] = $license_manager->access_source($slug);
            $available[$index]['access_product'] = $license_manager->access_product($slug);
        }
        $available = array_values(array_filter($available, function ($item) use ($installed_by_slug) {
            $slug = (string) ($item['slug'] ?? '');

            return '' !== $slug && empty($installed_by_slug[$slug]);
        }));

        return [
            'installed' => $this->sort_items($installed),
            'available' => $this->sort_items($available),
            'bundles' => $bundles,
        ];
    }

    public function server_notice() {
        return $this->last_server_notice;
    }

    public function bundle_notice() {
        return $this->last_bundle_notice;
    }

    public function bundled() {
        $items = [
            [
                'slug' => 'mds-grid',
                'plugin_file' => '',
                'name' => __('Classic Pixel Grid', 'million-dollar-script'),
                'description' => __('Default Million Dollar Script grid, block sales, pages, shortcodes, blocks, orders, migration support, and local rendering.', 'million-dollar-script'),
                'version' => defined('MILLION_DOLLAR_SCRIPT_VERSION') ? MILLION_DOLLAR_SCRIPT_VERSION : '',
                'source' => 'core',
                'setup_source' => 'core',
                'installed' => true,
                'active' => (new ExtensionRuntime())->is_enabled('mds-grid'),
                'bundled' => true,
                'locked' => false,
                'setup_default' => true,
                'setup_category' => 'classic',
                'provides' => ['inventory.grid'],
                'requires' => ['platform.core'],
                'recommends' => ['mds-woocommerce'],
                'conflicts' => [],
                'requires_service' => false,
                'minimum_security_level' => ExtensionDependencyResolver::DEFAULT_SECURITY_LEVEL,
                'llm_safe_actions' => ['read_grid', 'read_stats'],
                'api_manifest' => function_exists('rest_url') ? rest_url(Api::REST_NAMESPACE) : '',
            ],
        ];

        return $this->visible_items(\MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/catalog/bundled', $items));
    }

    public function installed() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $items = [];
        $updates = get_site_transient('update_plugins');
        $updates = is_object($updates) && is_array($updates->response ?? null) ? $updates->response : [];

        foreach (get_plugins() as $file => $plugin) {
            $slug = sanitize_key('.' === dirname($file) ? basename($file, '.php') : dirname($file));
            if ($file === MILLION_DOLLAR_SCRIPT_BASENAME || $this->is_core_product($file, $plugin) || $this->is_hidden_slug($slug) || !$this->looks_like_mds_extension($file, $plugin)) {
                continue;
            }
            if (!$this->installed_plugin_supports_mds3($file, $plugin)) {
                continue;
            }

            $update = $updates[$file] ?? null;
            $items[] = [
                'slug' => $slug,
                'plugin_file' => $file,
                'name' => $this->customer_text($plugin['Name'] ?? $file),
                'description' => $this->customer_text($plugin['Description'] ?? ''),
                'version' => (string) ($plugin['Version'] ?? ''),
                'info_url' => esc_url_raw((string) ($plugin['PluginURI'] ?? $plugin['Plugin URI'] ?? '')),
                'installed' => true,
                'active' => is_plugin_active($file),
                'source' => 'installed',
                'supports_mds3' => true,
                'update_available' => is_object($update),
                'update_version' => is_object($update) ? (string) ($update->new_version ?? '') : '',
                'download_url' => is_object($update) ? esc_url_raw($update->package ?? '') : '',
            ];
        }

        $items = (new ExtensionDependencyResolver())->installed_with_metadata($items);
        $items = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/catalog/installed', $items);
        if (!is_array($items)) {
            $items = [];
        }
        $items = array_values(array_filter($items, function ($item) {
            return is_array($item) && $this->catalog_item_supports_mds3($item);
        }));

        return $this->visible_items($items);
    }

    public function available() {
        $this->last_available_base_url = '';
        $this->last_server_notice = null;
        if (!Distribution::allows_remote_catalog()) {
            return [];
        }

        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $result = $this->fetch_available_rows($settings);
        $base_url = (string) ($result['base_url'] ?? '');
        $rows = $result['rows'] ?? [];
        if (!$base_url || !is_array($rows)) {
            return [];
        }

        $public_url = ExtensionServer::public_url($settings, $base_url);

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = sanitize_text_field($row['id'] ?? '');
            $slug = sanitize_key($row['slug'] ?? $row['name'] ?? $id);
            if (!$slug || $this->is_core_product_slug($slug) || $this->is_hidden_slug($slug)) {
                continue;
            }
            if (!$this->remote_row_supports_mds3($row)) {
                continue;
            }

            $license_required = !empty($row['license_required']) || !empty($row['is_premium']);
            $download_url = esc_url_raw($row['download_url'] ?? $row['package_url'] ?? $row['package'] ?? $row['download_link'] ?? '');
            if (!$download_url && $id && !$license_required) {
                $download_url = esc_url_raw(add_query_arg(ExtensionServer::compatibility_args(), $base_url . '/api/extensions/' . rawurlencode($id) . '/download'));
            }
            $license_url = $row['license_url'] ?? $row['manage_license_url'] ?? '';
            if (!$license_url && $license_required && $public_url) {
                $license_url = rtrim($public_url, '/') . '/portal/licenses';
            }
            $info_url = $this->extension_info_url($row);
            $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
            $requires = $this->metadata_list($metadata, 'requires_capabilities');
            if (!$requires) {
                $requires = $this->metadata_list($metadata, 'mds_requires');
            }
            if (!$requires && is_array($metadata['requires'] ?? null)) {
                $requires = $metadata['requires'];
            }

            $items[] = [
                'id' => $id,
                'slug' => $slug,
                'name' => sanitize_text_field($this->customer_text($row['display_name'] ?? $row['name'] ?? $slug)),
                'description' => sanitize_text_field($this->customer_text($row['description'] ?? '')),
                'version' => sanitize_text_field($row['version'] ?? ''),
                'source' => 'mds',
                'license_required' => $license_required,
                'purchase_url' => esc_url_raw($this->absolute_url($row['purchase_url'] ?? $row['buy_url'] ?? $this->purchase_url($row) ?? '', $public_url ?: $base_url)),
                'license_url' => esc_url_raw($this->absolute_url($license_url, $public_url ?: $base_url)),
                'info_url' => esc_url_raw($this->absolute_url($info_url, $public_url ?: $base_url)),
                'download_url' => $download_url,
                'provides' => $this->metadata_list($metadata, 'provides'),
                'requires' => $requires,
                'recommends' => $this->metadata_list($metadata, 'recommends'),
                'conflicts' => $this->metadata_list($metadata, 'conflicts'),
                'llm_safe_actions' => $this->metadata_list($metadata, 'llm_safe_actions'),
                'setup_default' => $this->metadata_bool($metadata, 'setup_default'),
                'requires_service' => $this->metadata_bool($metadata, 'requires_service'),
                'setup_category' => sanitize_key((string) ($metadata['setup_category'] ?? 'extensions')),
                'minimum_security_level' => sanitize_key((string) ($metadata['minimum_security_level'] ?? ExtensionDependencyResolver::DEFAULT_SECURITY_LEVEL)),
                'api_manifest' => esc_url_raw((string) ($metadata['api_manifest'] ?? '')),
            ];
        }

        $items = (new ExtensionDependencyResolver())->available_with_metadata($items);

        return $this->visible_items(\MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/catalog/available', $items, $base_url));
    }

    public function bundles() {
        $this->last_bundle_notice = null;
        if (!Distribution::allows_remote_catalog()) {
            return [];
        }

        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $configured = ExtensionServer::base_url($settings);
        if (!$configured) {
            return [];
        }

        if ($this->server_probe_down_fresh()) {
            return [];
        }

        $errors = [];
        $candidates = array_values(array_unique(array_filter(array_merge(
            $this->last_available_base_url ? [$this->last_available_base_url] : [],
            $this->extension_server_candidates($configured)
        ))));
        foreach ($candidates as $base_url) {
            $url = rtrim($base_url, '/') . '/api/public/products?' . http_build_query(array_merge(ExtensionServer::compatibility_args(), ['type' => 'bundle']), '', '&', PHP_QUERY_RFC3986);
            $response = wp_remote_get($url, ['timeout' => 2]);
            if (is_wp_error($response)) {
                $errors[] = $this->server_error_line($base_url, $response->get_error_message());
                continue;
            }
            $code = (int) wp_remote_retrieve_response_code($response);
            if (200 !== $code) {
                $errors[] = $this->server_error_line($base_url, (string) $code);
                continue;
            }
            $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
            $rows = is_array($decoded) ? ($decoded['data'] ?? []) : [];
            if (!is_array($rows)) {
                $errors[] = $this->server_error_line($base_url, __('Invalid extension pack response.', 'million-dollar-script'));
                continue;
            }

            $public_url = ExtensionServer::public_url($settings, $base_url);
            $bundles = [];
            foreach ($rows as $row) {
                if (!is_array($row) || 'bundle' !== sanitize_key((string) ($row['product_type'] ?? ''))) {
                    continue;
                }
                $slug = sanitize_key((string) ($row['slug'] ?? $row['name'] ?? ''));
                if (!$slug || !$this->remote_row_supports_mds3($row)) {
                    continue;
                }
                $members = [];
                foreach ((array) ($row['included_extensions'] ?? []) as $member) {
                    if (!is_array($member)) {
                        continue;
                    }
                    $member_slug = sanitize_key((string) ($member['slug'] ?? $member['name'] ?? ''));
                    if (!$member_slug) {
                        continue;
                    }
                    $members[] = [
                        'id' => sanitize_text_field((string) ($member['id'] ?? '')),
                        'slug' => $member_slug,
                        'name' => sanitize_text_field((string) ($member['name'] ?? $member_slug)),
                        'version' => sanitize_text_field((string) ($member['version'] ?? '')),
                        'available' => !empty($member['available']),
                    ];
                }
                $license_url = $public_url ? rtrim($public_url, '/') . '/portal/licenses' : '';
                $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
                $bundles[] = [
                    'id' => sanitize_text_field((string) ($row['id'] ?? '')),
                    'slug' => $slug,
                    'name' => sanitize_text_field((string) ($row['display_name'] ?? $row['name'] ?? $slug)),
                    'description' => sanitize_text_field((string) ($row['description'] ?? '')),
                    'product_type' => 'bundle',
                    'license_required' => true,
                    'purchase_url' => esc_url_raw($this->absolute_url($row['purchase_url'] ?? $this->purchase_url($row) ?? '', $public_url ?: $base_url)),
                    'license_url' => esc_url_raw($license_url),
                    'pricing' => is_array($metadata['pricing'] ?? null) ? $metadata['pricing'] : [],
                    'included_extensions' => $members,
                    'is_active' => (new ExtensionLicenseManager())->is_product_active($slug),
                ];
            }
            (new ExtensionLicenseManager())->sync_bundle_catalog($bundles);

            return $bundles;
        }

        $this->last_bundle_notice = [
            'type' => 'warning',
            'message' => __('Extension packs could not be refreshed. Individual extension management remains available.', 'million-dollar-script'),
            'errors' => array_slice($errors, 0, 3),
        ];

        return [];
    }

    private function hydrate_bundle_members(array $bundles, array $remote_by_slug, array $installed_by_slug) {
        foreach ($bundles as $bundle_index => $bundle) {
            $members = [];
            foreach ((array) ($bundle['included_extensions'] ?? []) as $summary) {
                $slug = sanitize_key((string) ($summary['slug'] ?? ''));
                if (!$slug) {
                    continue;
                }
                $member = array_merge([
                    'slug' => $slug,
                    'name' => sanitize_text_field((string) ($summary['name'] ?? $slug)),
                    'id' => sanitize_text_field((string) ($summary['id'] ?? '')),
                    'version' => sanitize_text_field((string) ($summary['version'] ?? '')),
                    'license_required' => true,
                    'source' => 'mds',
                ], $remote_by_slug[$slug] ?? []);
                if (!empty($installed_by_slug[$slug])) {
                    $member = array_merge($member, $installed_by_slug[$slug]);
                    $member['source'] = 'installed';
                    $member['installed'] = true;
                }
                $member['included_in_pack'] = sanitize_text_field((string) ($bundle['name'] ?? $bundle['slug'] ?? ''));
                $members[] = $member;
            }
            $bundles[$bundle_index]['included_extensions'] = $members;
        }

        return $bundles;
    }

    private function fetch_available_rows(array $settings) {
        $configured = ExtensionServer::base_url($settings);
        if (!$configured) {
            $this->last_server_notice = [
                'type' => 'warning',
                'message' => __('Extension server URL is not configured. Installed extensions can still be managed.', 'million-dollar-script'),
            ];

            return ['base_url' => '', 'rows' => []];
        }

        if ($this->server_probe_down_fresh()) {
            $this->last_server_notice = [
                'type' => 'error',
                'message' => __('The extension catalog could not be reached. Installed extensions can still be managed.', 'million-dollar-script'),
                'configured_url' => rtrim($configured, '/'),
                'errors' => [__('Extension server recently unreachable; retrying shortly.', 'million-dollar-script')],
            ];

            return ['base_url' => '', 'rows' => []];
        }

        $errors = [];
        $candidates = $this->extension_server_candidates($configured);
        foreach ($candidates as $base_url) {
            $response = wp_remote_get($this->public_extensions_url($base_url), ['timeout' => 2]);
            if (is_wp_error($response)) {
                $errors[] = $this->server_error_line($base_url, $response->get_error_message());
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            if (200 !== $code) {
                $message = function_exists('wp_remote_retrieve_response_message') ? wp_remote_retrieve_response_message($response) : '';
                $errors[] = $this->server_error_line($base_url, trim($code . ' ' . $message));
                continue;
            }

            $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
            $rows = is_array($decoded) ? ($decoded['data'] ?? $decoded) : [];
            if (!is_array($rows)) {
                $errors[] = $this->server_error_line($base_url, __('Invalid catalog response.', 'million-dollar-script'));
                continue;
            }

            $this->last_available_base_url = rtrim($base_url, '/');
            $this->remember_server_probe($this->last_available_base_url);
            if ($this->last_available_base_url !== rtrim($configured, '/')) {
                $this->last_server_notice = [
                    'type' => 'warning',
                    'message' => __('Connected to the extension server using a fallback URL.', 'million-dollar-script'),
                    'configured_url' => rtrim($configured, '/'),
                    'resolved_url' => $this->last_available_base_url,
                    'errors' => array_slice($errors, 0, 3),
                ];
            }

            return [
                'base_url' => $this->last_available_base_url,
                'rows' => $rows,
            ];
        }

        $this->remember_server_probe('');
        $this->last_server_notice = [
            'type' => 'error',
            'message' => __('The extension catalog could not be reached. Installed extensions can still be managed.', 'million-dollar-script'),
            'configured_url' => rtrim($configured, '/'),
            'errors' => array_slice($errors, 0, 4),
        ];

        return ['base_url' => '', 'rows' => []];
    }

    private static function server_probe_down_fresh() {
        $state = get_transient(self::SERVER_PROBE_TRANSIENT);
        if (!is_array($state) || empty($state['ts']) || !empty($state['ok'])) {
            return false;
        }

        return (time() - (int) $state['ts']) < self::SERVER_PROBE_DOWN_TTL;
    }

    private static function remember_server_probe($url) {
        $url = rtrim((string) $url, '/');
        set_transient(
            self::SERVER_PROBE_TRANSIENT,
            ['ok' => '' !== $url, 'url' => $url, 'ts' => time()],
            '' !== $url ? self::SERVER_PROBE_UP_TTL : self::SERVER_PROBE_DOWN_TTL
        );
    }

    /**
     * Whether the extension server was recently confirmed unreachable.
     *
     * Shared so render-path consumers (catalog, docs) fail fast on a down
     * server instead of each waiting out full request timeouts.
     *
     * @return bool
     */
    public static function server_down_fresh() {
        return self::server_probe_down_fresh();
    }

    /**
     * Record an extension-server reachability probe for other consumers.
     *
     * @param bool   $reachable Whether the server answered.
     * @param string $url       Base URL that answered (used when reachable).
     * @return void
     */
    public static function record_reachability($reachable, $url = '') {
        self::remember_server_probe($reachable ? $url : '');
    }

    private function extension_server_candidates($configured) {
        $candidates = [rtrim((string) $configured, '/')];
        if (ExtensionServer::is_local()) {
            $candidates[] = ExtensionServer::LOCAL_URL;
            $candidates[] = 'http://extension-server-go:3030';
            $candidates[] = 'http://extension-server:3030';
            $candidates[] = 'http://host.docker.internal:3030';
        }

        $candidates = array_filter(array_map([$this, 'normalize_candidate_url'], $candidates));

        return array_values(array_unique($candidates));
    }

    private function public_extensions_url($base_url) {
        $url = rtrim((string) $base_url, '/') . '/api/public/extensions';
		$args = ExtensionServer::compatibility_args();

        return $url . '?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986);
    }

    private function normalize_candidate_url($url) {
        $url = rtrim((string) $url, '/');
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host']) || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return '';
        }

        return $url;
    }

    private function server_error_line($base_url, $message) {
        $message = wp_strip_all_tags((string) $message);
        $message = sanitize_text_field($message);
        if ('' === $message) {
            $message = __('No response.', 'million-dollar-script');
        }

        return rtrim((string) $base_url, '/') . ': ' . $message;
    }

    private function metadata_list(array $metadata, $key) {
        $value = $metadata[$key] ?? [];
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value);
        }

        return is_array($value) ? $value : [];
    }

    private function metadata_bool(array $metadata, $key) {
        $value = $metadata[$key] ?? false;
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on'], true);
    }

    public function available_item($slug) {
        $slug = sanitize_key($slug);
        foreach ($this->available() as $item) {
            if ($slug === (string) ($item['slug'] ?? '')) {
                return $item;
            }
        }

        return null;
    }

    public function bundle_item($slug) {
        $slug = sanitize_key($slug);
        foreach ($this->bundles() as $item) {
            if ($slug === (string) ($item['slug'] ?? '')) {
                return $item;
            }
        }

        return null;
    }

    public function installed_item_by_file($plugin_file) {
        $plugin_file = plugin_basename((string) $plugin_file);
        foreach ($this->installed() as $item) {
            if ($plugin_file === (string) ($item['plugin_file'] ?? '')) {
                return $item;
            }
        }

        return null;
    }

    public function installed_item_by_slug($slug) {
        $slug = sanitize_key($slug);
        if ($this->is_hidden_slug($slug)) {
            return null;
        }

        foreach (array_merge($this->bundled(), $this->installed()) as $item) {
            if ($slug === (string) ($item['slug'] ?? '')) {
                return $item;
            }
        }

        return null;
    }

    public function hidden_slugs() {
        $hard_hidden = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/catalog/hard/hidden/slugs', self::HARD_HIDDEN_SLUGS);
        $hard_hidden = is_array($hard_hidden) ? $hard_hidden : self::HARD_HIDDEN_SLUGS;
        $hard_hidden = array_map('sanitize_key', $hard_hidden);

        $slugs = $hard_hidden;
        if (!$this->show_developer_extensions()) {
            $slugs = array_merge($slugs, self::DEVELOPER_SLUGS);
        }

        $slugs = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/catalog/hidden/slugs', $slugs);
        if (!is_array($slugs)) {
            $slugs = array_merge($hard_hidden, self::DEVELOPER_SLUGS);
        }

        $slugs = array_merge($slugs, $hard_hidden);
        $slugs = array_map('sanitize_key', $slugs);

        return array_values(array_unique(array_filter($slugs)));
    }

    public function is_hidden_slug($slug) {
        return in_array(sanitize_key((string) $slug), $this->hidden_slugs(), true);
    }

    private function visible_items($items) {
        if (!is_array($items)) {
            return [];
        }

        $visible = [];
        foreach ($items as $item) {
            if (!is_array($item) || $this->is_hidden_slug((string) ($item['slug'] ?? ''))) {
                continue;
            }

            $visible[] = $item;
        }

        return $visible;
    }

    private function sort_items(array $items) {
        usort($items, static function ($a, $b) {
            $a = is_array($a) ? $a : [];
            $b = is_array($b) ? $b : [];
            $weight = static function (array $item) {
                if (!empty($item['bundled']) || 'core' === (string) ($item['source'] ?? '')) {
                    return 0;
                }
                if (!empty($item['active'])) {
                    return 1;
                }
                if (!empty($item['update_available'])) {
                    return 2;
                }
                if (!empty($item['installed'])) {
                    return 3;
                }

                return 4;
            };

            $weight_compare = $weight($a) <=> $weight($b);
            if (0 !== $weight_compare) {
                return $weight_compare;
            }

            return strcasecmp((string) ($a['name'] ?? $a['slug'] ?? ''), (string) ($b['name'] ?? $b['slug'] ?? ''));
        });

        return $items;
    }

    private function show_developer_extensions() {
        $enabled = defined('MDS3_SHOW_DEVELOPER_EXTENSIONS') && MDS3_SHOW_DEVELOPER_EXTENSIONS;

        return (bool) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/catalog/show/developer/extensions', $enabled);
    }

    private function looks_like_mds_extension($file, array $plugin) {
        $slug = strtolower(dirname((string) $file));
        $name = strtolower((string) ($plugin['Name'] ?? ''));
        $requires = strtolower((string) ($plugin['RequiresPlugins'] ?? $plugin['Requires Plugins'] ?? ''));

        return 0 === strpos($slug, 'mds-')
            || false !== strpos($requires, 'million-dollar-script')
            || false !== strpos($name, 'million dollar script')
            || 0 === strpos($name, 'mds ');
    }

    private function installed_plugin_supports_mds3($file, array $plugin) {
        $headers = $this->installed_compatibility_headers($file);
        $requires_plugins = strtolower((string) ($plugin['RequiresPlugins'] ?? $plugin['Requires Plugins'] ?? $headers['requires_plugins'] ?? ''));
        $signals = [
            'requires_mds' => $headers['requires_mds'] ?? '',
            'mds_generation' => $headers['mds_generation'] ?? '',
            'mds_compatible' => $headers['mds_compatible'] ?? '',
            'requires_plugins' => $requires_plugins,
        ];

        if ($this->compatibility_signals_mds2_only($signals)) {
            return false;
        }

        if ($this->compatibility_signals_mds3($signals)) {
            return true;
        }

        return false;
    }

    private function catalog_item_supports_mds3(array $item) {
        if (!empty($item['bundled']) || 'core' === (string) ($item['source'] ?? '')) {
            return true;
        }
        if ($this->show_developer_extensions() && in_array(sanitize_key((string) ($item['slug'] ?? '')), self::DEVELOPER_SLUGS, true)) {
            return true;
        }

        $signals = [
            'requires_mds' => $item['requires_mds'] ?? '',
            'mds_generation' => $item['mds_generation'] ?? '',
            'mds_compatible' => $item['mds_compatible'] ?? '',
            'requires_plugins' => $item['requires_plugins'] ?? '',
            'supports_mds2' => $item['supports_mds2'] ?? null,
            'supports_mds3' => $item['supports_mds3'] ?? null,
        ];

        if ($this->compatibility_signals_mds2_only($signals)) {
            return false;
        }

        if ($this->compatibility_signals_mds3($signals)) {
            return true;
        }

        return !empty($item['provides']) || !empty($item['requires']);
    }

    private function installed_compatibility_headers($plugin_file) {
        if (!$plugin_file || !defined('WP_PLUGIN_DIR') || !function_exists('get_file_data')) {
            return [];
        }

        $path = trailingslashit(WP_PLUGIN_DIR) . plugin_basename($plugin_file);
        if (!is_readable($path)) {
            return [];
        }

        return get_file_data($path, [
            'requires_mds' => 'Requires MDS',
            'mds_generation' => 'MDS Generation',
            'mds_compatible' => 'MDS Compatible',
            'requires_plugins' => 'Requires Plugins',
        ], 'plugin');
    }

    private function remote_row_supports_mds3(array $row) {
        $metadata = [];
        if (isset($row['metadata']) && is_array($row['metadata'])) {
            $metadata = $row['metadata'];
        }

        $signals = [
            'requires_mds' => $metadata['requires_mds'] ?? $metadata['requiresMds'] ?? $row['requires_mds'] ?? '',
            'mds_generation' => $metadata['mds_generation'] ?? $metadata['mdsGeneration'] ?? $metadata['platform_generation'] ?? $row['mds_generation'] ?? '',
            'mds_compatible' => $metadata['mds_compatible'] ?? $metadata['mdsCompatible'] ?? $metadata['compatible_mds'] ?? $metadata['compatibleMds'] ?? $row['mds_compatible'] ?? '',
            'requires_plugins' => $metadata['requires_plugins'] ?? $metadata['requiresPlugins'] ?? $row['requires_plugins'] ?? '',
            'supports_mds2' => $metadata['supports_mds2'] ?? $metadata['supportsMds2'] ?? $row['supports_mds2'] ?? null,
            'supports_mds3' => $metadata['supports_mds3'] ?? $metadata['supportsMds3'] ?? $row['supports_mds3'] ?? null,
        ];

        if ($this->compatibility_signals_mds2_only($signals)) {
            return false;
        }

        if ($this->compatibility_signals_mds3($signals)) {
            return true;
        }

        // MDS 3.0 catalog rows must opt in explicitly. Markerless records belong
        // to the legacy compatibility path and must not cross product families.
        return false;
    }

    private function compatibility_signals_mds3(array $signals) {
        if ($this->truthy_signal($signals['supports_mds3'] ?? null)) {
            return true;
        }

        $requires_mds = strtolower(trim($this->signal_text($signals['requires_mds'] ?? '')));
        if (preg_match('/(^|[^0-9])3(\.|$)/', $requires_mds)) {
            return true;
        }

        $generation = strtolower(trim($this->signal_text($signals['mds_generation'] ?? '')));
        if (in_array($generation, ['3', 'mds3', 'mds-3', 'mds_3', 'million-dollar-script-3', 'million dollar script 3'], true)) {
            return true;
        }

        $compatible = strtolower($this->signal_text($signals['mds_compatible'] ?? ''));
        if (preg_match('/(^|[^a-z0-9])(?:mds3|mds\s*3|3\.0)([^a-z0-9]|$)/', $compatible)) {
            return true;
        }

        $requires_plugins = strtolower($this->signal_text($signals['requires_plugins'] ?? ''));
        return false !== strpos($requires_plugins, 'million-dollar-script')
            && false === strpos($requires_plugins, 'milliondollarscript-two')
            && false === strpos($requires_plugins, 'million-dollar-script-two');
    }

    private function compatibility_signals_mds2_only(array $signals) {
        if (false === $this->truthy_signal($signals['supports_mds3'] ?? null) && true === $this->truthy_signal($signals['supports_mds2'] ?? null)) {
            return true;
        }

        $requires_mds = strtolower(trim($this->signal_text($signals['requires_mds'] ?? '')));
        if (preg_match('/(^|[^0-9])2(\.|$)/', $requires_mds) && !preg_match('/(^|[^0-9])3(\.|$)/', $requires_mds)) {
            return true;
        }

        $generation = strtolower(trim($this->signal_text($signals['mds_generation'] ?? '')));
        if (in_array($generation, ['2', 'mds2', 'mds-2', 'mds_2', 'million-dollar-script-2', 'million dollar script 2'], true)) {
            return true;
        }

        $compatible = strtolower($this->signal_text($signals['mds_compatible'] ?? ''));
        if (preg_match('/(^|[^a-z0-9])(?:mds2|mds\s*2|2\.0)([^a-z0-9]|$)/', $compatible)
            && !preg_match('/(^|[^a-z0-9])(?:mds3|mds\s*3|3\.0)([^a-z0-9]|$)/', $compatible)) {
            return true;
        }

        $requires_plugins = strtolower($this->signal_text($signals['requires_plugins'] ?? ''));
        return false !== strpos($requires_plugins, 'milliondollarscript-two')
            || false !== strpos($requires_plugins, 'million-dollar-script-two');
    }

    private function truthy_signal($value) {
        if (is_bool($value)) {
            return $value;
        }
        $value = $this->signal_text($value);
        if ('' === trim($value)) {
            return null;
        }

        return in_array(strtolower(trim($value)), ['1', 'yes', 'true', 'on', 'mds3', '3', '3.0'], true);
    }

    private function signal_text($value) {
        if (is_array($value)) {
            $parts = [];
            array_walk_recursive($value, static function ($part) use (&$parts) {
                if (is_scalar($part)) {
                    $parts[] = (string) $part;
                }
            });

            return implode(' ', $parts);
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function is_core_product($file, array $plugin) {
        $slug = strtolower(dirname((string) $file));
        $name = strtolower((string) ($plugin['Name'] ?? ''));

        return $this->is_core_product_slug($slug)
            || false !== strpos($name, 'million dollar script two')
            || false !== strpos($name, 'milliondollarscript two');
    }

    private function is_core_product_slug($slug) {
        return in_array(strtolower((string) $slug), [
            '.',
            'million-dollar-script',
            'million-dollar-script-two',
            'milliondollarscript-two',
            'mds-grid',
            'mds3',
        ], true);
    }

    private function purchase_url(array $row) {
        $purchase = $row['purchase'] ?? null;
        if (is_array($purchase) && !empty($purchase['url'])) {
            return (string) $purchase['url'];
        }
        if (is_array($purchase) && !empty($purchase['links']) && is_array($purchase['links'])) {
            foreach (['default', 'oneTime', 'monthly', 'yearly'] as $key) {
                if (!empty($purchase['links'][$key]) && is_string($purchase['links'][$key])) {
                    return (string) $purchase['links'][$key];
                }
            }
            if (!empty($purchase['links']['options']) && is_array($purchase['links']['options'])) {
                foreach ($purchase['links']['options'] as $options) {
                    if (!is_array($options)) {
                        continue;
                    }
                    foreach ($options as $option) {
                        if (is_array($option) && !empty($option['checkout'])) {
                            return (string) $option['checkout'];
                        }
                    }
                }
            }
        }

        $links = $row['purchase_links'] ?? null;
        if (is_array($links)) {
            foreach ($links as $link) {
                if (is_string($link) && '' !== $link) {
                    return $link;
                }
                if (is_array($link) && !empty($link['url'])) {
                    return (string) $link['url'];
                }
            }
        }

        return '';
    }

    private function extension_info_url(array $row) {
        foreach (['detail_url', 'details_url', 'info_url', 'product_url', 'docs_url', 'documentation_url', 'homepage_url', 'website_url', 'url'] as $key) {
            if (!empty($row[$key]) && is_scalar($row[$key])) {
                return (string) $row[$key];
            }
        }

        return '';
    }

    private function absolute_url($url, $base_url) {
        if (!is_scalar($url)) {
            return '';
        }

        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }

        $parts = wp_parse_url($url);
        if (!empty($parts['scheme']) && !empty($parts['host'])) {
            return $url;
        }

        if (0 === strpos($url, '/')) {
            return rtrim((string) $base_url, '/') . $url;
        }

        return $url;
    }

    private function customer_text($text) {
        $text = is_scalar($text) ? (string) $text : '';
        $text = preg_replace('/\bMillionDollarScript\V3\b/i', 'Million Dollar Script', $text);
        $text = preg_replace('/\bMDS\s*\(Million Dollar Script\)/i', 'Million Dollar Script', $text);
        $text = preg_replace('/^MDS\s+Extension:\s*/i', 'Million Dollar Script ', $text);
        $text = preg_replace('/\bMDS\b/i', 'Million Dollar Script', $text);

        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }

    private function check_update($base_url, $slug, $current_version, array $item, $force = false) {
        $slug = sanitize_key($slug);
        $current_version = sanitize_text_field($current_version);
        if (!$slug || !$current_version) {
            return null;
        }

        $license_key = (string) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/update/license/key', '', $slug, $item);
        $license_candidates = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/update/license/candidates', [], $slug, $item);
        $license_candidates = is_array($license_candidates) ? $license_candidates : [];
        if ('' !== trim($license_key)) {
            $license_candidates[] = trim($license_key);
        }
        $license_candidates = array_values(array_unique(array_filter(array_map('trim', $license_candidates))));
        if (!$license_candidates) {
            $license_candidates = [''];
        }

        $cache_key = 'mds3_ext_update_' . md5(rtrim((string) $base_url, '/') . '|' . $slug . '|' . $current_version . '|' . md5(implode('|', $license_candidates)));
        $failure_ttl = defined('MINUTE_IN_SECONDS') ? 5 * MINUTE_IN_SECONDS : 300;
        $success_ttl = defined('HOUR_IN_SECONDS') ? 6 * HOUR_IN_SECONDS : 21600;
        if (!$force && function_exists('get_site_transient')) {
            $cached = get_site_transient($cache_key);
            if (is_array($cached) && array_key_exists('payload', $cached)) {
                return is_array($cached['payload']) ? $cached['payload'] : null;
            }
        }

        $response = null;
        foreach ($license_candidates as $candidate) {
            $headers = ['Content-Type' => 'application/json'];
            if ('' !== $candidate) {
                $headers['X-License-Key'] = $candidate;
            }
            $attempt = wp_remote_post($base_url . '/api/public/v1/extensions/check-update', [
                'timeout' => 10,
                'headers' => $headers,
                'body' => wp_json_encode(array_merge(ExtensionServer::compatibility_args(), [
                    'extension_id' => $slug,
                    'current_version' => $current_version,
                    'instance_id' => ExtensionServer::installation_id(),
                    'site_id' => home_url('/'),
                ])),
            ]);
            if (is_wp_error($attempt)) {
                $response = $attempt;
                break;
            }
            $status = (int) wp_remote_retrieve_response_code($attempt);
            if (in_array($status, [401, 403], true)) {
                $response = $attempt;
                continue;
            }
            $response = $attempt;
            break;
        }

        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            if (function_exists('set_site_transient')) {
                set_site_transient($cache_key, ['payload' => null], $failure_ttl);
            }
            return null;
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        $payload = is_array($decoded) && isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;

        $payload = is_array($payload) ? $payload : null;
        if (function_exists('set_site_transient')) {
            set_site_transient($cache_key, ['payload' => $payload], $success_ttl);
        }

        return $payload;
    }

}
