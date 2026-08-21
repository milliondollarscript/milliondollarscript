<?php
/**
 * Grid legend partial.
 *
 * @package MillionDollarScript
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<ul class="mds3-grid-legend" aria-label="<?php echo esc_attr__('Grid legend', 'million-dollar-script'); ?>">
    <?php foreach (($items ?? []) as $state => $label) : ?>
        <li>
            <span class="mds3-grid-legend-swatch mds3-grid-legend-<?php echo esc_attr($state); ?>"></span>
            <?php echo esc_html($label); ?>
        </li>
    <?php endforeach; ?>
</ul>
