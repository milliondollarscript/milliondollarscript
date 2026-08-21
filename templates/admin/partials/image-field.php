<?php
/**
 * Shared image picker field.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mds3-image-field" data-empty-label="<?php esc_attr_e('No image selected', 'million-dollar-script'); ?>">
    <input id="<?php echo esc_attr($id); ?>" type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>"<?php disabled($disabled, true); ?> />
    <div class="mds3-image-field-preview">
        <?php if ($image) : ?>
            <?php echo wp_kses_post($image); ?>
        <?php else : ?>
            <span><?php esc_html_e('No image selected', 'million-dollar-script'); ?></span>
        <?php endif; ?>
    </div>
    <div class="mds3-button-row">
        <button type="button" class="button mds3-image-select"<?php disabled($disabled, true); ?>><?php esc_html_e('Choose image', 'million-dollar-script'); ?></button>
        <button type="button" class="button mds3-image-clear"<?php disabled($disabled, true); ?>><?php esc_html_e('Clear', 'million-dollar-script'); ?></button>
    </div>
</div>
