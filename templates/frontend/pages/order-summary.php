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
<?php $mds3_heading = (string) ($title ?? __('Order Summary', 'million-dollar-script')); ?>
<section class="mds3-page-panel mds3-order-summary-panel <?php echo esc_attr($theme_class ?? ''); ?>">
    <?php if (get_the_title() !== $mds3_heading) : ?>
        <h2><?php echo esc_html($mds3_heading); ?></h2>
    <?php endif; ?>
    <dl class="mds3-order-summary">
        <div><dt><?php echo esc_html__('Order', 'million-dollar-script'); ?></dt><dd>#<?php echo esc_html(absint($order['id'] ?? 0)); ?></dd></div>
        <div><dt><?php echo esc_html__('Status', 'million-dollar-script'); ?></dt><dd><?php echo esc_html(!empty($status_label) ? (string) $status_label : $this->status_label($order['status'] ?? '')); ?></dd></div>
        <div><dt><?php echo esc_html__('Total', 'million-dollar-script'); ?></dt><dd><?php echo esc_html($this->money($order)); ?></dd></div>
        <?php if (!empty($term_expires_at)) : ?>
            <div><dt><?php echo esc_html__('Term', 'million-dollar-script'); ?></dt><dd><?php echo esc_html($term_expires_at); ?></dd></div>
        <?php endif; ?>
    </dl>
    <?php /* A review asks the customer to look at something, so the artwork they are approving is part of this screen. */ ?>
    <?php if (!empty($confirm_attachment)) : ?>
        <div class="mds3-current-placement"><?php echo wp_kses_post(wp_get_attachment_image(absint($confirm_attachment), 'medium')); ?></div>
    <?php endif; ?>
    <?php if (!empty($renewal_error)) : ?>
        <p class="mds3-grid-status mds3-grid-status-error"><?php echo esc_html($renewal_error); ?></p>
    <?php elseif (!empty($renewal_notice)) : ?>
        <p class="mds3-grid-status"><?php echo esc_html($renewal_notice); ?></p>
    <?php endif; ?>
    <?php if (!empty($confirm_error)) : ?>
        <p class="mds3-grid-status mds3-grid-status-error"><?php echo esc_html($confirm_error); ?></p>
    <?php elseif (!empty($confirm_notice)) : ?>
        <p class="mds3-grid-status"><?php echo esc_html($confirm_notice); ?></p>
    <?php endif; ?>
    <?php if (!empty($cleanup_notice)) : ?>
        <p class="mds3-grid-status"><?php echo esc_html($cleanup_notice); ?></p>
    <?php endif; ?>
    <?php /* The actions row below already carries payment, renewal and manage links, so this block only says where the order stands. */ ?>
    <?php $mds3_order_state = is_array($order_state ?? null) ? $order_state : []; ?>
    <?php if (!empty($mds3_order_state['headline'])) : ?>
        <div class="mds3-order-state mds3-order-state-<?php echo esc_attr($mds3_order_state['tone'] ?? 'neutral'); ?>">
            <p class="mds3-order-state-headline"><?php echo esc_html((string) $mds3_order_state['headline']); ?></p>
            <?php if (!empty($mds3_order_state['detail'])) : ?>
                <p class="mds3-order-state-detail"><?php echo esc_html((string) $mds3_order_state['detail']); ?></p>
            <?php endif; ?>
            <?php if (!empty($mds3_order_state['payment_instructions'])) : ?>
                <?php /* The site's own words for paying outside the plugin, kept as the site owner wrote them. */ ?>
                <div class="mds3-order-state-payment"><?php echo wp_kses_post(wpautop((string) $mds3_order_state['payment_instructions'])); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="mds3-page-actions">
        <?php /* Secondary navigation leads from the left; the action that moves this order
           forward - confirm, pay, renew - is always rendered last, so it lands at the right
           end of the row whatever state the order is in. Read left to right, the row then
           says where you can go, and what to do next. */ ?>
        <?php if (!empty($manage_url)) : ?>
            <a class="button" href="<?php echo esc_url($manage_url); ?>"><?php echo esc_html__('Manage upload', 'million-dollar-script'); ?></a>
        <?php endif; ?>
        <?php if (!empty($back_url)) : ?>
            <a class="button mds3-button-secondary" href="<?php echo esc_url($back_url); ?>"><?php echo esc_html__('My pixels', 'million-dollar-script'); ?></a>
        <?php endif; ?>
        <?php if (!empty($confirm_available)) : ?>
            <form method="post" class="mds3-inline-form">
                <input type="hidden" name="mds3_action" value="confirm_order" />
                <input type="hidden" name="order_id" value="<?php echo esc_attr(absint($order['id'] ?? 0)); ?>" />
                <input type="hidden" name="order_key" value="<?php echo esc_attr($order['order_key'] ?? ''); ?>" />
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($confirm_nonce ?? ''); ?>" />
                <button type="submit" class="button"><?php echo esc_html($mds3_order_state['confirm_label'] ?? __('Confirm order', 'million-dollar-script')); ?></button>
            </form>
        <?php endif; ?>
        <?php /* A screen that is not the review page itself still points at it. */ ?>
        <?php if (empty($confirm_available) && !empty($mds3_order_state['confirm_url'])) : ?>
            <a class="button" href="<?php echo esc_url((string) $mds3_order_state['confirm_url']); ?>"><?php echo esc_html__('Confirm your order', 'million-dollar-script'); ?></a>
        <?php endif; ?>
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
