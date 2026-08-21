<?php
/**
 * Standard pages panel.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$tag = $standalone ? 'section' : 'div';
$class = $standalone ? 'mds3-card' : 'mds3-standard-pages-panel';
?>
<<?php echo esc_html($tag); ?> class="<?php echo esc_attr($class); ?>">
    <h2><?php esc_html_e('Standard Pages', 'million-dollar-script'); ?></h2>
    <?php if (!$grid_enabled) : ?>
        <p><?php esc_html_e('Classic Pixel Grid is disabled, so Million Dollar Script grid, checkout, upload, and account pages are not created yet.', 'million-dollar-script'); ?></p>
        <p><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>"><?php esc_html_e('Enable Classic Pixel Grid', 'million-dollar-script'); ?></a></p>
    <?php else : ?>
        <p><?php esc_html_e('Create or repair the standard Million Dollar Script page set. Existing assigned or migrated pages are preserved; only missing page roles are created as normal WordPress Pages.', 'million-dollar-script'); ?></p>
        <?php $this->inline_post_button('mds3_ensure_standard_pages', 'mds3_ensure_standard_pages', [], __('Create missing standard pages', 'million-dollar-script'), 'button-secondary'); ?>
    <?php endif; ?>
</<?php echo esc_html($tag); ?>>
