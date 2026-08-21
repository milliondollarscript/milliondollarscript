<?php
/**
 * MDS 3.0 extensions page.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = is_array($stats ?? null) ? $stats : [];
$installed_count = absint($stats['installed'] ?? 0);
$active_count = absint($stats['active'] ?? 0);
$updates_count = absint($stats['updates'] ?? 0);
$premium_count = absint($stats['premium'] ?? 0);
$free_count = absint($stats['free'] ?? 0);
$server_notice = is_array($server_notice ?? null) ? $server_notice : [];
$server_notice_type = sanitize_key((string) ($server_notice['type'] ?? 'info'));
$server_notice_class = 'error' === $server_notice_type ? 'notice-error' : ('warning' === $server_notice_type ? 'notice-warning' : 'notice-info');
$bundle_notice = is_array($bundle_notice ?? null) ? $bundle_notice : [];
$external_plugin_delivery = !empty($external_plugin_delivery);
$remote_catalog_enabled = !empty($remote_catalog_enabled);
$external_catalog_url = esc_url((string) ($external_catalog_url ?? ''));
?>
<div class="wrap mds3-admin mds3-extensions-page">
    <section class="mds3-extensions-hero">
        <div class="mds3-extensions-hero-main">
            <p class="mds3-admin-eyebrow"><?php esc_html_e('Extension Catalog', 'million-dollar-script'); ?></p>
            <h1><?php esc_html_e('Million Dollar Script Extensions', 'million-dollar-script'); ?></h1>
            <p><?php esc_html_e('Add checkout adapters, rendering services, field tools, and premium capabilities from one trusted workspace.', 'million-dollar-script'); ?></p>
        </div>
        <div class="mds3-extensions-hero-actions">
            <?php if ($external_plugin_delivery) : ?>
                <?php $this->inline_post_button('mds3_check_extension_updates', 'mds3_check_extension_updates', [], __('Check updates', 'million-dollar-script'), 'button-secondary'); ?>
            <?php endif; ?>
            <?php if (!empty($server_public_url)) : ?>
                <a class="button" href="<?php echo esc_url(rtrim((string) $server_public_url, '/') . '/portal/login'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Client portal', 'million-dollar-script'); ?></a>
            <?php endif; ?>
        </div>
    </section>

    <div class="mds3-extension-notices" aria-live="polite">
        <?php $this->extension_notice(); ?>
        <?php $this->extension_claim_panel(); ?>
        <?php if (!empty($server_notice)) : ?>
            <div class="notice <?php echo esc_attr($server_notice_class); ?> inline mds3-extension-server-notice">
                <p><strong><?php esc_html_e('Catalog connection:', 'million-dollar-script'); ?></strong> <?php echo esc_html((string) ($server_notice['message'] ?? '')); ?></p>
                <?php if (!empty($server_notice['configured_url'])) : ?>
                    <p>
                        <?php esc_html_e('Configured:', 'million-dollar-script'); ?>
                        <code><?php echo esc_html((string) $server_notice['configured_url']); ?></code>
                        <?php if (!empty($server_notice['resolved_url'])) : ?>
                            <?php esc_html_e('Connected:', 'million-dollar-script'); ?>
                            <code><?php echo esc_html((string) $server_notice['resolved_url']); ?></code>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($server_notice['errors']) && is_array($server_notice['errors'])) : ?>
                    <details>
                        <summary><?php esc_html_e('Connection details', 'million-dollar-script'); ?></summary>
                        <ul>
                            <?php foreach ($server_notice['errors'] as $error) : ?>
                                <li><code><?php echo esc_html((string) $error); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($bundle_notice)) : ?>
            <div class="notice notice-warning inline mds3-extension-server-notice">
                <p><strong><?php esc_html_e('Extension packs:', 'million-dollar-script'); ?></strong> <?php echo esc_html((string) ($bundle_notice['message'] ?? '')); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($external_plugin_delivery) : ?>
        <?php \MillionDollarScript\V3\Support\Template::display('admin/partials/extension-tester-access.php', [], $this); ?>
    <?php endif; ?>

    <div class="mds3-extension-summary-grid" aria-label="<?php esc_attr_e('Extension summary', 'million-dollar-script'); ?>">
        <div><strong><?php echo esc_html(number_format_i18n($installed_count)); ?></strong><span><?php esc_html_e('Installed', 'million-dollar-script'); ?></span></div>
        <div><strong><?php echo esc_html(number_format_i18n($active_count)); ?></strong><span><?php esc_html_e('Active', 'million-dollar-script'); ?></span></div>
        <div><strong><?php echo esc_html(number_format_i18n($updates_count)); ?></strong><span><?php esc_html_e('Updates', 'million-dollar-script'); ?></span></div>
        <div><strong><?php echo esc_html(number_format_i18n($premium_count)); ?></strong><span><?php esc_html_e('Premium', 'million-dollar-script'); ?></span></div>
        <div><strong><?php echo esc_html(number_format_i18n($free_count)); ?></strong><span><?php esc_html_e('Free', 'million-dollar-script'); ?></span></div>
    </div>

    <?php if ($remote_catalog_enabled) : ?>
    <section class="mds3-extension-section">
        <div class="mds3-extension-section-heading">
            <div>
                <h2><?php esc_html_e('Extension Packs', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Use one product-level license while installing and updating each included extension independently.', 'million-dollar-script'); ?></p>
            </div>
        </div>
        <?php $this->extension_bundle_list((array) ($catalog['bundles'] ?? [])); ?>
    </section>

    <div class="mds3-extension-server-line">
        <span><?php esc_html_e('Server', 'million-dollar-script'); ?></span>
        <code><?php echo esc_html($server_url ?: __('Not configured', 'million-dollar-script')); ?></code>
        <?php if ($server_public_url && $server_public_url !== $server_url) : ?>
            <span><?php esc_html_e('Public', 'million-dollar-script'); ?></span>
            <code><?php echo esc_html($server_public_url); ?></code>
        <?php endif; ?>
        <?php if ('production' !== $server_mode) : ?>
            <span class="mds3-badge"><?php echo esc_html($server_mode); ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <section class="mds3-extension-section">
        <div class="mds3-extension-section-heading">
            <div>
                <h2><?php esc_html_e('Discover Extensions', 'million-dollar-script'); ?></h2>
                <?php if ($remote_catalog_enabled) : ?>
                    <p><?php esc_html_e('Install free tools or connect a license for premium capabilities.', 'million-dollar-script'); ?></p>
                <?php else : ?>
                    <p><?php esc_html_e('Browse compatible extensions, then install free extensions through WordPress or upload premium extension packages provided with your purchase.', 'million-dollar-script'); ?></p>
                <?php endif; ?>
            </div>
            <?php if (!$remote_catalog_enabled && $external_catalog_url) : ?>
                <a class="button button-primary" href="<?php echo esc_url($external_catalog_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Discover extensions', 'million-dollar-script'); ?></a>
            <?php endif; ?>
        </div>
        <?php if ($remote_catalog_enabled) : ?>
            <?php $this->extension_list($catalog['available'], 'available'); ?>
        <?php endif; ?>
    </section>

    <section class="mds3-extension-section">
        <div class="mds3-extension-section-heading">
            <div>
                <h2><?php esc_html_e('Installed Extensions', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Manage active capabilities, licenses, and available updates for this site.', 'million-dollar-script'); ?></p>
            </div>
        </div>
        <?php $this->extension_list($catalog['installed'], 'installed'); ?>
    </section>
</div>
