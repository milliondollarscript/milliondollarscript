<?php
/**
 * MDS3 page shortcodes and legacy shortcode adapter.
 *
 * @package MillionDollarScript\V3\Pages
 */

namespace MillionDollarScript\V3\Pages;

use MillionDollarScript\V3\Grid\GridShortcode;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Pages\Concerns\RendersAdvertiserLists;
use MillionDollarScript\V3\Pages\Concerns\RendersPagePanels;
use MillionDollarScript\V3\Pages\Concerns\ResolvesPageOrders;
use MillionDollarScript\V3\Setup\LegacyPlugin;
use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class PageShortcodes implements Component {
    use RendersAdvertiserLists;
    use RendersPagePanels;
    use ResolvesPageOrders;

    public function register() {
        add_action('init', [$this, 'shortcodes'], 12);
    }

    public function shortcodes() {
        add_shortcode('mds3_page', [$this, 'render']);
        if (LegacyPlugin::should_register_legacy_embeds()) {
            add_shortcode('milliondollarscript', [$this, 'legacy']);
            add_shortcode('million_dollar_script', [$this, 'legacy']);
            add_shortcode('mds', [$this, 'legacy']);
            add_shortcode('mds_display', [$this, 'legacy']);
        }
    }

    public function legacy($atts = []) {
        $atts = $this->normalize_legacy_atts(is_array($atts) ? $atts : []);
        if (!isset($atts['read_only'])) {
            $atts['read_only'] = 'false';
        }

        return $this->render($atts);
    }

    public function render($atts = []) {
        $atts = shortcode_atts([
            'type' => 'grid',
            'grid_id' => 0,
            'id' => 0,
            'width' => '100%',
            'height' => '{height}',
            'align' => 'center',
            'read_only' => 'true',
            'renderer' => '',
            'show_stats' => 'inherit',
            'unit' => 'settings',
            'number_color' => '',
            'label_color' => '',
            'background_color' => '',
            'border_color' => '',
            'list_layout' => 'list',
            'list_columns' => '',
            'list_search' => 'yes',
            'list_accent_color' => '',
            'list_background_color' => '',
        ], is_array($atts) ? $atts : [], 'mds3_page');

        $type = sanitize_key($atts['type']);
        $grid_id = absint($atts['grid_id'] ?: $atts['id']);
        $grid_id = $this->resolve_page_grid_id($type, $grid_id);

        if ('grid' === $type) {
            return (new GridShortcode())->grid([
                'id' => $grid_id,
                'width' => $atts['width'],
                'height' => $atts['height'],
                'read_only' => $atts['read_only'],
                'renderer' => $atts['renderer'],
                'show_stats' => $atts['show_stats'],
            ]);
        }

        if ('order' === $type) {
            if (!$grid_id) {
                $grid_repository = new GridRepository();
                if (1 === $grid_repository->active_count()) {
                    $grid = $grid_repository->first_active();
                    $grid_id = $grid ? $grid->id() : 0;
                } else {
                    wp_enqueue_style('mds3-grid', MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'css/grid.css', [], GridShortcode::asset_version('css/grid.css'));
                    wp_enqueue_script('mds3-grid');
                    return $this->grid_picker('order');
                }
            }

            return (new GridShortcode())->grid([
                'id' => $grid_id,
                'width' => $atts['width'],
                'height' => $atts['height'],
                'read_only' => 'false',
                'renderer' => $atts['renderer'],
                'show_stats' => $atts['show_stats'],
            ]);
        }

        wp_enqueue_style('mds3-grid', MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'css/grid.css', [], GridShortcode::asset_version('css/grid.css'));
        wp_enqueue_script('mds3-grid');

        if ('stats' === $type) {
            return $this->stats($grid_id, [
                'unit' => $atts['unit'],
                'width' => $atts['width'],
                'number_color' => $atts['number_color'],
                'label_color' => $atts['label_color'],
                'background_color' => $atts['background_color'],
                'border_color' => $atts['border_color'],
            ]);
        }

        if (in_array($type, ['upload', 'manage'], true) && !empty($_GET['mds3_order_id']) && !empty($_GET['mds3_order_key'])) {
            return $this->order_upload_panel(absint($_GET['mds3_order_id']), sanitize_text_field(wp_unslash($_GET['mds3_order_key'])));
        }

        return $this->panel($type, $grid_id, $atts);
    }

    private function resolve_page_grid_id($type, $grid_id) {
        $requested_grid_id = absint($_GET['mds3_grid_id'] ?? 0);
        if ($requested_grid_id && $this->active_grid_exists($requested_grid_id)) {
            return $requested_grid_id;
        }

        if ($grid_id && $this->is_global_standard_page($type) && $this->has_multiple_active_grids()) {
            return 0;
        }

        return $grid_id;
    }

    private function is_global_standard_page($type) {
        if (!PageRepository::is_valid_type($type)) {
            return false;
        }

        $post_id = function_exists('get_queried_object_id') ? absint(get_queried_object_id()) : 0;
        if (!$post_id && function_exists('get_the_ID')) {
            $post_id = absint(get_the_ID());
        }

        return $post_id && $post_id === absint(get_option('mds3_page_' . sanitize_key($type) . '_id', 0));
    }

    private function active_grid_exists($grid_id) {
        $grid = (new GridRepository())->find($grid_id);

        return $grid && 'active' === $grid->get('status');
    }

    private function has_multiple_active_grids() {
        return (new GridRepository())->active_count() > 1;
    }
}
