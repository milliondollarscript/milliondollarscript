<?php
/**
 * Accountless extension tester access fixture.
 *
 * @package MillionDollarScript\V3\Tests
 */

use MillionDollarScript\V3\Extensions\ExtensionLicenseManager;
use MillionDollarScript\V3\Extensions\ExtensionServer;

if (!defined('ABSPATH')) {
    throw new RuntimeException('WordPress must be loaded.');
}

$option_name = 'mds3_extension_tester_access';
$previous = get_option($option_name, null);
$license_option_name = 'mds3_extension_licenses';
$previous_licenses = get_option($license_option_name, null);
$requests = [];

try {
    delete_option($option_name);
    delete_option($license_option_name);

    add_filter('million-dollar-script/extension/server/base/url', static function () {
        return 'https://tester-access-fixture.test';
    });

    add_filter('pre_http_request', static function ($pre, $args, $url) use (&$requests) {
        if (!is_string($url) || 0 !== strpos($url, 'https://tester-access-fixture.test/api/public/')) {
            return $pre;
        }

        $body = json_decode((string) ($args['body'] ?? ''), true);
        $body = is_array($body) ? $body : [];
        $requests[] = ['url' => $url, 'body' => $body];

        if ('3' !== (string) ($body['mds_generation'] ?? '') ||
            'wordpress' !== (string) ($body['platform'] ?? '') ||
			'million-dollar-script' !== (string) ($body['core'] ?? '') ||
			'modern' !== (string) ($body['product_family'] ?? '') ||
			'1' !== (string) ($body['core_api_version'] ?? '') ||
			MILLION_DOLLAR_SCRIPT_VERSION !== (string) ($body['core_version'] ?? '')) {
            throw new RuntimeException('Tester access request did not identify the MDS 3.0 product family.');
        }

        if (str_ends_with($url, '/activate')) {
            if (home_url('/') !== (string) ($body['siteId'] ?? '')) {
                throw new RuntimeException('Tester activation did not send the current site URL.');
            }
            if (ExtensionServer::installation_id() !== (string) ($body['deviceId'] ?? '')) {
                throw new RuntimeException('Tester activation did not send the current installation ID.');
            }

            return [
                'headers' => [],
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => wp_json_encode([
                    'success' => true,
                    'license' => [
                        'status_effective' => 'active',
                        'metadata' => [
                            'test_key' => true,
                            'tester_access' => true,
                            'tester_catalog_access' => true,
                        ],
                    ],
                ]),
            ];
        }

        return [
            'headers' => [],
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => wp_json_encode(['success' => true]),
        ];
    }, 10, 3);

    $manager = new ExtensionLicenseManager();
    $result = $manager->activate_tester_access('MDS-FIXTURE-TESTER-KEY');
    if (is_wp_error($result) || empty($result['success'])) {
        throw new RuntimeException('Tester access fixture activation failed.');
    }
    if (!$manager->has_tester_access() || !$manager->tester_access_allows('mds-fields')) {
        throw new RuntimeException('Connected tester access was not stored with catalog scope.');
    }
    if ('MDS-FIXTURE-TESTER-KEY' !== $manager->access_key('mds-fields')) {
        throw new RuntimeException('Tester access key was not selected for an entitled extension.');
    }

    $manager->deactivate_tester_access();
    if ($manager->has_tester_access()) {
        throw new RuntimeException('Tester access remained connected after deactivation.');
    }
    if (count($requests) < 2) {
        throw new RuntimeException('Expected activation and deactivation API requests.');
    }

    echo wp_json_encode([
        'activation_bound' => true,
        'requests' => count($requests),
    ], JSON_PRETTY_PRINT) . PHP_EOL;
} finally {
    if (null !== $previous) {
        update_option($option_name, $previous, false);
    } else {
        delete_option($option_name);
    }
    if (null !== $previous_licenses) {
        update_option($license_option_name, $previous_licenses, false);
    } else {
        delete_option($license_option_name);
    }
}
