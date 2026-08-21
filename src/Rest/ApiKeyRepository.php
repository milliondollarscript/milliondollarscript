<?php
/**
 * Scoped API key storage.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest;

use MillionDollarScript\V3\Extensions\ExtensionRuntime;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class ApiKeyRepository {

    public function create($name, array $scopes, $rate_limit_per_hour = 120) {
        global $wpdb;

        $plain_key = $this->generate_key();
        $now = $this->now();
        $scopes = $this->sanitize_scopes($scopes);
        if (!$scopes) {
            $scopes = $this->default_scopes();
        }

        $result = $wpdb->insert(DB::table('api_keys'), [
            'key_prefix' => substr($plain_key, 0, 32),
            'key_hash' => $this->hash_key($plain_key),
            'name' => sanitize_text_field($name ?: __('Automation key', 'million-dollar-script')),
            'scopes' => wp_json_encode($scopes),
            'status' => 'active',
            'rate_limit_per_hour' => max(0, absint($rate_limit_per_hour)),
            'last_used_at' => null,
            'created_by' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
            'revoked_at' => null,
        ], ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s']);

        if (false === $result) {
            return new \WP_Error('mds3_api_key_create_failed', $wpdb->last_error ?: __('API key could not be created.', 'million-dollar-script'));
        }

        return [
            'key' => $plain_key,
            'record' => $this->find(absint($wpdb->insert_id)),
        ];
    }

    public function active($limit = 100) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::ident(DB::table('api_keys')) . ' WHERE status = %s ORDER BY created_at DESC LIMIT %d',
                'active',
                max(1, absint($limit))
            ),
            ARRAY_A
        );

        return array_map([$this, 'normalize_row'], is_array($rows) ? $rows : []);
    }

    public function recent_audit_logs($limit = 50) {
        return (new ApiAuditRepository())->recent($limit);
    }

    public function find($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('api_keys')) . ' WHERE id = %d', absint($id)),
            ARRAY_A
        );

        return $row ? $this->normalize_row($row) : null;
    }

    public function revoke($id) {
        global $wpdb;

        $result = $wpdb->update(DB::table('api_keys'), [
            'status' => 'revoked',
            'updated_at' => $this->now(),
            'revoked_at' => $this->now(),
        ], ['id' => absint($id)], ['%s', '%s', '%s'], ['%d']);

        return false !== $result;
    }

    public function rotate($id) {
        global $wpdb;

        $id = absint($id);
        $existing = $this->find($id);
        if (!$existing || 'active' !== sanitize_key((string) ($existing['status'] ?? ''))) {
            return new \WP_Error('mds3_api_key_not_found', __('Active API key not found.', 'million-dollar-script'), ['status' => 404]);
        }

        $plain_key = $this->generate_key();
        $result = $wpdb->update(DB::table('api_keys'), [
            'key_prefix' => substr($plain_key, 0, 32),
            'key_hash' => $this->hash_key($plain_key),
            'last_used_at' => null,
            'updated_at' => $this->now(),
        ], ['id' => $id], ['%s', '%s', '%s', '%s'], ['%d']);

        if (false === $result) {
            return new \WP_Error('mds3_api_key_rotate_failed', $wpdb->last_error ?: __('API key could not be rotated.', 'million-dollar-script'));
        }

        if (function_exists('delete_transient')) {
            delete_transient('mds3_api_rate_' . $id . '_' . gmdate('YmdH'));
        }

        return [
            'key' => $plain_key,
            'record' => $this->find($id),
        ];
    }

    public function authenticate($plain_key, $required_scope, $request = null) {
        global $wpdb;

        $plain_key = trim((string) $plain_key);
        if ('' === $plain_key) {
            return new \WP_Error('mds3_api_key_missing', __('API key is required.', 'million-dollar-script'), ['status' => 401]);
        }

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('api_keys')) . ' WHERE key_hash = %s AND status = %s', $this->hash_key($plain_key), 'active'),
            ARRAY_A
        );
        if (!$row) {
            $this->audit(0, $request, $required_scope, 'denied', __('Unknown or revoked API key.', 'million-dollar-script'));

            return new \WP_Error('mds3_api_key_invalid', __('API key is invalid or revoked.', 'million-dollar-script'), ['status' => 401]);
        }

        $row = $this->normalize_row($row);
        if (!$this->scope_allowed($required_scope, $row['scopes'])) {
            $this->audit(absint($row['id']), $request, $required_scope, 'denied', __('API key scope does not permit this action.', 'million-dollar-script'));

            return new \WP_Error('mds3_api_key_scope_denied', __('API key scope does not permit this action.', 'million-dollar-script'), ['status' => 403]);
        }

        if (!$this->rate_limit_ok($row)) {
            $this->audit(absint($row['id']), $request, $required_scope, 'denied', __('API key rate limit exceeded.', 'million-dollar-script'));

            return new \WP_Error('mds3_api_key_rate_limited', __('API key rate limit exceeded.', 'million-dollar-script'), ['status' => 429]);
        }

        $wpdb->update(DB::table('api_keys'), [
            'last_used_at' => $this->now(),
            'updated_at' => $this->now(),
        ], ['id' => absint($row['id'])], ['%s', '%s'], ['%d']);
        $this->audit(absint($row['id']), $request, $required_scope, 'allowed', '');

        return $row;
    }

    public function audit($key_id, $request, $scope, $decision, $message = '') {
        (new ApiAuditRepository())->record([
            'key_id' => absint($key_id),
            'auth_type' => 'api_key',
            'actor' => (string) absint($key_id),
            'request' => $request,
            'scope' => (string) $scope,
            'decision' => (string) $decision,
            'reason_code' => 'allowed' === sanitize_key((string) $decision) ? 'verified' : 'api_key_denied',
            'message' => (string) $message,
        ]);
    }

    public function sanitize_scopes(array $scopes) {
        $clean = [];
        foreach ($scopes as $scope) {
            if (!is_scalar($scope)) {
                continue;
            }

            $scope = strtolower(trim((string) $scope));
            $scope = preg_replace('/[^a-z0-9*._:-]/', '', $scope);
            if ($scope) {
                $clean[] = $scope;
            }
        }

        return array_values(array_unique($clean));
    }

    private function default_scopes() {
        $scopes = ['core.extension.read'];
        if ((new ExtensionRuntime())->is_enabled('mds-grid')) {
            array_unshift($scopes, 'core.grid.read');
        }

        return $scopes;
    }

    private function normalize_row(array $row) {
        $scopes = json_decode((string) ($row['scopes'] ?? '[]'), true);
        $row['id'] = absint($row['id'] ?? 0);
        $row['created_by'] = absint($row['created_by'] ?? 0);
        $row['rate_limit_per_hour'] = absint($row['rate_limit_per_hour'] ?? 0);
        $row['scopes'] = is_array($scopes) ? $this->sanitize_scopes($scopes) : [];

        return $row;
    }

    private function generate_key() {
        $random = function_exists('wp_generate_password') ? wp_generate_password(42, false, false) : bin2hex(random_bytes(24));

        return 'milliondollarscript_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $random));
    }

    private function hash_key($plain_key) {
        $secret = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_KEY') ? AUTH_KEY : 'million-dollar-script');

        return hash_hmac('sha256', (string) $plain_key, $secret);
    }

    private function hash_fingerprint($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        return substr(hash_hmac('sha256', $value, $this->hash_key('fingerprint')), 0, 32);
    }

    private function scope_allowed($required_scope, array $scopes) {
        $required_scope = strtolower(trim((string) $required_scope));
        if (in_array('*', $scopes, true) || in_array($required_scope, $scopes, true)) {
            return true;
        }

        foreach ($scopes as $scope) {
            if ('*' !== substr($scope, -1)) {
                continue;
            }

            $prefix = rtrim($scope, '*');
            if (0 === strpos($required_scope, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function rate_limit_ok(array $row) {
        $limit = absint($row['rate_limit_per_hour'] ?? 0);
        if (!$limit || !function_exists('get_transient') || !function_exists('set_transient')) {
            return true;
        }

        $key = 'mds3_api_rate_' . absint($row['id']) . '_' . gmdate('YmdH');
        $count = absint(get_transient($key));
        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, HOUR_IN_SECONDS + 60);

        return true;
    }

    private function remote_addr() {
        $addr = sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')));

        return preg_replace('/[^a-fA-F0-9:.]/', '', $addr);
    }

    private function now() {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
