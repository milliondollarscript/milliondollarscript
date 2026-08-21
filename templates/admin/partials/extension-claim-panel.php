<?php
/**
 * Extension license claim panel.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="mds3-card mds3-extension-claim">
    <h2><?php esc_html_e('Claim Purchased License', 'million-dollar-script'); ?></h2>
    <?php if (!empty($stub_session)) : ?>
        <div class="notice notice-warning inline">
            <p><strong><?php esc_html_e('Local stub checkout:', 'million-dollar-script'); ?></strong> <?php esc_html_e('No Stripe payment occurred. Claiming this license creates local test access only.', 'million-dollar-script'); ?></p>
        </div>
    <?php endif; ?>
    <p><?php esc_html_e('Complete the license claim after checkout, then Million Dollar Script will activate it for this site.', 'million-dollar-script'); ?></p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('mds3_claim_extension_license_' . $slug); ?>
        <input type="hidden" name="action" value="mds3_claim_extension_license" />
        <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>" />
        <input type="hidden" name="claim_token" value="<?php echo esc_attr($claim_token); ?>" />
        <?php submit_button(__('Claim license', 'million-dollar-script'), 'primary', '', false); ?>
    </form>
</section>
