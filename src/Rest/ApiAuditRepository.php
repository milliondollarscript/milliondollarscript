<?php
/**
 * Privacy-safe REST authorization audit storage.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare().

final class ApiAuditRepository {

    public function recent($limit = 50): array {
        global $wpdb;

        if (!is_object($wpdb) || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::ident(DB::table('api_audit_logs')) . ' ORDER BY created_at DESC LIMIT %d',
                max(1, absint($limit))
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function record(array $event): void {
        global $wpdb;

        if (!is_object($wpdb) || !method_exists($wpdb, 'insert')) {
            return;
        }

        $request = $event['request'] ?? null;
        $route = is_object($request) && method_exists($request, 'get_route') ? (string) $request->get_route() : '';
        $method = is_object($request) && method_exists($request, 'get_method') ? (string) $request->get_method() : '';
        $actor = (string) ($event['actor'] ?? '');

        $wpdb->insert(DB::table('api_audit_logs'), [
            'key_id' => absint($event['key_id'] ?? 0),
            'auth_type' => sanitize_key((string) ($event['auth_type'] ?? 'unknown')),
            'endpoint_id' => sanitize_key((string) ($event['endpoint_id'] ?? '')),
            'actor_ref' => $this->hash_fingerprint($actor),
            'route' => sanitize_text_field($route),
            'method' => sanitize_key($method),
            'scope' => sanitize_text_field((string) ($event['scope'] ?? '')),
            'decision' => sanitize_key((string) ($event['decision'] ?? 'denied')),
            'reason_code' => sanitize_key((string) ($event['reason_code'] ?? '')),
            'ip_hash' => $this->hash_fingerprint($this->remote_addr()),
            'user_agent_hash' => $this->hash_fingerprint(
                sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')))
            ),
            'message' => sanitize_text_field((string) ($event['message'] ?? '')),
            'created_at' => $this->now(),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
    }

    public function actor_reference($actor): string {
        return $this->hash_fingerprint((string) $actor);
    }

    private function hash_fingerprint($value): string {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        $secret = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_KEY') ? AUTH_KEY : 'million-dollar-script');

        return substr(hash_hmac('sha256', $value, hash('sha256', (string) $secret)), 0, 32);
    }

    private function remote_addr(): string {
        $addr = sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')));

        return (string) preg_replace('/[^a-fA-F0-9:.]/', '', $addr);
    }

    private function now(): string {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
