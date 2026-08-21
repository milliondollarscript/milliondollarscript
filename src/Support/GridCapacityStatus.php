<?php
/**
 * Low-cost grid capacity reporting and guidance.
 *
 * @package MillionDollarScript\V3\Support
 */

namespace MillionDollarScript\V3\Support;

use MillionDollarScript\V3\Grid\Grid;

if (!defined('ABSPATH')) {
    exit;
}

final class GridCapacityStatus implements Component {

    public const TESTED_VIRTUAL_BLOCKS = 1000000;
    public const RECOMMENDED_ACTIVE_PLACEMENTS = 5000;
    public const REVIEW_ACTIVE_PLACEMENTS = 10000;

    /** @var array|null */
    private static $status;

    public function register() {
        add_filter('site_status_tests', [$this, 'site_health_tests']);
    }

    public function site_health_tests($tests) {
        $tests = is_array($tests) ? $tests : [];
        $tests['direct']['million-dollar-script-grid-capacity'] = [
            'label' => __('Million Dollar Script grid capacity', 'million-dollar-script'),
            'test' => [$this, 'site_health_result'],
        ];

        return $tests;
    }

    public function site_health_result() {
        $status = self::status();
        $needs_review = !empty($status['needs_review']);
        $largest = absint($status['largest_active_placements'] ?? 0);

        return [
            'label' => $needs_review
                ? __('A large Million Dollar Script grid should be reviewed', 'million-dollar-script')
                : __('Million Dollar Script grids are within the recommended capacity range', 'million-dollar-script'),
            'status' => $needs_review ? 'recommended' : 'good',
            'badge' => [
                'label' => __('Performance', 'million-dollar-script'),
                'color' => 'blue',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                esc_html($needs_review
                    ? sprintf(
                        /* translators: 1: active placement count, 2: recommended placement count. */
                        __('The largest grid has %1$s active placements. Review rendering and page composition once a grid reaches about %2$s active placements.', 'million-dollar-script'),
                        number_format_i18n($largest),
                        number_format_i18n(self::RECOMMENDED_ACTIVE_PLACEMENTS)
                    )
                    : sprintf(
                        /* translators: %s: active placement count. */
                        __('The largest grid has %s active placements. Empty grid cells remain virtual and do not create database rows.', 'million-dollar-script'),
                        number_format_i18n($largest)
                    ))
            ),
            'actions' => $needs_review ? sprintf(
                '<p><a href="%1$s">%2$s</a></p>',
                esc_url(self::capacity_guide_url()),
                esc_html__('Review grid capacity guidance', 'million-dollar-script')
            ) : '',
            'test' => 'million-dollar-script-grid-capacity',
        ];
    }

    /**
     * Return indexed aggregate counts used by Site Health and System Status.
     */
    public static function status() {
        global $wpdb;

        if (is_array(self::$status)) {
            return self::$status;
        }

        $grid_table = DB::table('grids');
        $placement_table = DB::table('placements');
        if (!DB::table_exists($grid_table) || !DB::table_exists($placement_table)) {
            self::$status = [
                'grid_count' => 0,
                'active_placements' => 0,
                'largest_grid_id' => 0,
                'largest_active_placements' => 0,
                'needs_review' => false,
            ];

            return self::$status;
        }

        $largest = $wpdb->get_row(
            "SELECT grid_id, COUNT(*) AS total FROM " . DB::ident($placement_table) . " WHERE status = 'active' GROUP BY grid_id ORDER BY total DESC LIMIT 1",
            ARRAY_A
        );
        $largest = is_array($largest) ? $largest : [];
        $active_placements = absint($wpdb->get_var(
            "SELECT COUNT(*) FROM " . DB::ident($placement_table) . " WHERE status = 'active'"
        ));

        self::$status = [
            'grid_count' => absint($wpdb->get_var('SELECT COUNT(*) FROM ' . DB::ident($grid_table))),
            'active_placements' => $active_placements,
            'largest_grid_id' => absint($largest['grid_id'] ?? 0),
            'largest_active_placements' => absint($largest['total'] ?? 0),
            'needs_review' => absint($largest['total'] ?? 0) >= self::RECOMMENDED_ACTIVE_PLACEMENTS,
        ];

        return self::$status;
    }

    /**
     * Return the edit-screen assessment for one grid.
     */
    public static function for_grid(Grid $grid) {
        global $wpdb;

        $virtual_blocks = self::virtual_blocks(
            $grid->get('width', 1),
            $grid->get('height', 1),
            $grid->get('block_width', 1),
            $grid->get('block_height', 1)
        );
        $active_placements = DB::table_exists(DB::table('placements'))
            ? absint($wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::ident(DB::table('placements')) . ' WHERE grid_id = %d AND status = %s',
                $grid->id(),
                'active'
            )))
            : 0;

        return [
            'virtual_blocks' => $virtual_blocks,
            'active_placements' => $active_placements,
            'placement_range' => self::placement_range($active_placements),
            'needs_review' => $virtual_blocks > self::TESTED_VIRTUAL_BLOCKS
                || $active_placements >= self::RECOMMENDED_ACTIVE_PLACEMENTS,
        ];
    }

    public static function virtual_blocks($width, $height, $block_width, $block_height) {
        $columns = max(1, intdiv(max(1, absint($width)), max(1, absint($block_width))));
        $rows = max(1, intdiv(max(1, absint($height)), max(1, absint($block_height))));

        return $columns * $rows;
    }

    public static function placement_range($active_placements) {
        $active_placements = absint($active_placements);
        if ($active_placements >= self::REVIEW_ACTIVE_PLACEMENTS) {
            return 'review';
        }
        if ($active_placements >= self::RECOMMENDED_ACTIVE_PLACEMENTS) {
            return 'conditional';
        }

        return 'recommended';
    }

    public static function capacity_guide_url() {
        return add_query_arg([
            'page' => 'mds3-docs',
            'doc' => 'million-dollar-script:mds3-grid-pricing-and-renderers',
        ], admin_url('admin.php'));
    }
}
