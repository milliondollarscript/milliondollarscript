<?php
/**
 * Order summary shortcode panel.
 *
 * @package MillionDollarScript
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="mds3-page-panel mds3-order-summary-panel <?php echo esc_attr($theme_class ?? ''); ?>">
    <h2><?php echo esc_html($title ?? __('Order Summary', 'million-dollar-script')); ?></h2>
    <dl class="mds3-order-summary">
        <div><dt><?php echo esc_html__('Order', 'million-dollar-script'); ?></dt><dd>#<?php echo esc_html(absint($order['id'] ?? 0)); ?></dd></div>
        <div><dt><?php echo esc_html__('Status', 'million-dollar-script'); ?></dt><dd><?php echo esc_html($this->status_label($order['status'] ?? '')); ?></dd></div>
        <div><dt><?php echo esc_html__('Total', 'million-dollar-script'); ?></dt><dd><?php echo esc_html($this->money($order)); ?></dd></div>
        <?php if (!empty($term_expires_at)) : ?>
            <div><dt><?php echo esc_html__('Term', 'million-dollar-script'); ?></dt><dd><?php echo esc_html($term_expires_at); ?></dd></div>
        <?php endif; ?>
    </dl>
    <?php if (!empty($renewal_error)) : ?>
        <p class="mds3-grid-status mds3-grid-status-error"><?php echo esc_html($renewal_error); ?></p>
    <?php elseif (!empty($renewal_notice)) : ?>
        <p class="mds3-grid-status"><?php echo esc_html($renewal_notice); ?></p>
    <?php endif; ?>
    <?php if (!empty($cleanup_notice)) : ?>
        <p class="mds3-grid-status"><?php echo esc_html($cleanup_notice); ?></p>
    <?php endif; ?>
    <div class="mds3-page-actions">
        <a class="button" href="<?php echo esc_url($manage_url ?? ''); ?>"><?php echo esc_html__('Manage upload', 'million-dollar-script'); ?></a>
        <?php if (!empty($payment_url)) : ?>
            <a class="button" href="<?php echo esc_url($payment_url); ?>"><?php echo esc_html__('Continue payment', 'million-dollar-script'); ?></a>
        <?php endif; ?>
        <?php if (!empty($renewal_available)) : ?>
            <form method="post" class="mds3-inline-form">
                <input type="hidden" name="mds3_action" value="renew_order" />
                <input type="hidden" name="order_id" value="<?php echo esc_attr(absint($order['id'] ?? 0)); ?>" />
                <input type="hidden" name="order_key" value="<?php echo esc_attr($order['order_key'] ?? ''); ?>" />
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($renewal_nonce ?? ''); ?>" />
                <button type="submit" class="button"><?php echo esc_html__('Renew placement', 'million-dollar-script'); ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>
