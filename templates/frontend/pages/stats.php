<?php
/**
 * Stats shortcode panel.
 *
 * @package MillionDollarScript
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<aside class="mds3-page-panel mds3-page-panel-stats <?php echo esc_attr($theme_class ?? ''); ?>" style="<?php echo esc_attr(implode(';', $styles ?? [])); ?>">
    <strong><?php echo esc_html(number_format_i18n($sold ?? 0)); ?></strong>
    <?php /* translators: %s: stats unit label such as blocks or pixels. */ ?>
    <span><?php echo esc_html(sprintf(__('sold %s', 'million-dollar-script'), $unit_label ?? __('blocks', 'million-dollar-script'))); ?></span>
    <strong><?php echo esc_html(number_format_i18n($available ?? 0)); ?></strong>
    <?php /* translators: %s: stats unit label such as blocks or pixels. */ ?>
    <span><?php echo esc_html(sprintf(__('available %s', 'million-dollar-script'), $unit_label ?? __('blocks', 'million-dollar-script'))); ?></span>
</aside>
