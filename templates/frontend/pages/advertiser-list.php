<?php
/**
 * Advertiser list shortcode panel.
 *
 * @package MillionDollarScript
 */

if (!defined('ABSPATH')) {
    exit;
}

$columns = is_array($columns ?? null) ? $columns : ['image', 'title', 'url'];
$items = is_array($items ?? null) ? $items : [];
$layout = sanitize_html_class($layout ?? 'list', 'list');
$styles = is_array($styles ?? null) ? $styles : [];
$all_grids = !empty($all_grids);
$has_active_grids = !empty($has_active_grids);
$search = (string) ($search ?? '');
$total = absint($total ?? 0);
$page = max(1, absint($page ?? 1));
$per_page = max(1, absint($per_page ?? 24));
$from = $total ? (($page - 1) * $per_page) + 1 : 0;
$to = $total ? min($total, $page * $per_page) : 0;
$column_labels = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/advertiser/list/columns', [
    'image' => __('Image', 'million-dollar-script'),
    'title' => __('Advertiser', 'million-dollar-script'),
    'url' => __('Website', 'million-dollar-script'),
    'popup' => __('Description', 'million-dollar-script'),
    'alt' => __('Alt text', 'million-dollar-script'),
    'grid' => __('Grid', 'million-dollar-script'),
]);
$column_labels = is_array($column_labels) ? $column_labels : [];
$search_id = 'mds3-advertiser-search-' . wp_rand(1000, 9999);
?>
<section
    class="mds3-page-panel mds3-advertiser-list-panel mds3-advertiser-layout-<?php echo esc_attr($layout); ?> <?php echo esc_attr($theme_class ?? ''); ?>"
    style="<?php echo esc_attr(implode(';', $styles)); ?>"
    data-mds3-advertiser-list
