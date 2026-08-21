<?php
/**
 * Public grid inventory statistics.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class GridStats {

    public function public_inventory(Grid $grid, array $settings = [], $unit = '') {
        $geometry = $grid->geometry();
        $total = $geometry->total_blocks();
        $block_repo = new BlockRepository();
        $counts = $block_repo->counts($grid->id());
        $published = min($total, $this->published_blocks($grid));
        $raw_sold = min($total, absint($counts['sold'] ?? 0));
        $raw_reserved = min($total, absint($counts['reserved'] ?? 0));
        $unavailable = min($total, absint($counts['unavailable'] ?? 0));
        $held_sold = max(0, $raw_sold - $published);
        $reserved = min($total, $raw_reserved + $held_sold);
        $available = max(0, $total - min($total, $published + $reserved + $unavailable));
        $unit = $this->unit($settings, $unit);
        $multiplier = 'pixels' === $unit ? max(1, absint($grid->get('block_width', 1)) * absint($grid->get('block_height', 1))) : 1;

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/public/inventory/stats', [
            'grid_id' => $grid->id(),
            'total_blocks' => $total,
            'available_blocks' => $available,
            'sold_blocks' => $published,
            'reserved_blocks' => $reserved,
            'unavailable_blocks' => $unavailable,
            'available' => $available * $multiplier,
            'sold' => $published * $multiplier,
            'unit' => $unit,
            'unit_label' => 'pixels' === $unit ? __('Pixels', 'million-dollar-script') : __('Blocks', 'million-dollar-script'),
            'diagnostics' => [
                'raw_sold_blocks' => $raw_sold,
                'raw_reserved_blocks' => $raw_reserved,
                'held_sold_blocks' => $held_sold,
                'published_placement_blocks' => $published,
            ],
        ], $grid, $settings, $counts);
    }

    private function unit(array $settings, $unit) {
        $unit = strtolower(sanitize_key((string) $unit));
        if (in_array($unit, ['blocks', 'block'], true)) {
            return 'blocks';
        }
        if (in_array($unit, ['pixels', 'pixel'], true)) {
            return 'pixels';
        }

        $mode = SettingsSchema::sanitize('stats-display-mode', $settings['stats-display-mode'] ?? SettingsSchema::defaults()['stats-display-mode']);

        return 'BLOCKS' === $mode ? 'blocks' : 'pixels';
    }

    private function published_blocks(Grid $grid) {
        global $wpdb;

        $total = 0;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT p.id, p.x, p.y, p.width, p.height, COUNT(i.id) item_count FROM ' . DB::ident(DB::table('placements')) . ' p LEFT JOIN ' . DB::ident(DB::table('order_items')) . ' i ON i.order_id = p.order_id AND i.grid_id = p.grid_id WHERE p.grid_id = %d AND p.status = %s GROUP BY p.id, p.x, p.y, p.width, p.height ORDER BY p.sort_order ASC, p.id ASC',
                [$grid->id(), 'active']
            ),
            ARRAY_A
        );

        foreach (is_array($rows) ? $rows : [] as $placement) {
            $item_count = absint($placement['item_count'] ?? 0);
            $total += $item_count ?: $this->rect_blocks(
                $grid,
                absint($placement['x'] ?? 0),
                absint($placement['y'] ?? 0),
                absint($placement['width'] ?? 0),
                absint($placement['height'] ?? 0)
            );
        }

        return min($grid->geometry()->total_blocks(), $total);
    }

    private function rect_blocks(Grid $grid, $x, $y, $width, $height) {
        $grid_width = max(1, absint($grid->get('width', 0)));
        $grid_height = max(1, absint($grid->get('height', 0)));
        $left = max(0, absint($x));
        $top = max(0, absint($y));
        $right = min($grid_width, $left + absint($width));
        $bottom = min($grid_height, $top + absint($height));
        if ($right <= $left || $bottom <= $top) {
            return 0;
        }

        $block_width = max(1, absint($grid->get('block_width', 1)));
        $block_height = max(1, absint($grid->get('block_height', 1)));

        return (int) (ceil(($right - $left) / $block_width) * ceil(($bottom - $top) / $block_height));
    }
}
