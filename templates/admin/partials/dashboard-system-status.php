<?php
/**
 * Dashboard system status rows.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$memory_status = is_array($memory_status ?? null) ? $memory_status : [];
?>
<?php if ($memory_status && empty($memory_status['meets_minimum'])) : ?>
    <div class="notice notice-error inline">
        <p>
            <strong><?php esc_html_e('PHP memory is below the supported minimum.', 'million-dollar-script'); ?></strong>
            <?php
            echo esc_html(sprintf(
                /* translators: 1: effective PHP memory limit, 2: required PHP memory limit. */
                __('This site provides %1$s. Million Dollar Script requires at least %2$s for supported operation with checkout and extensions.', 'million-dollar-script'),
                (string) ($memory_status['effective_label'] ?? ''),
                (string) ($memory_status['minimum_label'] ?? '')
            ));
            ?>
            <a href="<?php echo esc_url(\MillionDollarScript\V3\Support\MemoryStatus::troubleshooting_url()); ?>"><?php esc_html_e('Review troubleshooting', 'million-dollar-script'); ?></a>
        </p>
    </div>
<?php endif; ?>
<dl class="mds3-dashboard-system">
    <?php foreach ($rows as $label => $value) : ?>
        <div>
            <dt><?php echo esc_html((string) $label); ?></dt>
            <dd><?php echo esc_html((string) $value); ?></dd>
        </div>
    <?php endforeach; ?>
</dl>
<p class="mds3-button-row">
    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-settings')); ?>"><?php esc_html_e('Open settings', 'million-dollar-script'); ?></a>
    <a class="button" href="<?php echo esc_url(admin_url('site-health.php')); ?>"><?php esc_html_e('Open Site Health', 'million-dollar-script'); ?></a>
</p>
