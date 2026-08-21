<?php
/**
 * REST API authorization helpers.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest\Concerns;

use MillionDollarScript\V3\Rest\Api;
use MillionDollarScript\V3\Rest\ApiKeyRepository;
use MillionDollarScript\V3\Rest\ServiceSignatureRegistry;

if (!defined('ABSPATH')) {
    exit;
}

trait AuthorizesApiRequests {

    public function authorize($request = null, $scope = 'core.manage', $minimum_security_level = 'api_key_write') {
        $policy = $this->policy_for_request($request, $minimum_security_level, $scope);
        if ('disabled' === $policy['security_level']) {
            return new \WP_Error('mds3_api_endpoint_disabled', __('This API endpoint is disabled by policy.', 'million-dollar-script'), ['status' => 403]);
        }

        if ('public_read' === $policy['security_level']) {
            return true;
        }

        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            if ('service_signature' === $policy['security_level']) {
                ServiceSignatureRegistry::audit_administrator($request, $policy);
            }
            return true;
        }

        if ('public_write_nonce' === $policy['security_level']) {
            if ($this->nonce_valid($request)) {
                return true;
            }

            return new \WP_Error('mds3_api_nonce_required', __('A valid WordPress REST nonce is required for this Million Dollar Script API endpoint.', 'million-dollar-script'), ['status' => 401]);
        }

        if ('service_signature' === $policy['security_level']) {
            return ServiceSignatureRegistry::authorize($request, $policy);
        }

        if (in_array($policy['security_level'], ['wp_capability', 'signed_manage_token'], true)) {
            return new \WP_Error('mds3_api_policy_requires_admin', __('This API endpoint requires an authenticated WordPress administrator by policy.', 'million-dollar-script'), ['status' => 403]);
        }

        $key = $this->key_from_request($request);
        if (!$key) {
            return new \WP_Error('mds3_api_auth_required', __('Authentication is required for this Million Dollar Script API endpoint.', 'million-dollar-script'), ['status' => 401]);
        }

        $required_scope = (string) ($policy['scope'] ?: $scope);
        $authenticated = (new ApiKeyRepository())->authenticate($key, $required_scope, $request);

        return is_wp_error($authenticated) ? $authenticated : true;
    }

    private function policy_for_request($request, $fallback_level, $fallback_scope = '') {
        $route = is_object($request) && method_exists($request, 'get_route') ? (string) $request->get_route() : '';
        $method = is_object($request) && method_exists($request, 'get_method') ? (string) $request->get_method() : '';

        foreach ($this->effective_manifest() as $endpoint) {
            if (!$this->matches_route($route, (string) ($endpoint['route'] ?? ''))) {
                continue;
            }
            if ($method && !in_array(strtoupper($method), array_map('strtoupper', (array) ($endpoint['methods'] ?? [])), true)) {
                continue;
            }

            return [
                'id' => sanitize_key((string) ($endpoint['id'] ?? '')),
                'security_level' => (string) ($endpoint['security_level'] ?? $fallback_level),
                'scope' => (string) ($endpoint['scope'] ?? ''),
                'route' => (string) ($endpoint['route'] ?? ''),
            ];
        }

        return [
            'id' => '',
            'security_level' => $this->security_level($fallback_level),
            'scope' => strtolower(trim((string) $fallback_scope)),
            'route' => '',
        ];
    }

    private function key_from_request($request) {
        if (is_object($request) && method_exists($request, 'get_header')) {
            $header = (string) $request->get_header('x_million_dollar_script_api_key');
            if ($header) {
                return $header;
            }

            $auth = (string) $request->get_header('authorization');
            if (0 === stripos($auth, 'Bearer ')) {
                return trim(substr($auth, 7));
            }
        }

        $header = sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_X_MILLION_DOLLAR_SCRIPT_API_KEY'] ?? '')));
        if ($header) {
            return $header;
        }

        $auth = sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '')));

        return 0 === stripos($auth, 'Bearer ') ? trim(substr($auth, 7)) : '';
    }

    private function nonce_valid($request) {
        if (!function_exists('wp_verify_nonce')) {
            return false;
        }

        $nonce = '';
        if (is_object($request) && method_exists($request, 'get_header')) {
            $nonce = (string) $request->get_header('x_wp_nonce');
            if (!$nonce) {
                $nonce = (string) $request->get_header('x-wp-nonce');
            }
        }

        if (!$nonce) {
            $nonce = sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_X_WP_NONCE'] ?? '')));
        }

        return $nonce && (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    private function matches_route($route, $pattern) {
        $route = $this->canonical_route($route);
        $pattern = '/' . ltrim((string) $pattern, '/');
        $pattern = preg_replace('/\(\?P<[^>]+>\\\\d\+\)/', '[0-9]+', $pattern);
        $pattern = str_replace('\\d+', '[0-9]+', $pattern);
        $pattern = str_replace('*', '.*', $pattern);
        $regex = '#^' . $pattern . '$#';

        return (bool) preg_match($regex, $route);
    }

    private function canonical_route($route): string {
        return '/' . ltrim((string) $route, '/');
    }
}
