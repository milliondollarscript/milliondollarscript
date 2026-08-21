<?php
/**
 * Customer-scoped placement updates for the stable extension API.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

use MillionDollarScript\V3\Orders\CustomerOrderService;
use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerPlacementService {

    /** @return array|\WP_Error */
    public function update($order_id, $placement_id, array $principal, array $data) {
        $context = $this->context($order_id, $placement_id, $principal);
        if (is_wp_error($context)) {
            return $context;
        }

        $current = $context['placement'];
        $merged = array_merge($current, array_intersect_key($data, array_flip([
            'fit_mode',
            'link_url',
            'alt_text',
            'popup_text',
        ])));
        $validated = PlacementFieldContract::validate($merged, $this->settings(), $current);
        if (is_wp_error($validated)) {
            return $validated;
        }

        $hook_data = array_merge($data, [
            'upload_context' => 'account',
            'order_id' => absint($order_id),
            'placement_id' => absint($placement_id),
        ]);
        $submission = \MillionDollarScript\Core\Hooks::apply(
            'million-dollar-script/validate/placement/submission',
            true,
            $context['order'],
            $hook_data
        );
        if (is_wp_error($submission)) {
            return $submission;
        }

        $placement = (new PlacementRepository())->update($placement_id, $validated);
        if (is_wp_error($placement)) {
            return $placement;
        }

        \MillionDollarScript\Core\Hooks::do('million-dollar-script/placement/saved', $placement, $context['order'], $hook_data);

        return $this->payload($placement);
    }

    /**
     * Replace placement artwork from a verified PHP upload array.
     *
     * @return array|\WP_Error
     */
    public function replace_image($order_id, $placement_id, array $principal, array $file) {
        $context = $this->context($order_id, $placement_id, $principal);
        if (is_wp_error($context)) {
            return $context;
        }

        $filename = sanitize_file_name((string) ($file['name'] ?? ''));
        $check = (new UploadValidator())->validate($file, $filename, $this->settings());
        if (is_wp_error($check)) {
            return $check;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_sideload($file, 0);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $placement = (new PlacementRepository())->update($placement_id, ['attachment_id' => absint($attachment_id)]);
        if (is_wp_error($placement)) {
            wp_delete_attachment(absint($attachment_id), true);
            return $placement;
        }

        $hook_data = [
            'upload_context' => 'account',
            'order_id' => absint($order_id),
            'placement_id' => absint($placement_id),
            'attachment_id' => absint($attachment_id),
            'previous_attachment_id' => absint($context['placement']['attachment_id'] ?? 0),
        ];
        \MillionDollarScript\Core\Hooks::do('million-dollar-script/placement/saved', $placement, $context['order'], $hook_data);

        return $this->payload($placement);
    }

    /** @return array|\WP_Error */
    private function context($order_id, $placement_id, array $principal) {
        $order = (new CustomerOrderService())->internal_order($order_id, $principal);
        if (is_wp_error($order)) {
            return $order;
        }
        if (!$order) {
            return new \WP_Error('million_dollar_script_order_not_found', __('Placement could not be found.', 'million-dollar-script'));
        }

        $status = sanitize_key((string) ($order['status'] ?? ''));
        if (in_array($status, ['cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'], true)) {
            return new \WP_Error('million_dollar_script_placement_closed', __('This placement can no longer be changed.', 'million-dollar-script'));
        }

        $placement = (new PlacementRepository())->find(absint($placement_id));
        if (!$placement || absint($placement['order_id'] ?? 0) !== absint($order_id)) {
            return new \WP_Error('million_dollar_script_placement_not_found', __('Placement could not be found.', 'million-dollar-script'));
        }

        return [
            'order' => $order,
            'placement' => $placement,
        ];
    }

    private function payload(array $placement) {
        $attachment_id = absint($placement['attachment_id'] ?? 0);

        return [
            'id' => absint($placement['id'] ?? 0),
            'grid_id' => absint($placement['grid_id'] ?? 0),
            'attachment_id' => $attachment_id,
            'image_url' => $attachment_id ? esc_url_raw((string) wp_get_attachment_image_url($attachment_id, 'large')) : '',
            'fit_mode' => sanitize_key((string) ($placement['fit_mode'] ?? 'cover')),
            'link_url' => esc_url_raw((string) ($placement['link_url'] ?? '')),
            'alt_text' => sanitize_text_field((string) ($placement['alt_text'] ?? '')),
            'popup_text' => wp_kses_post((string) ($placement['popup_text'] ?? '')),
            'status' => sanitize_key((string) ($placement['status'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($placement['updated_at'] ?? '')),
        ];
    }

    private function settings() {
        $stored = get_option('mds3_settings', []);

        return wp_parse_args(is_array($stored) ? $stored : [], SettingsSchema::defaults());
    }
}
