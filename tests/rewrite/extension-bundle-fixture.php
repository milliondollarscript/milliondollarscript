<?php
/**
 * Extension entitlement migration and bundle precedence fixture.
 */

use MillionDollarScript\V3\Extensions\ExtensionLicenseManager;
use MillionDollarScript\Extensions\Licensing;

$GLOBALS['mds3_test_options']['mds3_extension_licenses'] = [
    'mds-fields' => [
        'license_key' => 'MDS-LEGACY-FIELDS',
        'extension_id' => 'extension-fields',
        'status' => 'active',
        'valid' => true,
        'updated_at' => '2026-07-18T12:00:00Z',
    ],
];

$license_manager = new ExtensionLicenseManager();
$migrated_entitlements = $license_manager->all();
$migrated_store = $GLOBALS['mds3_test_options']['mds3_extension_licenses'] ?? [];
mds3_assert_same(2, $migrated_store['schema_version'] ?? 0, 'Expected legacy extension licenses to migrate to the product entitlement schema.');
mds3_assert_same(1, count($migrated_entitlements), 'Expected legacy migration to preserve the individual extension license.');
mds3_assert_same('individual', $license_manager->access_source('mds-fields'), 'Expected a migrated license to remain an individual entitlement.');
mds3_assert_same('MDS-LEGACY-FIELDS', $license_manager->access_key('mds-fields'), 'Expected legacy migration to preserve the license key.');

$GLOBALS['mds3_test_options']['mds3_extension_licenses'] = [
    'schema_version' => 2,
    'claims' => [],
    'entitlements' => [
        'individual-fields' => [
            'entitlement_id' => 'individual-fields',
            'source_type' => 'individual',
            'product_slug' => 'mds-fields',
            'product_name' => 'Fields',
            'product_type' => 'plugin',
            'extension_ids' => ['extension-fields'],
            'extension_slugs' => ['mds-fields'],
            'license_key' => 'MDS-INDIVIDUAL-FIELDS',
            'status' => 'active',
            'valid' => true,
        ],
        'bundle-complete-pack' => [
            'entitlement_id' => 'bundle-complete-pack',
            'source_type' => 'bundle',
            'product_slug' => 'complete-extension-pack',
            'product_name' => 'Complete Extension Pack',
            'product_type' => 'bundle',
            'extension_ids' => ['extension-fields', 'extension-translation'],
            'extension_slugs' => ['mds-fields', 'mds-translation'],
            'license_key' => 'MDS-COMPLETE-PACK',
            'status' => 'active',
            'valid' => true,
            'plan' => 'one_time',
            'plan_key' => 'one_time',
            'max_activations' => 5,
        ],
    ],
];
$GLOBALS['mds3_test_options']['mds3_extension_tester_access'] = [
    'access_key' => 'MDS-TESTER-ACCESS',
    'status' => 'active',
    'valid' => true,
    'license' => [
        'metadata' => [
            'tester_catalog_access' => true,
        ],
    ],
];

