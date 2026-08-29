<?php
/**
 * Frontend AJAX request handlers for grid state, reservations, and clicks.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid\Concerns;

use MillionDollarScript\V3\Extensions\ExtensionSupport;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Grid\PackageRepository;
use MillionDollarScript\V3\Grid\PriceRuleRepository;
use MillionDollarScript\V3\Media\PlacementFieldContract;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\ReservationService;
use MillionDollarScript\V3\Rendering\TileController;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesGridAjaxRequests {

    public function state() {
        $this->verify_nonce();

        $request = wp_unslash($_REQUEST);
        $grid = (new GridRepository())->find($this->param_absint($request, 'grid_id'));
        if (!$this->can_access_grid($grid)) {
            wp_send_json_error(['message' => __('Grid not found.', 'million-dollar-script')], 404);
        }

        $block_repo = new BlockRepository();
        $blocks = array_values(array_filter($block_repo->for_grid($grid->id()), [$this, 'include_runtime_block']));
        $placements = (new PlacementRepository())->for_grid($grid->id(), ['active']);
        $placement_masks = (new OrderRepository())->item_masks(array_column($placements, 'order_id'));
        $order_map = $this->orders_for_placements($placements);
        $settings = $this->settings();

        $tile = $this->tile_payload($grid);

        wp_send_json_success([
            'grid' => $this->grid_payload($grid),
            'blocks' => array_map([$this, 'block_payload'], $blocks),
            'availabilityRegions' => array_map([$this, 'availability_region_payload'], $block_repo->unavailable_regions($grid)),
            'placements' => array_map(function ($placement) use ($settings, $placement_masks, $order_map) {
                $order_id = absint($placement['order_id'] ?? 0);

                return $this->placement_payload($placement, $settings, $placement_masks[$order_id] ?? [], $order_map[$order_id] ?? null);
            }, $placements),
            'packages' => (new PackageRepository())->active_for_grid($grid->id()),
            'priceRules' => (new PriceRuleRepository())->active_for_grid($grid->id()),
            'interaction' => $this->interaction_payload($settings),
            'selection' => $this->selection_payload($settings, $grid),
            'upload' => $this->upload_payload($settings),
            'stats' => $this->public_stats_payload($grid, $settings),
            'tileUrl' => $tile['url'],
            'tile' => $tile,
        ]);
    }

    private function orders_for_placements(array $placements) {
        $order_ids = array_values(array_unique(array_filter(array_map('absint', array_column($placements, 'order_id')))));
        $order_map = [];

        foreach ((new OrderRepository())->for_ids($order_ids) as $order) {
            $order_map[absint($order['id'] ?? 0)] = $order;
        }

        return $order_map;
    }

    private function tile_payload($grid) {
        $metadata = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/remote/tile/metadata', [], $grid->id(), $grid);
        $metadata = is_array($metadata) ? $metadata : [];
        $remote = (bool) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/has/remote/tiles', false, $grid->id());
        $format = strtolower(sanitize_key((string) ($metadata['format'] ?? 'png')));
        if ('jpg' === $format) {
            $format = 'jpeg';
        }
        if (!in_array($format, ['png', 'jpeg', 'webp'], true)) {
            $format = 'png';
        }
        $cache_key = sanitize_text_field((string) ($metadata['cache_key'] ?? $metadata['updated_at'] ?? $metadata['job_id'] ?? ''));
        $ajax_url = add_query_arg([
            'action' => 'mds3_tile',
            'grid_id' => $grid->id(),
        ], admin_url('admin-ajax.php'));
        $direct_template = $this->sanitize_tile_url_template(
            (string) (
                $metadata['url_template']
                ?? $metadata['tile_url_template']
                ?? $metadata['direct_url_template']
                ?? $metadata['public_url_template']
                ?? ''
            )
        );
        $direct_mode = $direct_template ? 'template' : '';
        $cache_coverage = [];
        $tile_size = max(64, absint($metadata['tile_size'] ?? 256));
        $min_level = absint($metadata['min_level'] ?? 0);
        $max_level = absint($metadata['max_level'] ?? 0);
        $level_scheme = sanitize_key((string) ($metadata['level_scheme'] ?? 'normalized'));
        if (!in_array($level_scheme, ['normalized', 'deepzoom'], true)) {
            $level_scheme = 'normalized';
        }

        if (!$cache_key) {
            $cache_key = TileController::cache_key($grid);
        }
        $local_cache_key = TileController::cache_key($grid);

        if (!$direct_template && TileController::has_public_tile_cache($grid, $cache_key)) {
            $direct_template = TileController::public_tile_url_template($grid, $cache_key, $format);
            $direct_mode = 'cache';
        }
        if (!$direct_template && $local_cache_key !== $cache_key && TileController::has_public_tile_cache($grid, $local_cache_key)) {
            $cache_key = $local_cache_key;
            $format = 'png';
            $direct_template = TileController::public_tile_url_template($grid, $cache_key, $format);
            $direct_mode = 'cache';
        }
        if ('cache' === $direct_mode) {
            $cache_coverage = TileController::public_tile_cache_coverage($grid, $cache_key, $format, $tile_size, $max_level, $min_level);
            if (empty($cache_coverage['complete'])) {
                $direct_template = '';
                $direct_mode = '';
            }
        }

        $has_direct_tile_source = (bool) $direct_template;

        return [
            'url' => $direct_template ?: $ajax_url,
            'fallbackUrl' => '',
            'remote' => $has_direct_tile_source && ($remote || !empty($metadata['remote']) || $has_direct_tile_source),
            'direct' => $has_direct_tile_source,
            'directMode' => $direct_mode,
            'cacheCoverage' => $cache_coverage,
            'format' => $format,
            'tileSize' => $tile_size,
            'minLevel' => $min_level,
            'maxLevel' => $max_level,
            'levelScheme' => $level_scheme,
            'cacheKey' => $cache_key,
        ];
    }

    private function sanitize_tile_url_template($template) {
        $template = trim((string) $template);
        if ('' === $template) {
            return '';
        }

        $template = str_replace(['%7Bz%7D', '%7Bx%7D', '%7By%7D', '%7Bformat%7D'], ['{z}', '{x}', '{y}', '{format}'], $template);
        foreach (['{z}', '{x}', '{y}'] as $token) {
            if (false === strpos($template, $token)) {
                return '';
            }
        }

        $check_url = str_replace(['{z}', '{x}', '{y}', '{format}'], ['0', '0', '0', 'png'], $template);
        $check_url = esc_url_raw($check_url);
        if (!$check_url) {
            return '';
        }

        return $template;
    }

    public function reserve() {
        $this->verify_nonce();
        $this->throttle_public_write('reserve', 30);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_nonce() rejects invalid public grid requests before input is read.
        $post = wp_unslash($_POST);
        $grid = (new GridRepository())->find($this->param_absint($post, 'grid_id'));
        if (!$this->can_access_grid($grid)) {
            wp_send_json_error(['message' => __('Grid not found.', 'million-dollar-script')], 404);
        }

        $coords = isset($post['blocks']) && is_array($post['blocks']) ? $post['blocks'] : [];
        if (!$coords) {
            wp_send_json_error(['message' => __('No blocks selected.', 'million-dollar-script')], 400);
        }

        $result = (new ReservationService())->reserve($grid, $coords, [
            'email' => sanitize_email($this->param($post, 'email')),
            'package_id' => $this->param_absint($post, 'package_id'),
            'metadata' => [
                'subscription_plan_id' => $this->param_absint($post, 'subscription_plan_id'),
            ],
        ]);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 409);
        }

        wp_send_json_success($result);
    }

    public function click() {
        $request = wp_unslash($_REQUEST);
        $placement = (new PlacementRepository())->find($this->param_absint($request, 'placement_id'));
        if (!$placement || 'active' !== sanitize_key($placement['status'] ?? '')) {
            $this->not_found();
        }

        $url = PlacementFieldContract::advertiser_url($placement['link_url'] ?? '');
        if (!$url) {
            $this->not_found();
        }

        \MillionDollarScript\Core\Hooks::do('million-dollar-script/placement/click', $placement, $url);

        nocache_headers();
        ExtensionSupport::external_redirect($url, home_url('/'));
    }

    private function verify_nonce() {
        $request = wp_unslash($_REQUEST);
        $nonce = sanitize_text_field($this->param($request, 'nonce'));
        if (!wp_verify_nonce($nonce, 'mds3_grid') && !wp_verify_nonce($nonce, 'mds_grid_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'million-dollar-script')], 403);
        }
    }

    /**
     * Per-IP cooldown for public write actions. The shared guest nonce only
     * guards against cross-site forgery, so spam from a single IP is bounded
     * here instead.
     */
    private function throttle_public_write(string $bucket, int $ttl): void {
        if (ExtensionSupport::rate_limited($bucket, 'mds3_grid_write', $ttl)) {
            wp_send_json_error(['message' => __('Please wait a moment and try again.', 'million-dollar-script')], 429);
        }
    }

    private function can_access_grid($grid) {
        return $grid && (current_user_can('manage_options') || 'active' === sanitize_key((string) $grid->get('status', '')));
    }

    private function param(array $source, $key, $default = '') {
        if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
            return $default;
        }

        return (string) $source[$key];
    }

    private function param_absint(array $source, $key) {
        return absint($this->param($source, $key, 0));
    }

    private function not_found() {
        status_header(404);
        nocache_headers();
        wp_die(
            esc_html__('Advertiser link not found.', 'million-dollar-script'),
            esc_html__('Not Found', 'million-dollar-script'),
            ['response' => 404]
        );
    }
}
