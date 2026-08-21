<?php
/**
 * Inline admin POST button.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<form
    class="mds3-inline-form"
    method="post"
    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
    <?php echo !empty($confirm_message) ? 'data-mds3-confirm="' . esc_attr($confirm_message) . '"' : ''; ?>
>
    <?php wp_nonce_field($nonce_action); ?>
    <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>" />
    <?php foreach ($data as $key => $value) : ?>
        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" />
    <?php endforeach; ?>
    <button type="submit" class="button <?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></button>
</form>
