<?php
/**
 * Extension license controls.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mds3-extension-license">
    <?php if ($license_manager->is_product_active($slug)) : ?>
        <span class="mds3-extension-license-status">
            <?php esc_html_e('License:', 'million-dollar-script'); ?>
            <code><?php echo esc_html($license_manager->masked_product_license_key($slug)); ?></code>
        </span>
        <?php $this->inline_post_button('mds3_deactivate_extension_license', 'mds3_deactivate_extension_license_' . $slug, ['slug' => $slug], __('Deactivate license', 'million-dollar-script'), 'button-small'); ?>
    <?php else : ?>
        <?php if ('bundle' === $license_manager->access_source($slug)) : ?>
            <p class="mds3-extension-license-status">
                <?php
                echo esc_html(sprintf(
                    /* translators: %s: extension pack name */
                    __('This extension currently uses access from %s. You can add an independent license without replacing that pack access.', 'million-dollar-script'),
                    $license_manager->bundle_name_for($slug)
                ));
                ?>
            </p>
        <?php endif; ?>
        <form class="mds3-extension-license-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mds3_save_extension_license_' . $slug); ?>
            <input type="hidden" name="action" value="mds3_save_extension_license" />
            <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>" />
            <label>
                <span class="screen-reader-text"><?php esc_html_e('License key', 'million-dollar-script'); ?></span>
                <input type="password" name="license_key" autocomplete="off" placeholder="<?php esc_attr_e('License key', 'million-dollar-script'); ?>" required />
            </label>
            <button type="submit" class="button button-small"><?php esc_html_e('Activate license', 'million-dollar-script'); ?></button>
        </form>
    <?php endif; ?>
</div>
