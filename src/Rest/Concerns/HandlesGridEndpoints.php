<?php
/**
 * Grid REST endpoints and payload helpers.
 *
 * @package MillionDollarScript\V3\Rest
 */

namespace MillionDollarScript\V3\Rest\Concerns;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Grid\PackageRepository;
use MillionDollarScript\V3\Grid\PriceRuleRepository;
use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\OriginalImage;
use MillionDollarScript\V3\Media\PlacementFieldContract;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\CheckoutRouter;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\ReservationService;
use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesGridEndpoints {

    public function grids() {
        $can_manage = current_user_can('manage_options');
        $grids = array_filter((new GridRepository())->all(), static function ($grid) use ($can_manage) {
            return $can_manage || 'active' === sanitize_key((string) $grid->get('status', ''));
        });

        return array_map([$this, 'grid_payload'], array_values($grids));
    }

    public function create_grid(\WP_REST_Request $request) {
        $created = (new GridRepository())->create($request->get_json_params() ?: $request->get_params());

        return is_wp_error($created) ? $created : $created->to_array();
    }

    public function grid(\WP_REST_Request $request) {
        $grid = (new GridRepository())->find($request['id']);

        if (!$grid || (!current_user_can('manage_options') && 'active' !== sanitize_key((string) $grid->get('status', '')))) {
            return new \WP_Error('mds3_grid_not_found', __('Grid not found.', 'million-dollar-script'), ['status' => 404]);
        }

        return current_user_can('manage_options') ? $grid->to_array() : $this->grid_payload($grid);
    }

    public function update_grid(\WP_REST_Request $request) {
        $updated = (new GridRepository())->update($request['id'], $request->get_json_params() ?: $request->get_params());

        return is_wp_error($updated) ? $updated : $updated->to_array();
    }

    public function archive_grid(\WP_REST_Request $request) {
        $updated = (new GridRepository())->archive($request['id']);

        return is_wp_error($updated) ? $updated : $updated->to_array();
    }

    public function blocks(\WP_REST_Request $request) {
        $grid = $this->readable_grid($request['id']);
        if (is_wp_error($grid)) {
            return $grid;
        }

        return array_map([$this, 'block_payload'], (new BlockRepository())->for_grid($request['id']));
    }

    public function placements(\WP_REST_Request $request) {
        $grid = $this->readable_grid($request['id']);
        if (is_wp_error($grid)) {
            return $grid;
        }

        $statuses = current_user_can('manage_options') ? [] : ['active'];

        return array_map([$this, 'placement_payload'], (new PlacementRepository())->for_grid($request['id'], $statuses));
    }

    public function create_placement(\WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $params['grid_id'] = absint($request['id']);
        $stored = get_option('mds3_settings', []);
        $settings = wp_parse_args(is_array($stored) ? $stored : [], SettingsSchema::defaults());
        $validated = PlacementFieldContract::validate($params, $settings);
        if (is_wp_error($validated)) {
            return $validated;
        }
        $params = array_merge($params, $validated);
        $created = (new PlacementRepository())->create($params);

        return is_wp_error($created) ? $created : ['id' => $created];
    }

    public function create_reservation(\WP_REST_Request $request) {
        $grid = (new GridRepository())->find($request['id']);
        if (!$grid) {
            return new \WP_Error('mds3_grid_not_found', __('Grid not found.', 'million-dollar-script'), ['status' => 404]);
        }

        $params = $request->get_json_params() ?: $request->get_params();
        $blocks = is_array($params['blocks'] ?? null) ? $params['blocks'] : [];
        $result = (new ReservationService())->reserve($grid, $blocks, [
            'email' => sanitize_email($params['email'] ?? ''),
            'user_id' => absint($params['user_id'] ?? get_current_user_id()),
            'package_id' => absint($params['package_id'] ?? 0),
            'metadata' => $this->reservation_metadata($params),
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        if (is_array($result) && is_array($result['order'] ?? null)) {
            $result['checkout'] = (new CheckoutRouter())->payload($result['order']);
        }

        return $result;
    }

    private function reservation_metadata(array $params): array {
        $metadata = is_array($params['metadata'] ?? null) ? $params['metadata'] : [];
        $metadata['subscription_plan_id'] = absint(
            $params['subscription_plan_id'] ?? ($metadata['subscription_plan_id'] ?? 0)
        );

        return $metadata;
    }

    public function update_availability(\WP_REST_Request $request) {
        $grid = (new GridRepository())->find($request['id']);
        if (!$grid) {
            return new \WP_Error('mds3_grid_not_found', __('Grid not found.', 'million-dollar-script'), ['status' => 404]);
        }

        $params = $request->get_json_params() ?: $request->get_params();
        $result = (new BlockRepository())->set_region_status($grid, $params, sanitize_key($params['status'] ?? 'unavailable'), [
            'note' => sanitize_text_field($params['note'] ?? ''),
        ]);

        return is_wp_error($result) ? $result : $result;
    }

    public function packages(\WP_REST_Request $request) {
        return (new PackageRepository())->for_grid($request['id']);
    }

    public function save_package(\WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $params['grid_id'] = absint($request['id']);
        $result = (new PackageRepository())->save($params);

        return is_wp_error($result) ? $result : $result;
    }

    public function price_rules(\WP_REST_Request $request) {
        return (new PriceRuleRepository())->for_grid($request['id']);
    }

    public function save_price_rule(\WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $params['grid_id'] = absint($request['id']);
        $result = (new PriceRuleRepository())->save($params);

        return is_wp_error($result) ? $result : $result;
    }

    private function grid_payload($grid) {
        $geometry = $grid->geometry();

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/rest/public/grid/payload', [
            'id' => $grid->id(),
            'slug' => sanitize_title((string) $grid->get('slug', '')),
            'title' => sanitize_text_field((string) $grid->get('title', '')),
            'description' => wp_kses_post((string) $grid->get('description', '')),
            'width' => absint($grid->get('width', 0)),
            'height' => absint($grid->get('height', 0)),
            'block_width' => absint($grid->get('block_width', 0)),
            'block_height' => absint($grid->get('block_height', 0)),
            'price_per_block' => (float) $grid->get('price_per_block', 0),
            'currency' => sanitize_text_field((string) $grid->get('currency', '')),
            'status' => sanitize_key((string) $grid->get('status', '')),
            'renderer_mode' => GridRepository::normalize_renderer_mode($grid->settings()['renderer_mode'] ?? 'auto'),
            'virtual_blocks' => [
                'rows' => $geometry->rows(),
                'columns' => $geometry->columns(),
                'total' => $geometry->total_blocks(),
            ],
        ], $grid);
    }

    private function readable_grid($id) {
        $grid = (new GridRepository())->find($id);
        if (!$grid || (!current_user_can('manage_options') && 'active' !== sanitize_key((string) $grid->get('status', '')))) {
            return new \WP_Error('mds3_grid_not_found', __('Grid not found.', 'million-dollar-script'), ['status' => 404]);
        }

        return $grid;
    }

    private function block_payload(array $block) {
        return [
            'id' => absint($block['id'] ?? 0),
            'grid_id' => absint($block['grid_id'] ?? 0),
            'x' => absint($block['x'] ?? 0),
            'y' => absint($block['y'] ?? 0),
            'width' => absint($block['width'] ?? 0),
            'height' => absint($block['height'] ?? 0),
            'status' => sanitize_key((string) ($block['status'] ?? 'available')),
        ];
    }

    private function placement_payload(array $placement) {
        $source = (new OriginalImage())->resolve($placement['attachment_id'] ?? 0);
        $advertiser_page_url = (new AdvertiserPageManager())->public_url(absint($placement['id'] ?? 0));

        return [
            'id' => absint($placement['id'] ?? 0),
            'grid_id' => absint($placement['grid_id'] ?? 0),
            'block_id' => absint($placement['block_id'] ?? 0),
            'attachment_id' => absint($placement['attachment_id'] ?? 0),
            'x' => absint($placement['x'] ?? 0),
            'y' => absint($placement['y'] ?? 0),
            'width' => absint($placement['width'] ?? 0),
            'height' => absint($placement['height'] ?? 0),
            'fit_mode' => sanitize_key((string) ($placement['fit_mode'] ?? 'cover')),
            'link_url' => esc_url_raw((string) ($placement['link_url'] ?? '')),
            'alt_text' => sanitize_text_field((string) ($placement['alt_text'] ?? '')),
            'popup_text' => sanitize_textarea_field(wp_strip_all_tags((string) ($placement['popup_text'] ?? ''))),
            'advertiser_page_url' => esc_url_raw($advertiser_page_url),
            'status' => sanitize_key((string) ($placement['status'] ?? 'pending')),
            'source' => [
                'url' => esc_url_raw((string) ($source['url'] ?? '')),
                'width' => absint($source['width'] ?? 0),
                'height' => absint($source['height'] ?? 0),
                'megapixels' => (float) ($source['megapixels'] ?? 0),
                'mime_type' => sanitize_text_field($source['mime_type'] ?? ''),
            ],
            'mask' => $this->placement_mask_payload(absint($placement['order_id'] ?? 0)),
        ];
    }

    private function placement_mask_payload($order_id) {
        if (!$order_id) {
            return [];
        }

        $mask = [];
        foreach ((new OrderRepository())->items($order_id) as $item) {
            $metadata = json_decode((string) ($item['metadata'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];
            $x = array_key_exists('x', $metadata) ? absint($metadata['x']) : null;
            $y = array_key_exists('y', $metadata) ? absint($metadata['y']) : null;
            $width = absint($metadata['width'] ?? 0);
            $height = absint($metadata['height'] ?? 0);

            if (null === $x || null === $y || !$width || !$height) {
                continue;
            }

            $mask[] = [
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $mask;
    }
}
