<?php
/**
 * MDS3 grids admin page.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$grid_list = is_array($grid_list ?? null) ? $grid_list : [];
$grid_counts = is_array($grid_list['counts'] ?? null) ? $grid_list['counts'] : [];
$current_status = sanitize_key((string) ($grid_list['status'] ?? 'all'));
$search = sanitize_text_field((string) ($grid_list['search'] ?? ''));
$orderby = sanitize_key((string) ($grid_list['orderby'] ?? 'id'));
$order = 'asc' === strtolower((string) ($grid_list['order'] ?? '')) ? 'asc' : 'desc';
$current_page = max(1, absint($grid_list['paged'] ?? 1));
$per_page = max(1, absint($grid_list['per_page'] ?? 20));
$total_grids = absint($grid_list['total'] ?? count($grids ?? []));
$total_pages = max(1, absint($grid_list['total_pages'] ?? 1));
$from = $total_grids ? (($current_page - 1) * $per_page) + 1 : 0;
$to = $total_grids ? min($total_grids, $current_page * $per_page) : 0;
$grid_list_url = static function (array $overrides = []) use ($current_status, $search, $orderby, $order, $current_page) {
    $state = [
        'grid_status' => $current_status,
        's' => $search,
        'orderby' => $orderby,
        'order' => $order,
        'paged' => $current_page,
    ];
    foreach ($overrides as $key => $value) {
        $state[$key] = $value;
    }

    $args = ['page' => 'mds3-grids'];
    foreach ($state as $key => $value) {
        if ('' === $value || null === $value || ('paged' === $key && 1 === absint($value))) {
            continue;
        }
        if ('grid_status' === $key && 'all' === $value) {
            continue;
        }
        if ('orderby' === $key && 'id' === $value) {
            continue;
        }
        if ('order' === $key && 'desc' === $value) {
            continue;
        }
        $args[$key] = $value;
    }

    return add_query_arg($args, admin_url('admin.php'));
};
$sort_link = static function ($key) use ($grid_list_url, $orderby, $order) {
    $key = sanitize_key($key);
    $next_order = ($orderby === $key && 'asc' === $order) ? 'desc' : 'asc';

    return $grid_list_url([
        'orderby' => $key,
        'order' => $next_order,
        'paged' => 1,
    ]);
};
$sort_label = static function ($key) use ($orderby, $order) {
    if ($orderby !== $key) {
        return '';
    }

    return 'asc' === $order ? ' ↑' : ' ↓';
};
$status_labels = [
    'all' => __('All', 'million-dollar-script'),
    'active' => __('Active', 'million-dollar-script'),
    'paused' => __('Paused', 'million-dollar-script'),
    'archived' => __('Archived', 'million-dollar-script'),
];
$grid_list_context = [
    'status' => $current_status,
    'search' => $search,
    'orderby' => $orderby,
    'order' => $order,
    'paged' => $current_page,
    'per_page' => $per_page,
    'total' => $total_grids,
    'total_pages' => $total_pages,
    'counts' => $grid_counts,
];
$extra_grid_columns = $this->grid_list_extra_columns($grid_list_context);
?>
<div class="wrap mds3-admin">
    <h1><?php esc_html_e('Grids', 'million-dollar-script'); ?></h1>

    <?php if (!empty($_GET['grid_error'])) : ?>
        <div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['grid_error']))); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($grid_import_result['created']) && is_array($grid_import_result['created'])) : ?>
        <div class="notice notice-success inline">
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: %d: number of grids imported. */
                    _n('%d grid was imported.', '%d grids were imported.', count($grid_import_result['created']), 'million-dollar-script'),
                    count($grid_import_result['created'])
                ));
                ?>
            </p>
            <ul>
                <?php foreach ($grid_import_result['created'] as $created_grid) : ?>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=mds3-grids&grid_id=' . absint($created_grid['id'] ?? 0))); ?>">
                            <?php echo esc_html($created_grid['title'] ?? __('Imported grid', 'million-dollar-script')); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($grid_import_result['errors'])) : ?>
                <p><?php echo esc_html(implode(' ', array_map('sanitize_text_field', (array) $grid_import_result['errors']))); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($editing) : ?>
        <?php $editing_settings = $editing->settings(); ?>
        <?php if (!empty($grid_capacity['needs_review'])) : ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: virtual block count, 2: active placement count. */
                        __('Capacity review recommended: this grid has %1$s virtual blocks and %2$s active placements. Empty cells remain sparse, but large placement payloads or dimensions beyond the measured range should be tested on the target host and browser.', 'million-dollar-script'),
                        number_format_i18n(absint($grid_capacity['virtual_blocks'] ?? 0)),
                        number_format_i18n(absint($grid_capacity['active_placements'] ?? 0))
                    ));
                    ?>
                    <a href="<?php echo esc_url(\MillionDollarScript\V3\Support\GridCapacityStatus::capacity_guide_url()); ?>"><?php esc_html_e('Review capacity guidance', 'million-dollar-script'); ?></a>
                </p>
            </div>
        <?php endif; ?>
        <section class="mds3-card mds3-grid-edit-shell" data-mds3-tab-container>
            <h2><?php esc_html_e('Edit Grid', 'million-dollar-script'); ?></h2>
            <div class="mds3-settings-tabs mds3-grid-tabs" role="tablist" aria-label="<?php esc_attr_e('Grid edit sections', 'million-dollar-script'); ?>">
                <?php foreach ($grid_tabs as $tab_id => $tab_label) : ?>
                    <?php $is_active = $active_tab === $tab_id; ?>
                    <button type="button" role="tab" class="<?php echo esc_attr($is_active ? 'is-active' : ''); ?>" aria-selected="<?php echo esc_attr($is_active ? 'true' : 'false'); ?>" data-settings-tab="<?php echo esc_attr($tab_id); ?>">
                        <?php echo esc_html($tab_label); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="mds3-settings-panels mds3-grid-edit-panels">
                <section class="mds3-settings-panel<?php echo esc_attr('grid-details' === $active_tab ? ' is-active' : ''); ?>" data-settings-panel="grid-details">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mds3_update_grid_' . $editing->id()); ?>
                        <input type="hidden" name="action" value="mds3_update_grid" />
                        <input type="hidden" name="grid_id" value="<?php echo esc_attr($editing->id()); ?>" />
                        <?php
                        $this->field('title', __('Title', 'million-dollar-script'), 'text', $editing->get('title'));
                        $this->field('description', __('Description', 'million-dollar-script'), 'textarea', $editing->get('description', ''));
                        $this->field('width', __('Width', 'million-dollar-script'), 'number', $editing->get('width'), '', [], [
                            'description' => __('For a classic million-pixel grid, use 1000.', 'million-dollar-script'),
                        ]);
                        $this->field('height', __('Height', 'million-dollar-script'), 'number', $editing->get('height'), '', [], [
                            'description' => __('For a classic million-pixel grid, use 1000 so the grid can fill a square viewport.', 'million-dollar-script'),
                        ]);
                        $this->field('block_width', __('Block Width', 'million-dollar-script'), 'number', $editing->get('block_width'), '', [], [
                            'description' => __('A 10-pixel block on a 1000-pixel grid creates 100 columns.', 'million-dollar-script'),
                        ]);
                        $this->field('block_height', __('Block Height', 'million-dollar-script'), 'number', $editing->get('block_height'), '', [], [
                            'description' => __('Keep this equal to block width for square classic-style selections.', 'million-dollar-script'),
                        ]);
                        $this->field('price_per_block', __('Price Per Block', 'million-dollar-script'), 'number', $editing->get('price_per_block'), '0.01', [], [
                            'description' => __('Used when no package or price zone overrides the selected blocks.', 'million-dollar-script'),
                        ]);
                        $this->field('currency', __('Currency', 'million-dollar-script'), 'text', $grid_currency, '', [], [
                            'disabled' => $currency_locked,
                            'hidden_value' => $currency_locked ? $grid_currency : null,
                            'help' => $currency_locked ? __('The active payment provider owns currency, so this grid uses the provider store currency.', 'million-dollar-script') : '',
                            'description' => $currency_locked ? __('Locked to the active payment provider currency.', 'million-dollar-script') : __('Historical orders keep their saved currency even if this value changes later.', 'million-dollar-script'),
                        ]);
                        $this->field('renderer_mode', __('Renderer', 'million-dollar-script'), 'select', $editing_settings['renderer_mode'] ?? 'auto', '', $renderer_modes, [
                            'description' => __('Automatic mode uses the best available renderer for this grid and active extensions.', 'million-dollar-script'),
                        ]);
                        $this->field('show_public_stats', __('Show Stats With Grid', 'million-dollar-script'), 'select', $editing_settings['show_public_stats'] ?? 'Y', '', ['Y', 'N'], [
                            'description' => __('Shows sold and available inventory above this grid on public read-only and order pages.', 'million-dollar-script'),
                        ]);
                        ?>
                        <h3><?php esc_html_e('Ordering Rules', 'million-dollar-script'); ?></h3>
                        <?php
                        $this->field('min_blocks', __('Minimum Blocks Per Order', 'million-dollar-script'), 'number', $editing_settings['min_blocks'] ?? 1, '', [], [
                            'description' => __('Set the smallest selection customers can reserve in one order.', 'million-dollar-script'),
                        ]);
                        $this->field('max_blocks', __('Maximum Blocks Per Order', 'million-dollar-script'), 'number', $editing_settings['max_blocks'] ?? 0, '', [], [
                            'description' => __('Use 0 when customers may buy any available selection size.', 'million-dollar-script'),
                        ]);
                        $this->field('max_orders', __('Maximum Orders', 'million-dollar-script'), 'number', $editing_settings['max_orders'] ?? 0, '', [], [
                            'description' => __('Use 0 for no grid-level order cap.', 'million-dollar-script'),
                        ]);
                        $this->field('days_expire', __('Order Duration Days', 'million-dollar-script'), 'number', $editing_settings['days_expire'] ?? 0, '', [], [
                            'description' => __('Use 0 for no placement duration limit on paid orders.', 'million-dollar-script'),
                        ]);
                        $this->field('auto_publish', __('Auto Publish Uploads', 'million-dollar-script'), 'select', $editing_settings['auto_publish'] ?? 'N', '', ['N', 'Y']);
                        $this->field('auto_approve', __('Auto Approve Orders', 'million-dollar-script'), 'select', $editing_settings['auto_approve'] ?? 'N', '', ['N', 'Y']);
                        $this->field('nfs_covered', __('Unavailable Blocks Covered', 'million-dollar-script'), 'select', $editing_settings['nfs_covered'] ?? 'N', '', ['N', 'Y']);
                        ?>
                        <h3><?php esc_html_e('Grid Background', 'million-dollar-script'); ?></h3>
                        <p><?php esc_html_e('Use a background image for unfilled inventory while keeping the background color as a reliable fallback. Background images are rendered locally so ads and interaction layers always remain visible.', 'million-dollar-script'); ?></p>
                        <?php
                        $this->field('background_color', __('Background Color', 'million-dollar-script'), 'color', $editing_settings['background_color'] ?? '#ffffff');
                        $this->field('background_image_id', __('Background Image', 'million-dollar-script'), 'image', $editing_settings['background_image_id'] ?? 0, '', [], [
                            'disabled' => !current_user_can('upload_files'),
                            'description' => __('Choose a PNG, JPEG, GIF, or WebP image from the Media Library. Clearing this field does not delete the media item.', 'million-dollar-script'),
                        ]);
                        $this->field('background_image_fit', __('Image Fit', 'million-dollar-script'), 'select', $editing_settings['background_image_fit'] ?? 'cover', '', [
                            'cover' => __('Cover', 'million-dollar-script'),
                            'contain' => __('Contain', 'million-dollar-script'),
                            'stretch' => __('Stretch', 'million-dollar-script'),
                            'auto' => __('Original size', 'million-dollar-script'),
                        ]);
                        $this->field('background_image_position', __('Image Position', 'million-dollar-script'), 'select', $editing_settings['background_image_position'] ?? 'center', '', [
                            'top-left' => __('Top left', 'million-dollar-script'),
                            'top' => __('Top center', 'million-dollar-script'),
                            'top-right' => __('Top right', 'million-dollar-script'),
                            'left' => __('Center left', 'million-dollar-script'),
                            'center' => __('Center', 'million-dollar-script'),
                            'right' => __('Center right', 'million-dollar-script'),
                            'bottom-left' => __('Bottom left', 'million-dollar-script'),
                            'bottom' => __('Bottom center', 'million-dollar-script'),
                            'bottom-right' => __('Bottom right', 'million-dollar-script'),
                        ]);
                        $this->field('background_image_repeat', __('Image Repeat', 'million-dollar-script'), 'select', $editing_settings['background_image_repeat'] ?? 'no-repeat', '', [
                            'no-repeat' => __('Do not repeat', 'million-dollar-script'),
                            'repeat' => __('Repeat horizontally and vertically', 'million-dollar-script'),
                            'repeat-x' => __('Repeat horizontally', 'million-dollar-script'),
                            'repeat-y' => __('Repeat vertically', 'million-dollar-script'),
                        ]);
                        $this->field('background_image_opacity', __('Image Opacity', 'million-dollar-script'), 'number', $editing_settings['background_image_opacity'] ?? 100, '1', [], [
                            'min' => 0,
                            'max' => 100,
                            'description' => __('Enter a percentage from 0 to 100.', 'million-dollar-script'),
                        ]);
                        $this->field('status', __('Status', 'million-dollar-script'), 'select', $editing->get('status', 'active'), '', ['active', 'paused', 'archived']);
                        submit_button(__('Save grid', 'million-dollar-script'));
                        ?>
                    </form>
                </section>

                <section class="mds3-settings-panel<?php echo esc_attr('grid-public' === $active_tab ? ' is-active' : ''); ?>" data-settings-panel="grid-public">
                    <?php $this->grid_public_page_panel($editing, false); ?>
                </section>
                <section class="mds3-settings-panel<?php echo esc_attr('grid-packages' === $active_tab ? ' is-active' : ''); ?>" data-settings-panel="grid-packages">
                    <?php $this->packages_panel($editing, false); ?>
                </section>
                <section class="mds3-settings-panel<?php echo esc_attr('grid-price-zones' === $active_tab ? ' is-active' : ''); ?>" data-settings-panel="grid-price-zones">
                    <?php $this->price_rules_panel($editing, false); ?>
                </section>
                <section class="mds3-settings-panel<?php echo esc_attr('grid-availability' === $active_tab ? ' is-active' : ''); ?>" data-settings-panel="grid-availability">
                    <?php $this->availability_panel($editing, false); ?>
                </section>
                <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/admin/grid/tab/panels', $editing, $active_tab); ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="mds3-grid-page-actions" aria-label="<?php esc_attr_e('Grid actions', 'million-dollar-script'); ?>">
    <details class="mds3-disclosure mds3-grid-action-disclosure">
        <summary><span class="button button-primary"><?php esc_html_e('Create Grid', 'million-dollar-script'); ?></span></summary>
        <h2 class="screen-reader-text"><?php esc_html_e('Create Grid', 'million-dollar-script'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mds3_create_grid'); ?>
            <input type="hidden" name="action" value="mds3_create_grid" />
            <?php
            $this->field('title', __('Title', 'million-dollar-script'), 'text', 'Main Grid');
            $this->field('width', __('Width', 'million-dollar-script'), 'number', '1000', '', [], [
                'description' => __('The default 1000-pixel width matches the classic grid scale.', 'million-dollar-script'),
            ]);
            $this->field('height', __('Height', 'million-dollar-script'), 'number', '1000', '', [], [
                'description' => __('The default 1000-pixel height lets the grid expand as a full square.', 'million-dollar-script'),
            ]);
            $this->field('block_width', __('Block Width', 'million-dollar-script'), 'number', '10', '', [], [
                'description' => __('Ten-pixel blocks create a classic 100 by 100 block grid.', 'million-dollar-script'),
            ]);
            $this->field('block_height', __('Block Height', 'million-dollar-script'), 'number', '10', '', [], [
                'description' => __('Use the same value as block width for square selections.', 'million-dollar-script'),
            ]);
            $this->field('price_per_block', __('Price Per Block', 'million-dollar-script'), 'number', '1.00', '0.01', [], [
                'description' => __('The starter price before packages or zones are added.', 'million-dollar-script'),
            ]);
            $this->field('renderer_mode', __('Renderer', 'million-dollar-script'), 'select', 'auto', '', $renderer_modes, [
                'description' => __('Automatic is recommended for new grids.', 'million-dollar-script'),
            ]);
            $this->field('show_public_stats', __('Show Stats With Grid', 'million-dollar-script'), 'select', 'Y', '', ['Y', 'N'], [
                'description' => __('Shows sold and available inventory above this grid on public read-only and order pages.', 'million-dollar-script'),
            ]);
            $this->field('min_blocks', __('Minimum Blocks Per Order', 'million-dollar-script'), 'number', '1');
            $this->field('max_blocks', __('Maximum Blocks Per Order', 'million-dollar-script'), 'number', '0');
            $this->field('days_expire', __('Order Duration Days', 'million-dollar-script'), 'number', '0');
            submit_button(__('Create grid', 'million-dollar-script'));
            ?>
        </form>
    </details>

    <details class="mds3-disclosure mds3-grid-action-disclosure">
        <summary><span class="button"><?php esc_html_e('Import Grids', 'million-dollar-script'); ?></span></summary>
        <h2 class="screen-reader-text"><?php esc_html_e('Import Grids', 'million-dollar-script'); ?></h2>
        <p class="description"><?php esc_html_e('Import a Million Dollar Script grid configuration export. Imported grids are created as new grids; existing grids, orders, paid placements, and uploaded ad media are not overwritten.', 'million-dollar-script'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('mds3_import_grids'); ?>
            <input type="hidden" name="action" value="mds3_import_grids" />
            <input type="file" name="grid_import_file" accept="application/json,.json" required />
            <?php submit_button(__('Import grids', 'million-dollar-script'), 'secondary', 'submit', false); ?>
        </form>
    </details>
    </div>

    <section class="mds3-card">
        <h2><?php esc_html_e('Grids', 'million-dollar-script'); ?></h2>
        <div class="mds3-grid-list-toolbar">
            <nav class="mds3-grid-status-filters" aria-label="<?php esc_attr_e('Grid status filters', 'million-dollar-script'); ?>">
                <?php foreach ($status_labels as $status_key => $status_label) : ?>
                    <?php $is_current_status = $current_status === $status_key; ?>
                    <a class="<?php echo esc_attr($is_current_status ? 'is-active' : ''); ?>" href="<?php echo esc_url($grid_list_url(['grid_status' => $status_key, 'paged' => 1])); ?>" <?php echo $is_current_status ? 'aria-current="page"' : ''; ?>>
                        <span><?php echo esc_html($status_label); ?></span>
                        <small><?php echo esc_html(absint($grid_counts[$status_key] ?? 0)); ?></small>
                    </a>
                <?php endforeach; ?>
            </nav>
            <form class="mds3-grid-search" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="mds3-grids" />
                <?php if ('all' !== $current_status) : ?>
                    <input type="hidden" name="grid_status" value="<?php echo esc_attr($current_status); ?>" />
                <?php endif; ?>
                <?php if ('id' !== $orderby) : ?>
                    <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>" />
                <?php endif; ?>
                <?php if ('desc' !== $order) : ?>
                    <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>" />
                <?php endif; ?>
                <label class="screen-reader-text" for="mds3-grid-search"><?php esc_html_e('Search grids', 'million-dollar-script'); ?></label>
                <input id="mds3-grid-search" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search grids', 'million-dollar-script'); ?>" />
                <button type="submit" class="button"><?php esc_html_e('Search', 'million-dollar-script'); ?></button>
                <?php if ('' !== $search) : ?>
                    <a class="button button-link" href="<?php echo esc_url($grid_list_url(['s' => '', 'paged' => 1])); ?>"><?php esc_html_e('Clear', 'million-dollar-script'); ?></a>
                <?php endif; ?>
            </form>
        </div>
        <?php if ($total_grids) : ?>
            <p class="mds3-grid-result-count">
                <?php /* translators: 1: first visible grid number, 2: last visible grid number, 3: total grids. */ ?>
                <?php echo esc_html(sprintf(__('Showing %1$d-%2$d of %3$d grids.', 'million-dollar-script'), $from, $to, $total_grids)); ?>
            </p>
            <form id="mds3-grid-bulk-export" class="mds3-grid-bulk-actions" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mds3_export_grids'); ?>
                <input type="hidden" name="action" value="mds3_export_grids" />
                <button type="submit" class="button"><?php esc_html_e('Export selected', 'million-dollar-script'); ?></button>
                <span class="description"><?php esc_html_e('Exports grid configuration, packages, price zones, and admin availability settings. Orders and uploaded ad media are summarized only.', 'million-dollar-script'); ?></span>
            </form>
        <?php endif; ?>
        <?php if (!$grids) : ?>
            <?php if ('' !== $search || 'all' !== $current_status) : ?>
                <p><?php esc_html_e('No grids match the current filters.', 'million-dollar-script'); ?></p>
            <?php else : ?>
                <p><?php esc_html_e('No grids have been created yet.', 'million-dollar-script'); ?></p>
            <?php endif; ?>
        <?php else : ?>
            <div class="mds3-grid-table-scroll">
            <table class="widefat striped mds3-grids-table">
                <thead>
                    <tr>
                        <td class="check-column">
                            <input type="checkbox" class="mds3-grid-select-all" data-form="mds3-grid-bulk-export" aria-label="<?php esc_attr_e('Select all grids on this page', 'million-dollar-script'); ?>" />
                        </td>
                        <th><a href="<?php echo esc_url($sort_link('id')); ?>"><?php echo esc_html__('ID', 'million-dollar-script') . esc_html($sort_label('id')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('title')); ?>"><?php echo esc_html__('Title', 'million-dollar-script') . esc_html($sort_label('title')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('status')); ?>"><?php echo esc_html__('Status', 'million-dollar-script') . esc_html($sort_label('status')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('renderer')); ?>"><?php echo esc_html__('Renderer', 'million-dollar-script') . esc_html($sort_label('renderer')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('dimensions')); ?>"><?php echo esc_html__('Dimensions', 'million-dollar-script') . esc_html($sort_label('dimensions')); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_link('updated')); ?>"><?php echo esc_html__('Updated', 'million-dollar-script') . esc_html($sort_label('updated')); ?></a></th>
                        <?php foreach ($extra_grid_columns as $column_label) : ?>
                            <th><?php echo esc_html($column_label); ?></th>
                        <?php endforeach; ?>
                        <th><?php esc_html_e('Embed', 'million-dollar-script'); ?></th>
                        <th><?php esc_html_e('Public Page', 'million-dollar-script'); ?></th>
                        <th><?php esc_html_e('Actions', 'million-dollar-script'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grids as $grid) : ?>
                        <?php $grid_settings = $grid->settings(); ?>
                        <tr>
                            <td class="check-column">
                                <?php /* translators: %s: grid title. */ ?>
                                <input form="mds3-grid-bulk-export" type="checkbox" name="grid_ids[]" value="<?php echo esc_attr($grid->id()); ?>" aria-label="<?php echo esc_attr(sprintf(__('Select %s', 'million-dollar-script'), $grid->get('title'))); ?>" />
                            </td>
                            <td><?php echo esc_html($grid->id()); ?></td>
                            <td><?php echo esc_html($grid->get('title')); ?></td>
                            <td><?php echo esc_html($grid->get('status')); ?></td>
                            <td><?php echo esc_html($grid_settings['renderer_mode'] ?? 'auto'); ?></td>
                            <td><?php echo esc_html($grid->get('width') . 'x' . $grid->get('height') . ' / ' . $grid->get('block_width') . 'x' . $grid->get('block_height')); ?></td>
                            <td><?php echo esc_html($grid->get('updated_at', '')); ?></td>
                            <?php foreach ($extra_grid_columns as $column_key => $column_label) : ?>
                                <td class="mds3-grid-extra-column mds3-grid-extra-column-<?php echo esc_attr($column_key); ?>"><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Registered column callbacks own context-aware escaping for their markup. ?><?php echo $this->grid_list_extra_column_html($column_key, $grid, $grid_list_context); ?></td>
                            <?php endforeach; ?>
                            <?php
                            $embed_page_mode = \MillionDollarScript\V3\Grid\GridPostType::page_mode($grid->id());
                            $embed_shortcode = \MillionDollarScript\V3\Grid\GridPostType::shortcode($grid->id(), 'interactive' !== $embed_page_mode);
                            ?>
                            <td>
                                <button type="button" class="mds3-shortcode-copy" data-mds3-copy-shortcode="<?php echo esc_attr($embed_shortcode); ?>" aria-label="<?php echo esc_attr(sprintf(
                                    /* translators: %s: grid title */
                                    __('Copy embed shortcode for %s', 'million-dollar-script'),
                                    (string) $grid->get('title')
                                )); ?>">
                                    <code><?php echo esc_html($embed_shortcode); ?></code>
                                    <span class="mds3-shortcode-copy-status" aria-live="polite"><?php esc_html_e('Click to copy', 'million-dollar-script'); ?></span>
                                </button>
                            </td>
                            <td class="mds3-grid-public-page-cell">
                                <div class="mds3-button-row mds3-table-button-row">
                                    <?php $this->grid_public_page_actions($grid); ?>
                                </div>
                            </td>
                            <td>
                                <div class="mds3-button-row mds3-table-button-row">
                                    <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=mds3-grids&grid_id=' . $grid->id())); ?>"><?php esc_html_e('Edit Grid', 'million-dollar-script'); ?></a>
                                    <?php $this->inline_post_button('mds3_export_grids', 'mds3_export_grids', ['grid_id' => $grid->id()], __('Export', 'million-dollar-script'), 'button-small'); ?>
                                    <?php $this->inline_post_button('mds3_archive_grid', 'mds3_archive_grid_' . $grid->id(), ['grid_id' => $grid->id()], __('Archive', 'million-dollar-script'), 'button-small'); ?>
                                    <?php $this->grid_list_extra_row_actions($grid, $grid_list_context); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php if ($total_pages > 1) : ?>
                <nav class="mds3-grid-pagination" aria-label="<?php esc_attr_e('Grid pages', 'million-dollar-script'); ?>">
                    <?php if ($current_page > 1) : ?>
                        <a class="button" href="<?php echo esc_url($grid_list_url(['paged' => $current_page - 1])); ?>"><?php esc_html_e('Previous', 'million-dollar-script'); ?></a>
                    <?php endif; ?>
                    <?php /* translators: 1: current page number, 2: total pages. */ ?>
                    <span><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'million-dollar-script'), $current_page, $total_pages)); ?></span>
                    <?php if ($current_page < $total_pages) : ?>
                        <a class="button" href="<?php echo esc_url($grid_list_url(['paged' => $current_page + 1])); ?>"><?php esc_html_e('Next', 'million-dollar-script'); ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
