<?php
/**
 * ImageGrid settings prompt.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mds3-settings-callout">
    <h3><?php esc_html_e('ImageGrid remote rendering', 'million-dollar-script'); ?></h3>
    <p><?php esc_html_e('Million Dollar Script uses local rendering by default. ImageGrid settings are shown only after the ImageGrid extension is active, because API keys, quotas, and remote rendering are owned by that extension and the ImageGrid service.', 'million-dollar-script'); ?></p>
    <div class="mds3-button-row">
        <?php if ($installed_inactive) : ?>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('plugins.php?s=mds-imagegrid')); ?>"><?php esc_html_e('Activate ImageGrid extension', 'million-dollar-script'); ?></a>
        <?php else : ?>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mds3-extensions')); ?>"><?php esc_html_e('Find ImageGrid extension', 'million-dollar-script'); ?></a>
        <?php endif; ?>
        <a class="button" href="<?php echo esc_url($service_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View ImageGrid service', 'million-dollar-script'); ?></a>
    </div>
</div>
