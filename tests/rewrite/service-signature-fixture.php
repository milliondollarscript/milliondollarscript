<?php
/**
 * Focused service-signature dispatcher contract checks.
 */

use MillionDollarScript\Core\ApiAccess;
use MillionDollarScript\Core\ServiceSignatureRequest;
use MillionDollarScript\V3\Rest\ServiceSignatureRegistry;

final class MDS3_ServiceSignatureTestRequest {

    private string $method;
    private string $route;
    private string $body;
    private array $headers;

    public function __construct($method, $route, $body, array $headers) {
        $this->method = (string) $method;
        $this->route = (string) $route;
        $this->body = (string) $body;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function get_method() {
        return $this->method;
    }

    public function get_route() {
        return $this->route;
    }

    public function get_body() {
        return $this->body;
    }

    public function get_header($name) {
        return $this->headers[strtolower(str_replace('_', '-', (string) $name))] ?? '';
    }
}

$previous_host = $_SERVER['HTTP_HOST'] ?? null;
$previous_remote_addr = $_SERVER['REMOTE_ADDR'] ?? null;
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$service_route = '/million-dollar-script/v1/service-signature-fixture';
$service_scope = 'fixture.service.write';
$service_endpoint = 'service-signature-fixture';
$service_secret = 'fixture-secret-that-is-never-an-api-label';
$service_id = 'fixture_service_0001';
$service_body = '{"fixture":true}';
$service_timestamp = time();
$service_nonce = str_repeat('a', 43);
$service_idempotency = 'fixture-request-0001';

$service_headers = static function (
    $method = 'POST',
    $route = null,
    $body = null,
    $timestamp = null,
    $nonce = null,
    $service = null,
    $idempotency = null
) use (
    $service_route,
    $service_body,
    $service_timestamp,
    $service_nonce,
    $service_id,
    $service_idempotency,
    $service_secret
) {
    $route = null === $route ? $service_route : (string) $route;
    $body = null === $body ? $service_body : (string) $body;
    $timestamp = null === $timestamp ? $service_timestamp : (int) $timestamp;
    $nonce = null === $nonce ? $service_nonce : (string) $nonce;
    $service = null === $service ? $service_id : (string) $service;
    $idempotency = null === $idempotency ? $service_idempotency : (string) $idempotency;
    $body_hash = hash('sha256', $body);
    $canonical = implode("\n", [
        'v1',
        $service,
        strtoupper((string) $method),
        $route,
        (string) $timestamp,
        $nonce,
        $body_hash,
        $idempotency,
    ]);

    return [
        'X-MDS-Service-Id' => $service,
        'X-MDS-Signature-Version' => 'v1',
        'X-MDS-Timestamp' => (string) $timestamp,
        'X-MDS-Nonce' => $nonce,
        'X-MDS-Content-SHA256' => $body_hash,
        'X-MDS-Signature' => hash_hmac('sha256', $canonical, $service_secret),
        'X-Idempotency-Key' => $idempotency,
    ];
};

$policy = [
    'id' => $service_endpoint,
    'scope' => $service_scope,
    'security_level' => 'service_signature',
    'route' => $service_route,
];
$service_openapi = (new \MillionDollarScript\V3\Rest\ApiGovernance())->openapi(false);
mds3_assert_same(
    'X-MDS-Signature',
    $service_openapi['components']['securitySchemes']['ServiceSignatureV1']['name'] ?? '',
    'Expected OpenAPI to advertise the v1 service-signature scheme.'
);
mds3_assert_same(
    300,
    $service_openapi['x-mds']['service_signature']['clock_skew_seconds'] ?? 0,
    'Expected OpenAPI to advertise the strict service-signature clock window.'
);
$make_request = static function ($method = 'POST', $route = null, $body = null, $headers = null) use (
    $service_route,
    $service_body,
    $service_headers
) {
    $route = null === $route ? $service_route : (string) $route;
    $body = null === $body ? $service_body : (string) $body;

    return new MDS3_ServiceSignatureTestRequest(
        $method,
        $route,
        $body,
        null === $headers ? $service_headers($method, $route, $body) : $headers
    );
};

$no_verifier = ServiceSignatureRegistry::authorize($make_request(), $policy);
mds3_assert_same(true, is_wp_error($no_verifier), 'Expected service-signature requests without a verifier to fail closed.');

$missing_headers = ServiceSignatureRegistry::authorize($make_request('POST', null, null, []), $policy);
mds3_assert_same(true, is_wp_error($missing_headers), 'Expected missing service-signature headers to be denied.');

$verifier_calls = 0;
$registered = ApiAccess::register_service_signature_verifier(
    $service_endpoint,
    $service_scope,
    $service_id,
    static function (ServiceSignatureRequest $signature) use (&$verifier_calls, $service_secret) {
        $verifier_calls++;
        return hash_equals(
            hash_hmac('sha256', $signature->canonical_string(), $service_secret),
            $signature->signature()
        );
    }
);
mds3_assert_same(true, $registered, 'Expected the public API to register a valid exact service verifier.');

$valid_request = $make_request();
mds3_assert_same(true, ServiceSignatureRegistry::authorize($valid_request, $policy), 'Expected a valid signed request to be authorized.');
$identity = ServiceSignatureRegistry::identity($valid_request);
mds3_assert_same('service_signature', $identity['authentication'] ?? '', 'Expected a valid signature to attach only a service identity.');
mds3_assert_same($service_id, $identity['service_id'] ?? '', 'Expected the service identity to retain the verified opaque service ID.');

$unknown_headers = $service_headers('POST', null, null, null, null, 'fixture_service_unknown');
$unknown_service = ServiceSignatureRegistry::authorize($make_request('POST', null, null, $unknown_headers), $policy);
mds3_assert_same(true, is_wp_error($unknown_service), 'Expected an unknown service ID to be denied.');

$bad_signature_headers = $service_headers();
$bad_signature_headers['X-MDS-Signature'] = str_repeat('0', 64);
$bad_signature = ServiceSignatureRegistry::authorize($make_request('POST', null, null, $bad_signature_headers), $policy);
mds3_assert_same(true, is_wp_error($bad_signature), 'Expected a bad service signature to be denied.');

foreach ([$service_timestamp - 301, $service_timestamp + 301] as $outside_window) {
    $stale = ServiceSignatureRegistry::authorize(
        $make_request('POST', null, null, $service_headers('POST', null, null, $outside_window)),
        $policy
    );
    mds3_assert_same(true, is_wp_error($stale), 'Expected service timestamps outside the strict clock window to be denied.');
}

$changed_body = ServiceSignatureRegistry::authorize(
    $make_request('POST', null, '{"fixture":false}', $service_headers()),
    $policy
);
mds3_assert_same(true, is_wp_error($changed_body), 'Expected a body changed after signing to be denied.');

$changed_route = ServiceSignatureRegistry::authorize(
    $make_request('POST', $service_route . '/changed', null, $service_headers()),
    $policy
);
mds3_assert_same(true, is_wp_error($changed_route), 'Expected a route changed after signing to be denied.');

$changed_method = ServiceSignatureRegistry::authorize(
    $make_request('PUT', null, null, $service_headers()),
    $policy
);
mds3_assert_same(true, is_wp_error($changed_method), 'Expected a method changed after signing to be denied.');

$scope_mismatch = ServiceSignatureRegistry::authorize(
    $make_request(),
    array_merge($policy, ['scope' => 'fixture.other.write'])
);
mds3_assert_same(true, is_wp_error($scope_mismatch), 'Expected a verifier scope mismatch to be denied.');

$exception_service = 'fixture_service_exception';
ApiAccess::register_service_signature_verifier(
    $service_endpoint,
    $service_scope,
    $exception_service,
    static function () {
        throw new RuntimeException('secret-bearing exception text must not escape');
    }
);
$exception_result = ServiceSignatureRegistry::authorize(
    $make_request('POST', null, null, $service_headers('POST', null, null, null, null, $exception_service)),
    $policy
);
mds3_assert_same('mds_service_signature_invalid', $exception_result->get_error_code(), 'Expected verifier exceptions to collapse to the generic denial.');
mds3_assert_same(false, str_contains($exception_result->get_error_message(), 'secret-bearing'), 'Expected verifier exception details not to leak.');

$malformed_service = 'fixture_service_malformed';
ApiAccess::register_service_signature_verifier(
    $service_endpoint,
    $service_scope,
    $malformed_service,
    static function () {
        return 1;
    }
);
$malformed_result = ServiceSignatureRegistry::authorize(
    $make_request('POST', null, null, $service_headers('POST', null, null, null, null, $malformed_service)),
    $policy
);
mds3_assert_same(true, is_wp_error($malformed_result), 'Expected a truthy but non-boolean verifier result to fail closed.');
mds3_assert_same(4, $verifier_calls, 'Expected only matching requests with structurally valid envelopes to invoke the verifier.');

if (null === $previous_host) {
    unset($_SERVER['HTTP_HOST']);
} else {
    $_SERVER['HTTP_HOST'] = $previous_host;
}
if (null === $previous_remote_addr) {
    unset($_SERVER['REMOTE_ADDR']);
} else {
    $_SERVER['REMOTE_ADDR'] = $previous_remote_addr;
}
