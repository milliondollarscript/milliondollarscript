<?php
/**
 * Focused fixture for privacy-minimized network diagnostics.
 */

use MillionDollarScript\V3\Support\NetworkDiagnostics;

if (!defined('ABSPATH')) {
    exit;
}

$filter = static function ($preempt, $args, $url) {
    unset($preempt, $args);
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    if ('/ok' === $path) {
        return ['headers' => [], 'body' => '{}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }
    if ('/blocked' === $path) {
        return ['headers' => ['cf-ray' => 'fixture-ray-id'], 'body' => '', 'response' => ['code' => 403, 'message' => 'Forbidden'], 'cookies' => [], 'filename' => null];
    }
    if ('/bunny' === $path) {
        return ['headers' => ['cdn-requestid' => 'fixture-bunny-request-id'], 'body' => '{}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }
    if ('/failed' === $path) {
        return ['headers' => ['x-request-id' => 'fixture-request-id'], 'body' => '', 'response' => ['code' => 503, 'message' => 'Unavailable'], 'cookies' => [], 'filename' => null];
    }

    return new WP_Error('http_request_failed', 'Fixture DNS failure.');
};
add_filter('pre_http_request', $filter, 10, 3);

try {
    $results = NetworkDiagnostics::run([
        ['id' => 'ok', 'label' => 'Reachable', 'url' => 'https://example.test/ok?token=private'],
        ['id' => 'blocked', 'label' => 'Blocked', 'url' => 'https://example.test/blocked'],
        ['id' => 'bunny', 'label' => 'Bunny edge', 'url' => 'https://example.test/bunny'],
        ['id' => 'failed', 'label' => 'Failed', 'url' => 'https://example.test/failed'],
        ['id' => 'network', 'label' => 'Network', 'url' => 'https://example.test/network'],
    ]);
} finally {
    remove_filter('pre_http_request', $filter, 10);
}

$checks = array_column((array) ($results['checks'] ?? []), null, 'id');
if (
    'reachable' !== ($checks['ok']['outcome'] ?? '')
    || 200 !== ($checks['ok']['status'] ?? 0)
    || 'https://example.test/ok' !== ($checks['ok']['url'] ?? '')
) {
    throw new RuntimeException('Reachable diagnostic or URL privacy minimization failed.');
}
if ('access_denied' !== ($checks['blocked']['outcome'] ?? '') || 'fixture-ray-id' !== ($checks['blocked']['request_id'] ?? '')) {
    throw new RuntimeException('Access-denied diagnostic did not preserve its provider request ID.');
}
if ('reachable' !== ($checks['bunny']['outcome'] ?? '') || 'fixture-bunny-request-id' !== ($checks['bunny']['request_id'] ?? '')) {
    throw new RuntimeException('Bunny diagnostic did not preserve its edge request ID.');
}
if ('server_error' !== ($checks['failed']['outcome'] ?? '') || 'fixture-request-id' !== ($checks['failed']['request_id'] ?? '')) {
    throw new RuntimeException('Server-error diagnostic classification failed.');
}
if ('network_error' !== ($checks['network']['outcome'] ?? '') || false === strpos((string) ($checks['network']['message'] ?? ''), 'DNS')) {
    throw new RuntimeException('Network transport diagnostic classification failed.');
}

echo "Network diagnostics fixture passed.\n";
