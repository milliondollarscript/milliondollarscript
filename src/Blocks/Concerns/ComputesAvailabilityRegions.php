<?php
/**
 * Availability-region geometry helpers.
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

trait ComputesAvailabilityRegions {

    private function normalize_region(Grid $grid, array $bounds) {
        $geometry = $grid->geometry();
        $row_from = absint($bounds['row_from'] ?? $bounds['row'] ?? 0);
        $row_to = array_key_exists('row_to', $bounds) && '' !== (string) $bounds['row_to'] ? absint($bounds['row_to']) : $row_from;
        $col_from = absint($bounds['col_from'] ?? $bounds['col'] ?? 0);
        $col_to = array_key_exists('col_to', $bounds) && '' !== (string) $bounds['col_to'] ? absint($bounds['col_to']) : $col_from;

        if ($row_from > $row_to) {
            [$row_from, $row_to] = [$row_to, $row_from];
        }
        if ($col_from > $col_to) {
            [$col_from, $col_to] = [$col_to, $col_from];
        }

        if (!$geometry->contains($row_from, $col_from) || !$geometry->contains($row_to, $col_to)) {
            return new \WP_Error('mds3_region_out_of_range', __('Region is outside the grid.', 'million-dollar-script'));
        }

        return [
            'row_from' => $row_from,
            'row_to' => $row_to,
            'col_from' => $col_from,
            'col_to' => $col_to,
        ];
    }

    private function region_rect(Grid $grid, array $region) {
        $geometry = $grid->geometry();
        $from = $geometry->rect($region['row_from'], $region['col_from']);
        $to = $geometry->rect($region['row_to'], $region['col_to']);

        if (!$from || !$to) {
            return new \WP_Error('mds3_region_out_of_range', __('Region is outside the grid.', 'million-dollar-script'));
        }

        return [
            'x_from' => absint($from['x']),
            'x_to' => absint($to['x']),
            'y_from' => absint($from['y']),
            'y_to' => absint($to['y']),
            'block_width' => absint($from['width']),
            'block_height' => absint($from['height']),
        ];
    }

    private function count_protected_blocks(Grid $grid, array $rect) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d AND x BETWEEN %d AND %d AND y BETWEEN %d AND %d AND status IN (%s, %s)',
                $grid->id(),
                $rect['x_from'],
                $rect['x_to'],
                $rect['y_from'],
                $rect['y_to'],
                'sold',
                'reserved'
            )
        );
    }

    private function subtract_region(array $region, array $subtract) {
        $intersection = $this->region_intersection($region, $subtract);
        if (!$intersection) {
            return [$region];
        }

        $pieces = [];
        $id_base = sanitize_key((string) ($region['id'] ?? '')) ?: wp_generate_uuid4();

        if ($region['row_from'] < $intersection['row_from']) {
            $pieces[] = $this->region_piece($region, $id_base, $region['row_from'], $intersection['row_from'] - 1, $region['col_from'], $region['col_to']);
        }
        if ($intersection['row_to'] < $region['row_to']) {
            $pieces[] = $this->region_piece($region, $id_base, $intersection['row_to'] + 1, $region['row_to'], $region['col_from'], $region['col_to']);
        }
        if ($region['col_from'] < $intersection['col_from']) {
            $pieces[] = $this->region_piece($region, $id_base, $intersection['row_from'], $intersection['row_to'], $region['col_from'], $intersection['col_from'] - 1);
        }
        if ($intersection['col_to'] < $region['col_to']) {
            $pieces[] = $this->region_piece($region, $id_base, $intersection['row_from'], $intersection['row_to'], $intersection['col_to'] + 1, $region['col_to']);
        }

        return array_values(array_filter($pieces));
    }

    private function region_intersection(array $a, array $b) {
        $intersection = [
            'row_from' => max(absint($a['row_from'] ?? 0), absint($b['row_from'] ?? 0)),
            'row_to' => min(absint($a['row_to'] ?? 0), absint($b['row_to'] ?? 0)),
            'col_from' => max(absint($a['col_from'] ?? 0), absint($b['col_from'] ?? 0)),
            'col_to' => min(absint($a['col_to'] ?? 0), absint($b['col_to'] ?? 0)),
        ];

        return $intersection['row_from'] <= $intersection['row_to'] && $intersection['col_from'] <= $intersection['col_to']
            ? $intersection
            : null;
    }

    private function region_piece(array $source, $id_base, $row_from, $row_to, $col_from, $col_to) {
        if ($row_from > $row_to || $col_from > $col_to) {
            return null;
        }

        $piece = [
            'id' => sanitize_key($id_base . '-' . substr(wp_generate_uuid4(), 0, 8)),
            'row_from' => absint($row_from),
            'row_to' => absint($row_to),
            'col_from' => absint($col_from),
            'col_to' => absint($col_to),
            'note' => sanitize_text_field((string) ($source['note'] ?? '')),
            'source' => sanitize_key((string) ($source['source'] ?? 'admin_region')),
            'virtual' => true,
        ];
        $piece['count'] = $this->region_area($piece);

        return $piece;
    }

    private function region_area(array $region) {
        return max(0, absint($region['row_to'] ?? 0) - absint($region['row_from'] ?? 0) + 1)
            * max(0, absint($region['col_to'] ?? 0) - absint($region['col_from'] ?? 0) + 1);
    }

    private function region_cell_count(array $regions) {
        $total = 0;
        foreach ($regions as $region) {
            $total += $this->region_area($region);
        }

        return $total;
    }
}
