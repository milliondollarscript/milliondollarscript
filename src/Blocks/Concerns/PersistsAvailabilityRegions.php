<?php
/**
 * Availability-region storage helpers.
 *
 * @package MillionDollarScript\V3\Blocks
 */

namespace MillionDollarScript\V3\Blocks\Concerns;

use MillionDollarScript\V3\Grid\Grid;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait PersistsAvailabilityRegions {

    private function stored_unavailable_regions(Grid $grid) {
        global $wpdb;

        $settings = $wpdb->get_var(
            $wpdb->prepare('SELECT settings FROM ' . DB::ident(DB::table('grids')) . ' WHERE id = %d', $grid->id())
        );
        $settings = is_string($settings) ? json_decode($settings, true) : [];
        $settings = is_array($settings) ? $settings : [];
        $regions = is_array($settings['unavailable_regions'] ?? null) ? $settings['unavailable_regions'] : [];
        $normalized = [];

        foreach ($regions as $region) {
            if (!is_array($region)) {
                continue;
            }

            $normalized_region = $this->stored_region_payload($grid, $region, $region);
            if ($normalized_region) {
                $normalized[] = $normalized_region;
            }
        }

        return $normalized;
    }

    private function save_stored_unavailable_regions(Grid $grid, array $regions) {
        global $wpdb;

        $settings = $wpdb->get_var(
            $wpdb->prepare('SELECT settings FROM ' . DB::ident(DB::table('grids')) . ' WHERE id = %d', $grid->id())
        );
        $settings = is_string($settings) ? json_decode($settings, true) : [];
        $settings = is_array($settings) ? $settings : [];
        $settings['unavailable_regions'] = array_values(array_map(function ($region) {
            return [
                'id' => sanitize_key((string) ($region['id'] ?? '')) ?: wp_generate_uuid4(),
                'row_from' => absint($region['row_from'] ?? 0),
                'row_to' => absint($region['row_to'] ?? 0),
                'col_from' => absint($region['col_from'] ?? 0),
                'col_to' => absint($region['col_to'] ?? 0),
                'note' => sanitize_text_field((string) ($region['note'] ?? '')),
                'source' => sanitize_key((string) ($region['source'] ?? 'admin_region')),
            ];
        }, $regions));

        $result = $wpdb->update(
            DB::table('grids'),
            [
                'settings' => wp_json_encode($settings),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $grid->id()]
        );

        return false === $result ? new \WP_Error('mds3_region_store_failed', $wpdb->last_error) : true;
    }

    private function stored_region_payload(Grid $grid, array $region, array $metadata = []) {
        $normalized = $this->normalize_region($grid, $region);
        if (is_wp_error($normalized)) {
            return null;
        }

        $id = sanitize_key((string) ($metadata['id'] ?? $metadata['availability_region_id'] ?? '')) ?: wp_generate_uuid4();
        $normalized['id'] = $id;
        $normalized['count'] = $this->region_area($normalized);
        $normalized['note'] = sanitize_text_field((string) ($metadata['note'] ?? ''));
        $normalized['source'] = sanitize_key((string) ($metadata['availability_source'] ?? $metadata['source'] ?? 'admin_region'));
        $normalized['virtual'] = true;

        return $normalized;
    }

    private function subtract_stored_unavailable_region(Grid $grid, array $stored_regions, array $available_region) {
        $remaining = [];
        $changed = 0;

        foreach ($stored_regions as $stored_region) {
            $pieces = $this->subtract_region($stored_region, $available_region);
            $changed += max(0, $this->region_area($stored_region) - $this->region_cell_count($pieces));
            foreach ($pieces as $piece) {
                $piece['note'] = sanitize_text_field((string) ($stored_region['note'] ?? ''));
                $piece['source'] = sanitize_key((string) ($stored_region['source'] ?? 'admin_region'));
                $remaining[] = $piece;
            }
        }

        $saved = $this->save_stored_unavailable_regions($grid, $remaining);
        if (is_wp_error($saved)) {
            return $saved;
        }

        return [
            'changed' => $changed,
            'remaining' => $remaining,
        ];
    }
}
