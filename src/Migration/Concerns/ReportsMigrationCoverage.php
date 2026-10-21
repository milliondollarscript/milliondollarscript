<?php
/**
 * What the import did not carry over.
 *
 * The per-entity table in the verification report answers "did the importer write
 * rows"; it cannot answer "is anything missing", which is the question a site
 * owner asks before switching Million Dollar Script 2 off. A run that imported
 * 101 orders and gave 62 of them a place on the grid reads as completely
 * successful there, so this covers the three shortfalls that have actually
 * happened on a real site: an order the old data held cells for that has no
 * place on the new grid and no explanation anywhere, an ad that had an image in
 * the old data and shows none now, and a placement that sits outside the grid
 * picture.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare().

trait ReportsMigrationCoverage {

    /**
     * @return array<string,array<string,mixed>>
     */
    public function coverage_report(): array {
        return [
            'orders' => $this->coverage_orders(),
            'images' => $this->coverage_images(),
            'canvas' => $this->coverage_canvas(),
        ];
    }

    /**
     * Records left without their counterpart and without an explanation.
     */
    public static function coverage_shortfall_total(array $coverage): int {
        return absint($coverage['orders']['unexplained'] ?? 0)
            + absint($coverage['images']['short'] ?? 0)
            + absint($coverage['canvas']['outside'] ?? 0);
    }

    /**
     * Every order in the old data against the orders that hold a place on the new
     * grid, with the ones this run already explained subtracted. What is left is
     * the part nobody was told about, which is the difference between a run that
     * lost records honestly and one that lost them quietly.
     *
     * Statuses are deliberately not used to decide which orders "should" have a
     * placement: an expired order keeps its cells as history in both versions, and
     * a denied one does not, so the statuses on either side do not line up. What
     * does line up is the run's own report - anything it says it skipped is
     * accounted for, and anything it does not mention is a finding.
     *
     * @return array<string,int>
     */
    private function coverage_orders(): array {
        global $wpdb;

        $source_table = $this->source->table('orders');
        $source = DB::table_exists($source_table)
            ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::ident($source_table))
            : 0;

        $reported = 0;
        $explained = [];
        foreach ((array) $this->migration_skips as $skip) {
            if (!in_array((string) ($skip['entity'] ?? ''), ['order', 'placement'], true)) {
                continue;
            }
            $legacy_id = (string) ($skip['source_id'] ?? '');
            if ('' === $legacy_id || isset($explained[$legacy_id])) {
                continue;
            }
            $explained[$legacy_id] = true;
            $reported++;
        }

        $placed = $this->placed_order_count(false);

        return [
            'source' => $source,
            'placed' => $placed,
            'reported' => $reported,
            'unexplained' => max(0, $source - $placed - $reported),
        ];
    }

    /**
     * Of the orders that did reach the grid, the ones whose old rows carried an ad
     * picture against the ones whose placement has one now. Orders that never
     * reached the grid are already counted above, and the old data keeps a picture
     * in three places - the ad record, a file name on the block, or a base64 crop
     * in the block row - so all three count as "there was an image here".
     *
     * @return array<string,int>
     */
    private function coverage_images(): array {
        global $wpdb;

        $blocks_table = $this->source->table('blocks');
        $orders_table = $this->source->table('orders');
        $expected = 0;

        if (DB::table_exists($orders_table)) {
            $meta_keys = self::IMAGE_META_KEYS;
            // This counts the same places the importer can reach, so the two cannot disagree:
            // the known keys, and any field on the ad record whose own name says it holds a
            // picture - that older plugin names the field after the site form that collected
            // it, so the name is not fixed. A value may be an attachment id, a path or a URL.
            $named_field = "( LOWER(meta_key) REGEXP 'image|banner|photo|graphic|picture|thumbnail|artwork|upload|file' AND meta_key NOT LIKE '\\_%' )";
            $image_value = "( meta_value REGEXP '^[0-9]+$' OR LOWER(meta_value) REGEXP '\\.(jpe?g|png|gif|webp)$' )";
            $ad_with_image = 'SELECT post_id FROM ' . DB::ident($wpdb->postmeta)
                . ' WHERE ( meta_key IN (' . implode(', ', array_fill(0, count($meta_keys), '%s')) . ') OR ' . $named_field . ' ) AND ' . $image_value;

            $signals = [];
            $args = [];
            if (DB::column_exists($orders_table, 'ad_id')) {
                $signals[] = 'o.ad_id IN (' . $ad_with_image . ')';
                $args = array_merge($args, $meta_keys);
            }

            $join = '';
            if (DB::table_exists($blocks_table)) {
                $join = ' LEFT JOIN ' . DB::ident($blocks_table) . ' b ON b.order_id = o.order_id';
                foreach (['image_data', 'file_name'] as $column) {
                    if (DB::column_exists($blocks_table, $column)) {
                        $signals[] = 'b.' . $column . " <> ''";
                    }
                }
                if (DB::column_exists($blocks_table, 'ad_id')) {
                    $signals[] = 'b.ad_id IN (' . $ad_with_image . ')';
                    $args = array_merge($args, $meta_keys);
                }
            }

            if ($signals) {
                $sql = 'SELECT COUNT(DISTINCT o.order_id) FROM ' . DB::ident($orders_table) . ' o' . $join
                    . ' WHERE (' . implode(' OR ', $signals) . ')'
                    . ' AND EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('migration_map')) . ' mm'
                    . " WHERE mm.source_prefix = %s AND mm.entity_type = 'order' AND mm.mds3_entity_type = 'order'"
                    . ' AND mm.legacy_id = CAST(o.order_id AS CHAR))';
                $expected = (int) $wpdb->get_var($wpdb->prepare($sql, ...array_merge($args, [$this->source->source_prefix()])));
            }
        }

        $attached = $this->placed_order_count(true);

        return [
            'expected' => $expected,
            'attached' => $attached,
            'short' => max(0, $expected - $attached),
            // Not a shortfall: these ads have a picture. It is the size of the
            // copy the old data kept, which the owner needs to know about.
            'cell_size_only' => count((array) $this->cell_size_copies),
        ];
    }

    /**
     * Placements of this source that reach past the edge of the grid. A grid
     * re-cut to different cells shifts a copied map position off the picture, and
     * the ad is then invisible on a page that lists it as sold.
     *
     * @return array<string,mixed>
     */
    private function coverage_canvas(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT p.id, p.x, p.y, p.width, p.height, g.width AS grid_width, g.height AS grid_height'
                    . ' FROM ' . DB::ident(DB::table('migration_map')) . ' m'
                    . ' JOIN ' . DB::ident(DB::table('placements')) . ' p ON p.order_id = m.mds3_id'
                    . ' JOIN ' . DB::ident(DB::table('grids')) . ' g ON g.id = p.grid_id'
                    . " WHERE m.source_prefix = %s AND m.entity_type = 'order' AND m.mds3_entity_type = 'order'",
                $this->source->source_prefix()
            ),
            ARRAY_A
        );

        $outside = 0;
        $examples = [];
        foreach ((array) $rows as $row) {
            if (
                absint($row['x']) + absint($row['width']) > absint($row['grid_width'])
                || absint($row['y']) + absint($row['height']) > absint($row['grid_height'])
            ) {
                $outside++;
                if (count($examples) < 5) {
                    $examples[] = [
                        'placement_id' => absint($row['id']),
                        'x' => absint($row['x']),
                        'y' => absint($row['y']),
                        'grid_width' => absint($row['grid_width']),
                        'grid_height' => absint($row['grid_height']),
                    ];
                }
            }
        }

        return ['placements' => count((array) $rows), 'outside' => $outside, 'examples' => $examples];
    }

    /**
     * Mapped orders of this source that own a placement - optionally one with an
     * image attached to it.
     */
    private function placed_order_count(bool $with_attachment): int {
        global $wpdb;

        $sql = 'SELECT COUNT(DISTINCT m.mds3_id) FROM ' . DB::ident(DB::table('migration_map')) . ' m'
            . ' JOIN ' . DB::ident(DB::table('placements')) . ' p ON p.order_id = m.mds3_id'
            . " WHERE m.source_prefix = %s AND m.entity_type = 'order' AND m.mds3_entity_type = 'order'";
        if ($with_attachment) {
            $sql .= ' AND p.attachment_id IS NOT NULL';
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $this->source->source_prefix()));
    }
}
