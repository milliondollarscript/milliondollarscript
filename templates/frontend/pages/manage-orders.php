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
<?php $mds3_heading = __('Manage Pixels', 'million-dollar-script'); ?>
<section class="mds3-page-panel mds3-order-list-panel <?php echo esc_attr($theme_class ?? ''); ?>">
    <?php if (get_the_title() !== $mds3_heading) : ?>
        <h2><?php echo esc_html($mds3_heading); ?></h2>
    <?php endif; ?>
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
                    <th><?php echo esc_html__('Pixel', 'million-dollar-script'); ?></th>
                    <th><?php echo esc_html__('Status', 'million-dollar-script'); ?></th>
                    <th><?php echo esc_html__('Total', 'million-dollar-script'); ?></th>
                    <th><?php echo esc_html__('Updated', 'million-dollar-script'); ?></th>
                    <th><?php echo esc_html__('Actions', 'million-dollar-script'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $item) : ?>
                    <?php $pixel = ($pixels[absint($item['id'] ?? 0)] ?? null); ?>
                    <tr>
                        <td data-label="<?php esc_attr_e('Order', 'million-dollar-script'); ?>">#<?php echo esc_html(absint($item['id'] ?? 0)); ?></td>
                        <td data-label="<?php esc_attr_e('Pixel', 'million-dollar-script'); ?>">
                            <?php if (!empty($pixel['image'])) : ?>
                                <span class="mds3-pixel-thumb"><?php echo $pixel['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output. ?></span>
                            <?php endif; ?>
                            <?php if (!empty($pixel['label'])) : ?>
                                <span class="mds3-pixel-meta"><?php echo esc_html($pixel['label']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php esc_attr_e('Status', 'million-dollar-script'); ?>"><?php echo esc_html($this->status_label($item['status'] ?? '')); ?></td>
                        <td data-label="<?php esc_attr_e('Total', 'million-dollar-script'); ?>"><?php echo esc_html($this->money($item)); ?></td>
                        <td data-label="<?php esc_attr_e('Updated', 'million-dollar-script'); ?>"><?php echo esc_html(mysql2date(get_option('date_format'), (string) ($item['updated_at'] ?? $item['created_at'] ?? ''), true)); ?></td>
                        <td data-label="<?php esc_attr_e('Actions', 'million-dollar-script'); ?>"><a class="button" href="<?php echo esc_url($this->customer_order_url($item)); ?>"><?php echo esc_html__('Manage', 'million-dollar-script'); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $mds3_paged = max(1, absint($paged ?? 1));
        $mds3_per_page = absint($per_page ?? 0);
        $mds3_total = absint($total ?? 0);
        $mds3_page_url = (string) get_permalink();
        ?>
        <?php if ($mds3_per_page > 0 && $mds3_total > $mds3_per_page) : ?>
            <nav class="mds3-pagination">
                <?php if ($mds3_paged > 1) : ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg('paged', $mds3_paged - 1, $mds3_page_url)); ?>"><?php echo esc_html__('Previous', 'million-dollar-script'); ?> &#8592;</a>
                <?php endif; ?>
                <?php if ($mds3_total > $mds3_paged * $mds3_per_page) : ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg('paged', $mds3_paged + 1, $mds3_page_url)); ?>"><?php echo esc_html__('Next', 'million-dollar-script'); ?> &#8594;</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
