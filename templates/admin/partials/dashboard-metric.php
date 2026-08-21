<?php
/**
 * Dashboard metric link/card.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if ($url) : ?>
    <a class="mds3-dashboard-metric" href="<?php echo esc_url($url); ?>">
        <span><?php echo esc_html($label); ?></span>
        <strong><?php echo esc_html($value); ?></strong>
        <small><?php echo esc_html($description); ?></small>
    </a>
<?php else : ?>
    <div class="mds3-dashboard-metric">
        <span><?php echo esc_html($label); ?></span>
        <strong><?php echo esc_html($value); ?></strong>
        <small><?php echo esc_html($description); ?></small>
    </div>
<?php endif; ?>
