<?php
/**
 * Grid region editor shell.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$readonly = !empty($readonly);
$extra_class = isset($extra_class) ? trim((string) $extra_class) : '';
$classes = trim('mds3-region-editor' . ($extra_class ? ' ' . $extra_class : '') . ($readonly ? ' mds3-region-editor--readonly' : ''));
$selections_json = is_string($selections_json ?? null) ? $selections_json : '[]';
$status_text = isset($status_text) ? (string) $status_text : '';
$zoom_label = isset($zoom_label) ? (string) $zoom_label : __('Grid editor zoom controls', 'million-dollar-script');
$canvas_label = isset($canvas_label) ? (string) $canvas_label : '';
$move_shape_json = is_string($move_shape_json ?? null) ? $move_shape_json : '[]';
$move_row_span = absint($move_row_span ?? 0);
$move_col_span = absint($move_col_span ?? 0);
?>
<div class="<?php echo esc_attr($classes); ?>" data-mode="<?php echo esc_attr($mode); ?>" data-rows="<?php echo esc_attr($rows); ?>" data-cols="<?php echo esc_attr($cols); ?>" data-blocks="<?php echo esc_attr($blocks_json); ?>" data-rules="<?php echo esc_attr($rules_json); ?>" data-regions="<?php echo esc_attr($regions_json); ?>" data-selections="<?php echo esc_attr($selections_json); ?>" data-move-shape="<?php echo esc_attr($move_shape_json); ?>" data-move-row-span="<?php echo esc_attr($move_row_span); ?>" data-move-col-span="<?php echo esc_attr($move_col_span); ?>"<?php echo $readonly ? ' data-readonly="1"' : ''; ?> data-status="<?php echo esc_attr($status_text); ?>">
    <div class="mds3-region-editor-toolbar">
        <div class="mds3-region-editor-metrics">
            <span><?php esc_html_e('Rows', 'million-dollar-script'); ?>: <?php echo esc_html(number_format_i18n($rows)); ?></span>
            <span><?php esc_html_e('Columns', 'million-dollar-script'); ?>: <?php echo esc_html(number_format_i18n($cols)); ?></span>
            <span><?php esc_html_e('Blocks', 'million-dollar-script'); ?>: <?php echo esc_html(number_format_i18n($total_blocks)); ?></span>
        </div>
        <div class="mds3-region-editor-zoom" aria-label="<?php echo esc_attr($zoom_label); ?>">
            <button type="button" class="button button-small" data-mds3-region-zoom="out" title="<?php echo esc_attr__('Zoom out', 'million-dollar-script'); ?>" aria-label="<?php echo esc_attr__('Zoom out', 'million-dollar-script'); ?>">-</button>
            <button type="button" class="button button-small" data-mds3-region-zoom="fit"><?php esc_html_e('Fit', 'million-dollar-script'); ?></button>
            <button type="button" class="button button-small" data-mds3-region-zoom="in" title="<?php echo esc_attr__('Zoom in', 'million-dollar-script'); ?>" aria-label="<?php echo esc_attr__('Zoom in', 'million-dollar-script'); ?>">+</button>
            <span data-mds3-region-zoom-label>100%</span>
        </div>
    </div>
    <div class="mds3-region-editor-canvas-wrap">
        <canvas width="1200" height="720"<?php echo $canvas_label ? ' role="img" aria-label="' . esc_attr($canvas_label) . '"' : ''; ?>></canvas>
    </div>
    <div class="mds3-region-editor-status" aria-live="polite"><?php echo esc_html($status_text); ?></div>
</div>
