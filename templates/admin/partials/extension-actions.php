<?php
/**
 * Extension action controls.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$slug = sanitize_key((string) ($item['slug'] ?? ''));
$license_panel_id = $slug ? 'mds3-extension-license-' . $slug : '';
$plugin_file = !empty($item['plugin_file']) ? (string) $item['plugin_file'] : '';
$info_url = !empty($item['info_url']) ? (string) $item['info_url'] : '';
$external_plugin_delivery = !empty($external_plugin_delivery);
$is_bundle = 'bundle' === sanitize_key((string) ($item['product_type'] ?? ''));
?>
<div class="mds3-extension-actions">
    <?php if (!empty($item['_claim_token']) && $slug) : ?>
        <?php
        $this->inline_post_button('mds3_claim_extension_license', 'mds3_claim_extension_license_' . $slug, [
            'slug' => $slug,
            'claim_token' => (string) $item['_claim_token'],
        ], __('Claim license', 'million-dollar-script'), 'button-small button-primary');
        ?>
    <?php endif; ?>

    <?php if (!empty($item['bundled']) || 'core' === ($item['source'] ?? '')) : ?>
        <span class="button button-small disabled" aria-disabled="true"><?php echo esc_html(!empty($item['active']) ? __('Enabled', 'million-dollar-script') : __('Disabled', 'million-dollar-script')); ?></span>
        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>"><?php esc_html_e('Manage capabilities', 'million-dollar-script'); ?></a>
    <?php elseif ('installed' === ($item['source'] ?? '') && $plugin_file) : ?>
        <?php if (!empty($item['active'])) : ?>
            <?php $this->inline_post_button('mds3_deactivate_extension', 'mds3_deactivate_extension_' . $plugin_file, ['plugin_file' => $plugin_file], __('Deactivate', 'million-dollar-script'), 'button-small'); ?>
        <?php else : ?>
            <?php $this->inline_post_button('mds3_activate_extension', 'mds3_activate_extension_' . $plugin_file, ['plugin_file' => $plugin_file], __('Activate', 'million-dollar-script'), 'button-small button-primary'); ?>
        <?php endif; ?>
        <?php if ($external_plugin_delivery && !empty($item['update_available'])) : ?>
            <?php $this->inline_post_button('mds3_update_extension', 'mds3_update_extension_' . $plugin_file, ['plugin_file' => $plugin_file], __('Update', 'million-dollar-script'), 'button-small button-primary'); ?>
        <?php elseif ($external_plugin_delivery) : ?>
            <?php $this->inline_post_button('mds3_check_extension_update', 'mds3_check_extension_update_' . $plugin_file, ['plugin_file' => $plugin_file], __('Check update', 'million-dollar-script'), 'button-small'); ?>
        <?php endif; ?>
    <?php else : ?>
        <?php if (!empty($item['installed'])) : ?>
            <span class="button button-small disabled" aria-disabled="true"><?php esc_html_e('Installed', 'million-dollar-script'); ?></span>
        <?php elseif (!$is_bundle && $external_plugin_delivery && (!empty($item['download_url']) || (!empty($item['license_required']) && $license_active))) : ?>
            <?php
            $this->inline_post_button('mds3_install_extension', 'mds3_install_extension_' . (string) ($item['slug'] ?? ''), [
                'slug' => (string) ($item['slug'] ?? ''),
                'activate' => '1',
            ], __('Install', 'million-dollar-script'), 'button-small button-primary');
            ?>
        <?php endif; ?>
        <?php if (!empty($item['purchase_url']) && !$license_active) : ?>
            <a class="button button-small" href="<?php echo esc_url($this->extension_purchase_url($item)); ?>" target="_blank" rel="noopener"><?php esc_html_e('Buy license', 'million-dollar-script'); ?></a>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($info_url) : ?>
        <a class="button button-small" href="<?php echo esc_url($info_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('More info', 'million-dollar-script'); ?></a>
    <?php endif; ?>

    <?php if (!empty($item['license_required']) && $license_panel_id) : ?>
        <div class="mds3-extension-license-menu">
            <button
                type="button"
                class="button button-small mds3-extension-license-toggle"
                aria-controls="<?php echo esc_attr($license_panel_id); ?>"
                aria-expanded="false"
                data-mds3-license-toggle
            >
                <?php esc_html_e('Manage license', 'million-dollar-script'); ?>
            </button>
            <div id="<?php echo esc_attr($license_panel_id); ?>" class="mds3-extension-license-popover" hidden>
                <?php $this->extension_license_controls($item, $license_manager); ?>
                <?php if (!empty($item['license_url'])) : ?>
                    <p class="mds3-extension-license-link">
                        <a href="<?php echo esc_url($item['license_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open license portal', 'million-dollar-script'); ?></a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
