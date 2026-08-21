<?php
/**
 * Manage orders shortcode panel.
 *
 * @package MillionDollarScript
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="mds3-page-panel mds3-order-list-panel <?php echo esc_attr($theme_class ?? ''); ?>">
    <h2><?php echo esc_html__('Manage Pixels', 'million-dollar-script'); ?></h2>
    <?php if (!empty($requires_login)) : ?>
        <p><?php echo esc_html__('Use the private manage link from your order confirmation, or sign in to review orders connected to your account.', 'million-dollar-script'); ?></p>
        <p><a class="button" href="<?php echo esc_url($login_url ?? ''); ?>"><?php echo esc_html__('Sign in', 'million-dollar-script'); ?></a></p>
    <?php elseif (empty($orders)) : ?>
        <p><?php echo esc_html__('No active orders were found for your account.', 'million-dollar-script'); ?></p>
    <?php else : ?>
        <table class="mds3-order-list">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Order', 'million-dollar-script'); ?></th>
                    <th><?php echo esc_html__('Status', 'million-dollar-script'); ?></th>
                    <th><?php echo esc_html__('Total', 'million-dollar-script'); ?></th>
                    <th><?php echo esc_html__('Updated', 'million-dollar-script'); ?></th>
                    <th><?php echo esc_html__('Actions', 'million-dollar-script'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $item) : ?>
                    <tr>
                        <td data-label="<?php esc_attr_e('Order', 'million-dollar-script'); ?>">#<?php echo esc_html(absint($item['id'] ?? 0)); ?></td>
                        <td data-label="<?php esc_attr_e('Status', 'million-dollar-script'); ?>"><?php echo esc_html($this->status_label($item['status'] ?? '')); ?></td>
                        <td data-label="<?php esc_attr_e('Total', 'million-dollar-script'); ?>"><?php echo esc_html($this->money($item)); ?></td>
                        <td data-label="<?php esc_attr_e('Updated', 'million-dollar-script'); ?>"><?php echo esc_html(mysql2date(get_option('date_format'), (string) ($item['updated_at'] ?? $item['created_at'] ?? ''), true)); ?></td>
                        <td data-label="<?php esc_attr_e('Actions', 'million-dollar-script'); ?>"><a class="button" href="<?php echo esc_url($this->customer_order_url($item)); ?>"><?php echo esc_html__('Manage', 'million-dollar-script'); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
