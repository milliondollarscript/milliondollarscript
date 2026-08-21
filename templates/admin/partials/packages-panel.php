<?php
/**
 * Grid packages panel.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$tag = $wrap ? 'section' : 'div';
$class = $wrap ? 'mds3-card' : 'mds3-panel-content mds3-packages-panel';
?>
<<?php echo esc_html($tag); ?> class="<?php echo esc_attr($class); ?>">
    <h2><?php esc_html_e('Packages', 'million-dollar-script'); ?></h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-admin-grid-form">
        <?php wp_nonce_field('mds3_save_package_' . $grid->id()); ?>
        <input type="hidden" name="action" value="mds3_save_package" />
        <input type="hidden" name="grid_id" value="<?php echo esc_attr($grid->id()); ?>" />
        <input type="hidden" name="id" value="" />
        <?php
        $this->field('title', __('Title', 'million-dollar-script'), 'text', '');
        $this->field('description', __('Description', 'million-dollar-script'), 'text', '');
        $this->field('duration_days', __('Duration Days', 'million-dollar-script'), 'number', '0');
        $this->field('price', __('Package Price', 'million-dollar-script'), 'number', '0.00', '0.01');
        $this->field('currency', __('Currency', 'million-dollar-script'), 'text', $currency, '', [], [
            'disabled' => $currency_locked,
            'hidden_value' => $currency_locked ? $currency : null,
            'help' => $currency_locked ? __('The active payment provider owns currency, so packages use the provider store currency.', 'million-dollar-script') : '',
        ]);
        $this->field('max_orders', __('Max Orders', 'million-dollar-script'), 'number', '0');
        $this->field('is_default', __('Default Package', 'million-dollar-script'), 'select', '0', '', ['0', '1']);
        $this->field('status', __('Status', 'million-dollar-script'), 'select', 'active', '', ['active', 'paused', 'archived']);
        submit_button(__('Save package', 'million-dollar-script'), 'secondary');
        ?>
        <p class="submit"><button type="button" class="button mds3-form-reset"><?php esc_html_e('New package', 'million-dollar-script'); ?></button></p>
    </form>

    <?php if ($package_rows) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Title', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Price', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Duration', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Max Orders', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Default', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Status', 'million-dollar-script'); ?></th>
                    <th><?php esc_html_e('Actions', 'million-dollar-script'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($package_rows as $package) : ?>
                    <tr>
                        <td><?php echo esc_html($package['title']); ?></td>
                        <td><?php echo esc_html($package['price_label']); ?></td>
                        <td><?php echo esc_html($package['duration_days']); ?></td>
                        <td><?php echo esc_html($package['max_orders']); ?></td>
                        <td><?php echo esc_html($package['is_default_label']); ?></td>
                        <td><?php echo esc_html($package['status']); ?></td>
                        <td>
                            <button type="button" class="button button-small mds3-package-load" data-package="<?php echo esc_attr($package['data_json']); ?>"><?php esc_html_e('Edit', 'million-dollar-script'); ?></button>
                            <?php $this->inline_post_button('mds3_archive_package', 'mds3_archive_package_' . $package['id'], ['grid_id' => $grid->id(), 'package_id' => $package['id']], __('Archive', 'million-dollar-script'), 'button-small'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</<?php echo esc_html($tag); ?>>
