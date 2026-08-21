<?php
/**
 * MDS3 orders page.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$selected_rendered = false;
$status_labels = is_array($status_labels ?? null) ? $status_labels : [];
$bulk_status_labels = is_array($bulk_status_labels ?? null) ? $bulk_status_labels : [];
$order_counts = is_array($order_counts ?? null) ? $order_counts : [];
$filters = is_array($filters ?? null) ? $filters : [];
$grid_options = is_array($grid_options ?? null) ? $grid_options : [];
$provider_options = is_array($provider_options ?? null) ? $provider_options : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];
$selected_status = sanitize_key((string) ($selected_status ?? ''));
$bulk_result = is_array($bulk_result ?? null) ? $bulk_result : [];
$all_count = array_sum(array_map('absint', $order_counts));
$orderby = sanitize_key((string) ($filters['orderby'] ?? 'id'));
$order = 'asc' === strtolower((string) ($filters['order'] ?? '')) ? 'asc' : 'desc';
$current_page = max(1, absint($pagination['current'] ?? 1));
$per_page = max(1, absint($pagination['per_page'] ?? 50));
$total_orders = absint($pagination['total'] ?? 0);
$total_pages = max(1, absint($pagination['total_pages'] ?? 1));
$next_cursor = absint($pagination['next_cursor'] ?? 0);
$from = $total_orders ? (($current_page - 1) * $per_page) + 1 : 0;
$to = $total_orders ? min($total_orders, $current_page * $per_page) : 0;
$orders_url = static function (array $overrides = []) use ($filters) {
    $state = [
        'order_status' => sanitize_key((string) ($filters['status'] ?? '')),
        'grid_id' => absint($filters['grid_id'] ?? 0),
        'provider' => sanitize_key((string) ($filters['provider'] ?? '')),
        'payment_state' => sanitize_key((string) ($filters['payment_state'] ?? '')),
        'upload_state' => sanitize_key((string) ($filters['upload_state'] ?? '')),
        'expiration_state' => sanitize_key((string) ($filters['expiration_state'] ?? '')),
        'placement_state' => sanitize_key((string) ($filters['placement_state'] ?? '')),
        's' => sanitize_text_field((string) ($filters['search'] ?? '')),
        'date_from' => sanitize_text_field((string) ($filters['date_from'] ?? '')),
        'date_to' => sanitize_text_field((string) ($filters['date_to'] ?? '')),
        'orderby' => sanitize_key((string) ($filters['orderby'] ?? 'id')),
        'order' => 'asc' === strtolower((string) ($filters['order'] ?? '')) ? 'asc' : 'desc',
        'paged' => max(1, absint($filters['paged'] ?? 1)),
        'order_cursor' => absint($filters['order_cursor'] ?? 0),
    ];
    if (array_key_exists('paged', $overrides) && !array_key_exists('order_cursor', $overrides)) {
        $state['order_cursor'] = 0;
    }
    foreach ($overrides as $key => $value) {
        $state[$key] = $value;
    }

    $args = ['page' => 'mds3-orders'];
    foreach ($state as $key => $value) {
        if ('' === $value || 0 === $value || null === $value || ('paged' === $key && 1 === absint($value))) {
            continue;
        }
        if ('orderby' === $key && 'id' === $value) {
            continue;
        }
        if ('order' === $key && 'desc' === $value) {
            continue;
        }
        $args[$key] = $value;
    }

    return add_query_arg($args, admin_url('admin.php'));
};
$sort_link = static function ($key) use ($orders_url, $orderby, $order) {
    $key = sanitize_key($key);
    $next_order = ($orderby === $key && 'asc' === $order) ? 'desc' : 'asc';

    return $orders_url([
        'orderby' => $key,
        'order' => $next_order,
        'paged' => 1,
    ]);
};
$sort_label = static function ($key) use ($orderby, $order) {
    if ($orderby !== $key) {
        return '';
    }

    return 'asc' === $order ? ' ↑' : ' ↓';
};
$provider_label = static function ($provider) {
    $provider = sanitize_key((string) $provider);
    $labels = [
        'legacy_mds2' => __('Million Dollar Script 2', 'million-dollar-script'),
        'standalone' => __('Standalone', 'million-dollar-script'),
        'woocommerce' => __('WooCommerce', 'million-dollar-script'),
    ];

    return $labels[$provider] ?? ($provider ? ucwords(str_replace(['-', '_'], ' ', $provider)) : __('Standalone', 'million-dollar-script'));
};
$payment_state_options = [
    '' => __('All payment states', 'million-dollar-script'),
    'paid' => __('Paid', 'million-dollar-script'),
    'unpaid' => __('Unpaid / awaiting payment', 'million-dollar-script'),
    'failed' => __('Failed / cancelled', 'million-dollar-script'),
    'refunded' => __('Refunded', 'million-dollar-script'),
];
$upload_state_options = [
    '' => __('All upload states', 'million-dollar-script'),
    'uploaded' => __('Has upload', 'million-dollar-script'),
    'missing' => __('Missing upload', 'million-dollar-script'),
];
$expiration_state_options = [
    '' => __('All terms', 'million-dollar-script'),
    'active_term' => __('Active term', 'million-dollar-script'),
    'expired_term' => __('Expired term', 'million-dollar-script'),
    'renewable' => __('Renewable', 'million-dollar-script'),
    'renewal_started' => __('Renewal started', 'million-dollar-script'),
    'has_term' => __('Has term', 'million-dollar-script'),
    'no_term' => __('No fixed term', 'million-dollar-script'),
];
$placement_state_options = [
    '' => __('All placements', 'million-dollar-script'),
    'active' => __('Active placement', 'million-dollar-script'),
    'pending' => __('Pending placement', 'million-dollar-script'),
    'not_active' => __('No active placement', 'million-dollar-script'),
    'none' => __('No placement record', 'million-dollar-script'),
    'cancelled' => __('Cancelled placement', 'million-dollar-script'),
    'archived' => __('Archived placement', 'million-dollar-script'),
];
$order_metadata = static function (array $order) {
    $metadata = json_decode((string) ($order['metadata'] ?? ''), true);

    return is_array($metadata) ? $metadata : [];
};
$placement_summary = static function (array $order) {
    $placement_count = absint($order['placement_count'] ?? 0);
    $upload_count = absint($order['upload_count'] ?? 0);
    $active_count = absint($order['active_placement_count'] ?? 0);
    $statuses = array_filter(array_map('sanitize_key', explode(',', (string) ($order['placement_statuses'] ?? ''))));
    $status_labels = [
        'active' => __('active', 'million-dollar-script'),
        'pending' => __('pending', 'million-dollar-script'),
        'cancelled' => __('cancelled', 'million-dollar-script'),
        'archived' => __('archived', 'million-dollar-script'),
    ];
    $readable_statuses = [];

    foreach ($statuses as $status) {
        $readable_statuses[] = $status_labels[$status] ?? $status;
    }

    if (!$placement_count) {
        return [
            'primary' => __('No placement', 'million-dollar-script'),
            'secondary' => __('No image has been saved yet.', 'million-dollar-script'),
        ];
    }

    return [
        'primary' => sprintf(
            /* translators: 1: upload count, 2: placement count. */
            __('%1$s uploads; %2$s placements', 'million-dollar-script'),
            number_format_i18n($upload_count),
            number_format_i18n($placement_count)
        ),
        'secondary' => sprintf(
            /* translators: 1: active placement count, 2: comma-separated placement statuses. */
            __('%1$d active; %2$s', 'million-dollar-script'),
            $active_count,
            $readable_statuses ? implode(', ', $readable_statuses) : __('no status', 'million-dollar-script')
        ),
    ];
};
$term_summary = static function (array $order) use ($order_metadata) {
    $metadata = $order_metadata($order);
    $expires_at = sanitize_text_field((string) ($order['term_expires_at'] ?? ($metadata['expires_at'] ?? '')));
    $expires_ts = $expires_at ? strtotime($expires_at) : false;
    $retained = absint($order['retained_inventory_count'] ?? 0);
    $status = sanitize_key((string) ($order['status'] ?? ''));

    if (!$expires_ts) {
        return [
            'primary' => __('No fixed term', 'million-dollar-script'),
            'secondary' => __('Does not expire automatically.', 'million-dollar-script'),
        ];
    }

    $primary = ($expires_ts <= time() || 'expired' === $status) ? __('Expired', 'million-dollar-script') : __('Expires', 'million-dollar-script');
    $secondary = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $expires_ts);

    if ('expired' === $status && $retained) {
        $primary = __('Renewable', 'million-dollar-script');
    }
    if (!empty($metadata['renewal_started_at'])) {
        $secondary = sprintf(
            /* translators: %s: expiration date. */
            __('Renewal started; %s', 'million-dollar-script'),
            $secondary
        );
    }

    return [
        'primary' => $primary,
        'secondary' => $secondary,
    ];
};
?>
<div class="wrap mds3-admin">
    <h1><?php esc_html_e('Orders', 'million-dollar-script'); ?></h1>

    <?php if (absint($bulk_result['updated'] ?? 0) || absint($bulk_result['skipped'] ?? 0)) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: updated count, 2: skipped count, 3: status */
                    __('Updated %1$d order(s), skipped %2$d, target status: %3$s.', 'million-dollar-script'),
                    absint($bulk_result['updated'] ?? 0),
                    absint($bulk_result['skipped'] ?? 0),
                    $status_labels[sanitize_key((string) ($bulk_result['status'] ?? ''))] ?? __('Unknown', 'million-dollar-script')
                ));
                ?>
            </p>
        </div>
    <?php endif; ?>

    <section class="mds3-card">
        <nav class="mds3-order-queues" aria-label="<?php esc_attr_e('Order queues', 'million-dollar-script'); ?>">
            <a class="<?php echo '' === $selected_status ? 'is-active' : ''; ?>" href="<?php echo esc_url($orders_url(['order_status' => '', 'paged' => 1])); ?>">
                <?php /* translators: %d: total order count. */ ?>
                <?php echo esc_html(sprintf(__('All (%d)', 'million-dollar-script'), absint($all_count))); ?>
            </a>
            <?php foreach ($status_labels as $status => $label) : ?>
                <?php $count = absint($order_counts[$status] ?? 0); ?>
                <a class="<?php echo $selected_status === $status ? 'is-active' : ''; ?>" href="<?php echo esc_url($orders_url(['order_status' => sanitize_key($status), 'paged' => 1])); ?>">
                    <?php echo esc_html(sprintf('%s (%d)', $label, $count)); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <form class="mds3-order-filters" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="mds3-orders" />
            <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>" />
            <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>" />
            <label>
                <span><?php esc_html_e('Search', 'million-dollar-script'); ?></span>
                <input type="search" name="s" value="<?php echo esc_attr($filters['search'] ?? ''); ?>" placeholder="<?php esc_attr_e('Order, customer, grid', 'million-dollar-script'); ?>" />
            </label>
            <label>
                <span><?php esc_html_e('Status', 'million-dollar-script'); ?></span>
                <select name="order_status">
                    <option value=""><?php esc_html_e('All statuses', 'million-dollar-script'); ?></option>
                    <?php foreach ($status_labels as $status => $label) : ?>
                        <option value="<?php echo esc_attr($status); ?>" <?php selected($selected_status, $status); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Grid', 'million-dollar-script'); ?></span>
                <select name="grid_id">
                    <option value="0"><?php esc_html_e('All grids', 'million-dollar-script'); ?></option>
                    <?php foreach ($grid_options as $grid) : ?>
                        <?php $grid_id = is_object($grid) && method_exists($grid, 'id') ? absint($grid->id()) : 0; ?>
                        <?php if (!$grid_id) : continue; endif; ?>
                        <option value="<?php echo esc_attr($grid_id); ?>" <?php selected(absint($filters['grid_id'] ?? 0), $grid_id); ?>>
                            <?php echo esc_html(sprintf('%s (#%d)', method_exists($grid, 'get') ? $grid->get('title', __('Grid', 'million-dollar-script')) : __('Grid', 'million-dollar-script'), $grid_id)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Provider', 'million-dollar-script'); ?></span>
                <select name="provider">
                    <option value=""><?php esc_html_e('All providers', 'million-dollar-script'); ?></option>
                    <?php foreach ($provider_options as $provider) : ?>
                        <option value="<?php echo esc_attr($provider); ?>" <?php selected(sanitize_key((string) ($filters['provider'] ?? '')), $provider); ?>><?php echo esc_html($provider_label($provider)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Payment', 'million-dollar-script'); ?></span>
                <select name="payment_state">
                    <?php foreach ($payment_state_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected(sanitize_key((string) ($filters['payment_state'] ?? '')), $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Upload', 'million-dollar-script'); ?></span>
                <select name="upload_state">
                    <?php foreach ($upload_state_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected(sanitize_key((string) ($filters['upload_state'] ?? '')), $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Term', 'million-dollar-script'); ?></span>
                <select name="expiration_state">
                    <?php foreach ($expiration_state_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected(sanitize_key((string) ($filters['expiration_state'] ?? '')), $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Placement', 'million-dollar-script'); ?></span>
                <select name="placement_state">
                    <?php foreach ($placement_state_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected(sanitize_key((string) ($filters['placement_state'] ?? '')), $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('From', 'million-dollar-script'); ?></span>
                <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from'] ?? ''); ?>" />
            </label>
            <label>
                <span><?php esc_html_e('To', 'million-dollar-script'); ?></span>
                <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to'] ?? ''); ?>" />
            </label>
            <div class="mds3-order-filter-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'million-dollar-script'); ?></button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-orders')); ?>"><?php esc_html_e('Reset', 'million-dollar-script'); ?></a>
            </div>
        </form>

        <p class="mds3-order-result-count">
            <?php
            if ($total_orders) {
                /* translators: 1: first visible order number, 2: last visible order number, 3: total orders. */
                echo esc_html(sprintf(__('Showing %1$d-%2$d of %3$d orders.', 'million-dollar-script'), $from, $to, $total_orders));
            } else {
                echo esc_html__('No orders match the current filters.', 'million-dollar-script');
            }
            ?>
        </p>

        <?php if (!$orders) : ?>
            <p><?php esc_html_e('Adjust the filters or create a new order from an interactive grid.', 'million-dollar-script'); ?></p>
        <?php else : ?>
            <form id="mds3-order-bulk-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-order-bulk-form" data-confirm="<?php esc_attr_e('Apply this status to the selected orders?', 'million-dollar-script'); ?>">
                <input type="hidden" name="action" value="mds3_bulk_order_status" />
                <input type="hidden" name="selected_status" value="<?php echo esc_attr($selected_status); ?>" />
                <input type="hidden" name="return_grid_id" value="<?php echo esc_attr(absint($filters['grid_id'] ?? 0)); ?>" />
                <input type="hidden" name="return_provider" value="<?php echo esc_attr(sanitize_key((string) ($filters['provider'] ?? ''))); ?>" />
                <input type="hidden" name="return_payment_state" value="<?php echo esc_attr(sanitize_key((string) ($filters['payment_state'] ?? ''))); ?>" />
                <input type="hidden" name="return_upload_state" value="<?php echo esc_attr(sanitize_key((string) ($filters['upload_state'] ?? ''))); ?>" />
                <input type="hidden" name="return_expiration_state" value="<?php echo esc_attr(sanitize_key((string) ($filters['expiration_state'] ?? ''))); ?>" />
                <input type="hidden" name="return_placement_state" value="<?php echo esc_attr(sanitize_key((string) ($filters['placement_state'] ?? ''))); ?>" />
                <input type="hidden" name="return_s" value="<?php echo esc_attr($filters['search'] ?? ''); ?>" />
                <input type="hidden" name="return_date_from" value="<?php echo esc_attr($filters['date_from'] ?? ''); ?>" />
                <input type="hidden" name="return_date_to" value="<?php echo esc_attr($filters['date_to'] ?? ''); ?>" />
                <input type="hidden" name="return_orderby" value="<?php echo esc_attr($orderby); ?>" />
                <input type="hidden" name="return_order" value="<?php echo esc_attr($order); ?>" />
                <?php wp_nonce_field('mds3_bulk_order_status'); ?>
                <div class="mds3-order-bulk-controls">
                    <label for="mds3-bulk-status"><?php esc_html_e('Bulk status', 'million-dollar-script'); ?></label>
                    <select id="mds3-bulk-status" name="bulk_status">
                        <option value=""><?php esc_html_e('Choose status', 'million-dollar-script'); ?></option>
                        <?php foreach ($bulk_status_labels as $status => $label) : ?>
                            <option value="<?php echo esc_attr($status); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button"><?php esc_html_e('Apply to selected', 'million-dollar-script'); ?></button>
                </div>
            </form>
            <div class="mds3-order-table-scroll" tabindex="0" aria-label="<?php esc_attr_e('Orders table', 'million-dollar-script'); ?>">
            <table class="widefat striped mds3-orders-table">
                <thead>
                    <tr>
                        <td class="check-column">
                            <input type="checkbox" class="mds3-order-select-all" data-form="mds3-order-bulk-form" aria-label="<?php esc_attr_e('Select all orders on this page', 'million-dollar-script'); ?>" />
                        </td>
                        <th><a href="<?php echo esc_url($sort_link('id')); ?>"><?php echo esc_html__('ID', 'million-dollar-script') . esc_html($sort_label('id')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('status')); ?>"><?php echo esc_html__('Status', 'million-dollar-script') . esc_html($sort_label('status')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('grid')); ?>"><?php echo esc_html__('Grid', 'million-dollar-script') . esc_html($sort_label('grid')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('customer')); ?>"><?php echo esc_html__('Customer', 'million-dollar-script') . esc_html($sort_label('customer')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('total')); ?>"><?php echo esc_html__('Total', 'million-dollar-script') . esc_html($sort_label('total')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('provider')); ?>"><?php echo esc_html__('Provider', 'million-dollar-script') . esc_html($sort_label('provider')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('placement')); ?>"><?php echo esc_html__('Placement', 'million-dollar-script') . esc_html($sort_label('placement')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('term')); ?>"><?php echo esc_html__('Term', 'million-dollar-script') . esc_html($sort_label('term')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('date')); ?>"><?php echo esc_html__('Created', 'million-dollar-script') . esc_html($sort_label('date')); ?></a></th>
                        <th><?php esc_html_e('Actions', 'million-dollar-script'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order) : ?>
                        <?php $order_id = absint($order['id'] ?? 0); ?>
                        <?php $is_selected_order = $selected_order && absint($selected_order['id'] ?? 0) === $order_id; ?>
                        <?php $placement_info = $placement_summary($order); ?>
                        <?php $term_info = $term_summary($order); ?>
                        <tr>
                            <th class="check-column" scope="row">
                                <?php /* translators: %d: order ID. */ ?>
                                <input type="checkbox" form="mds3-order-bulk-form" name="order_ids[]" value="<?php echo esc_attr(absint($order['id'] ?? 0)); ?>" aria-label="<?php echo esc_attr(sprintf(__('Select order #%d', 'million-dollar-script'), absint($order['id'] ?? 0))); ?>" />
                            </th>
                            <td><?php echo esc_html($order['id']); ?></td>
                            <td><?php echo esc_html($status_labels[sanitize_key((string) ($order['status'] ?? ''))] ?? $order['status']); ?></td>
                            <td class="mds3-order-grid-cell">
                                <?php if (absint($order['grid_count'] ?? 0)) : ?>
                                    <span><?php echo esc_html((string) ($order['grid_titles'] ?? __('Grid', 'million-dollar-script'))); ?></span>
                                    <?php /* translators: %s: formatted grid count. */ ?>
                                    <small><?php echo esc_html(sprintf(_n('%s grid', '%s grids', absint($order['grid_count'] ?? 0), 'million-dollar-script'), number_format_i18n(absint($order['grid_count'] ?? 0)))); ?><?php echo !empty($order['grid_ids']) ? esc_html(' #' . $order['grid_ids']) : ''; ?></small>
                                <?php else : ?>
                                    <span><?php esc_html_e('No grid', 'million-dollar-script'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($order['email'] ?: '-'); ?></td>
                            <td><?php echo esc_html(\MillionDollarScript\V3\Commerce\Currency::format((float) $order['total'], $order['currency'] ?? '')); ?></td>
                            <td><?php echo esc_html($provider_label($order['commerce_provider'] ?: 'standalone')); ?></td>
                            <td class="mds3-order-summary-cell">
                                <span><?php echo esc_html($placement_info['primary'] ?? ''); ?></span>
                                <small><?php echo esc_html($placement_info['secondary'] ?? ''); ?></small>
                            </td>
                            <td class="mds3-order-summary-cell">
                                <span><?php echo esc_html($term_info['primary'] ?? ''); ?></span>
                                <small><?php echo esc_html($term_info['secondary'] ?? ''); ?></small>
                            </td>
                            <td><?php echo esc_html($order['created_at']); ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="button button-small mds3-order-inspect"
                                    data-order-id="<?php echo esc_attr($order_id); ?>"
                                    data-inspect-url="<?php echo esc_url($orders_url(['order_id' => $order_id])); ?>"
                                    aria-expanded="<?php echo $is_selected_order ? 'true' : 'false'; ?>"
                                    aria-controls="mds3-order-detail-<?php echo esc_attr($order_id); ?>"
                                >
                                    <span class="mds3-order-inspect-label"><?php esc_html_e('Inspect', 'million-dollar-script'); ?></span>
                                    <span class="mds3-order-inspect-indicator" aria-hidden="true"></span>
                                </button>
                                <?php $this->order_status_button($order, 'paid', __('Mark paid', 'million-dollar-script')); ?>
                                <?php $this->order_status_button($order, 'cancelled', __('Cancel', 'million-dollar-script')); ?>
                            </td>
                        </tr>
                        <?php if ($is_selected_order) : ?>
                            <tr class="mds3-order-detail-row" id="mds3-order-detail-<?php echo esc_attr($order_id); ?>" data-order-id="<?php echo esc_attr($order_id); ?>">
                                <td colspan="11">
                                    <?php $this->order_detail_panel($selected_order, true); ?>
                                </td>
                            </tr>
                            <?php $selected_rendered = true; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php if ($total_pages > 1) : ?>
                <nav class="mds3-order-pagination" aria-label="<?php esc_attr_e('Order pages', 'million-dollar-script'); ?>">
                    <?php if ($current_page > 1) : ?>
                        <a class="button" href="<?php echo esc_url($orders_url(['paged' => $current_page - 1])); ?>"><?php esc_html_e('Previous', 'million-dollar-script'); ?></a>
                    <?php endif; ?>
                    <?php /* translators: 1: current page number, 2: total page count. */ ?>
                    <span><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'million-dollar-script'), $current_page, $total_pages)); ?></span>
                    <?php if ($current_page < $total_pages) : ?>
                        <a class="button" href="<?php echo esc_url($orders_url(array_filter([
                            'paged' => $current_page + 1,
                            'order_cursor' => $next_cursor,
                        ]))); ?>"><?php esc_html_e('Next', 'million-dollar-script'); ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($selected_order && !$selected_rendered) : ?>
        <?php $this->order_detail_panel($selected_order); ?>
    <?php endif; ?>
</div>
