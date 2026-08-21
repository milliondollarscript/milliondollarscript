<?php
/**
 * Dashboard recent orders table.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (!$orders) : ?>
    <p><?php esc_html_e('No orders have been created yet.', 'million-dollar-script'); ?></p>
<?php else : ?>
    <div class="mds3-dashboard-table-scroll" tabindex="0" aria-label="<?php esc_attr_e('Recent orders', 'million-dollar-script'); ?>">
        <table class="widefat striped mds3-dashboard-orders">
            <thead>
                <tr>
                    <th><?php esc_html_e('Order', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Status', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Customer', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Total', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Created', 'million-dollar-script'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order) : ?>
                    <?php $created = (string) ($order['created_at'] ?? ''); ?>
                    <tr>
                        <td><a href="<?php echo esc_url(admin_url('admin.php?page=mds3-orders&order_id=' . absint($order['id'] ?? 0))); ?>">#<?php echo esc_html(absint($order['id'] ?? 0)); ?></a></td>
                        <td><?php echo esc_html($this->dashboard_status_label($order['status'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($order['email'] ?? '') ?: __('Account user', 'million-dollar-script')); ?></td>
                        <td><?php echo esc_html(\MillionDollarScript\V3\Commerce\Currency::format((float) ($order['total'] ?? 0), $order['currency'] ?? '')); ?></td>
                        <td><?php echo esc_html($created ? mysql2date(get_option('date_format'), $created, true) : '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
