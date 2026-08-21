<?php
/**
 * Accountless extension-server tester access panel.
 *
 * @package MillionDollarScript\V3\Admin
 */

use MillionDollarScript\V3\Extensions\ExtensionLicenseManager;

if (!defined('ABSPATH')) {
    exit;
}

$tester_access = new ExtensionLicenseManager();
$tester_record = $tester_access->tester_access_record();
$tester_license = isset($tester_record['license']) && is_array($tester_record['license']) ? $tester_record['license'] : [];
$tester_metadata = isset($tester_license['metadata']) && is_array($tester_license['metadata']) ? $tester_license['metadata'] : [];
$tester_label = sanitize_text_field((string) ($tester_metadata['tester_label'] ?? __('Private tester', 'million-dollar-script')));
$tester_scope = !empty($tester_metadata['tester_catalog_access'])
    ? __('All approved MDS 3.0 extensions', 'million-dollar-script')
    : __('Extension subset selected by the server administrator', 'million-dollar-script');
?>
<section class="mds3-extension-tester-access" aria-labelledby="mds3-tester-access-title">
    <div class="mds3-extension-tester-access-copy">
        <p class="mds3-admin-eyebrow"><?php esc_html_e('Private testing', 'million-dollar-script'); ?></p>
        <h2 id="mds3-tester-access-title"><?php esc_html_e('Extension server tester access', 'million-dollar-script'); ?></h2>
        <p><?php esc_html_e('Connect one accountless key issued by the extension server administrator. It is used only when an extension does not have its own customer license.', 'million-dollar-script'); ?></p>
    </div>

    <?php if ($tester_access->has_tester_access()) : ?>
        <div class="mds3-extension-tester-access-status">
            <span class="mds3-badge mds3-badge-success"><?php esc_html_e('Connected', 'million-dollar-script'); ?></span>
            <strong><?php echo esc_html($tester_label); ?></strong>
            <span><?php echo esc_html($tester_scope); ?></span>
            <code><?php echo esc_html($tester_access->masked_tester_access_key()); ?></code>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-extension-tester-access-form">
            <?php wp_nonce_field('mds3_deactivate_tester_access'); ?>
            <input type="hidden" name="action" value="mds3_deactivate_tester_access" />
            <button type="submit" class="button"><?php esc_html_e('Disconnect tester access', 'million-dollar-script'); ?></button>
        </form>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-extension-tester-access-form">
            <?php wp_nonce_field('mds3_activate_tester_access'); ?>
            <input type="hidden" name="action" value="mds3_activate_tester_access" />
            <label for="mds3-extension-tester-key"><?php esc_html_e('Tester access key', 'million-dollar-script'); ?></label>
            <div class="mds3-extension-tester-key-row">
                <input id="mds3-extension-tester-key" type="password" name="access_key" autocomplete="off" required placeholder="MDS-…" />
                <button type="submit" class="button button-primary"><?php esc_html_e('Connect tester access', 'million-dollar-script'); ?></button>
            </div>
            <p class="description"><?php esc_html_e('This does not create an account or purchase. The server administrator controls its scope, expiry, site limit, and revocation.', 'million-dollar-script'); ?></p>
        </form>
    <?php endif; ?>
</section>