>
    <h2><?php echo esc_html__('Advertiser List', 'million-dollar-script'); ?></h2>
    <?php if ($all_grids) : ?>
        <p class="mds3-advertiser-list-grid"><?php echo esc_html__('Published advertisers across all active grids.', 'million-dollar-script'); ?></p>
    <?php elseif (!empty($grid)) : ?>
        <p class="mds3-advertiser-list-grid"><?php echo esc_html((string) $grid->get('title', '')); ?></p>
    <?php endif; ?>

    <?php if (!$has_active_grids) : ?>
        <p><?php echo esc_html__('No active grid is available yet.', 'million-dollar-script'); ?></p>
    <?php elseif (!$all_grids && empty($grid)) : ?>
        <p><?php echo esc_html__('The selected grid is not available.', 'million-dollar-script'); ?></p>
    <?php else : ?>
        <?php if (!empty($search_enabled)) : ?>
            <form class="mds3-advertiser-list-toolbar" method="get" action="<?php echo esc_url($form_action ?? ''); ?>" role="search">
                <?php foreach (($hidden_inputs ?? []) as $name => $value) : ?>
                    <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" />
                <?php endforeach; ?>
                <label for="<?php echo esc_attr($search_id); ?>"><?php echo esc_html__('Search advertisers', 'million-dollar-script'); ?></label>
                <div class="mds3-advertiser-search-controls">
                    <input id="<?php echo esc_attr($search_id); ?>" type="search" inputmode="search" name="mds3_advertiser_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Advertiser, website, description, or grid', 'million-dollar-script'); ?>" />
                    <button class="button" type="submit"><?php echo esc_html__('Search', 'million-dollar-script'); ?></button>
                    <?php if ('' !== $search) : ?>
                        <a class="button mds3-button-secondary" href="<?php echo esc_url($clear_url ?? ''); ?>"><?php echo esc_html__('Clear', 'million-dollar-script'); ?></a>
                    <?php endif; ?>
                </div>
                <span class="mds3-advertiser-count">
                    <?php
                    if ($total) {
                        /* translators: 1: first visible advertiser number, 2: last visible advertiser number, 3: total matching advertisers. */
                        echo esc_html(sprintf(__('Showing %1$d-%2$d of %3$d advertisers.', 'million-dollar-script'), $from, $to, $total));
                    } elseif ('' !== $search) {
                        echo esc_html__('No advertisers match your search.', 'million-dollar-script');
                    } else {
                        echo esc_html__('No published advertisers are available yet.', 'million-dollar-script');
                    }
                    ?>
                </span>
            </form>
        <?php endif; ?>

        <?php if (empty($items) && empty($search_enabled)) : ?>
            <p><?php echo esc_html__('No published advertisers are available yet.', 'million-dollar-script'); ?></p>
        <?php elseif ('accordion' === $layout) : ?>
            <div class="mds3-advertiser-accordion">
                <?php foreach ($items as $item) : ?>
                    <?php
                    $values = is_array($item['values'] ?? null) ? $item['values'] : [];
                    $title = (string) ($values['title'] ?? '');
                    $url = (string) ($item['url'] ?? '');
                    $display_url = (string) ($values['url'] ?? '');
                    $image = (string) ($values['image'] ?? '');
                    ?>
                    <details class="mds3-advertiser-item <?php echo in_array('image', $columns, true) && $image ? '' : 'mds3-advertiser-item-no-thumb'; ?>" data-mds3-advertiser-item>
                        <summary>
                            <?php if (in_array('image', $columns, true) && $image) : ?>
                                <span class="mds3-advertiser-thumb"><?php echo wp_kses_post($image); ?></span>
                            <?php endif; ?>
                            <span class="mds3-advertiser-summary">
                                <strong><?php echo esc_html($title); ?></strong>
                                <?php if ($url && $display_url) : ?>
                                    <span><?php echo esc_html($display_url); ?></span>
                                <?php endif; ?>
                            </span>
                        </summary>
                        <dl class="mds3-advertiser-fields">
                            <?php foreach ($columns as $column) : ?>
                                <?php
                                if (in_array($column, ['image', 'title'], true) || !isset($values[$column])) {
                                    continue;
                                }
                                $label = sanitize_text_field((string) ($column_labels[$column] ?? ucfirst(str_replace('-', ' ', $column))));
                                $value = (string) $values[$column];
                                if ('' === $value) {
                                    continue;
                                }
                                ?>
                                <div>
                                    <dt><?php echo esc_html($label); ?></dt>
                                    <dd>
                                        <?php if ('url' === $column && $url) : ?>
                                            <a href="<?php echo esc_url($url); ?>" rel="nofollow sponsored noopener" target="_blank"><?php echo esc_html($value); ?></a>
                                        <?php else : ?>
                                            <?php echo esc_html($value); ?>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <ul class="mds3-advertiser-list mds3-advertiser-list--<?php echo esc_attr($layout); ?>">
                <?php foreach ($items as $item) : ?>
                    <?php
                    $values = is_array($item['values'] ?? null) ? $item['values'] : [];
                    $title = (string) ($values['title'] ?? '');
                    $url = (string) ($item['url'] ?? '');
                    $image = (string) ($values['image'] ?? '');
                    ?>
                    <li class="mds3-advertiser-item <?php echo in_array('image', $columns, true) && $image ? '' : 'mds3-advertiser-item-no-thumb'; ?>" data-mds3-advertiser-item>
                        <?php if (in_array('image', $columns, true) && $image) : ?>
                            <span class="mds3-advertiser-thumb"><?php echo wp_kses_post($image); ?></span>
                        <?php endif; ?>
                        <span class="mds3-advertiser-meta">
                            <?php if (in_array('title', $columns, true) && $title) : ?>
                                <strong><?php echo esc_html($title); ?></strong>
                            <?php endif; ?>
                            <?php foreach ($columns as $column) : ?>
                                <?php
                                if (in_array($column, ['image', 'title'], true) || !isset($values[$column])) {
                                    continue;
                                }
                                $label = sanitize_text_field((string) ($column_labels[$column] ?? ucfirst(str_replace('-', ' ', $column))));
                                $value = (string) $values[$column];
                                if ('' === $value) {
                                    continue;
                                }
                                ?>
                                <span class="mds3-advertiser-field mds3-advertiser-field-<?php echo esc_attr(sanitize_html_class($column)); ?>">
                                    <span><?php echo esc_html($label); ?></span>
                                    <?php if ('url' === $column && $url) : ?>
                                        <a href="<?php echo esc_url($url); ?>" rel="nofollow sponsored noopener" target="_blank"><?php echo esc_html($value); ?></a>
                                    <?php else : ?>
                                        <span><?php echo esc_html($value); ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($pagination)) : ?>
            <nav class="mds3-grid-picker-pagination mds3-advertiser-pagination" aria-label="<?php echo esc_attr__('Advertiser pages', 'million-dollar-script'); ?>">
                <?php foreach ($pagination as $pagination_item) : ?>
                    <?php if ('gap' === ($pagination_item['type'] ?? '')) : ?>
                        <span aria-hidden="true"><?php echo wp_kses_post($pagination_item['label'] ?? '&hellip;'); ?></span>
                    <?php elseif (!empty($pagination_item['current'])) : ?>
                        <span aria-current="page"><?php echo esc_html($pagination_item['label'] ?? ''); ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url($pagination_item['url'] ?? ''); ?>"><?php echo esc_html($pagination_item['label'] ?? ''); ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
