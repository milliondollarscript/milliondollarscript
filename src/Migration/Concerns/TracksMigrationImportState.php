<?php
/**
 * Migration run, verification, and metadata helpers.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait TracksMigrationImportState {

    private function legacy_rows($table_suffix, array $order_columns, $batch_size = 500) {
        $columns = $this->legacy_order_columns($order_columns);
        if (!$columns) {
            return;
        }

        $batch_size = max(1, absint($batch_size));
        $last = [];

        do {
            $batch = $this->legacy_batch($table_suffix, $columns, $last, $batch_size);
            if (empty($batch['rows'])) {
                break;
            }

            foreach ($batch['rows'] as $row) {
                yield $row;
            }

            $last = is_array($batch['cursor'] ?? null) ? $batch['cursor'] : [];
        } while (!empty($batch['has_more']));
    }

    private function legacy_batch($table_suffix, array $order_columns, array $last = [], $batch_size = 100) {
        global $wpdb;

        $table = $this->source->table($table_suffix);
        if (!DB::table_exists($table)) {
            return [
                'cursor' => [],
                'has_more' => false,
                'rows' => [],
            ];
        }

        $columns = $this->legacy_order_columns($order_columns);
        if (!$columns) {
            return [
                'cursor' => [],
                'has_more' => false,
                'rows' => [],
            ];
        }

        $batch_size = max(1, absint($batch_size));
        $last = $this->legacy_cursor_for_columns($columns, $last);
        $args = [];
        $where = $this->legacy_keyset_where($columns, $last, $args);
        $order_by = implode(', ', array_map(static function ($column) {
            return DB::ident($column) . ' ASC';
        }, $columns));
        $sql = 'SELECT * FROM ' . DB::ident($table) . $where . ' ORDER BY ' . $order_by . ' LIMIT %d';
        $args[] = $batch_size;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
        $rows = is_array($rows) ? $rows : [];
        $cursor = $last;

        if ($rows) {
            $last_row = end($rows);
            $cursor = [];
            foreach ($columns as $column) {
                $cursor[$column] = absint($last_row[$column] ?? 0);
            }
        }

        return [
            'cursor' => $cursor,
            'has_more' => count($rows) === $batch_size,
            'rows' => $rows,
        ];
    }

    private function legacy_order_columns(array $order_columns) {
        $columns = [];
        foreach ($order_columns as $column) {
            $column = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $column));
            if ($column) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    private function legacy_cursor_for_columns(array $columns, array $cursor) {
        $last = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $cursor)) {
                $last[$column] = absint($cursor[$column]);
            }
        }

        return $last;
    }

    private function legacy_keyset_where(array $columns, array $last, array &$args) {
        if (!$last) {
            return '';
        }

        $clauses = [];
        foreach ($columns as $index => $column) {
            $parts = [];
            for ($i = 0; $i < $index; $i++) {
                $parts[] = DB::ident($columns[$i]) . ' = %d';
                $args[] = absint($last[$columns[$i]] ?? 0);
            }
            $parts[] = DB::ident($column) . ' > %d';
            $args[] = absint($last[$column] ?? 0);
            $clauses[] = '(' . implode(' AND ', $parts) . ')';
        }

        return ' WHERE ' . implode(' OR ', $clauses);
    }

    private function parse_blocks_csv($csv) {
        $value = maybe_unserialize($csv);
        if (is_string($value)) {
            $json = json_decode($value, true);
            if (is_array($json)) {
                $value = $json;
            }
        }

        $values = [];
        if (is_array($value)) {
            array_walk_recursive($value, static function ($item) use (&$values) {
                $values[] = $item;
            });
        } elseif (preg_match_all('/\d+/', (string) $value, $matches)) {
            $values = $matches[0];
        }

        $ids = array_values(array_unique(array_map('absint', $values)));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /**
     * Reconcile the order snapshot with authoritative legacy block ownership.
     */
    private function legacy_order_block_ids(array $order) {
        global $wpdb;

        $legacy_order_id = absint($order['order_id'] ?? 0);
        $legacy_grid_id = absint($order['banner_id'] ?? 0) ?: 1;
        $snapshot_ids = $this->parse_blocks_csv($order['blocks'] ?? '');
        $linked_ids = [];
        $blocks_table = $this->source->table('blocks');

        if ($legacy_order_id && DB::table_exists($blocks_table)) {
            $linked_ids = array_map('absint', $wpdb->get_col($wpdb->prepare(
                'SELECT block_id FROM ' . DB::ident($blocks_table) . ' WHERE order_id = %d AND banner_id = %d ORDER BY block_id ASC',
                $legacy_order_id,
                $legacy_grid_id
            )));
        }

        $ids = array_values(array_unique(array_merge($snapshot_ids, $linked_ids)));
        sort($ids, SORT_NUMERIC);

        if ($linked_ids && $ids !== $snapshot_ids) {
            $this->record_migration_repair(
                'order',
                $legacy_order_id,
                sprintf(
                    /* translators: 1: source snapshot count, 2: reconciled block count. */
                    __('Reconciled a stale block snapshot (%1$d listed, %2$d linked).', 'million-dollar-script'),
                    count($snapshot_ids),
                    count($ids)
                )
            );
        }

        return $ids;
    }

    private function verification_report(array $totals) {
        return [
            'will_drop_mds2_tables' => false,
            'source_tables' => $this->source->table_report(),
            'target_tables' => $this->source->target_report(),
            'mapped' => [
                'grids' => $this->map->mapped_count($this->source->source_prefix(), 'banner', 'grid'),
                'packages' => $this->map->mapped_count($this->source->source_prefix(), 'package', 'package'),
                'price_rules' => $this->map->mapped_count($this->source->source_prefix(), 'price', 'price_rule'),
                'orders' => $this->map->mapped_count($this->source->source_prefix(), 'order', 'order'),
                'blocks' => $this->map->mapped_count($this->source->source_prefix(), 'block', 'block'),
                'placements' => $this->map->mapped_count($this->source->source_prefix(), 'placement', 'placement'),
                'pages' => $this->map->mapped_count($this->source->source_prefix(), 'page', 'page'),
            ],
            'totals' => $totals,
            'skipped' => array_values($this->migration_skips),
            'repairs' => array_values($this->migration_repairs),
            'page_outcomes' => array_values($this->page_outcomes),
            'warnings' => array_values(array_unique($this->warnings)),
        ];
    }

    private function create_run($source_prefix, $mode, $status, array $report) {
        global $wpdb;

        $now = current_time('mysql', true);
        $wpdb->insert(DB::table('migration_runs'), [
            'source_prefix' => (string) $source_prefix,
            'mode' => sanitize_key($mode),
            'status' => sanitize_key($status),
            'totals' => wp_json_encode([]),
            'report' => wp_json_encode($report),
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return absint($wpdb->insert_id);
    }

    private function load_run($run_id) {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DB::ident(DB::table('migration_runs')) . ' WHERE id = %d LIMIT 1', absint($run_id)), ARRAY_A);
        if (!is_array($row)) {
            return null;
        }

        $row['totals'] = json_decode((string) ($row['totals'] ?? ''), true) ?: [];
        $row['report'] = json_decode((string) ($row['report'] ?? ''), true) ?: [];

        return $row;
    }

    private function update_run($run_id, $status, array $totals, array $report, $completed = false) {
        global $wpdb;

        $data = [
            'status' => sanitize_key($status),
            'totals' => wp_json_encode($totals),
            'report' => wp_json_encode($report),
            'updated_at' => current_time('mysql', true),
        ];

        if ($completed) {
            $data['completed_at'] = current_time('mysql', true);
        }

        $wpdb->update(DB::table('migration_runs'), $data, ['id' => absint($run_id)]);
    }

    private function finish_run($run_id, $status, array $totals, array $report) {
        $this->update_run($run_id, $status, $totals, $report, true);
    }

    private function summarize_banner_media(array $row) {
        $summary = [];
        foreach (['grid_block', 'nfs_block', 'tile', 'usr_grid_block', 'usr_nfs_block', 'usr_ord_block', 'usr_res_block', 'usr_sel_block', 'usr_sol_block'] as $field) {
            $value = (string) ($row[$field] ?? '');
            $summary[$field] = [
                'present' => '' !== $value,
                'bytes' => strlen($value),
                'sha1' => '' !== $value ? sha1($value) : '',
            ];
        }

        return $summary;
    }

    private function legacy_order_metadata(array $row) {
        $metadata = $row;
        if (isset($metadata['block_info'])) {
            $metadata['block_info_present'] = '' !== (string) $metadata['block_info'];
            $metadata['block_info_bytes'] = strlen((string) $metadata['block_info']);
            unset($metadata['block_info']);
        }

        $legacy_package_id = absint($row['package_id'] ?? 0);
        $target_package_id = $legacy_package_id ? $this->map->target_id($this->source->source_prefix(), 'package', $legacy_package_id, 'package') : 0;
        $legacy_ad_metadata = $this->legacy_ad_metadata(absint($row['ad_id'] ?? 0));

        $order_metadata = array_filter(array_merge([
            'legacy_source' => 'mds2',
            'legacy_order_id' => absint($row['order_id'] ?? 0),
            'legacy_package_id' => $legacy_package_id ?: null,
            'package_id' => $target_package_id ?: null,
            'mds_fields' => $legacy_ad_metadata['mds_fields'] ?? null,
            'legacy_ad_post_meta' => $legacy_ad_metadata['legacy_ad_post_meta'] ?? null,
            'legacy_row' => $metadata,
        ], $this->legacy_order_term_metadata($row)), static function ($value) {
            return null !== $value;
        });

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/migration/legacy/order/metadata', $order_metadata, $row, $legacy_ad_metadata);
    }

    private function legacy_order_term_metadata(array $row) {
        $days = absint($row['days_expire'] ?? 0);
        $term_started_at = $this->valid_mysql_date($row['date_published'] ?? '') ?: $this->valid_mysql_date($row['order_date'] ?? '');
        $status = self::order_status($row['status'] ?? '');
        $metadata = [];

        if ($days) {
            $metadata['duration_days'] = $days;
        }

        if ($term_started_at && in_array($status, ['paid', 'expired'], true)) {
            $metadata['term_started_at'] = $term_started_at;
            $metadata['paid_at'] = $term_started_at;
            $term_start = strtotime($term_started_at);
            if ($days && false !== $term_start) {
                $metadata['expires_at'] = gmdate('Y-m-d H:i:s', $term_start + ($days * DAY_IN_SECONDS));
            }
        }

        if ('Y' === strtoupper((string) ($row['expiry_notice_sent'] ?? ''))) {
            $metadata['legacy_expiry_notice_sent'] = true;
        }

        return $metadata;
    }

    private function legacy_block_metadata(array $row) {
        $metadata = $row;
        if (isset($metadata['image_data'])) {
            $metadata['image_data_present'] = '' !== (string) $metadata['image_data'];
            $metadata['image_data_bytes'] = strlen((string) $metadata['image_data']);
            $metadata['image_data_sha1'] = '' !== (string) $metadata['image_data'] ? sha1((string) $metadata['image_data']) : '';
            unset($metadata['image_data']);
        }

        return [
            'legacy_source' => 'mds2',
            'legacy_block_id' => absint($row['block_id'] ?? 0),
            'legacy_banner_id' => absint($row['banner_id'] ?? 0),
            'legacy_row' => $metadata,
        ];
    }

    private function first_banner_value($key) {
        global $wpdb;

        $table = $this->source->table('banners');
        if (!DB::table_exists($table)) {
            return '';
        }

        $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key));
        if (!$key) {
            return '';
        }

        return (string) $wpdb->get_var('SELECT ' . DB::ident($key) . ' FROM ' . DB::ident($table) . ' ORDER BY banner_id ASC LIMIT 1');
    }

    private function normalize_legacy_options(array $options) {
        $normalized = [];
        foreach ($options as $key => $value) {
            $normalized[$key] = $this->normalize_legacy_value($value);
        }

        return $normalized;
    }

    private function normalize_legacy_value($value) {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = $this->normalize_legacy_value($item);
            }

            return $out;
        }

        if (is_object($value)) {
            return $this->normalize_legacy_value((array) $value);
        }

        $string = (string) $value;
        if (strlen($string) > 20000) {
            return [
                'truncated' => true,
                'bytes' => strlen($string),
                'sha1' => sha1($string),
                'preview' => substr($string, 0, 500),
            ];
        }

        return $value;
    }

    private function first_string(array $values, $default = '') {
        foreach ($values as $value) {
            if (is_scalar($value) && '' !== trim((string) $value)) {
                return trim((string) $value);
            }
        }

        return (string) $default;
    }

    private function truthy($value) {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'y', 'true', 'on'], true);
    }

    private function nullable_absint($value) {
        if (null === $value || '' === $value) {
            return null;
        }

        return absint($value);
    }

    private function valid_mysql_date($value) {
        $value = trim((string) $value);
        if (!$value || '0000-00-00 00:00:00' === $value) {
            return '';
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ? $value : '';
    }
}
