<?php
/**
 * Empty grid shortcode state.
 *
 * @package MillionDollarScript
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mds3-empty <?php echo esc_attr($theme_class ?? ''); ?>">
    <?php echo esc_html__('No grid is available yet.', 'million-dollar-script'); ?>
</div>
