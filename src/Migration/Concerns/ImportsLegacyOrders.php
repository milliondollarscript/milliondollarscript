<?php
/**
 * MDS2 order import step.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Orders\OrderLifecycleFields;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

trait ImportsLegacyOrders {

    private function import_orders() {
        $table = $this->source->table('orders');
        if (!DB::table_exists($table)) {
            return 0;
        }

        $count = 0;
        foreach ($this->legacy_rows('orders', ['order_id']) as $row) {
            if ($this->import_order_row($row)) {
                $count++;
            }
        }

        if ($count) {
            OrderRepository::invalidate_overview_cache();
        }

        return $count;
    }

    private function import_order_row(array $row) {
        global $wpdb;

        $now = current_time('mysql', true);
        $legacy_id = absint($row['order_id'] ?? 0);
        if (!$legacy_id) {
            $this->warnings[] = 'Skipped an order row without an order_id.';
            $this->record_migration_skip('order', '', __('The source row has no order ID.', 'million-dollar-script'));
            return 0;
        }

        $order_key = 'mds2-' . substr(md5($this->source->source_prefix()), 0, 8) . '-' . $legacy_id;
        $user_id = absint($row['user_id'] ?? 0);
        $user = $user_id ? get_userdata($user_id) : false;
        $created = $this->valid_mysql_date($row['order_date'] ?? '') ?: $now;
        $metadata = $this->legacy_order_metadata($row);
        $payload = [
            'order_key' => $order_key,
            'user_id' => $user_id ?: null,
            'email' => $user ? sanitize_email($user->user_email) : '',
            'status' => self::order_status($row['status'] ?? ''),
            'currency' => strtoupper(substr(sanitize_text_field((string) ($row['currency'] ?? 'USD')), 0, 3)),
            'subtotal' => (float) ($row['price'] ?? 0),
            'total' => (float) ($row['price'] ?? 0),
            'commerce_provider' => 'legacy_mds2',
            'commerce_order_id' => (string) $legacy_id,
            'expires_at' => OrderLifecycleFields::expires_at($metadata),
            'metadata' => wp_json_encode($metadata),
            'updated_at' => $now,
        ];

        $target_id = $this->map->target_id($this->source->source_prefix(), 'order', $legacy_id, 'order');
        if (!$target_id) {
            $target_id = absint($wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . DB::ident(DB::table('orders')) . ' WHERE order_key = %s LIMIT 1',
                $order_key
            )));
            if ($target_id) {
                $this->record_migration_repair('order', $legacy_id, __('Recovered an existing order whose migration-map row was missing.', 'million-dollar-script'));
            }
        }
        if ($target_id && $this->target_exists('orders', $target_id)) {
            $wpdb->update(DB::table('orders'), $payload, ['id' => $target_id]);
        } else {
            $payload['created_at'] = $created;
            $wpdb->insert(DB::table('orders'), $payload);
            $target_id = absint($wpdb->insert_id);
        }

        if ($target_id) {
            $this->map->remember($this->source->source_prefix(), 'order', $legacy_id, 'order', $target_id, ['legacy_grid_id' => absint($row['banner_id'] ?? 0)]);
        }

        return $target_id;
    }
}
