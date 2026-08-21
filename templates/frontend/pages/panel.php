<?php
/**
 * Generic migrated page panel.
 *
 * @package MillionDollarScript
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="mds3-page-panel <?php echo esc_attr($theme_class ?? ''); ?>">
    <h2><?php echo esc_html($title ?? ''); ?></h2>
    <p><?php echo esc_html($copy ?? ''); ?></p>
    <?php if (!empty($action['url']) && !empty($action['label'])) : ?>
        <p><a class="button" href="<?php echo esc_url($action['url']); ?>"><?php echo esc_html($action['label']); ?></a></p>
    <?php endif; ?>
</section>
