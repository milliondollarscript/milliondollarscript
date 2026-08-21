<?php
/**
 * API governance REST endpoints and payload helpers.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest\Concerns;

use MillionDollarScript\V3\Rest\ApiGovernance;
use MillionDollarScript\V3\Rest\ApiKeyRepository;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesApiGovernanceEndpoints {

    public function api_discovery() {
        return (new ApiGovernance())->discovery();
    }

    public function api_openapi() {
        return (new ApiGovernance())->openapi();
    }

    public function api_keys() {
        return array_map([$this, 'api_key_payload'], (new ApiKeyRepository())->active());
    }

    public function create_api_key(\WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $scopes = is_array($params['scopes'] ?? null) ? $params['scopes'] : preg_split('/[\s,]+/', (string) ($params['scopes'] ?? ''));
        $created = (new ApiKeyRepository())->create(
            sanitize_text_field($params['name'] ?? ''),
            is_array($scopes) ? $scopes : [],
            absint($params['rate_limit_per_hour'] ?? 120)
        );

        if (is_wp_error($created)) {
            return $created;
        }

        return [
            'key' => (string) ($created['key'] ?? ''),
            'record' => $this->api_key_payload(is_array($created['record'] ?? null) ? $created['record'] : []),
        ];
    }

    public function revoke_api_key(\WP_REST_Request $request) {
        return ['revoked' => (new ApiKeyRepository())->revoke(absint($request['id']))];
    }

    public function rotate_api_key(\WP_REST_Request $request) {
        $rotated = (new ApiKeyRepository())->rotate(absint($request['id']));
        if (is_wp_error($rotated)) {
            return $rotated;
        }

        return [
            'key' => (string) ($rotated['key'] ?? ''),
            'record' => $this->api_key_payload(is_array($rotated['record'] ?? null) ? $rotated['record'] : []),
        ];
    }

    public function api_audit_logs() {
        return array_map([$this, 'api_audit_log_payload'], (new ApiKeyRepository())->recent_audit_logs(100));
    }

    private function api_key_payload(array $row) {
        return [
            'id' => absint($row['id'] ?? 0),
            'name' => sanitize_text_field((string) ($row['name'] ?? '')),
            'key_prefix' => sanitize_text_field((string) ($row['key_prefix'] ?? '')),
            'scopes' => array_values(array_map('sanitize_text_field', (array) ($row['scopes'] ?? []))),
            'status' => sanitize_key((string) ($row['status'] ?? '')),
            'rate_limit_per_hour' => absint($row['rate_limit_per_hour'] ?? 0),
            'last_used_at' => sanitize_text_field((string) ($row['last_used_at'] ?? '')),
            'created_at' => sanitize_text_field((string) ($row['created_at'] ?? '')),
            'revoked_at' => sanitize_text_field((string) ($row['revoked_at'] ?? '')),
        ];
    }

    private function api_audit_log_payload(array $row) {
        return [
            'id' => absint($row['id'] ?? 0),
            'key_id' => absint($row['key_id'] ?? 0),
            'auth_type' => sanitize_key((string) ($row['auth_type'] ?? 'api_key')),
            'endpoint_id' => sanitize_key((string) ($row['endpoint_id'] ?? '')),
            'actor_ref' => sanitize_text_field((string) ($row['actor_ref'] ?? '')),
            'route' => sanitize_text_field((string) ($row['route'] ?? '')),
            'method' => sanitize_key((string) ($row['method'] ?? '')),
            'scope' => sanitize_text_field((string) ($row['scope'] ?? '')),
            'decision' => sanitize_key((string) ($row['decision'] ?? '')),
            'reason_code' => sanitize_key((string) ($row['reason_code'] ?? '')),
            'message' => sanitize_text_field((string) ($row['message'] ?? '')),
            'created_at' => sanitize_text_field((string) ($row['created_at'] ?? '')),
        ];
    }
}
