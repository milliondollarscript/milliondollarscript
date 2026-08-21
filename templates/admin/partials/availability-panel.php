<?php
/**
 * Grid availability panel.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$tag = $wrap ? 'section' : 'div';
$class = $wrap ? 'mds3-card mds3-region-panel' : 'mds3-panel-content mds3-region-panel mds3-availability-panel';
?>
<<?php echo esc_html($tag); ?> class="<?php echo esc_attr($class); ?>">
    <h2><?php esc_html_e('Unavailable Regions', 'million-dollar-script'); ?></h2>
    <?php if ($region_error) : ?>
        <div class="notice notice-error inline"><p><?php echo esc_html($region_error); ?></p></div>
    <?php elseif ($region_updated) : ?>
        <div class="notice notice-success inline">
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: changed block count, 2: skipped block count. */
                    __('Region update applied. %1$s blocks changed, %2$s protected blocks skipped.', 'million-dollar-script'),
                    $region_updated['changed'],
                    $region_updated['skipped']
                ));
                ?>
            </p>
        </div>
    <?php endif; ?>

    <ul class="mds3-inline-list">
        <li><?php esc_html_e('Virtual blocks:', 'million-dollar-script'); ?> <?php echo esc_html($virtual); ?></li>
        <?php foreach ($count_rows as $row) : ?>
            <li><code><?php echo esc_html($row['status']); ?></code>: <?php echo esc_html($row['count']); ?></li>
        <?php endforeach; ?>
    </ul>

    <?php $this->region_editor($grid, 'availability', [], $blocks, $regions); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-admin-grid-form mds3-availability-form">
        <?php wp_nonce_field('mds3_set_region_status_' . $grid->id()); ?>
        <input type="hidden" name="action" value="mds3_set_region_status" />
        <input type="hidden" name="grid_id" value="<?php echo esc_attr($grid->id()); ?>" />
        <?php
        $this->field('row_from', __('Row From', 'million-dollar-script'), 'number', '0');
        $this->field('row_to', __('Row To', 'million-dollar-script'), 'number', '0');
        $this->field('col_from', __('Column From', 'million-dollar-script'), 'number', '0');
        $this->field('col_to', __('Column To', 'million-dollar-script'), 'number', '0');
        $this->field('region_status', __('Set Status', 'million-dollar-script'), 'select', 'unavailable', '', ['unavailable', 'available']);
        $this->field('note', __('Note', 'million-dollar-script'), 'text', '');
        submit_button(__('Apply region update', 'million-dollar-script'), 'secondary');
        ?>
    </form>

    <?php if ($region_rows) : ?>
        <h3><?php esc_html_e('Existing unavailable regions', 'million-dollar-script'); ?></h3>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Rows', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Columns', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Blocks', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Note', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Actions', 'million-dollar-script'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($region_rows as $region) : ?>
                    <tr>
                        <td><?php echo esc_html($region['row_label']); ?></td>
                        <td><?php echo esc_html($region['col_label']); ?></td>
                        <td><?php echo esc_html($region['count']); ?></td>
                        <td><?php echo esc_html($region['note']); ?></td>
                        <td>
                            <button type="button" class="button button-small mds3-region-load" data-row-from="<?php echo esc_attr($region['row_from']); ?>" data-row-to="<?php echo esc_attr($region['row_to']); ?>" data-col-from="<?php echo esc_attr($region['col_from']); ?>" data-col-to="<?php echo esc_attr($region['col_to']); ?>" data-region-status="available" data-note="<?php echo esc_attr($region['note']); ?>"><?php esc_html_e('Load to edit', 'million-dollar-script'); ?></button>
                            <?php
                            $this->inline_post_button('mds3_set_region_status', 'mds3_set_region_status_' . $grid->id(), [
                                'grid_id' => $grid->id(),
                                'row_from' => $region['row_from'],
                                'row_to' => $region['row_to'],
                                'col_from' => $region['col_from'],
                                'col_to' => $region['col_to'],
                                'region_status' => 'available',
                                'note' => $region['note'],
                            ], __('Mark available', 'million-dollar-script'), 'button-small');
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</<?php echo esc_html($tag); ?>>
