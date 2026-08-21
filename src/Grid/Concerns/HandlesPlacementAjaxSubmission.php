<?php
/**
 * Frontend placement upload and submission handler.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid\Concerns;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Media\PlacementDraftRepository;
use MillionDollarScript\V3\Media\PlacementFieldContract;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Media\UploadValidator;
use MillionDollarScript\V3\Orders\CheckoutRouter;
use MillionDollarScript\V3\Orders\OrderRepository;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public AJAX entry points call verify_nonce() before reading request data.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload arrays are validated by UploadValidator before media_handle_upload().

trait HandlesPlacementAjaxSubmission {

    public function submit_placement() {
        $this->verify_nonce();

        $post = wp_unslash($_POST);
        $order_id = $this->param_absint($post, 'order_id');
        $order_key = sanitize_text_field($this->param($post, 'order_key'));
        $upload_context = sanitize_key($this->param($post, 'upload_context', 'checkout'));
        $is_manage_context = in_array($upload_context, ['manage', 'account'], true);
        $order_repo = new OrderRepository();
        $order = $order_id ? $order_repo->find($order_id) : null;

        if (!$order || !hash_equals((string) ($order['order_key'] ?? ''), $order_key)) {
            wp_send_json_error(['message' => __('Order could not be verified.', 'million-dollar-script')], 403);
        }

        $terminal_statuses = ['paid', 'cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'];
        if (in_array((string) ($order['status'] ?? ''), array_diff($terminal_statuses, ['paid']), true)) {
            wp_send_json_error(['message' => __('This order can no longer receive uploads.', 'million-dollar-script')], 409);
        }

        $rect = $order_repo->item_rect($order_id);
        if (!$rect) {
            wp_send_json_error(['message' => __('Order has no block placement target.', 'million-dollar-script')], 400);
        }

        $submission_validation = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/validate/placement/submission', true, $order, $post);
        if (is_wp_error($submission_validation)) {
            wp_send_json_error(['message' => $submission_validation->get_error_message()], 400);
        }

        $placement_repo = new PlacementRepository();
        $draft_repo = new PlacementDraftRepository();
        $current_draft = $draft_repo->current($order);
        $requested_draft_attachment_id = $this->param_absint($post, 'draft_attachment_id');
        $requested_draft_token = sanitize_text_field($this->param($post, 'draft_token'));
        if (
            ($requested_draft_attachment_id || $requested_draft_token) &&
            !$this->draft_matches($current_draft, $requested_draft_attachment_id, $requested_draft_token)
        ) {
            wp_send_json_error(['message' => __('Draft image could not be verified.', 'million-dollar-script')], 403);
        }

        $existing_placements = $placement_repo->for_order($order_id);
        $existing_placement = $existing_placements ? $existing_placements[0] : null;
        $was_unpaid = !in_array(sanitize_key((string) ($order['status'] ?? '')), $terminal_statuses, true);
        $has_upload = !empty($_FILES['image']) && is_array($_FILES['image']) && UPLOAD_ERR_NO_FILE !== absint($_FILES['image']['error'] ?? 0);
        $use_draft = !$has_upload && $current_draft && (!$existing_placement || $requested_draft_attachment_id || $requested_draft_token);
        if (!$has_upload && !$use_draft && !$existing_placement) {
            wp_send_json_error(['message' => __('Image upload is required.', 'million-dollar-script')], 400);
        }

        $attachment_id = $existing_placement ? absint($existing_placement['attachment_id'] ?? 0) : 0;
        if ($use_draft) {
            $attachment_id = absint($current_draft['attachment_id'] ?? 0);
        }
        if ($has_upload) {
            $file = $_FILES['image'];
            $filename = sanitize_file_name($file['name'] ?? '');
            $upload_check = (new UploadValidator())->validate($file, $filename, $this->settings());
            if (is_wp_error($upload_check)) {
                wp_send_json_error(['message' => $upload_check->get_error_message()], 400);
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $attachment_id = media_handle_upload('image', 0, [], [
                'test_form' => false,
                'mimes' => [
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                ],
            ]);

            if (is_wp_error($attachment_id)) {
                wp_send_json_error(['message' => $attachment_id->get_error_message()], 400);
            }
        }

        $status = 'paid' === ($order['status'] ?? '') ? 'active' : 'pending';
        $settings = $this->settings();
        $validated_fields = PlacementFieldContract::validate($post, $settings, $existing_placement ?: []);
        if (is_wp_error($validated_fields)) {
            wp_send_json_error(['message' => $validated_fields->get_error_message()], 400);
        }
        $fit_mode = $validated_fields['fit_mode'];
        $link_url = $validated_fields['link_url'];
        $alt_text = $validated_fields['alt_text'];
        $popup_text = $validated_fields['popup_text'];
        $placement = $existing_placement
            ? $placement_repo->update($existing_placement['id'], [
                'attachment_id' => $attachment_id,
                'fit_mode' => $fit_mode,
                'link_url' => $link_url,
                'alt_text' => $alt_text,
                'popup_text' => $popup_text,
                'status' => $status,
            ])
            : $placement_repo->create([
                'grid_id' => $rect['grid_id'],
                'block_id' => $rect['block_id'],
                'order_id' => $order_id,
                'user_id' => absint($order['user_id'] ?? 0),
                'attachment_id' => $attachment_id,
                'x' => $rect['x'],
                'y' => $rect['y'],
                'width' => $rect['width'],
                'height' => $rect['height'],
                'fit_mode' => $fit_mode,
                'link_url' => $link_url,
                'alt_text' => $alt_text,
                'popup_text' => $popup_text,
                'status' => $status,
            ]);

        if (is_wp_error($placement)) {
            if ($has_upload && $attachment_id) {
                wp_delete_attachment($attachment_id, true);
            }
            wp_send_json_error(['message' => $placement->get_error_message()], 500);
        }

        if (!$existing_placement) {
            $placement = $placement_repo->find($placement);
        }

        if ($use_draft) {
            $draft_repo->consume($order_repo, $order, $attachment_id, $requested_draft_token);
            $order = $order_repo->find($order_id) ?: $order;
        } elseif ($has_upload && $current_draft) {
            $draft_repo->remove($order_repo, $order, (string) ($current_draft['token'] ?? ''));
            $order = $order_repo->find($order_id) ?: $order;
        }

        [$order, $placement] = $this->finalize_standalone_manual_order($order_repo, $placement_repo, $order_id, $order, $placement ?: []);
        $checkout = (new CheckoutRouter())->payload($order);
        $refreshed_order = $order_repo->find($order_id);
        if ($refreshed_order) {
            $order = $refreshed_order;
        }
        if ('paid' === sanitize_key((string) ($order['status'] ?? '')) && !empty($placement['id']) && 'active' !== sanitize_key((string) ($placement['status'] ?? ''))) {
            $placement_repo->update_status_by_order($order_id, 'active');
            $placement = $placement_repo->find($placement['id']) ?: $placement;
        }
        $redirect_url = (!$is_manage_context && $was_unpaid) ? ($checkout['checkout_url'] ?: ($checkout['after_upload_url'] ?? '')) : '';

        \MillionDollarScript\Core\Hooks::do('million-dollar-script/placement/saved', $placement ?: [], $order, $post);

        wp_send_json_success([
            'placement' => $this->placement_payload($placement ?: []),
            'redirect_url' => esc_url_raw($redirect_url),
            'order_status' => sanitize_key((string) ($order['status'] ?? '')),
            'message' => __('Image received from the original upload.', 'million-dollar-script'),
        ]);
    }

    public function placement_draft_state() {
        $this->verify_nonce();

        $context = $this->verified_order_context(wp_unslash($_POST));
        if (is_wp_error($context)) {
            wp_send_json_error(['message' => $context->get_error_message()], $this->error_status($context, 400));
        }

        $repo = new PlacementDraftRepository();
        $draft = $repo->current($context['order']);

        wp_send_json_success([
            'draft' => $draft ? $repo->payload($draft) : null,
            'message' => $draft ? __('Draft image found.', 'million-dollar-script') : __('No draft image found.', 'million-dollar-script'),
        ]);
    }

    public function upload_placement_draft_image() {
        $this->verify_nonce();

        $context = $this->verified_order_context(wp_unslash($_POST));
        if (is_wp_error($context)) {
            wp_send_json_error(['message' => $context->get_error_message()], $this->error_status($context, 400));
        }

        $repo = new PlacementDraftRepository();
        $draft = $repo->upload($context['orders'], $context['order'], $context['rect'], 'image', $this->settings());
        if (is_wp_error($draft)) {
            wp_send_json_error(['message' => $draft->get_error_message()], $this->error_status($draft, 400));
        }

        wp_send_json_success([
            'draft' => $repo->payload($draft),
            'message' => __('Image saved for this order.', 'million-dollar-script'),
        ]);
    }

    public function remove_placement_draft_image() {
        $this->verify_nonce();

        $post = wp_unslash($_POST);
        $context = $this->verified_order_context($post);
        if (is_wp_error($context)) {
            wp_send_json_error(['message' => $context->get_error_message()], $this->error_status($context, 400));
        }

        $repo = new PlacementDraftRepository();
        $removed = $repo->remove($context['orders'], $context['order'], $this->param($post, 'draft_token'));
        if (is_wp_error($removed)) {
            wp_send_json_error(['message' => $removed->get_error_message()], $this->error_status($removed, 400));
        }

        wp_send_json_success([
            'draft' => null,
            'message' => __('Draft image removed.', 'million-dollar-script'),
        ]);
    }

    public function cleanup_released_order_draft($source, $source_id, $status, array $context = []) {
        if ('mds-grid' !== sanitize_key((string) $source)) {
            return;
        }

        $orders = new OrderRepository();
        $order = $orders->find($source_id);
        if (!$order) {
            return;
        }

        (new PlacementDraftRepository())->remove($orders, $order);
    }

    private function finalize_standalone_manual_order(OrderRepository $orders, PlacementRepository $placements, $order_id, array $order, array $placement) {
        $settings = $this->settings();
        $provider = sanitize_key((string) ($order['commerce_provider'] ?? 'standalone')) ?: 'standalone';
        $status = sanitize_key((string) ($order['status'] ?? ''));

        if ('standalone' !== $provider || !empty($settings['checkout-url']) || in_array($status, ['paid', 'cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'], true)) {
            return [$order, $placement];
        }

        $auto_complete = 'yes' === SettingsSchema::sanitize('auto-approve', $settings['auto-approve'] ?? 'no');
        $updated = $orders->update($order_id, ['status' => $auto_complete ? 'paid' : 'pending_payment']);
        if (is_wp_error($updated)) {
            return [$order, $placement];
        }

        $order = $updated;
        if ($auto_complete) {
            (new BlockRepository())->mark_by_order($order_id, 'sold');
            $placements->update_status_by_order($order_id, 'active');
            if (!empty($placement['id'])) {
                $placement = $placements->find($placement['id']) ?: $placement;
            }
        }

        return [$order, $placement];
    }

    private function fit_mode($fit_mode) {
        $fit_mode = sanitize_key((string) $fit_mode);

        return in_array($fit_mode, ['cover', 'contain'], true) ? $fit_mode : 'cover';
    }

    private function draft_matches($draft, $attachment_id, $token) {
        if (!is_array($draft)) {
            return false;
        }

        if ($attachment_id && absint($draft['attachment_id'] ?? 0) !== absint($attachment_id)) {
            return false;
        }

        $token = sanitize_text_field((string) $token);
        if ($token && !hash_equals((string) ($draft['token'] ?? ''), $token)) {
            return false;
        }

        return true;
    }

    private function error_status(\WP_Error $error, $fallback = 400) {
        $data = $error->get_error_data();

        return is_array($data) && !empty($data['status']) ? absint($data['status']) : absint($fallback);
    }

    private function verified_order_context(array $post) {
        $order_id = $this->param_absint($post, 'order_id');
        $order_key = sanitize_text_field($this->param($post, 'order_key'));
        $orders = new OrderRepository();
        $order = $order_id ? $orders->find($order_id) : null;

        if (!$order || !hash_equals((string) ($order['order_key'] ?? ''), $order_key)) {
            return new \WP_Error('mds3_order_not_verified', __('Order could not be verified.', 'million-dollar-script'), ['status' => 403]);
        }

        $terminal_statuses = ['cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'];
        if (in_array(sanitize_key((string) ($order['status'] ?? '')), $terminal_statuses, true)) {
            return new \WP_Error('mds3_order_upload_closed', __('This order can no longer receive uploads.', 'million-dollar-script'), ['status' => 409]);
        }

        $rect = $orders->item_rect($order_id);
        if (!$rect) {
            return new \WP_Error('mds3_order_missing_rect', __('Order has no block placement target.', 'million-dollar-script'), ['status' => 400]);
        }

        $grid_id = $this->param_absint($post, 'grid_id');
        if ($grid_id && absint($rect['grid_id'] ?? 0) !== $grid_id) {
            return new \WP_Error('mds3_order_grid_mismatch', __('Order does not belong to this grid.', 'million-dollar-script'), ['status' => 403]);
        }

        return [
            'orders' => $orders,
            'order' => $order,
            'rect' => $rect,
        ];
    }

}
