<?php
/**
 * Grid public page panel.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$tag = $wrap ? 'section' : 'div';
$class = $wrap ? 'mds3-card' : 'mds3-panel-content mds3-public-page-panel';
?>
<<?php echo esc_html($tag); ?> class="<?php echo esc_attr($class); ?>">
    <h2><?php esc_html_e('Public Page', 'million-dollar-script'); ?></h2>
    <p><?php esc_html_e('Grid pages are normal WordPress pages with a grid shortcode. New pages start in view-only mode so visitors can browse the launched grid.', 'million-dollar-script'); ?></p>
    <p><?php esc_html_e('You can leave this page view-only and let visitors order from the standard Order Pixels page. Enable ordering here only when this specific public grid page should accept selections directly.', 'million-dollar-script'); ?></p>
    <div class="mds3-shortcode-presets">
        <p><strong><?php esc_html_e('Read-only showcase', 'million-dollar-script'); ?></strong><br><code><?php echo esc_html('[mds_grid id="' . $grid->id() . '" read_only="true"]'); ?></code></p>
        <p><strong><?php esc_html_e('Interactive order page', 'million-dollar-script'); ?></strong><br><code><?php echo esc_html('[mds_grid id="' . $grid->id() . '" read_only="false"]'); ?></code></p>
        <p><strong><?php esc_html_e('Classic canvas fallback', 'million-dollar-script'); ?></strong><br><code><?php echo esc_html('[mds_grid id="' . $grid->id() . '" read_only="false" renderer="classic"]'); ?></code></p>
    </div>
    <div class="mds3-button-row">
        <?php $this->grid_public_page_actions($grid); ?>
    </div>
</<?php echo esc_html($tag); ?>>
