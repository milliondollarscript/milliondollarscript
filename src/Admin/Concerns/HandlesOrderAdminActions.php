<?php
/**
 * Admin order actions.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Extensions\ExtensionSupport;

use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Orders\OrderPlacementMover;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\OrderRenewal;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesOrderAdminActions {

    public function update_order_status() {
        $order_id = absint($_POST['order_id'] ?? 0);
        $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));
        check_admin_referer('mds3_update_order_status_' . $order_id . '_' . $status);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        if (in_array($status, OrderRepository::statuses(), true)) {
            $order = (new OrderRepository())->find($order_id);
            if ($order) {
                $this->record_order_status_event($order, $status, 'admin');
            }
            Payments::mark_source_status('mds-grid', $order_id, $status, ['source' => 'admin']);
            if ('paid' === $status) {
                Payments::complete_provider_order_for_mds_order($order_id);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=mds3-orders&order_id=' . $order_id));
        exit;
    }

    public function bulk_order_status() {
        check_admin_referer('mds3_bulk_order_status');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $status = sanitize_key(wp_unslash($_POST['bulk_status'] ?? ''));
        $selected_status = sanitize_key(wp_unslash($_POST['selected_status'] ?? ''));
        $raw_ids = array_map('absint', (array) wp_unslash($_POST['order_ids'] ?? []));
        if (!is_array($raw_ids)) {
            $raw_ids = [$raw_ids];
        }

        $order_ids = array_values(array_unique(array_filter($raw_ids)));
        $updated = 0;
        $skipped = 0;
        $repo = new OrderRepository();

        if ($status && in_array($status, $this->order_bulk_statuses(), true) && $order_ids) {
            foreach ($order_ids as $order_id) {
                $order = $repo->find($order_id);
                if (!$order || $status === sanitize_key((string) ($order['status'] ?? ''))) {
                    $skipped++;
                    continue;
                }

                $this->record_order_status_event($order, $status, 'admin-bulk');
                if (Payments::mark_source_status('mds-grid', $order_id, $status, [
                    'source' => 'admin-bulk',
                    'admin_user_id' => get_current_user_id(),
                ])) {
                    if ('paid' === $status) {
                        Payments::complete_provider_order_for_mds_order($order_id);
                    }
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        } else {
            $skipped = count($order_ids);
        }

        $redirect_args = [
            'page' => 'mds3-orders',
            'bulk_updated' => $updated,
            'bulk_skipped' => $skipped,
            'bulk_status' => $status,
        ];
        if ($selected_status && in_array($selected_status, OrderRepository::statuses(), true)) {
            $redirect_args['order_status'] = $selected_status;
        }
        $return_grid_id = absint($_POST['return_grid_id'] ?? 0);
        if ($return_grid_id) {
            $redirect_args['grid_id'] = $return_grid_id;
        }
        $return_provider = sanitize_key(wp_unslash($_POST['return_provider'] ?? ''));
        if ($return_provider) {
            $redirect_args['provider'] = $return_provider;
        }
        $return_payment_state = sanitize_key(wp_unslash($_POST['return_payment_state'] ?? ''));
        if (in_array($return_payment_state, ['paid', 'unpaid', 'failed', 'refunded'], true)) {
            $redirect_args['payment_state'] = $return_payment_state;
        }
        $return_upload_state = sanitize_key(wp_unslash($_POST['return_upload_state'] ?? ''));
        if (in_array($return_upload_state, ['uploaded', 'missing'], true)) {
            $redirect_args['upload_state'] = $return_upload_state;
        }
        $return_expiration_state = sanitize_key(wp_unslash($_POST['return_expiration_state'] ?? ''));
        if (in_array($return_expiration_state, ['active_term', 'expired_term', 'renewable', 'renewal_started', 'has_term', 'no_term'], true)) {
            $redirect_args['expiration_state'] = $return_expiration_state;
        }
        $return_placement_state = sanitize_key(wp_unslash($_POST['return_placement_state'] ?? ''));
        if (in_array($return_placement_state, ['active', 'pending', 'cancelled', 'archived', 'none', 'not_active'], true)) {
            $redirect_args['placement_state'] = $return_placement_state;
        }
        $return_search = sanitize_text_field(wp_unslash($_POST['return_s'] ?? ''));
        if ('' !== $return_search) {
            $redirect_args['s'] = $return_search;
        }
        foreach (['date_from', 'date_to'] as $date_key) {
            $date = sanitize_text_field(wp_unslash($_POST['return_' . $date_key] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $redirect_args[$date_key] = $date;
            }
        }
        $return_orderby = sanitize_key(wp_unslash($_POST['return_orderby'] ?? ''));
        if (in_array($return_orderby, ['id', 'status', 'customer', 'total', 'provider', 'date', 'grid', 'placement', 'term'], true) && 'id' !== $return_orderby) {
            $redirect_args['orderby'] = $return_orderby;
        }
        $return_order = 'asc' === strtolower(sanitize_text_field(wp_unslash($_POST['return_order'] ?? ''))) ? 'asc' : 'desc';
        if ('desc' !== $return_order) {
            $redirect_args['order'] = $return_order;
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    public function start_order_renewal() {
        $order_id = absint($_POST['order_id'] ?? 0);
        check_admin_referer('mds3_start_order_renewal_' . $order_id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $order = (new OrderRepository())->find($order_id);
        if ($order) {
            $result = (new OrderRenewal())->start($order, [
                'source' => 'admin',
                'force_new_checkout' => true,
            ]);
            if (!is_wp_error($result)) {
                $checkout = is_array($result['checkout'] ?? null) ? $result['checkout'] : [];
                $redirect_url = esc_url_raw((string) ($checkout['checkout_url'] ?? ($checkout['after_upload_url'] ?? '')));
                if ($redirect_url) {
                    ExtensionSupport::external_redirect($redirect_url, admin_url('admin.php?page=mds3-orders&order_id=' . $order_id));
                }
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=mds3-orders&order_id=' . $order_id));
        exit;
    }

    public function ajax_order_detail() {
        check_ajax_referer('mds3_order_detail', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'million-dollar-script')], 403);
        }

        $order_id = absint($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error(['message' => __('Invalid order ID.', 'million-dollar-script')], 400);
        }

        $order = (new OrderRepository())->find($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'million-dollar-script')], 404);
        }

        ob_start();
        $this->order_detail_panel($order, true);
        $html = ob_get_clean();

        wp_send_json_success([
            'order_id' => $order_id,
            'html' => $html,
        ]);
    }

    public function ajax_preview_order_move() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'million-dollar-script')], 403);
        }

        $order_id = absint($_POST['order_id'] ?? 0);
        $grid_id = absint($_POST['grid_id'] ?? 0);
        check_ajax_referer('mds3_preview_order_move_' . $order_id . '_' . $grid_id, 'nonce');
        $target_row = $this->order_move_coordinate(wp_unslash($_POST['row_from'] ?? null));
        $target_col = $this->order_move_coordinate(wp_unslash($_POST['col_from'] ?? null));
        if (null === $target_row || null === $target_col) {
            wp_send_json_error(['message' => __('Choose a valid target row and column.', 'million-dollar-script')], 400);
        }

        $preview = (new OrderPlacementMover())->preview($order_id, $grid_id, $target_row, $target_col);
        if (is_wp_error($preview)) {
            wp_send_json_error(['message' => $preview->get_error_message()]);
        }

        wp_send_json_success(array_merge($preview, $this->order_move_preview_messages($preview)));
    }

    public function move_order_placement() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        $order_id = absint($_POST['order_id'] ?? 0);
        $grid_id = absint($_POST['grid_id'] ?? 0);
        check_admin_referer('mds3_move_order_placement_' . $order_id . '_' . $grid_id);
        $target_row = $this->order_move_coordinate(wp_unslash($_POST['row_from'] ?? null));
        $target_col = $this->order_move_coordinate(wp_unslash($_POST['col_from'] ?? null));

        if (null === $target_row || null === $target_col) {
            $result = new \WP_Error('mds3_order_move_invalid_target', __('Choose a valid target row and column.', 'million-dollar-script'));
        } else {
            $result = (new OrderPlacementMover())->move($order_id, $grid_id, $target_row, $target_col, [
                'source' => 'admin',
                'user_id' => get_current_user_id(),
            ]);
        }

        if (is_wp_error($result)) {
            set_transient($this->order_move_notice_key($order_id), [
                'message' => $result->get_error_message(),
                'type' => 'error',
            ], MINUTE_IN_SECONDS);
        } else {
            $block_count = absint($result['block_count'] ?? 0);
            set_transient($this->order_move_notice_key($order_id), [
                'message' => sprintf(
                    /* translators: 1: block count, 2: target row, 3: target column. */
                    _n(
                        'Placement moved successfully. %1$s block now begins at row %2$s, column %3$s. The order total and status were preserved.',
                        'Placement moved successfully. %1$s blocks now begin at row %2$s, column %3$s. The order total and status were preserved.',
                        $block_count,
                        'million-dollar-script'
                    ),
                    number_format_i18n($block_count),
                    number_format_i18n(absint($result['target_origin']['row'] ?? 0)),
                    number_format_i18n(absint($result['target_origin']['col'] ?? 0))
                ),
                'type' => 'success',
            ], MINUTE_IN_SECONDS);
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-orders',
            'order_id' => $order_id,
        ], admin_url('admin.php')));
        exit;
    }

    private function order_move_preview_messages(array $preview) {
        $currency = Currency::code($preview['currency'] ?? '');
        $difference = (float) ($preview['price_difference'] ?? 0);
        $status_labels = $this->order_status_labels();
        $status = sanitize_key((string) ($preview['order_status'] ?? ''));
        $target = is_array($preview['target_origin'] ?? null) ? $preview['target_origin'] : [];
        $block_count = absint($preview['block_count'] ?? 0);

        $price_message = abs($difference) < 0.005
            ? sprintf(
                /* translators: %s: formatted placement price. */
                __('The placement list price remains %s.', 'million-dollar-script'),
                Currency::format((float) ($preview['target_list_price'] ?? 0), $currency)
            )
            : sprintf(
                /* translators: 1: current price, 2: target price, 3: difference, 4: existing order total. */
                __('Current item price: %1$s. Target list price: %2$s (%3$s difference). The existing order total of %4$s will not change.', 'million-dollar-script'),
                Currency::format((float) ($preview['current_list_price'] ?? 0), $currency),
                Currency::format((float) ($preview['target_list_price'] ?? 0), $currency),
                Currency::format($difference, $currency),
                Currency::format((float) ($preview['order_total'] ?? 0), $currency)
            );

        return [
            'message' => sprintf(
                /* translators: 1: block count, 2: target row, 3: target column. */
                _n(
                    'Placement is available. %1$s block will begin at row %2$s, column %3$s.',
                    'Placement is available. %1$s blocks will begin at row %2$s, column %3$s.',
                    $block_count,
                    'million-dollar-script'
                ),
                number_format_i18n($block_count),
                number_format_i18n(absint($target['row'] ?? 0)),
                number_format_i18n(absint($target['col'] ?? 0))
            ),
            'price_message' => $price_message,
            'state_message' => sprintf(
                /* translators: %s: order status label. */
                __('The order will remain %s, and linked placement artwork will move with the inventory.', 'million-dollar-script'),
                $status_labels[$status] ?? $status
            ),
        ];
    }

    private function order_move_coordinate($value) {
        $value = trim((string) wp_unslash(null === $value ? '' : $value));

        return preg_match('/^\d+$/', $value) ? (int) $value : null;
    }

    private function order_move_notice_key($order_id) {
        return 'mds3_order_move_notice_' . get_current_user_id() . '_' . absint($order_id);
    }

    private function record_order_status_event(array $order, $status, $source) {
        $repo = new OrderRepository();
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $events = is_array($metadata['status_events'] ?? null) ? $metadata['status_events'] : [];
        $events[] = [
            'created_at' => gmdate('Y-m-d H:i:s'),
            'source' => sanitize_key((string) $source),
            'status' => sanitize_key((string) $status),
            'user_id' => get_current_user_id(),
        ];

        $metadata['status_events'] = array_slice($events, -25);
        $repo->update(absint($order['id'] ?? 0), ['metadata' => $metadata]);
    }
}
