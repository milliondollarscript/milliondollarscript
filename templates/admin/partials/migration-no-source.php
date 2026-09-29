<?php
/**
 * Migration empty state for sites without a Million Dollar Script 2 install.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap mds3-admin">
    <h1><?php esc_html_e('Million Dollar Script 2 Migration', 'million-dollar-script'); ?></h1>
    <section class="mds3-card">
        <h2><?php esc_html_e('Nothing to migrate', 'million-dollar-script'); ?></h2>
        <p><?php esc_html_e('No Million Dollar Script 2 tables, pages, or plugin files were found on this site, so there is nothing to import. Install Million Dollar Script 2 or enter its table prefix below to review a dry run.', 'million-dollar-script'); ?></p>
        <?php if (!$grid_enabled) : ?>
            <div class="notice notice-info inline">
                <p><?php esc_html_e('Classic Pixel Grid is required before importing Million Dollar Script 2 grid data.', 'million-dollar-script'); ?></p>
                <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>"><?php esc_html_e('Enable Classic Pixel Grid', 'million-dollar-script'); ?></a></p>
            </div>
        <?php endif; ?>
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="mds3-migration" />
            <?php $this->field('source_prefix', __('Million Dollar Script 2 Table Prefix', 'million-dollar-script'), 'text', ''); ?>
            <?php submit_button(__('Run dry run', 'million-dollar-script'), 'secondary', '', false); ?>
        </form>
    </section>
</div>