<?php
/**
 * Trusted scheduling operations for existing placement visibility.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

if (!defined('ABSPATH')) {
    exit;
}

final class PlacementSchedulingService {
    /** @return array{items:array,total:int,limit:int,offset:int} */
    public function query(array $args = []): array {
        $result = (new PlacementRepository())->query_for_scheduling($args);
        $result['items'] = array_map([$this, 'payload'], $result['items']);

        return $result;
    }

    /** @return array|\WP_Error|null */
    public function find($placement_id) {
        $placement = (new PlacementRepository())->find_for_scheduling(absint($placement_id));

        return $placement ? $this->payload($placement) : null;
    }

    /** @return true|\WP_Error */
    public function preflight($placement_id, $activate = true) {
        $placement = (new PlacementRepository())->find_for_scheduling(absint($placement_id));
        if (!$placement) {
            return new \WP_Error('million_dollar_script_schedule_placement_missing', __('The scheduled placement could not be found.', 'million-dollar-script'));
        }
        if (!$activate) {
            return true;
        }
        if ('active' !== sanitize_key((string) ($placement['grid_status'] ?? ''))) {
            return new \WP_Error('million_dollar_script_schedule_grid_inactive', __('The placement grid is not active.', 'million-dollar-script'));
        }
        if ('paid' !== sanitize_key((string) ($placement['order_status'] ?? ''))) {
            return new \WP_Error('million_dollar_script_schedule_order_unpaid', __('The linked order must be paid before this placement can be activated.', 'million-dollar-script'));
        }
        if (!absint($placement['attachment_id'] ?? 0)) {
            return new \WP_Error('million_dollar_script_schedule_image_missing', __('The placement needs an approved image before it can be activated.', 'million-dollar-script'));
        }

        $x = max(0, (int) ($placement['x'] ?? 0));
        $y = max(0, (int) ($placement['y'] ?? 0));
        $width = max(1, absint($placement['width'] ?? 1));
        $height = max(1, absint($placement['height'] ?? 1));
        if (
            $x + $width > absint($placement['grid_width'] ?? 0)
            || $y + $height > absint($placement['grid_height'] ?? 0)
        ) {
            return new \WP_Error('million_dollar_script_schedule_out_of_bounds', __('The placement is outside the current grid bounds.', 'million-dollar-script'));
        }

        $block_id = absint($placement['block_id'] ?? 0);
        if ($block_id) {
            $block_status = sanitize_key((string) ($placement['block_status'] ?? ''));
            $block_order_id = absint($placement['block_order_id'] ?? 0);
            if (!in_array($block_status, ['sold', 'reserved'], true) || $block_order_id !== absint($placement['order_id'] ?? 0)) {
                return new \WP_Error('million_dollar_script_schedule_inventory_changed', __('The placement no longer owns its reserved grid inventory.', 'million-dollar-script'));
            }
        }

        return true;
    }

    /** @return array|\WP_Error */
    public function set_visible($placement_id, $visible, array $context = []) {
        $placement_id = absint($placement_id);
        $visible = (bool) $visible;
        $preflight = $this->preflight($placement_id, $visible);
        if (is_wp_error($preflight)) {
            return $preflight;
        }

        $before = (new PlacementRepository())->find_for_scheduling($placement_id);
        $from = sanitize_key((string) ($before['status'] ?? ''));
        $to = $visible ? 'active' : 'pending';
        if ($from === $to) {
            return $this->payload($before);
        }
        if (in_array($from, ['cancelled'], true)) {
            return new \WP_Error('million_dollar_script_schedule_placement_closed', __('A cancelled placement cannot be scheduled.', 'million-dollar-script'));
        }

        $updated = (new PlacementRepository())->transition_for_scheduling(
            $placement_id,
            $to,
            $visible ? ['pending', 'archived'] : ['active']
        );
        if (is_wp_error($updated)) {
            return $updated;
        }

        $payload = $this->payload($updated);
        \MillionDollarScript\Core\Hooks::do(
            'million-dollar-script/placement/schedule/visibility-changed',
            $payload,
            $from,
            $to,
            $this->context($context)
        );

        return $payload;
    }

    /** @return array<string,mixed> */
    private function payload(array $placement): array {
        return [
            'id' => absint($placement['id'] ?? 0),
            'resource_ref' => 'core-placement:' . absint($placement['id'] ?? 0),
            'order_id' => absint($placement['order_id'] ?? 0),
            'grid_id' => absint($placement['grid_id'] ?? 0),
            'grid_title' => sanitize_text_field((string) ($placement['grid_title'] ?? '')),
            'grid_status' => sanitize_key((string) ($placement['grid_status'] ?? '')),
            'order_status' => sanitize_key((string) ($placement['order_status'] ?? '')),
            'currency' => strtoupper(substr(sanitize_text_field((string) ($placement['order_currency'] ?? $placement['grid_currency'] ?? '')), 0, 3)),
            'owner_user_id' => absint($placement['order_user_id'] ?? $placement['user_id'] ?? 0),
            'owner_email' => sanitize_email((string) ($placement['order_email'] ?? '')),
            'attachment_id' => absint($placement['attachment_id'] ?? 0),
            'image_url' => esc_url_raw((string) wp_get_attachment_image_url(absint($placement['attachment_id'] ?? 0), 'medium')),
            'alt_text' => sanitize_text_field((string) ($placement['alt_text'] ?? '')),
            'link_url' => esc_url_raw((string) ($placement['link_url'] ?? '')),
            'status' => sanitize_key((string) ($placement['status'] ?? '')),
            'x' => max(0, (int) ($placement['x'] ?? 0)),
            'y' => max(0, (int) ($placement['y'] ?? 0)),
            'width' => max(1, absint($placement['width'] ?? 1)),
            'height' => max(1, absint($placement['height'] ?? 1)),
            'updated_at' => sanitize_text_field((string) ($placement['updated_at'] ?? '')),
        ];
    }

    /** @return array<string,mixed> */
    private function context(array $context): array {
        return [
            'source' => sanitize_key((string) ($context['source'] ?? 'extension')),
            'campaign_id' => absint($context['campaign_id'] ?? 0),
            'actor_user_id' => absint($context['actor_user_id'] ?? 0),
        ];
    }
}
