<?php
/**
 * Product-level extension entitlement storage and extension-server API calls.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

use MillionDollarScript\V3\Docs\RemoteDocsClient;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionLicenseManager {

    private const OPTION = 'mds3_extension_licenses';
    private const SCHEMA_VERSION = 2;
    private const TESTER_ACCESS_OPTION = 'mds3_extension_tester_access';
    private const TESTER_ACCESS_PRODUCT = 'mds-tester-access';

    /**
     * Return stored product entitlements keyed by stable entitlement ID.
     *
     * @return array<string,array>
     */
    public function all() {
        $store = $this->store();

        return is_array($store['entitlements'] ?? null) ? $store['entitlements'] : [];
    }

    /**
     * Return the highest-priority active entitlement for an extension.
     *
     * Priority is individual, then bundle. Tester access remains a separate
     * last-resort candidate and is exposed by access_candidates().
     */
    public function record($slug) {
        $candidates = $this->entitlement_candidates($slug);

        return $candidates ? reset($candidates) : [];
    }

    public function product_record($product_slug) {
        $product_slug = sanitize_key($product_slug);
        if (!$product_slug) {
            return [];
        }
        foreach ($this->all() as $record) {
            if (is_array($record) && $product_slug === sanitize_key((string) ($record['product_slug'] ?? ''))) {
                return $record;
            }
        }

        return [];
    }

    public function is_active($slug) {
        return $this->record_is_active($this->record($slug));
    }

    public function is_product_active($product_slug) {
        return $this->record_is_active($this->product_record($product_slug));
    }

    public function license_key($slug) {
        $record = $this->record($slug);

        return $this->record_is_active($record) ? $this->sanitize_license_key($record['license_key'] ?? '') : '';
    }

    public function product_license_key($product_slug) {
        $record = $this->product_record($product_slug);

        return $this->record_is_active($record) ? $this->sanitize_license_key($record['license_key'] ?? '') : '';
    }

    public function access_source($slug) {
        $record = $this->record($slug);

        return $this->record_is_active($record) ? sanitize_key((string) ($record['source_type'] ?? 'individual')) : ($this->tester_access_allows($slug) ? 'tester' : '');
    }

    public function access_product($slug) {
        $record = $this->record($slug);

        return $this->record_is_active($record) ? sanitize_key((string) ($record['product_slug'] ?? '')) : ($this->tester_access_allows($slug) ? self::TESTER_ACCESS_PRODUCT : '');
    }

    public function bundle_name_for($slug) {
        foreach ($this->entitlement_candidates($slug) as $record) {
            if ('bundle' === sanitize_key((string) ($record['source_type'] ?? ''))) {
                return sanitize_text_field((string) ($record['product_name'] ?? $record['product_slug'] ?? ''));
            }
        }

        return '';
    }

    public function tester_access_record() {
        $record = get_option(self::TESTER_ACCESS_OPTION, []);

        return is_array($record) ? $record : [];
    }

    public function has_tester_access() {
        $record = $this->tester_access_record();

        return !empty($record['valid']) && 'active' === sanitize_key((string) ($record['status'] ?? '')) && '' !== $this->sanitize_license_key($record['access_key'] ?? '');
    }

    public function tester_access_key() {
        return $this->has_tester_access() ? $this->sanitize_license_key($this->tester_access_record()['access_key'] ?? '') : '';
    }

    public function tester_access_allows($slug) {
        $slug = sanitize_key($slug);
        if (!$slug || !$this->has_tester_access()) {
            return false;
        }

        $record = $this->tester_access_record();
        $license = isset($record['license']) && is_array($record['license']) ? $record['license'] : [];
        $metadata = isset($license['metadata']) && is_array($license['metadata']) ? $license['metadata'] : [];
        if (!empty($metadata['tester_catalog_access'])) {
            return true;
        }

        $allowed = is_array($metadata['tester_extension_slugs'] ?? null) ? array_map('sanitize_key', $metadata['tester_extension_slugs']) : [];

        return in_array($slug, $allowed, true);
    }

    /**
     * Return access credentials in deterministic fallback order.
     *
     * @return array<int,array{source_type:string,product_slug:string,license_key:string}>
     */
    public function access_candidates($slug) {
        $candidates = [];
        foreach ($this->entitlement_candidates($slug) as $record) {
            $license_key = $this->sanitize_license_key($record['license_key'] ?? '');
            if (!$license_key) {
                continue;
            }
            $candidates[] = [
                'source_type' => sanitize_key((string) ($record['source_type'] ?? 'individual')),
                'product_slug' => sanitize_key((string) ($record['product_slug'] ?? '')),
                'license_key' => $license_key,
            ];
        }
        if ($this->tester_access_allows($slug)) {
            $candidates[] = [
                'source_type' => 'tester',
                'product_slug' => self::TESTER_ACCESS_PRODUCT,
                'license_key' => $this->tester_access_key(),
            ];
        }

        return $candidates;
    }

    public function access_key($slug) {
        $candidates = $this->access_candidates($slug);

        return $candidates ? (string) $candidates[0]['license_key'] : '';
    }

    public function has_access($slug) {
        return '' !== $this->access_key($slug);
    }

    public function update_license_key($license_key, $slug, array $item = []) {
        $stored = $this->access_key($slug);

        return '' !== $stored ? $stored : $license_key;
    }

    public function update_license_candidates($candidates, $slug, array $item = []) {
        $keys = array_map(static function ($candidate) {
            return (string) ($candidate['license_key'] ?? '');
        }, $this->access_candidates($slug));
        if (is_array($candidates)) {
            $keys = array_merge($keys, $candidates);
        }

        return array_values(array_unique(array_filter(array_map([$this, 'sanitize_license_key'], $keys))));
    }

    public function activate_tester_access($access_key) {
        $access_key = $this->sanitize_license_key($access_key);
        if (!$access_key) {
            return new \WP_Error('mds3_tester_access_invalid', __('Enter a valid tester access key.', 'million-dollar-script'));
        }

        $response = $this->api_post('/api/public/activate', [
            'licenseKey' => $access_key,
            'productIdentifier' => self::TESTER_ACCESS_PRODUCT,
            'deviceId' => ExtensionServer::installation_id(),
            'siteId' => home_url('/'),
            'version' => defined('MILLION_DOLLAR_SCRIPT_VERSION') ? MILLION_DOLLAR_SCRIPT_VERSION : '',
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        if (empty($response['success'])) {
            return new \WP_Error('mds3_tester_access_rejected', $this->response_message($response, __('Tester access activation failed.', 'million-dollar-script')));
        }

        $license = isset($response['license']) && is_array($response['license']) ? $response['license'] : [];
        $metadata = isset($license['metadata']) && is_array($license['metadata']) ? $license['metadata'] : [];
        if ((empty($metadata['test_key']) && empty($metadata['testkey'])) || (empty($metadata['tester_access']) && empty($metadata['testeraccess']))) {
            return new \WP_Error('mds3_tester_access_wrong_key_type', __('This is not an extension-server tester access key. Use Manage license for a customer extension license.', 'million-dollar-script'));
        }

        $status = sanitize_key((string) ($license['status_effective'] ?? $license['status'] ?? 'active')) ?: 'active';
        update_option(self::TESTER_ACCESS_OPTION, $this->sanitize_payload([
            'access_key' => $access_key,
            'status' => $status,
            'valid' => 'active' === $status,
            'license' => $license,
            'connected_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ]), false);
        RemoteDocsClient::purge_all();

        return $response;
    }

    public function deactivate_tester_access() {
        $access_key = $this->tester_access_key();
        if ($access_key) {
            $this->api_post('/api/public/deactivate', [
                'licenseKey' => $access_key,
                'productIdentifier' => self::TESTER_ACCESS_PRODUCT,
                'deviceId' => ExtensionServer::installation_id(),
            ]);
        }

        delete_option(self::TESTER_ACCESS_OPTION);
        RemoteDocsClient::purge_all();

        return ['success' => true, 'message' => __('Tester access disconnected from this site.', 'million-dollar-script')];
    }

    public function pending_claim_token($product_slug) {
        $product_slug = sanitize_key($product_slug);
        if (!$product_slug) {
            return '';
        }

        $store = $this->store();
        $claim = is_array($store['claims'][$product_slug] ?? null) ? $store['claims'][$product_slug] : [];
        if (!empty($claim['claim_token'])) {
            return (string) $claim['claim_token'];
        }

        $claim['claim_token'] = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : md5(wp_rand() . microtime(true));
        $claim['updated_at'] = gmdate('c');
        $store['claims'][$product_slug] = $claim;
        $this->save_store($store);

        return (string) $claim['claim_token'];
    }

    public function activate($product_slug, $license_key, array $item = [], $version = '') {
        $product_slug = sanitize_key($product_slug);
        $license_key = $this->sanitize_license_key($license_key);
        if (!$product_slug || !$license_key) {
            return new \WP_Error('mds3_license_invalid', __('A valid product and license key are required.', 'million-dollar-script'));
        }

        $is_bundle = 'bundle' === sanitize_key((string) ($item['product_type'] ?? ''));
        $payload = [
            'licenseKey' => $license_key,
            'productIdentifier' => $product_slug,
            'extensionId' => $is_bundle ? '' : sanitize_text_field((string) ($item['id'] ?? '')),
            'deviceId' => ExtensionServer::installation_id(),
            'siteId' => home_url('/'),
            'version' => sanitize_text_field((string) $version),
        ];

        $response = $this->api_post('/api/public/activate', $payload);
        if (is_wp_error($response)) {
            return $response;
        }
        if (empty($response['success'])) {
            return new \WP_Error('mds3_license_activation_rejected', $this->response_message($response, __('License activation failed.', 'million-dollar-script')));
        }

        $license = isset($response['license']) && is_array($response['license']) ? $response['license'] : [];
        $metadata = isset($license['metadata']) && is_array($license['metadata']) ? $license['metadata'] : [];
        $status = sanitize_key((string) ($license['status_effective'] ?? $license['status'] ?? 'active')) ?: 'active';
        $extension_slugs = $is_bundle ? $this->bundle_member_slugs($item, $metadata) : [$product_slug];
        $extension_ids = $is_bundle ? $this->bundle_member_ids($item, $metadata) : array_filter([sanitize_text_field((string) ($item['id'] ?? ($license['primary_extension_id'] ?? '')))]);
        $source_type = $is_bundle ? 'bundle' : 'individual';
        $record = [
            'entitlement_id' => $this->entitlement_id($source_type, $product_slug, $license),
            'source_type' => $source_type,
            'product_slug' => $product_slug,
            'product_name' => sanitize_text_field((string) ($item['name'] ?? $metadata['product_name'] ?? $product_slug)),
            'product_type' => $is_bundle ? 'bundle' : 'plugin',
            'extension_ids' => array_values($extension_ids),
            'extension_slugs' => array_values($extension_slugs),
            'license_key' => $license_key,
            'status' => $status,
            'valid' => 'active' === $status && empty($license['is_expired']),
            'message' => $this->response_message($response, __('License activated.', 'million-dollar-script')),
            'license' => $this->sanitize_payload($license),
            'activated_at' => gmdate('c'),
            'last_checked_at' => gmdate('c'),
            'last_error' => '',
            'updated_at' => gmdate('c'),
        ];
        $this->save_entitlement($record);
        $this->delete_claim($product_slug);
        foreach ($extension_slugs as $slug) {
            RemoteDocsClient::purge_extension($slug);
        }

        return $response;
    }

    public function deactivate($product_slug) {
        $product_slug = sanitize_key($product_slug);
        $record = $this->product_record($product_slug);
        $license_key = $this->sanitize_license_key($record['license_key'] ?? '');
        if (!$product_slug || !$license_key) {
            $this->delete_product_entitlement($product_slug);
            return ['success' => true, 'message' => __('Stored license removed.', 'million-dollar-script')];
        }

        $response = $this->api_post('/api/public/deactivate', [
            'licenseKey' => $license_key,
            'productIdentifier' => $product_slug,
            'extensionId' => 'bundle' === ($record['source_type'] ?? '') ? '' : sanitize_text_field((string) (($record['extension_ids'][0] ?? ''))),
            'deviceId' => ExtensionServer::installation_id(),
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        if (empty($response['success'])) {
            return new \WP_Error('mds3_license_deactivation_rejected', $this->response_message($response, __('License deactivation failed.', 'million-dollar-script')));
        }

        $extension_slugs = is_array($record['extension_slugs'] ?? null) ? $record['extension_slugs'] : [];
        $this->delete_product_entitlement($product_slug);
        foreach ($extension_slugs as $slug) {
            RemoteDocsClient::purge_extension($slug);
        }

        return $response;
    }

    public function claim($product_slug, $claim_token, array $item = []) {
        $product_slug = sanitize_key($product_slug);
        $claim_token = sanitize_text_field((string) $claim_token);
        if (!$product_slug || !$claim_token) {
            return new \WP_Error('mds3_license_claim_invalid', __('A valid product and claim token are required.', 'million-dollar-script'));
        }

        $response = $this->api_post('/api/public/licenses/claim', [
            'claimToken' => $claim_token,
            'siteId' => home_url('/'),
            'honeypot' => '',
        ]);
        if (is_wp_error($response)) {
            return $response;
        }

        $license = isset($response['license']) && is_array($response['license']) ? $response['license'] : [];
        $license_key = $this->sanitize_license_key($license['licenseKey'] ?? $license['license_key'] ?? $license['key'] ?? '');
        if (!$license_key) {
            return new \WP_Error('mds3_license_claim_missing_key', __('The claimed license response did not include a license key.', 'million-dollar-script'));
        }

        return $this->activate($product_slug, $license_key, $item);
    }

    /**
     * Reconcile bundle member displays with a successfully fetched catalog.
     */
    public function sync_bundle_catalog(array $bundles) {
        $by_slug = [];
        foreach ($bundles as $bundle) {
            if (is_array($bundle) && !empty($bundle['slug'])) {
                $by_slug[sanitize_key((string) $bundle['slug'])] = $bundle;
            }
        }
        if (!$by_slug) {
            return;
        }

        $store = $this->store();
        $changed = false;
        foreach ($store['entitlements'] as $id => $record) {
            if (!is_array($record) || 'bundle' !== sanitize_key((string) ($record['source_type'] ?? ''))) {
                continue;
            }
            $product_slug = sanitize_key((string) ($record['product_slug'] ?? ''));
            if (empty($by_slug[$product_slug])) {
                continue;
            }
            $bundle = $by_slug[$product_slug];
            $record['product_name'] = sanitize_text_field((string) ($bundle['name'] ?? $record['product_name'] ?? $product_slug));
            $record['extension_slugs'] = $this->bundle_member_slugs($bundle, []);
            $record['extension_ids'] = $this->bundle_member_ids($bundle, []);
            $record['updated_at'] = gmdate('c');
            $store['entitlements'][$id] = $this->sanitize_payload($record);
            $changed = true;
        }
        if ($changed) {
            $this->save_store($store);
        }
    }

    /**
     * Validate stored individual and bundle products. Network failures preserve
     * the last known state; confirmed invalid responses disable only that source.
     */
    public function refresh_all() {
        $store = $this->store();
        $changed = false;
        foreach ($store['entitlements'] as $id => $record) {
            if (!is_array($record)) {
                continue;
            }
            $license_key = $this->sanitize_license_key($record['license_key'] ?? '');
            $product_slug = sanitize_key((string) ($record['product_slug'] ?? ''));
            if (!$license_key || !$product_slug) {
                continue;
            }
            $response = $this->api_post('/api/public/validate', [
                'licenseKey' => $license_key,
                'productIdentifier' => $product_slug,
            ]);
            $record['last_checked_at'] = gmdate('c');
            if (is_wp_error($response)) {
                $record['last_error'] = sanitize_text_field($response->get_error_message());
                $store['entitlements'][$id] = $this->sanitize_payload($record);
                $changed = true;
                continue;
            }

            $license = isset($response['license']) && is_array($response['license']) ? $response['license'] : [];
            $status = sanitize_key((string) ($license['status_effective'] ?? $license['status'] ?? ($record['status'] ?? 'inactive'))) ?: 'inactive';
            $record['status'] = $status;
            $record['valid'] = !empty($response['valid']) && 'active' === $status && empty($license['is_expired']);
            $record['message'] = $this->response_message($response, $record['valid'] ? __('License is active.', 'million-dollar-script') : __('License is not active.', 'million-dollar-script'));
            $record['last_error'] = '';
            if ($license) {
                $record['license'] = $this->sanitize_payload($license);
                $metadata = is_array($license['metadata'] ?? null) ? $license['metadata'] : [];
                if ('bundle' === sanitize_key((string) ($record['source_type'] ?? ''))) {
                    $record['extension_slugs'] = $this->bundle_member_slugs([], $metadata);
                    $record['extension_ids'] = $this->bundle_member_ids([], $metadata);
                }
            }
            $record['updated_at'] = gmdate('c');
            $store['entitlements'][$id] = $this->sanitize_payload($record);
            $changed = true;
        }
        if ($changed) {
            $this->save_store($store);
            RemoteDocsClient::purge_all();
        }

        return ['success' => true, 'message' => __('Extension access refreshed.', 'million-dollar-script')];
    }

    public function protected_package_url(array $item) {
        $id = sanitize_text_field((string) ($item['id'] ?? ''));
        $slug = sanitize_key((string) ($item['slug'] ?? ''));
        $base_url = ExtensionServer::base_url();
        if (!$id || !$slug || !$base_url) {
            return '';
        }

        foreach ($this->access_candidates($slug) as $candidate) {
            $response = $this->api_post('/api/public/v1/extensions/check-update', [
                'extension_id' => $slug,
                'current_version' => '0.0.0',
                'instance_id' => ExtensionServer::installation_id(),
                'site_id' => home_url('/'),
            ], ['X-License-Key' => $candidate['license_key']]);
            if (is_wp_error($response)) {
                if ($this->is_authorization_error($response)) {
                    continue;
                }
                return '';
            }

            $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : $response;
            $package_url = $data['package_url'] ?? $data['download_url'] ?? '';
            if (is_scalar($package_url) && '' !== trim((string) $package_url)) {
                return esc_url_raw((string) $package_url);
            }
        }

        return '';
    }

    public function masked_license_key($slug) {
        return $this->mask_key($this->license_key($slug));
    }

    public function masked_product_license_key($product_slug) {
        return $this->mask_key($this->product_license_key($product_slug));
    }

    public function masked_tester_access_key() {
        return $this->mask_key($this->tester_access_key());
    }

    private function store() {
        $raw = get_option(self::OPTION, []);
        $raw = is_array($raw) ? $raw : [];
        if (self::SCHEMA_VERSION === absint($raw['schema_version'] ?? 0)) {
            return [
                'schema_version' => self::SCHEMA_VERSION,
                'claims' => is_array($raw['claims'] ?? null) ? $raw['claims'] : [],
                'entitlements' => is_array($raw['entitlements'] ?? null) ? $raw['entitlements'] : [],
            ];
        }

        $store = ['schema_version' => self::SCHEMA_VERSION, 'claims' => [], 'entitlements' => []];
        foreach ($raw as $slug => $legacy) {
            $slug = sanitize_key($slug);
            if (!$slug || !is_array($legacy)) {
                continue;
            }
            if (!empty($legacy['claim_token'])) {
                $store['claims'][$slug] = [
                    'claim_token' => sanitize_text_field((string) $legacy['claim_token']),
                    'updated_at' => sanitize_text_field((string) ($legacy['updated_at'] ?? gmdate('c'))),
                ];
            }
            $license_key = $this->sanitize_license_key($legacy['license_key'] ?? '');
            if (!$license_key) {
                continue;
            }
            $license = is_array($legacy['license'] ?? null) ? $legacy['license'] : [];
            $record = array_merge($legacy, [
                'entitlement_id' => $this->entitlement_id('individual', $slug, $license),
                'source_type' => 'individual',
                'product_slug' => $slug,
                'product_name' => sanitize_text_field((string) ($legacy['product_name'] ?? $slug)),
                'product_type' => 'plugin',
                'extension_ids' => array_values(array_filter([sanitize_text_field((string) ($legacy['extension_id'] ?? ''))])),
                'extension_slugs' => [$slug],
                'last_checked_at' => sanitize_text_field((string) ($legacy['updated_at'] ?? '')),
                'last_error' => '',
            ]);
            $store['entitlements'][$record['entitlement_id']] = $this->sanitize_payload($record);
        }
        $this->save_store($store);

        return $store;
    }

    private function save_store(array $store) {
        $store['schema_version'] = self::SCHEMA_VERSION;
        $store['claims'] = is_array($store['claims'] ?? null) ? $store['claims'] : [];
        $store['entitlements'] = is_array($store['entitlements'] ?? null) ? $store['entitlements'] : [];
        update_option(self::OPTION, $this->sanitize_payload($store), false);
    }

    private function save_entitlement(array $record) {
        $id = sanitize_key((string) ($record['entitlement_id'] ?? ''));
        if (!$id) {
            return;
        }
        $store = $this->store();
        $store['entitlements'][$id] = $this->sanitize_payload($record);
        $this->save_store($store);
    }

    private function delete_product_entitlement($product_slug) {
        $product_slug = sanitize_key($product_slug);
        $store = $this->store();
        foreach ($store['entitlements'] as $id => $record) {
            if (is_array($record) && $product_slug === sanitize_key((string) ($record['product_slug'] ?? ''))) {
                unset($store['entitlements'][$id]);
            }
        }
        $this->save_store($store);
    }

    private function delete_claim($product_slug) {
        $store = $this->store();
        unset($store['claims'][sanitize_key($product_slug)]);
        $this->save_store($store);
    }

    private function entitlement_candidates($slug) {
        $slug = sanitize_key($slug);
        $individual = [];
        $bundles = [];
        foreach ($this->all() as $record) {
            if (!is_array($record) || !$this->record_is_active($record)) {
                continue;
            }
            $members = is_array($record['extension_slugs'] ?? null) ? array_map('sanitize_key', $record['extension_slugs']) : [];
            $source = sanitize_key((string) ($record['source_type'] ?? 'individual')) ?: 'individual';
            $matches = in_array($slug, $members, true) || ('individual' === $source && $slug === sanitize_key((string) ($record['product_slug'] ?? '')));
            if (!$matches) {
                continue;
            }
            if ('individual' === $source) {
                $individual[] = $record;
            } elseif ('bundle' === $source) {
                $bundles[] = $record;
            }
        }

        return array_merge($individual, $bundles);
    }

    private function record_is_active($record) {
        return is_array($record) && !empty($record['valid']) && 'active' === sanitize_key((string) ($record['status'] ?? ''));
    }

    private function entitlement_id($source_type, $product_slug, array $license) {
        $license_id = sanitize_key((string) ($license['id'] ?? ''));
        if ($license_id) {
            return sanitize_key($source_type . '-' . $license_id);
        }

        return sanitize_key($source_type . '-' . $product_slug . '-' . substr(md5((string) ($license['key'] ?? $product_slug)), 0, 12));
    }

    private function bundle_member_slugs(array $item, array $metadata) {
        $slugs = [];
        foreach ((array) ($item['included_extensions'] ?? []) as $member) {
            if (is_array($member)) {
                $slugs[] = sanitize_key((string) ($member['slug'] ?? $member['name'] ?? ''));
            }
        }
        if (!$slugs) {
            $slugs = is_array($metadata['extension_slugs'] ?? null) ? array_map('sanitize_key', $metadata['extension_slugs']) : [];
        }

        return array_values(array_unique(array_filter($slugs)));
    }

    private function bundle_member_ids(array $item, array $metadata) {
        $ids = [];
        foreach ((array) ($item['included_extensions'] ?? []) as $member) {
            if (is_array($member) && !empty($member['id'])) {
                $ids[] = sanitize_text_field((string) $member['id']);
            }
        }
        if (!$ids) {
            $ids = is_array($metadata['extension_ids'] ?? null) ? array_map('sanitize_text_field', $metadata['extension_ids']) : [];
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function mask_key($key) {
        $key = $this->sanitize_license_key($key);
        if ('' === $key) {
            return '';
        }
        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 4) . str_repeat('*', max(4, strlen($key) - 8)) . substr($key, -4);
    }

    private function sanitize_license_key($license_key) {
        if (!is_scalar($license_key)) {
            return '';
        }

        $license_key = trim((string) $license_key);
        $license_key = preg_replace('/[\x00-\x1F\x7F\s]+/', '', $license_key);
        if (!is_string($license_key) || strlen($license_key) < 3 || strlen($license_key) > 255) {
            return '';
        }

        return sanitize_text_field($license_key);
    }

    private function api_post($path, array $payload, array $headers = []) {
        $base_url = ExtensionServer::base_url();
        if (!$base_url) {
            return new \WP_Error('mds3_extension_server_missing', __('Extension server URL is not configured.', 'million-dollar-script'));
        }

        $payload = array_merge(ExtensionServer::compatibility_args(), $payload);
        $response = wp_remote_post($base_url . $path, [
            'timeout' => 15,
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
            'body' => wp_json_encode($payload),
        ]);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        $decoded = is_array($decoded) ? $decoded : [];
        if ($code < 200 || $code >= 300) {
            return new \WP_Error('mds3_extension_server_error', $this->response_message($decoded, __('Extension server rejected the request.', 'million-dollar-script')), [
                'status' => $code,
                'response' => $decoded,
            ]);
        }

        return $decoded;
    }

    private function is_authorization_error(\WP_Error $error) {
        $data = $error->get_error_data();
        $status = is_array($data) ? absint($data['status'] ?? 0) : 0;

        return in_array($status, [401, 403], true);
    }

    private function response_message(array $payload, $fallback) {
        foreach (['message', 'error', 'detail'] as $key) {
            if (!empty($payload[$key]) && is_scalar($payload[$key])) {
                return sanitize_text_field((string) $payload[$key]);
            }
        }
        if (!empty($payload['error']) && is_array($payload['error'])) {
            return $this->response_message($payload['error'], $fallback);
        }

        return $fallback;
    }

    private function sanitize_payload($payload, $depth = 0) {
        if ($depth > 8) {
            return null;
        }
        if (is_array($payload)) {
            $clean = [];
            foreach ($payload as $key => $value) {
                $clean[sanitize_key((string) $key)] = $this->sanitize_payload($value, $depth + 1);
            }

            return $clean;
        }
        if (is_bool($payload) || is_int($payload) || is_float($payload) || null === $payload) {
            return $payload;
        }

        return sanitize_text_field((string) $payload);
    }
}
