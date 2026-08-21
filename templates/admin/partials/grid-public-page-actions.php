<?php
/**
 * Grid public page actions.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if ($page_id) : ?>
    <span class="mds3-inline-status">
        <?php
        echo esc_html(
            'interactive' === $page_mode
                ? __('Mode: ordering enabled', 'million-dollar-script')
                : __('Mode: view-only', 'million-dollar-script')
        );
        ?>
    </span>
<?php endif; ?>
<?php if ($page_url) : ?>
    <a class="button button-small" href="<?php echo esc_url($page_url); ?>"><?php esc_html_e('View Page', 'million-dollar-script'); ?></a>
<?php endif; ?>
<?php if ($edit_url) : ?>
    <a class="button button-small" href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit Page', 'million-dollar-script'); ?></a>
<?php endif; ?>
<?php if (!empty($allow_mode_toggle) && $page_id && 'interactive' !== $page_mode) : ?>
    <?php $this->inline_post_button('mds3_set_grid_page_mode', 'mds3_set_grid_page_mode_' . $grid->id(), ['grid_id' => $grid->id(), 'page_mode' => 'interactive'], __('Enable ordering', 'million-dollar-script'), 'button-small'); ?>
<?php elseif (!empty($allow_mode_toggle) && $page_id) : ?>
    <?php $this->inline_post_button('mds3_set_grid_page_mode', 'mds3_set_grid_page_mode_' . $grid->id(), ['grid_id' => $grid->id(), 'page_mode' => 'read_only'], __('Switch to view-only', 'million-dollar-script'), 'button-small'); ?>
<?php endif; ?>
<?php if (!$page_id) : ?>
    <?php $this->inline_post_button('mds3_create_grid_page', 'mds3_create_grid_page_' . $grid->id(), ['grid_id' => $grid->id()], __('Create Page', 'million-dollar-script'), 'button-small'); ?>
<?php endif; ?>
