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
    <?php $mds3_heading = (string) ($title ?? ''); ?>
    <?php if ('' !== $mds3_heading && get_the_title() !== $mds3_heading) : ?>
        <h2><?php echo esc_html($mds3_heading); ?></h2>
    <?php endif; ?>
    <p><?php echo esc_html($copy ?? ''); ?></p>
    <?php if (!empty($action['url']) && !empty($action['label'])) : ?>
        <p><a class="button" href="<?php echo esc_url($action['url']); ?>"><?php echo esc_html($action['label']); ?></a></p>
    <?php endif; ?>
</section>
