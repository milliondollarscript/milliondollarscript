<?php
/**
 * Grid import/export transfer service.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class GridTransfer {

    private const PACKAGE = 'million-dollar-script';
    private const TYPE = 'grid-transfer';
    private const SCHEMA_VERSION = 1;

    public function export_payload(array $grid_ids) {
        $grid_ids = array_values(array_unique(array_filter(array_map('absint', $grid_ids))));
        if (!$grid_ids) {
            return new \WP_Error('mds3_grid_export_empty', __('Select at least one grid to export.', 'million-dollar-script'));
        }

        $repo = new GridRepository();
        $payload = [
            'package' => self::PACKAGE,
            'type' => self::TYPE,
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => gmdate('c'),
            'source_site' => home_url('/'),
            'notes' => [
                __('This export contains grid configuration, packages, price zones, and admin availability settings.', 'million-dollar-script'),
                __('Orders, paid placements, customer data, and uploaded ad media are not imported into new grids.', 'million-dollar-script'),
            ],
            'grids' => [],
        ];

        foreach ($grid_ids as $grid_id) {
            $grid = $repo->find($grid_id);
            if (!$grid) {
                continue;
            }

            $payload['grids'][] = $this->export_grid($grid);
        }

        if (!$payload['grids']) {
            return new \WP_Error('mds3_grid_export_not_found', __('No exportable grids were found.', 'million-dollar-script'));
        }

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/export/payload', $payload, $grid_ids);
    }

    public function import_payload($payload) {
        $valid = $this->validate_payload($payload);
        if (is_wp_error($valid)) {
            return $valid;
        }

        $repo = new GridRepository();
        $created = [];
        $errors = [];

        foreach ($payload['grids'] as $index => $entry) {
            if (!is_array($entry)) {
                /* translators: %d: grid entry number in the imported file. */
                $errors[] = sprintf(__('Grid entry %d was skipped because it is invalid.', 'million-dollar-script'), $index + 1);
                continue;
            }

            $grid_data = $this->sanitize_grid_data($entry['grid'] ?? []);
            $grid = $repo->create($grid_data);
            if (is_wp_error($grid)) {
                $errors[] = $grid->get_error_message();
                continue;
            }

            $created[] = [
                'id' => $grid->id(),
                'title' => (string) $grid->get('title', ''),
            ];

            $this->import_packages($grid->id(), $entry['packages'] ?? []);
            $this->import_price_rules($grid->id(), $entry['price_rules'] ?? []);
            $this->import_unavailable_blocks($grid, $entry['unavailable_blocks'] ?? []);
        }

        if (!$created) {
            return new \WP_Error('mds3_grid_import_failed', $errors ? implode(' ', $errors) : __('No grids could be imported.', 'million-dollar-script'));
        }

        return [
            'created' => $created,
            'errors' => $errors,
        ];
    }

    public function validate_payload($payload) {
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (!is_array($payload)) {
            return new \WP_Error('mds3_grid_import_json_invalid', __('The selected file is not valid JSON.', 'million-dollar-script'));
        }

        if (self::PACKAGE !== (string) ($payload['package'] ?? '') || self::TYPE !== (string) ($payload['type'] ?? '')) {
            return new \WP_Error('mds3_grid_import_package_invalid', __('The selected file is not a Million Dollar Script grid export.', 'million-dollar-script'));
        }

        if (self::SCHEMA_VERSION !== absint($payload['schema_version'] ?? 0)) {
            return new \WP_Error('mds3_grid_import_schema_invalid', __('This grid export uses an unsupported schema version.', 'million-dollar-script'));
        }

        if (empty($payload['grids']) || !is_array($payload['grids'])) {
            return new \WP_Error('mds3_grid_import_empty', __('The grid export does not contain any grids.', 'million-dollar-script'));
        }

        $max_grids = (int) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/import/max/grids', 50);
        if (count($payload['grids']) > max(1, $max_grids)) {
            /* translators: %d: maximum number of grids allowed in one import file. */
            return new \WP_Error('mds3_grid_import_too_large', sprintf(__('This file contains more than %d grids.', 'million-dollar-script'), max(1, $max_grids)));
        }

        return $payload;
    }

    private function export_grid(Grid $grid) {
        $packages = array_map([$this, 'export_package'], (new PackageRepository())->for_grid($grid->id()));
        $price_rules = array_map([$this, 'export_price_rule'], (new PriceRuleRepository())->for_grid($grid->id()));
        $unavailable_blocks = array_map([$this, 'export_block'], $this->exportable_unavailable_blocks($grid));
        $settings = GridBackground::storage_settings($grid->settings());
        $background = GridBackground::public_payload($settings);
        unset($settings['background_image_id']);
        if (!empty($background['url'])) {
            $settings['background_image_url'] = $background['url'];
        }

        return [
            'source_id' => $grid->id(),
            'grid' => [
                'slug' => (string) $grid->get('slug', ''),
                'title' => (string) $grid->get('title', ''),
                'description' => (string) $grid->get('description', ''),
                'width' => absint($grid->get('width', 1000)),
                'height' => absint($grid->get('height', 1000)),
                'block_width' => absint($grid->get('block_width', 10)),
                'block_height' => absint($grid->get('block_height', 10)),
                'price_per_block' => (float) $grid->get('price_per_block', 1),
                'currency' => (string) $grid->get('currency', ''),
                'status' => (string) $grid->get('status', 'active'),
                'settings' => $this->sanitize_json_value($settings),
            ],
            'packages' => $packages,
            'price_rules' => $price_rules,
            'unavailable_blocks' => $unavailable_blocks,
            'summary' => [
                'packages' => count($packages),
                'price_rules' => count($price_rules),
                'unavailable_blocks' => count($unavailable_blocks),
                'placements' => $this->status_counts('placements', $grid->id()),
                'orders' => $this->order_status_counts($grid->id()),
            ],
        ];
    }

    private function sanitize_grid_data($data) {
        $data = is_array($data) ? $data : [];
        $settings = $this->sanitize_json_value(is_array($data['settings'] ?? null) ? $data['settings'] : []);
        $background_url = esc_url_raw((string) ($settings['background_image_url'] ?? ''));
        unset($settings['background_image_url']);
        $settings['background_image_id'] = $background_url ? absint(attachment_url_to_postid($background_url)) : 0;
        if (true !== GridBackground::validate_attachment($settings['background_image_id'])) {
            $settings['background_image_id'] = 0;
        }
        $settings = GridBackground::storage_settings($settings);

        return [
            'slug' => sanitize_title((string) ($data['slug'] ?? '')),
            'title' => sanitize_text_field((string) ($data['title'] ?? __('Imported Grid', 'million-dollar-script'))),
            'description' => wp_kses_post((string) ($data['description'] ?? '')),
            'width' => max(1, absint($data['width'] ?? 1000)),
            'height' => max(1, absint($data['height'] ?? 1000)),
            'block_width' => max(1, absint($data['block_width'] ?? 10)),
            'block_height' => max(1, absint($data['block_height'] ?? 10)),
            'price_per_block' => (float) ($data['price_per_block'] ?? 1),
            'currency' => sanitize_text_field((string) ($data['currency'] ?? '')),
            'status' => sanitize_key((string) ($data['status'] ?? 'active')),
            'settings' => $settings,
        ];
    }

    private function export_package(array $package) {
        return [
            'title' => (string) ($package['title'] ?? ''),
            'description' => (string) ($package['description'] ?? ''),
            'duration_days' => absint($package['duration_days'] ?? 0),
            'price' => (float) ($package['price'] ?? 0),
            'currency' => (string) ($package['currency'] ?? ''),
            'max_orders' => absint($package['max_orders'] ?? 0),
            'is_default' => !empty($package['is_default']) ? 1 : 0,
            'status' => sanitize_key((string) ($package['status'] ?? 'active')),
            'metadata' => $this->sanitize_json_value(is_array($package['metadata'] ?? null) ? $package['metadata'] : []),
        ];
    }

    private function export_price_rule(array $rule) {
        return [
            'row_from' => $this->nullable_absint($rule['row_from'] ?? null),
            'row_to' => $this->nullable_absint($rule['row_to'] ?? null),
            'col_from' => $this->nullable_absint($rule['col_from'] ?? null),
            'col_to' => $this->nullable_absint($rule['col_to'] ?? null),
            'block_id_from' => $this->nullable_absint($rule['block_id_from'] ?? null),
            'block_id_to' => $this->nullable_absint($rule['block_id_to'] ?? null),
            'price' => (float) ($rule['price'] ?? 0),
            'currency' => (string) ($rule['currency'] ?? ''),
            'color' => sanitize_text_field((string) ($rule['color'] ?? '')),
            'status' => sanitize_key((string) ($rule['status'] ?? 'active')),
            'metadata' => $this->sanitize_json_value(is_array($rule['metadata'] ?? null) ? $rule['metadata'] : []),
        ];
    }

    private function export_block(array $block) {
        return [
            'x' => absint($block['x'] ?? 0),
            'y' => absint($block['y'] ?? 0),
            'width' => max(1, absint($block['width'] ?? 1)),
            'height' => max(1, absint($block['height'] ?? 1)),
            'status' => 'unavailable',
            'price_override' => isset($block['price_override']) && '' !== (string) $block['price_override'] ? (float) $block['price_override'] : null,
            'metadata' => $this->sanitize_json_value(json_decode((string) ($block['metadata'] ?? ''), true) ?: []),
        ];
    }

    private function exportable_unavailable_blocks(Grid $grid) {
        $blocks = (new BlockRepository())->for_grid($grid->id(), ['unavailable']);

        return array_values(array_filter($blocks, static function ($block) {
            return empty($block['order_id']) && empty($block['user_id']) && empty($block['reserved_until']);
        }));
    }

    private function import_packages($grid_id, $packages) {
        $repo = new PackageRepository();
        foreach (is_array($packages) ? $packages : [] as $package) {
            if (!is_array($package)) {
                continue;
            }

            $repo->save(array_merge($this->export_package($package), [
                'grid_id' => $grid_id,
                'id' => 0,
            ]));
        }
    }

    private function import_price_rules($grid_id, $rules) {
        $repo = new PriceRuleRepository();
        foreach (is_array($rules) ? $rules : [] as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $repo->save(array_merge($this->export_price_rule($rule), [
                'grid_id' => $grid_id,
                'id' => 0,
            ]));
        }
    }

    private function import_unavailable_blocks(Grid $grid, $blocks) {
        global $wpdb;

        $now = current_time('mysql', true);
        foreach (is_array($blocks) ? $blocks : [] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $x = absint($block['x'] ?? 0);
            $y = absint($block['y'] ?? 0);
            if ($x >= absint($grid->get('width', 0)) || $y >= absint($grid->get('height', 0))) {
                continue;
            }

            $wpdb->insert(DB::table('blocks'), [
                'grid_id' => $grid->id(),
                'x' => $x,
                'y' => $y,
                'width' => max(1, absint($block['width'] ?? $grid->get('block_width', 10))),
                'height' => max(1, absint($block['height'] ?? $grid->get('block_height', 10))),
                'status' => 'unavailable',
                'price_override' => isset($block['price_override']) && '' !== (string) $block['price_override'] ? (float) $block['price_override'] : null,
                'metadata' => wp_json_encode($this->sanitize_json_value(is_array($block['metadata'] ?? null) ? $block['metadata'] : [])),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function status_counts($table, $grid_id) {
        global $wpdb;

        $table = sanitize_key((string) $table);
        if (!in_array($table, ['placements', 'blocks'], true)) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT status, COUNT(*) AS total FROM ' . DB::ident(DB::table($table)) . ' WHERE grid_id = %d GROUP BY status', absint($grid_id)),
            ARRAY_A
        );

        $counts = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $counts[sanitize_key((string) ($row['status'] ?? ''))] = absint($row['total'] ?? 0);
        }

        return $counts;
    }

    private function order_status_counts($grid_id) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT o.status, COUNT(DISTINCT o.id) AS total FROM ' . DB::ident(DB::table('orders')) . ' o INNER JOIN ' . DB::ident(DB::table('order_items')) . ' i ON i.order_id = o.id WHERE i.grid_id = %d GROUP BY o.status',
                absint($grid_id)
            ),
            ARRAY_A
        );

        $counts = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $counts[sanitize_key((string) ($row['status'] ?? ''))] = absint($row['total'] ?? 0);
        }

        return $counts;
    }

    private function sanitize_json_value($value, $depth = 0) {
        if ($depth > 8) {
            return null;
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $key = is_int($key) ? $key : sanitize_key((string) $key);
                if ('' === (string) $key) {
                    continue;
                }
                $sanitized[$key] = $this->sanitize_json_value($item, $depth + 1);
            }

            return $sanitized;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || null === $value) {
            return $value;
        }

        return sanitize_text_field((string) $value);
    }

    private function nullable_absint($value) {
        return null === $value || '' === (string) $value ? null : absint($value);
    }
}