$license_manager = new ExtensionLicenseManager();
mds3_assert_same('MDS-INDIVIDUAL-FIELDS', $license_manager->access_key('mds-fields'), 'Expected an individual license to take priority over bundle and tester access.');
mds3_assert_same('individual', $license_manager->access_source('mds-fields'), 'Expected the highest-priority source to be reported.');
mds3_assert_same('MDS-COMPLETE-PACK', $license_manager->access_key('mds-translation'), 'Expected a bundle license to take priority over tester access.');
mds3_assert_same('bundle', $license_manager->access_source('mds-translation'), 'Expected bundle access to identify its source.');
mds3_assert_same('Complete Extension Pack', $license_manager->bundle_name_for('mds-translation'), 'Expected bundle-derived access to expose the pack name.');
mds3_assert_same('MDS-TESTER-ACCESS', $license_manager->access_key('mds-revenue-agent'), 'Expected tester access to remain the last-resort catalog credential.');
mds3_assert_same('tester', $license_manager->access_source('mds-revenue-agent'), 'Expected tester access to identify its source.');
mds3_assert_same(true, Licensing::has_access('mds-fields'), 'Expected the public licensing facade to expose extension access.');
mds3_assert_same('individual', Licensing::access_source('mds-fields'), 'Expected the public licensing facade to preserve individual precedence.');
mds3_assert_same('bundle', Licensing::access_record('mds-translation')['source_type'] ?? '', 'Expected the public licensing facade to expose bundle records.');
mds3_assert_same('one_time', Licensing::access_record('mds-translation')['plan_key'] ?? '', 'Expected lifetime bundle access to preserve the server plan contract.');
mds3_assert_same(5, Licensing::access_record('mds-translation')['max_activations'] ?? 0, 'Expected lifetime bundle access to preserve the five-site activation limit.');
mds3_assert_same('tester', Licensing::access_record('mds-revenue-agent')['source_type'] ?? '', 'Expected the public licensing facade to expose tester records.');

$license_manager->sync_bundle_catalog([
    [
        'slug' => 'complete-extension-pack',
        'name' => 'Complete Extension Pack',
        'included_extensions' => [
            ['id' => 'extension-fields', 'slug' => 'mds-fields'],
            ['id' => 'extension-time-capsule', 'slug' => 'mds-time-capsule'],
        ],
    ],
]);

mds3_assert_same('tester', $license_manager->access_source('mds-translation'), 'Expected a removed bundle member to fall back to tester access.');
mds3_assert_same('bundle', $license_manager->access_source('mds-time-capsule'), 'Expected a newly added current bundle member to gain bundle access.');
mds3_assert_same('MDS-COMPLETE-PACK', $license_manager->access_key('mds-time-capsule'), 'Expected current bundle membership to use the pack license.');
mds3_assert_same(true, $license_manager->is_product_active('complete-extension-pack'), 'Expected product-level bundle state to remain independently manageable.');

$GLOBALS['mds3_test_options']['mds3_extension_licenses'] = [
    'schema_version' => 2,
    'claims' => [],
    'entitlements' => [
        'individual-fields' => [
            'entitlement_id' => 'individual-fields',
            'source_type' => 'individual',
            'product_slug' => 'mds-fields',
            'product_name' => 'Fields',
            'product_type' => 'plugin',
            'extension_ids' => ['extension-fields'],
            'extension_slugs' => ['mds-fields'],
            'license_key' => 'MDS-INDIVIDUAL-FIELDS',
            'status' => 'active',
            'valid' => true,
            'last_error' => '',
        ],
    ],
];
$GLOBALS['mds3_test_remote_post_queue'] = [new WP_Error('network_timeout', 'Connection timed out.')];
$license_manager = new ExtensionLicenseManager();
$license_manager->refresh_all();
$stale_record = $license_manager->product_record('mds-fields');
mds3_assert_same(true, $license_manager->is_product_active('mds-fields'), 'Expected a transport failure to preserve last-known active access.');
mds3_assert_same('Connection timed out.', $stale_record['last_error'] ?? '', 'Expected a transport failure to retain a visible refresh warning.');

$GLOBALS['mds3_test_remote_post_queue'] = [[
    'response' => ['code' => 200],
    'body' => wp_json_encode([
        'success' => true,
        'valid' => false,
        'message' => 'License is revoked.',
        'license' => [
            'status_effective' => 'revoked',
            'status' => 'revoked',
        ],
    ]),
]];
$license_manager->refresh_all();
$invalid_record = $license_manager->product_record('mds-fields');
mds3_assert_same(false, $license_manager->is_product_active('mds-fields'), 'Expected a confirmed invalid response to disable that access source.');
mds3_assert_same('', $invalid_record['last_error'] ?? '', 'Expected a confirmed response to clear the transport warning.');
