<?php
/**
 * Stable extension licensing and entitlement helpers.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Extensions;

use MillionDollarScript\V3\Extensions\ExtensionLicenseManager;

if (!defined('ABSPATH')) {
    exit;
}

final class Licensing {

    public static function has_access(string $extension_slug): bool {
        return (new ExtensionLicenseManager())->has_access($extension_slug);
    }

    public static function access_key(string $extension_slug): string {
        return (string) (new ExtensionLicenseManager())->access_key($extension_slug);
    }

    public static function access_source(string $extension_slug): string {
        return (string) (new ExtensionLicenseManager())->access_source($extension_slug);
    }

    public static function access_product(string $extension_slug): string {
        return (string) (new ExtensionLicenseManager())->access_product($extension_slug);
    }

    /**
     * Return the active individual, bundle, or tester entitlement for an extension.
     *
     * @return array<string,mixed>
     */
    public static function access_record(string $extension_slug): array {
        $extension_slug = sanitize_key($extension_slug);
        if (!$extension_slug) {
            return [];
        }

        $manager = new ExtensionLicenseManager();
        $record = $manager->record($extension_slug);
        if ($record) {
            return $record;
        }
        if (!$manager->tester_access_allows($extension_slug)) {
            return [];
        }

        $tester = $manager->tester_access_record();
        $license = is_array($tester['license'] ?? null) ? $tester['license'] : [];
        $metadata = is_array($license['metadata'] ?? null) ? $license['metadata'] : [];

        return [
            'entitlement_id' => 'tester-access',
            'source_type' => 'tester',
            'product_slug' => 'mds-tester-access',
            'product_name' => sanitize_text_field((string) ($metadata['tester_label'] ?? __('Tester access', 'million-dollar-script'))),
            'product_type' => 'tester_access',
            'extension_ids' => [],
            'extension_slugs' => [$extension_slug],
            'license_key' => $manager->tester_access_key(),
            'status' => sanitize_key((string) ($tester['status'] ?? 'active')) ?: 'active',
            'valid' => true,
            'message' => __('Access provided by an extension-server tester key.', 'million-dollar-script'),
            'license' => $license,
            'updated_at' => sanitize_text_field((string) ($tester['updated_at'] ?? '')),
        ];
    }

    /**
     * Return access candidates in individual, bundle, then tester order.
     *
     * @return array<int,array{source_type:string,product_slug:string,license_key:string}>
     */
    public static function access_candidates(string $extension_slug): array {
        return (new ExtensionLicenseManager())->access_candidates($extension_slug);
    }
}
