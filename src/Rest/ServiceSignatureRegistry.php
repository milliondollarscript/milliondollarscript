<?php
/**
 * Fail-closed service-signature verifier registry and dispatcher.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest;

use MillionDollarScript\Core\ServiceSignatureRequest;

if (!defined('ABSPATH')) {
    exit;
}

final class ServiceSignatureRegistry {

    private const CLOCK_SKEW_SECONDS = 300;
    private const FAILURE_LIMIT = 120;
    private const FAILURE_WINDOW_SECONDS = 300;

    private static array $verifiers = [];
    private static ?\WeakMap $identities = null;

    /**
     * @return bool|\WP_Error
     */
    public static function register($endpoint_id, $scope, $service_id, callable $verifier, array $versions = ['v1']) {
        $endpoint_id = sanitize_key((string) $endpoint_id);
        $scope = strtolower(trim((string) $scope));
        $service_id = trim((string) $service_id);
        $versions = array_values(array_unique(array_map('sanitize_key', $versions)));

        if (
            '' === $endpoint_id
            || !preg_match('/^[a-z0-9*._:-]+$/', $scope)
            || !preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $service_id)
            || ['v1'] !== $versions
        ) {
            return new \WP_Error(
                'mds_service_signature_registration_invalid',
                __('The service-signature verifier registration is invalid.', 'million-dollar-script')
            );
        }

        $key = self::key($endpoint_id, $scope, $service_id, 'v1');
        if (isset(self::$verifiers[$key])) {
            return new \WP_Error(
                'mds_service_signature_registration_conflict',
                __('A verifier is already registered for this endpoint, scope, service, and version.', 'million-dollar-script')
            );
        }

        self::$verifiers[$key] = $verifier;

        return true;
    }

    public static function identity($request): ?array {
        if (!is_object($request) || null === self::$identities || !isset(self::$identities[$request])) {
            return null;
        }

        return self::$identities[$request];
    }

    public static function error($category = 'invalid', $retry_after = 0): \WP_Error {
        $category = sanitize_key((string) $category);
        if ('rate_limited' === $category) {
            return new \WP_Error(
                'mds_service_signature_rate_limited',
                __('Too many signed service requests were received. Try again later.', 'million-dollar-script'),
                ['status' => 429, 'retry_after' => max(1, absint($retry_after))]
            );
        }
        if ('https_required' === $category) {
            return new \WP_Error(
                'mds_service_signature_https_required',
                __('Signed service requests require HTTPS.', 'million-dollar-script'),
                ['status' => 403]
            );
        }
        if ('unavailable' === $category) {
            return new \WP_Error(
                'mds_service_signature_unavailable',
                __('Signed service authentication is temporarily unavailable.', 'million-dollar-script'),
                ['status' => 503]
            );
        }

        return new \WP_Error(
            'mds_service_signature_invalid',
            __('The signed service request could not be authenticated.', 'million-dollar-script'),
            ['status' => 401]
        );
    }

    /**
     * @return bool|\WP_Error
     */
    public static function authorize($request, array $policy) {
        $endpoint_id = sanitize_key((string) ($policy['id'] ?? ''));
        $scope = strtolower(trim((string) ($policy['scope'] ?? '')));

        if (!self::transport_allowed()) {
            self::audit($request, $policy, '', 'denied', 'https_required');
            return self::error('https_required');
        }

        if (self::attempt_rate_limited($endpoint_id)) {
            self::audit($request, $policy, '', 'denied', 'dispatcher_rate_limited');
            return self::error('rate_limited', self::FAILURE_WINDOW_SECONDS);
        }

        $parsed = self::parse_request($request, $endpoint_id, $scope);
        if (is_wp_error($parsed)) {
            self::audit($request, $policy, self::header($request, 'X-MDS-Service-Id'), 'denied', 'invalid_envelope');
            return self::error('invalid');
        }

        $service_id = $parsed->service_id();
        $key = self::key($endpoint_id, $scope, $service_id, $parsed->version());
        if (!isset(self::$verifiers[$key])) {
            self::audit($request, $policy, $service_id, 'denied', 'no_matching_verifier');
            return self::error('invalid');
        }

        try {
            $result = call_user_func(self::$verifiers[$key], $parsed, $request);
        } catch (\Throwable $exception) {
            self::audit($request, $policy, $service_id, 'denied', 'verifier_exception');
            return self::error('invalid');
        }

        if (true === $result) {
            if (null === self::$identities) {
                self::$identities = new \WeakMap();
            }
            self::$identities[$request] = [
                'authentication' => 'service_signature',
                'endpoint_id' => $endpoint_id,
                'scope' => $scope,
                'service_id' => $service_id,
                'signature_version' => $parsed->version(),
            ];
            self::audit($request, $policy, $service_id, 'allowed', 'verified');

            return true;
        }

        self::audit($request, $policy, $service_id, 'denied', is_wp_error($result) ? 'verifier_denied' : 'malformed_verifier_result');

        return is_wp_error($result) ? self::safe_verifier_error($result) : self::error('invalid');
    }

    public static function audit_administrator($request, array $policy): void {
        (new ApiAuditRepository())->record([
            'auth_type' => 'wp_admin',
            'endpoint_id' => (string) ($policy['id'] ?? ''),
            'actor' => (string) (function_exists('get_current_user_id') ? get_current_user_id() : 0),
            'request' => $request,
            'scope' => (string) ($policy['scope'] ?? ''),
            'decision' => 'allowed',
            'reason_code' => 'administrator',
        ]);
    }

    private static function parse_request($request, $endpoint_id, $scope) {
        $service_id = self::header($request, 'X-MDS-Service-Id');
        $version = strtolower(self::header($request, 'X-MDS-Signature-Version'));
        $timestamp_raw = self::header($request, 'X-MDS-Timestamp');
        $nonce = self::header($request, 'X-MDS-Nonce');
        $body_sha256 = strtolower(self::header($request, 'X-MDS-Content-SHA256'));
        $signature = strtolower(self::header($request, 'X-MDS-Signature'));
        $idempotency_key = self::header($request, 'X-Idempotency-Key');
        $method = is_object($request) && method_exists($request, 'get_method')
            ? strtoupper((string) $request->get_method())
            : '';
        $route = is_object($request) && method_exists($request, 'get_route')
            ? '/' . ltrim((string) $request->get_route(), '/')
            : '';
        $body = is_object($request) && method_exists($request, 'get_body') ? (string) $request->get_body() : '';

        if (
            !preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $service_id)
            || 'v1' !== $version
            || !preg_match('/^[0-9]{10}$/', $timestamp_raw)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/', $nonce)
            || !preg_match('/^[a-f0-9]{64}$/', $body_sha256)
            || !preg_match('/^[a-f0-9]{64}$/', $signature)
            || !in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], true)
            || 0 !== strpos($route, '/million-dollar-script/v1/')
            || preg_match('/[\x00-\x20\x7F?#]/', $route)
            || ('' !== $idempotency_key && !preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $idempotency_key))
        ) {
            return self::error('invalid');
        }

        $timestamp = (int) $timestamp_raw;
        if (abs(time() - $timestamp) > self::CLOCK_SKEW_SECONDS) {
            return self::error('invalid');
        }

        $calculated_body_hash = hash('sha256', $body);
        if (!hash_equals($calculated_body_hash, $body_sha256)) {
            return self::error('invalid');
        }

        return new ServiceSignatureRequest([
            'endpoint_id' => $endpoint_id,
            'scope' => $scope,
            'service_id' => $service_id,
            'version' => $version,
            'method' => $method,
            'route' => $route,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'body_sha256' => $body_sha256,
            'signature' => $signature,
            'idempotency_key' => $idempotency_key,
        ]);
    }

    private static function safe_verifier_error(\WP_Error $error): \WP_Error {
        $code = (string) $error->get_error_code();
        if ('mds_service_signature_rate_limited' === $code) {
            $data = $error->get_error_data();
            return self::error('rate_limited', absint(is_array($data) ? ($data['retry_after'] ?? 0) : 0));
        }
        if ('mds_service_signature_https_required' === $code) {
            return self::error('https_required');
        }
        if ('mds_service_signature_unavailable' === $code) {
            return self::error('unavailable');
        }

        return self::error('invalid');
    }

    private static function header($request, $name): string {
        if (is_object($request) && method_exists($request, 'get_header')) {
            $value = (string) $request->get_header($name);
            if ('' === $value) {
                $value = (string) $request->get_header(str_replace('-', '_', strtolower($name)));
            }
            return trim($value);
        }

        $server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return trim(sanitize_text_field(wp_unslash((string) ($_SERVER[$server_key] ?? ''))));
    }

    private static function transport_allowed(): bool {
        if (function_exists('is_ssl') && is_ssl()) {
            return true;
        }

        $host_header = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (false !== strpos($host_header, ':')) {
            $host_header = preg_replace('/:\d+$/', '', $host_header);
        }
        $host = trim($host_header, '[]');
        $remote_addr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''), '[]');

        return self::is_loopback_host($host) && self::is_loopback_host($remote_addr);
    }

    private static function is_loopback_host($host): bool {
        $host = strtolower(trim((string) $host, '[]'));

        return 'localhost' === $host
            || '::1' === $host
            || (bool) preg_match('/^127(?:\.[0-9]{1,3}){3}$/', $host);
    }

    private static function attempt_rate_limited($endpoint_id): bool {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            return false;
        }

        $addr = sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
        $secret = function_exists('wp_salt') ? wp_salt('auth') : 'million-dollar-script';
        $key = 'mds3_service_rate_' . substr(hash_hmac('sha256', $endpoint_id . '|' . $addr, (string) $secret), 0, 32);
        $count = absint(get_transient($key));
        if ($count >= self::FAILURE_LIMIT) {
            return true;
        }

        set_transient($key, $count + 1, self::FAILURE_WINDOW_SECONDS);

        return false;
    }

    private static function audit($request, array $policy, $service_id, $decision, $reason_code): void {
        (new ApiAuditRepository())->record([
            'auth_type' => 'service_signature',
            'endpoint_id' => (string) ($policy['id'] ?? ''),
            'actor' => (string) $service_id,
            'request' => $request,
            'scope' => (string) ($policy['scope'] ?? ''),
            'decision' => (string) $decision,
            'reason_code' => (string) $reason_code,
        ]);
    }

    private static function key($endpoint_id, $scope, $service_id, $version): string {
        return hash('sha256', implode("\0", [(string) $endpoint_id, (string) $scope, (string) $service_id, (string) $version]));
    }
}
