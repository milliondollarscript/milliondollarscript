<?php
/**
 * Setup payment dependency readiness.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$woocommerce = is_array($payment_dependencies['woocommerce'] ?? null) ? $payment_dependencies['woocommerce'] : [];
$adapter = is_array($woocommerce_adapter ?? null) ? $woocommerce_adapter : [];
$woocommerce_active = !empty($woocommerce['active']);
$woocommerce_installed = !empty($woocommerce['installed']);
$adapter_active = !empty($adapter['active']);
$adapter_installed = !empty($adapter['installed']);
$adapter_available = !empty($adapter['available']);
$adapter_can_install = !empty($adapter['can_install']);
$adapter_can_activate = !empty($adapter['can_activate']);
$woocommerce_ready = class_exists('\MillionDollarScript\V3\Commerce\Payments') && \MillionDollarScript\V3\Commerce\Payments::provider_ready('woocommerce');
$status_label = $woocommerce_ready ? __('Ready', 'million-dollar-script') : __('Setup needed', 'million-dollar-script');
$status_class = $woocommerce_ready ? 'is-ready' : 'needs-action';
?>
<div class="mds3-setup-payment-card <?php echo esc_attr($status_class); ?>">
    <header>
        <div>
            <h3><?php esc_html_e('WooCommerce Checkout', 'million-dollar-script'); ?></h3>
            <p><?php esc_html_e('Use WooCommerce when you want store checkout, gateways, taxes, receipts, and WooCommerce order records for Million Dollar Script payments.', 'million-dollar-script'); ?></p>
        </div>
        <span class="mds3-status-pill"><?php echo esc_html($status_label); ?></span>
    </header>

    <ol class="mds3-setup-checklist">
        <li class="<?php echo esc_attr($woocommerce_active ? 'is-complete' : 'is-pending'); ?>">
            <span>
                <strong><?php esc_html_e('WooCommerce plugin', 'million-dollar-script'); ?></strong>
                <small>
                    <?php
                    echo esc_html($woocommerce_active
                        ? __('Installed and active.', 'million-dollar-script')
                        : __('Required before WooCommerce can be selected as the payment provider.', 'million-dollar-script'));
                    ?>
                </small>
            </span>
            <?php if (!$woocommerce_active) : ?>
                <button
                    type="button"
                    class="button button-secondary"
                    data-mds3-install-dependency
                    data-dependency="woocommerce"
                    <?php disabled(empty($woocommerce['can_install'])); ?>
                >
                    <?php echo esc_html($woocommerce_installed ? __('Activate WooCommerce', 'million-dollar-script') : __('Install WooCommerce', 'million-dollar-script')); ?>
                </button>
            <?php elseif (!empty($woocommerce['setup_url'])) : ?>
                <a class="button button-small" href="<?php echo esc_url($woocommerce['setup_url']); ?>"><?php esc_html_e('Open WooCommerce setup', 'million-dollar-script'); ?></a>
            <?php endif; ?>
        </li>

        <li class="<?php echo esc_attr($adapter_active ? 'is-complete' : 'is-pending'); ?>">
            <span>
                <strong><?php esc_html_e('WooCommerce Checkout extension', 'million-dollar-script'); ?></strong>
                <small>
                    <?php
                    if ($adapter_active) {
                        esc_html_e('Installed and active. WooCommerce payment routing is selected by default and can be changed without deactivating the extension.', 'million-dollar-script');
                    } elseif ($adapter_installed) {
                        esc_html_e('Installed but inactive. Select it in site capabilities or activate it from Plugins.', 'million-dollar-script');
                    } elseif ($adapter_available) {
                        esc_html_e('Available from the Extensions page as a free payment extension.', 'million-dollar-script');
                    } else {
                        esc_html_e('Not listed by the current extension catalog. Reconnect or re-seed the local extension server, then refresh Extensions.', 'million-dollar-script');
                    }
                    ?>
                </small>
            </span>
            <?php if (!$adapter_active) : ?>
                <?php if (($adapter_installed && $adapter_can_activate) || (!$adapter_installed && $adapter_can_install)) : ?>
                    <button
                        type="button"
                        class="button button-small button-primary"
                        data-mds3-setup-extension-action
                        data-action="<?php echo esc_attr($adapter_installed ? 'mds3_activate_extension' : 'mds3_install_extension'); ?>"
                        data-nonce="<?php echo esc_attr(wp_create_nonce($adapter_installed ? 'mds3_activate_extension_' . (string) ($adapter['plugin_file'] ?? '') : 'mds3_install_extension_mds-woocommerce')); ?>"
                        data-plugin-file="<?php echo esc_attr((string) ($adapter['plugin_file'] ?? '')); ?>"
                        data-slug="mds-woocommerce"
                    >
                        <?php esc_html_e('Activate extension', 'million-dollar-script'); ?>
                    </button>
                <?php else : ?>
                    <a class="button button-small" href="<?php echo esc_url($adapter['extensions_url'] ?? admin_url('admin.php?page=mds3-extensions')); ?>"><?php esc_html_e('View extension', 'million-dollar-script'); ?></a>
                <?php endif; ?>
            <?php endif; ?>
        </li>
    </ol>

    <p class="mds3-setup-dependency-status" data-mds3-dependency-status aria-live="polite"></p>
</div>
