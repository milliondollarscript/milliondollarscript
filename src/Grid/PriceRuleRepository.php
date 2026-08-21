<?php
/**
 * Grid price-rule repository.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class PriceRuleRepository {

    public function for_grid($grid_id, array $statuses = []) {
        global $wpdb;

        $sql = 'SELECT * FROM ' . DB::ident(DB::table('price_rules')) . ' WHERE grid_id = %d';
        $args = [absint($grid_id)];
        if ($statuses) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $sql .= " AND status IN ({$placeholders})";
            foreach ($statuses as $status) {
                $args[] = sanitize_key($status);
            }
        }
        $sql .= ' ORDER BY id ASC';

        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

        return array_map([$this, 'normalize'], is_array($rows) ? $rows : []);
    }

    public function active_for_grid($grid_id) {
        return $this->for_grid($grid_id, ['active']);
    }

    public function find($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('price_rules')) . ' WHERE id = %d', absint($id)),
            ARRAY_A
        );

        return is_array($row) ? $this->normalize($row) : null;
    }

    public function save(array $data) {
        global $wpdb;

        $grid_id = absint($data['grid_id'] ?? 0);
        if (!$grid_id) {
            return new \WP_Error('mds3_price_rule_grid_required', __('Grid is required.', 'million-dollar-script'));
        }

        $id = absint($data['id'] ?? 0);
        $status = sanitize_key($data['status'] ?? 'active');
        if (!in_array($status, ['active', 'paused', 'archived'], true)) {
            $status = 'active';
        }

        $payload = [
            'grid_id' => $grid_id,
            'row_from' => $this->nullable_absint($data['row_from'] ?? null),
            'row_to' => $this->nullable_absint($data['row_to'] ?? null),
            'col_from' => $this->nullable_absint($data['col_from'] ?? null),
            'col_to' => $this->nullable_absint($data['col_to'] ?? null),
            'block_id_from' => $this->nullable_absint($data['block_id_from'] ?? null),
            'block_id_to' => $this->nullable_absint($data['block_id_to'] ?? null),
            'price' => (float) ($data['price'] ?? 0),
            'currency' => Currency::code($data['currency'] ?? Currency::current_code()),
            'color' => $this->sanitize_color((string) ($data['color'] ?? '')),
            'status' => $status,
            'metadata' => $this->metadata_json($data['metadata'] ?? []),
            'updated_at' => current_time('mysql', true),
        ];

        $payload = $this->normalize_bounds_payload($payload);

        if ($id && $this->find($id)) {
            $result = $wpdb->update(DB::table('price_rules'), $payload, ['id' => $id]);

            return false === $result ? new \WP_Error('mds3_price_rule_update_failed', $wpdb->last_error) : $this->find($id);
        }

        $payload['created_at'] = current_time('mysql', true);
        $result = $wpdb->insert(DB::table('price_rules'), $payload);

        return false === $result ? new \WP_Error('mds3_price_rule_create_failed', $wpdb->last_error) : $this->find($wpdb->insert_id);
    }

    public function archive($id) {
        global $wpdb;

        $result = $wpdb->update(
            DB::table('price_rules'),
            [
                'status' => 'archived',
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => absint($id)]
        );

        return false !== $result;
    }

    public function effective_price(Grid $grid, array $block, ?array $rules = null) {
        if (isset($block['price_override']) && '' !== (string) $block['price_override']) {
            return [
                'price' => (float) $block['price_override'],
                'currency' => Currency::code($grid->get('currency', 'USD')),
                'rule' => null,
                'source' => 'block_override',
            ];
        }

        $fallback = (float) $grid->get('price_per_block', 0);
        $coordinate = $grid->geometry()->coordinate_from_pixel(absint($block['x'] ?? 0), absint($block['y'] ?? 0));
        if (!$coordinate) {
            return [
                'price' => $fallback,
                'currency' => Currency::code($grid->get('currency', 'USD')),
                'rule' => null,
                'source' => 'grid',
            ];
        }

        $columns = $grid->geometry()->columns();
        $block_index = (int) $coordinate['row'] * $columns + (int) $coordinate['col'];
        $best = null;
        $best_score = -1;

        foreach (null === $rules ? $this->active_for_grid($grid->id()) : $rules as $rule) {
            $score = $this->match_score($rule, (int) $coordinate['row'], (int) $coordinate['col'], $block_index);
            if ($score < 0) {
                continue;
            }

            if ($score > $best_score || ($score === $best_score && absint($rule['id'] ?? 0) > absint($best['id'] ?? 0))) {
                $best = $rule;
                $best_score = $score;
            }
        }

        if ($best) {
            return [
                'price' => (float) ($best['price'] ?? $fallback),
                'currency' => Currency::code($best['currency'] ?: $grid->get('currency', 'USD')),
                'rule' => $best,
                'source' => 'price_rule',
            ];
        }

        return [
            'price' => $fallback,
            'currency' => Currency::code($grid->get('currency', 'USD')),
            'rule' => null,
            'source' => 'grid',
        ];
    }

    private function match_score(array $rule, $row, $col, $block_index) {
        $has_coordinate_bounds = null !== $rule['row_from']
            || null !== $rule['row_to']
            || null !== $rule['col_from']
            || null !== $rule['col_to'];

        if ($has_coordinate_bounds) {
            if (!$this->in_range($row, $rule['row_from'], $rule['row_to']) || !$this->in_range($col, $rule['col_from'], $rule['col_to'])) {
                return -1;
            }

            return $this->specificity($rule, ['row_from', 'row_to', 'col_from', 'col_to']);
        }

        if (null !== $rule['block_id_from'] || null !== $rule['block_id_to']) {
            if (!$this->in_range($block_index, $rule['block_id_from'], $rule['block_id_to'])) {
                return -1;
            }

            return $this->specificity($rule, ['block_id_from', 'block_id_to']);
        }

        return 0;
    }

    private function in_range($value, $from, $to) {
        if (null !== $from && $value < (int) $from) {
            return false;
        }

        if (null !== $to && $value > (int) $to) {
            return false;
        }

        return true;
    }

    private function specificity(array $rule, array $keys) {
        $score = 0;
        foreach ($keys as $key) {
            if (null !== $rule[$key]) {
                $score++;
            }
        }

        return $score;
    }

    private function normalize(array $row) {
        foreach (['id', 'grid_id'] as $key) {
            $row[$key] = absint($row[$key] ?? 0);
        }
        foreach (['row_from', 'row_to', 'col_from', 'col_to', 'block_id_from', 'block_id_to'] as $key) {
            $row[$key] = isset($row[$key]) && '' !== (string) $row[$key] ? absint($row[$key]) : null;
        }
        $row = $this->normalize_bounds_payload($row);
        $row['price'] = (float) ($row['price'] ?? 0);
        $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
        $row['metadata'] = is_array($metadata) ? $metadata : [];

        return $row;
    }

    private function normalize_bounds_payload(array $row) {
        foreach ([['row_from', 'row_to'], ['col_from', 'col_to'], ['block_id_from', 'block_id_to']] as $pair) {
            [$from, $to] = $pair;
            if (null !== $row[$from] && null !== $row[$to] && $row[$from] > $row[$to]) {
                $tmp = $row[$from];
                $row[$from] = $row[$to];
                $row[$to] = $tmp;
            }
        }

        return $row;
    }

    private function nullable_absint($value) {
        return (null === $value || '' === (string) $value) ? null : absint($value);
    }

    private function metadata_json($metadata) {
        if (is_array($metadata)) {
            return wp_json_encode($metadata);
        }

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            if (is_array($decoded)) {
                return wp_json_encode($decoded);
            }
        }

        return wp_json_encode([]);
    }

    private function sanitize_color($value) {
        if (function_exists('sanitize_hex_color')) {
            return sanitize_hex_color($value) ?: '';
        }

        $value = trim((string) $value);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : '';
    }
}
