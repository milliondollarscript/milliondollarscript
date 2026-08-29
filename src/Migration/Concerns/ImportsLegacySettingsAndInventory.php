<?php
/**
 * MDS2 settings, grids, pricing, and page import steps.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Grid\GridPostType;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Migration\LegacySource;
use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

trait ImportsLegacySettingsAndInventory {

    private function import_settings() {
        $legacy_options = $this->source->option_values();
        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $schema_mapped = SettingsSchema::map_legacy_options($legacy_options, $settings);

        $mapped = array_merge($schema_mapped, [
            'currency' => $this->first_string([
                $schema_mapped['currency'] ?? '',
                $this->first_banner_value('currency'),
                $settings['currency'] ?? 'USD',
            ], 'USD'),
            'payment_provider' => sanitize_key((string) ($schema_mapped['payment_provider'] ?? 'standalone')),
            'legacy_mds2_source_prefix' => $this->source->source_prefix(),
            'legacy_mds2_options' => $this->normalize_legacy_options($legacy_options),
            'legacy_mds2_page_options' => (new LegacySource($this->source->source_prefix()))->options_report()['page_options'] ?? [],
        ]);

        update_option('mds3_settings', array_merge($settings, $mapped), false);

        return count($legacy_options);
    }

    private function import_grids() {
        $table = $this->source->table('banners');
        if (!DB::table_exists($table)) {
            return 0;
        }

        $count = 0;
        foreach ($this->legacy_rows('banners', ['banner_id']) as $row) {
            if ($this->import_grid_row($row)) {
                $count++;
            }
        }

        return $count;
    }

    private function import_grid_row(array $row) {
        global $wpdb;

        $repo = new GridRepository();
        $legacy_id = absint($row['banner_id'] ?? $row['id'] ?? 0);
        if (!$legacy_id) {
            $this->warnings[] = 'Skipped a banner row without a banner_id.';
            $this->record_migration_skip('grid', '', __('The source row has no banner ID.', 'million-dollar-script'));
            return 0;
        }

        $dimensions = self::banner_pixel_dimensions($row);
        $title = sanitize_text_field((string) ($row['name'] ?? $row['title'] ?? 'Imported Grid ' . $legacy_id));
        $background_attachment_id = $this->legacy_background_attachment($legacy_id);
        $background_opacity = max(0, min(100, absint(get_option('mds_background_opacity_' . $legacy_id, 100))));
        $background_settings = $background_attachment_id ? [
            'background_image_id' => $background_attachment_id,
            'background_image_fit' => 'stretch',
            'background_image_position' => 'center',
            'background_image_repeat' => 'no-repeat',
            'background_image_opacity' => $background_opacity,
        ] : [];
        $payload = [
            'slug' => 'mds2-grid-' . $legacy_id,
            'title' => $title ?: 'Imported Grid ' . $legacy_id,
            'description' => '',
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'block_width' => $dimensions['block_width'],
            'block_height' => $dimensions['block_height'],
            'price_per_block' => (float) ($row['price_per_block'] ?? $row['price'] ?? 1),
            'currency' => strtoupper(substr(sanitize_text_field((string) ($row['currency'] ?? 'USD')), 0, 3)),
            'status' => ('N' === strtoupper((string) ($row['enabled'] ?? 'Y'))) ? 'paused' : 'active',
            'settings' => array_merge([
                'legacy_source' => 'mds2',
                'legacy_banner_id' => $legacy_id,
                'renderer_mode' => 'auto',
                'legacy_grid_width_blocks' => $dimensions['blocks_wide'],
                'legacy_grid_height_blocks' => $dimensions['blocks_high'],
                'days_expire' => absint($row['days_expire'] ?? 0),
                'max_orders' => absint($row['max_orders'] ?? 0),
                'max_blocks' => absint($row['max_blocks'] ?? 0),
                'min_blocks' => absint($row['min_blocks'] ?? 0),
                'background_color' => sanitize_text_field((string) ($row['bgcolor'] ?? '')),
                'auto_publish' => (string) ($row['auto_publish'] ?? ''),
                'auto_approve' => (string) ($row['auto_approve'] ?? ''),
                'nfs_covered' => (string) ($row['nfs_covered'] ?? ''),
                'legacy_media_fields' => $this->summarize_banner_media($row),
            ], $background_settings),
        ];

        $target_id = $this->map->target_id($this->source->source_prefix(), 'banner', $legacy_id, 'grid');
        if (!$target_id) {
            $candidate = $wpdb->get_row($wpdb->prepare(
                'SELECT id, settings FROM ' . DB::ident(DB::table('grids')) . ' WHERE slug = %s LIMIT 1',
                $payload['slug']
            ), ARRAY_A);
            $candidate_settings = is_array($candidate) ? json_decode((string) ($candidate['settings'] ?? ''), true) : [];
            if (
                is_array($candidate_settings) &&
                'mds2' === (string) ($candidate_settings['legacy_source'] ?? '') &&
                $legacy_id === absint($candidate_settings['legacy_banner_id'] ?? 0)
            ) {
                $target_id = absint($candidate['id'] ?? 0);
                $this->record_migration_repair('grid', $legacy_id, __('Recovered an existing grid whose migration-map row was missing.', 'million-dollar-script'));
            }
        }
        if ($target_id && $this->target_exists('grids', $target_id)) {
            $this->update_grid($target_id, $payload);
        } else {
            $created = $repo->create($payload);
            if (is_wp_error($created)) {
                $this->warnings[] = 'Failed to import banner ' . $legacy_id . ': ' . $created->get_error_message();
                return 0;
            }
            $target_id = $created->id();
        }

        $this->map->remember($this->source->source_prefix(), 'banner', $legacy_id, 'grid', $target_id, ['title' => $payload['title']]);
        $this->legacy_grids[$legacy_id] = array_merge($row, ['_mds3_grid_id' => $target_id]);

        // Pages are reconciled once after the pages stage, so a grid never gets a
        // duplicate auto-created page before its existing MDS2 page is associated.
        return $target_id;
    }

    private function legacy_background_attachment($legacy_grid_id) {
        $upload = wp_upload_dir();
        $directory = trailingslashit((string) ($upload['basedir'] ?? '')) . 'grids';
        if (!$directory || !is_dir($directory)) {
            return 0;
        }

        foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $extension) {
            $path = $directory . '/background' . absint($legacy_grid_id) . '.' . $extension;
            if (!is_readable($path)) {
                continue;
            }

            $attachment_id = $this->attachment_from_path($path);
            if ($attachment_id) {
                return $attachment_id;
            }
        }

        return 0;
    }

    private function import_packages() {
        global $wpdb;

        $table = $this->source->table('packages');
        if (!DB::table_exists($table)) {
            return 0;
        }

        $count = 0;
        foreach ($this->legacy_rows('packages', ['package_id']) as $row) {
            if ($this->import_package_row($row)) {
                $count++;
            }
        }

        return $count;
    }

    private function import_package_row(array $row) {
        global $wpdb;

        $now = current_time('mysql', true);
        $legacy_id = absint($row['package_id'] ?? 0);
        $legacy_grid_id = absint($row['banner_id'] ?? 0);
        $grid_id = $this->target_grid_id($legacy_grid_id);
        if (!$legacy_id || !$grid_id) {
            $this->warnings[] = 'Skipped a package row without a mapped grid.';
            $this->record_migration_skip('package', $legacy_id, __('No mapped target grid exists.', 'million-dollar-script'));
            return 0;
        }

        $payload = [
            'grid_id' => $grid_id,
            'title' => sanitize_text_field((string) ($row['description'] ?: 'Imported Package ' . $legacy_id)),
            'description' => sanitize_text_field((string) ($row['description'] ?? '')),
            'duration_days' => absint($row['days_expire'] ?? 0),
            'price' => (float) ($row['price'] ?? 0),
            'currency' => strtoupper(substr(sanitize_text_field((string) ($row['currency'] ?? 'USD')), 0, 3)),
            'max_orders' => absint($row['max_orders'] ?? 0),
            'is_default' => ('Y' === strtoupper((string) ($row['is_default'] ?? ''))) ? 1 : 0,
            'status' => 'active',
            'metadata' => wp_json_encode(['legacy_source' => 'mds2', 'legacy_package_id' => $legacy_id, 'legacy_row' => $row]),
            'updated_at' => $now,
        ];

        $target_id = $this->map->target_id($this->source->source_prefix(), 'package', $legacy_id, 'package');
        if ($target_id && $this->target_exists('packages', $target_id)) {
            $wpdb->update(DB::table('packages'), $payload, ['id' => $target_id]);
        } else {
            $payload['created_at'] = $now;
            $wpdb->insert(DB::table('packages'), $payload);
            $target_id = absint($wpdb->insert_id);
        }

        if ($target_id) {
            $this->map->remember($this->source->source_prefix(), 'package', $legacy_id, 'package', $target_id, ['legacy_grid_id' => $legacy_grid_id]);
        }

        return $target_id;
    }

    private function import_price_rules() {
        global $wpdb;

        $table = $this->source->table('prices');
        if (!DB::table_exists($table)) {
            return 0;
        }

        $count = 0;
        foreach ($this->legacy_rows('prices', ['price_id']) as $row) {
            if ($this->import_price_rule_row($row)) {
                $count++;
            }
        }

        return $count;
    }

    private function import_price_rule_row(array $row) {
        global $wpdb;

        $now = current_time('mysql', true);
        $legacy_id = absint($row['price_id'] ?? 0);
        $legacy_grid_id = absint($row['banner_id'] ?? 0);
        $grid_id = $this->target_grid_id($legacy_grid_id);
        if (!$legacy_id || !$grid_id) {
            $this->warnings[] = 'Skipped a price-rule row without a mapped grid.';
            $this->record_migration_skip('price_rule', $legacy_id, __('No mapped target grid exists.', 'million-dollar-script'));
            return 0;
        }

        $payload = [
            'grid_id' => $grid_id,
            'row_from' => $this->nullable_absint($row['row_from'] ?? null),
            'row_to' => $this->nullable_absint($row['row_to'] ?? null),
            'col_from' => $this->nullable_absint($row['col_from'] ?? null),
            'col_to' => $this->nullable_absint($row['col_to'] ?? null),
            'block_id_from' => $this->nullable_absint($row['block_id_from'] ?? null),
            'block_id_to' => $this->nullable_absint($row['block_id_to'] ?? null),
            'price' => (float) ($row['price'] ?? 0),
            'currency' => strtoupper(substr(sanitize_text_field((string) ($row['currency'] ?? 'USD')), 0, 3)),
            'color' => sanitize_text_field((string) ($row['color'] ?? '')),
            'status' => 'active',
            'metadata' => wp_json_encode(['legacy_source' => 'mds2', 'legacy_price_id' => $legacy_id, 'legacy_row' => $row]),
            'updated_at' => $now,
        ];

        $target_id = $this->map->target_id($this->source->source_prefix(), 'price', $legacy_id, 'price_rule');
        if ($target_id && $this->target_exists('price_rules', $target_id)) {
            $wpdb->update(DB::table('price_rules'), $payload, ['id' => $target_id]);
        } else {
            $payload['created_at'] = $now;
            $wpdb->insert(DB::table('price_rules'), $payload);
            $target_id = absint($wpdb->insert_id);
        }

        if ($target_id) {
            $this->map->remember($this->source->source_prefix(), 'price', $legacy_id, 'price_rule', $target_id, ['legacy_grid_id' => $legacy_grid_id]);
        }

        return $target_id;
    }

    private function import_pages() {
        $repo = new PageRepository();
        $count = 0;

        foreach ($this->source->page_candidates() as $candidate) {
            if ($this->import_page_candidate($candidate, $repo)) {
                $count++;
            }
        }

        return $count;
    }

    private function import_page_candidate(array $candidate, ?PageRepository $repo = null) {
        $repo = $repo ?: new PageRepository();
        $post_id = absint($candidate['post_id'] ?? 0);
        $type = sanitize_key($candidate['type'] ?? 'grid');
        if (!$post_id || !PageRepository::is_valid_type($type) || !get_post($post_id)) {
            return 0;
        }

        $legacy_grid_id = absint($candidate['legacy_grid_id'] ?? 0);
        $existing_target_grid_id = absint(get_post_meta($post_id, '_mds3_grid_id', true));
        $target_grid_id = $legacy_grid_id
            ? $this->target_grid_id($legacy_grid_id)
            : ($this->is_source_target_grid($existing_target_grid_id) ? $existing_target_grid_id : $this->first_target_grid_id());
        if ($legacy_grid_id && !$target_grid_id) {
            $this->record_migration_skip('page', $post_id, __('Its referenced legacy grid has no mapped target grid.', 'million-dollar-script'));
            return 0;
        }
        if (!$target_grid_id && in_array($type, ['grid', 'order', 'confirm-order', 'write-ad', 'thank-you', 'upload', 'manage', 'list', 'stats'], true)) {
            $this->record_migration_skip('page', $post_id, __('No grid imported from this source is available for the page.', 'million-dollar-script'));
            return 0;
        }
        $content = PageRepository::shortcode($type, $target_grid_id);
        $post = get_post($post_id);
        $unmodified = !empty($candidate['unmodified']);
        $replace_modified = !empty($this->page_options['replace_modified']);
        $create_new = !empty($this->page_options['create_new']) && !$replace_modified;

        // A modified MDS2 page is left untouched by default. The opt-ins decide
        // whether to overwrite it in place (replace) or leave it and create a new
        // separate page (create_new, which is ignored when replace is set).
        if (!$unmodified && !$replace_modified) {
            if ($create_new) {
                $created_id = $this->create_new_page($type, $target_grid_id, $content, $repo);
                if ($created_id) {
                    $this->record_grid_page_disposition($target_grid_id, 'created_new');
                    $this->record_page_outcome($post_id, $type, 'created_new', (string) get_the_title($post_id));
                    return $created_id;
                }
            }

            $this->record_grid_page_disposition($target_grid_id, 'left_unchanged');
            $this->record_page_outcome($post_id, $type, 'left_unchanged', (string) get_the_title($post_id));
            $this->record_migration_skip('page', $post_id, __('Its content differs from the original Million Dollar Script 2 page, so it was left unchanged.', 'million-dollar-script'));
            return 0;
        }

        $this->record_grid_page_disposition($target_grid_id, 'in_place');
        if ($post && (string) $post->post_content !== $content) {
            if (!metadata_exists('post', $post_id, '_mds3_migration_original_content')) {
                update_post_meta($post_id, '_mds3_migration_original_content', (string) $post->post_content);
                update_post_meta($post_id, '_mds3_migration_original_title', (string) $post->post_title);
            }

            wp_update_post([
                'ID' => $post_id,
                'post_content' => $content,
            ]);
        }

        update_post_meta($post_id, '_mds3_page_type', $type);
        update_post_meta($post_id, '_mds3_grid_id', $target_grid_id);
        update_post_meta($post_id, '_mds3_migration_source', 'mds2');
        update_post_meta($post_id, '_mds3_migration_source_prefix', $this->source->source_prefix());
        update_option('mds3_page_' . $type . '_id', $post_id, false);

        $repo->upsert($post_id, $type, [
            'grid_id' => $target_grid_id,
            'source' => 'mds2',
            'legacy_post_id' => $post_id,
            'legacy_metadata' => $candidate,
            'configuration' => [
                'legacy_grid_id' => $legacy_grid_id,
                'sources' => $candidate['sources'] ?? [],
            ],
        ]);

        $this->map->remember($this->source->source_prefix(), 'page', $post_id, 'page', $post_id, ['type' => $type, 'legacy_grid_id' => $legacy_grid_id]);
        $this->record_page_outcome($post_id, $type, $unmodified ? 'updated_in_place' : 'replaced', (string) get_the_title($post_id));

        return $post_id;
    }

    private function create_new_page($type, $target_grid_id, $content, PageRepository $repo) {
        if ('grid' === $type && $target_grid_id) {
            $grid = (new GridRepository())->find($target_grid_id);
            if ($grid) {
                $post_id = GridPostType::ensure_page($grid);
                if (!is_wp_error($post_id) && $post_id) {
                    update_post_meta($post_id, '_mds3_migration_source', 'mds2');
                    return absint($post_id);
                }
            }

            return 0;
        }

        $label = (string) (PageRepository::standard_labels()[$type] ?? $type);
        $post_id = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => $label,
            'post_name' => sanitize_title($label),
            'post_content' => $content,
        ], true);
        if (is_wp_error($post_id) || !$post_id) {
            return 0;
        }

        update_post_meta($post_id, '_mds3_page_type', $type);
        if ($target_grid_id) {
            update_post_meta($post_id, '_mds3_grid_id', $target_grid_id);
        }
        update_post_meta($post_id, '_mds3_migration_source', 'mds2');
        update_option('mds3_page_' . $type . '_id', absint($post_id), false);
        $repo->upsert($post_id, $type, [
            'grid_id' => $target_grid_id,
            'source' => 'mds2_created_new',
            'configuration' => ['created_new' => true],
        ]);

        return absint($post_id);
    }

    private function record_grid_page_disposition($grid_id, $disposition) {
        $grid_id = absint($grid_id);
        if (!$grid_id) {
            return;
        }
        $this->grid_page_disposition[$grid_id] = sanitize_key((string) $disposition);
    }

    private function record_page_outcome($post_id, $type, $outcome, $title) {
        $this->page_outcomes[absint($post_id)] = [
            'post_id' => absint($post_id),
            'title' => (string) $title,
            'type' => sanitize_key((string) $type),
            'outcome' => sanitize_key((string) $outcome),
        ];
    }

    /**
     * Give a page to every imported grid that has none (an orphan grid), leaving
     * alone grids whose MDS2 page was deliberately left unchanged or created new.
     */
    private function reconcile_grid_pages() {
        global $wpdb;

        $map_table = DB::table('migration_map');
        $grid_ids = DB::table_exists($map_table)
            ? (array) $wpdb->get_col($wpdb->prepare(
                'SELECT mds3_id FROM ' . DB::ident($map_table) . " WHERE source_prefix = %s AND entity_type = 'banner' AND mds3_entity_type = 'grid'",
                $this->source->source_prefix()
            ))
            : [];

        $created = 0;
        foreach (array_map('absint', array_filter($grid_ids)) as $grid_id) {
            if (!$grid_id || isset($this->grid_page_disposition[$grid_id])) {
                continue;
            }
            if (GridPostType::page_id($grid_id)) {
                continue;
            }

            $grid = (new GridRepository())->find($grid_id);
            if (!$grid) {
                continue;
            }

            $post_id = GridPostType::ensure_page($grid);
            if (!is_wp_error($post_id) && $post_id) {
                $created++;
            }
        }

        return $created;
    }
}
