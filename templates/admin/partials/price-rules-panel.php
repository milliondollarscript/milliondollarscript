<?php
/**
 * Grid price rules panel.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$tag = $wrap ? 'section' : 'div';
$class = $wrap ? 'mds3-card mds3-region-panel' : 'mds3-panel-content mds3-region-panel mds3-price-zones-panel';
?>
<<?php echo esc_html($tag); ?> class="<?php echo esc_attr($class); ?>">
    <h2><?php esc_html_e('Price Zones', 'million-dollar-script'); ?></h2>
    <?php $this->region_editor($grid, 'price', $rules, []); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-admin-grid-form mds3-price-zone-form">
        <?php wp_nonce_field('mds3_save_price_rule_' . $grid->id()); ?>
        <input type="hidden" name="action" value="mds3_save_price_rule" />
        <input type="hidden" name="grid_id" value="<?php echo esc_attr($grid->id()); ?>" />
        <input type="hidden" name="id" value="" />
        <?php
        $this->field('row_from', __('Row From', 'million-dollar-script'), 'number', '');
        $this->field('row_to', __('Row To', 'million-dollar-script'), 'number', '');
        $this->field('col_from', __('Column From', 'million-dollar-script'), 'number', '');
        $this->field('col_to', __('Column To', 'million-dollar-script'), 'number', '');
        $this->field('block_id_from', __('Legacy Block ID From', 'million-dollar-script'), 'number', '');
        $this->field('block_id_to', __('Legacy Block ID To', 'million-dollar-script'), 'number', '');
        $this->field('price', __('Price Per Block', 'million-dollar-script'), 'number', $grid->get('price_per_block'), '0.01');
        $this->field('currency', __('Currency', 'million-dollar-script'), 'text', $currency, '', [], [
            'disabled' => $currency_locked,
            'hidden_value' => $currency_locked ? $currency : null,
            'help' => $currency_locked ? __('The active payment provider owns currency, so price zones use the provider store currency.', 'million-dollar-script') : '',
        ]);
        $this->field('color', __('Color', 'million-dollar-script'), 'color', '#2563eb', '', [], [
            'help' => __('Color used to preview this price zone in the grid editor.', 'million-dollar-script'),
        ]);
        $this->field('status', __('Status', 'million-dollar-script'), 'select', 'active', '', ['active', 'paused', 'archived']);
        submit_button(__('Save price zone', 'million-dollar-script'), 'secondary');
        ?>
        <p class="submit"><button type="button" class="button mds3-form-reset"><?php esc_html_e('New price zone', 'million-dollar-script'); ?></button></p>
    </form>

    <?php if ($rule_rows) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php esc_html_e('Rows', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Columns', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Legacy Blocks', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Price', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Status', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Actions', 'million-dollar-script'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rule_rows as $rule) : ?>
                    <tr>
                        <td><?php echo esc_html($rule['id']); ?></td>
                        <td><?php echo esc_html($rule['row_label']); ?></td>
                        <td><?php echo esc_html($rule['col_label']); ?></td>
                        <td><?php echo esc_html($rule['block_label']); ?></td>
                        <td><?php echo esc_html($rule['price_label']); ?></td>
                        <td><?php echo esc_html($rule['status']); ?></td>
                        <td>
                            <button type="button" class="button button-small mds3-region-load" data-id="<?php echo esc_attr($rule['id']); ?>" data-row-from="<?php echo esc_attr($rule['row_from']); ?>" data-row-to="<?php echo esc_attr($rule['row_to']); ?>" data-col-from="<?php echo esc_attr($rule['col_from']); ?>" data-col-to="<?php echo esc_attr($rule['col_to']); ?>" data-block-id-from="<?php echo esc_attr($rule['block_id_from']); ?>" data-block-id-to="<?php echo esc_attr($rule['block_id_to']); ?>" data-price="<?php echo esc_attr($rule['price']); ?>" data-currency="<?php echo esc_attr($rule['currency']); ?>" data-color="<?php echo esc_attr($rule['color']); ?>" data-status="<?php echo esc_attr($rule['status']); ?>"><?php esc_html_e('Edit', 'million-dollar-script'); ?></button>
                            <?php $this->inline_post_button('mds3_archive_price_rule', 'mds3_archive_price_rule_' . $rule['id'], ['grid_id' => $grid->id(), 'price_rule_id' => $rule['id']], __('Archive', 'million-dollar-script'), 'button-small'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</<?php echo esc_html($tag); ?>>
