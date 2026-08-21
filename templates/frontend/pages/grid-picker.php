<?php
/**
 * Multi-grid picker for standard frontend pages.
 *
 * @package MillionDollarScript
 */

if (!defined('ABSPATH')) {
    exit;
}

$search = (string) ($search ?? '');
$total = absint($total ?? 0);
$page = max(1, absint($page ?? 1));
$per_page = max(1, absint($per_page ?? 20));
$from = $total ? (($page - 1) * $per_page) + 1 : 0;
$to = $total ? min($total, $page * $per_page) : 0;
$search_id = 'mds3-grid-picker-search';
?>
<section class="mds3-page-panel mds3-grid-picker-panel <?php echo esc_attr($theme_class ?? ''); ?>">
    <h2><?php echo esc_html($title ?? __('Choose a grid', 'million-dollar-script')); ?></h2>
    <p><?php echo esc_html($copy ?? __('Choose the grid you want to continue with.', 'million-dollar-script')); ?></p>

    <form class="mds3-grid-picker-search" method="get" action="<?php echo esc_url($form_action ?? ''); ?>" role="search">
        <?php foreach (($hidden_inputs ?? []) as $name => $value) : ?>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" />
        <?php endforeach; ?>
        <label for="<?php echo esc_attr($search_id); ?>"><?php echo esc_html__('Search grids', 'million-dollar-script'); ?></label>
        <div>
            <input id="<?php echo esc_attr($search_id); ?>" type="search" name="mds3_grid_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Name, slug, or description', 'million-dollar-script'); ?>" />
            <button class="button" type="submit"><?php echo esc_html__('Search', 'million-dollar-script'); ?></button>
            <?php if ('' !== $search) : ?>
                <a class="button mds3-button-secondary" href="<?php echo esc_url($clear_url ?? ''); ?>"><?php echo esc_html__('Clear', 'million-dollar-script'); ?></a>
            <?php endif; ?>
        </div>
    </form>

    <p class="mds3-grid-picker-count">
        <?php
        if ($total) {
            /* translators: 1: first visible grid number, 2: last visible grid number, 3: total active grids. */
            echo esc_html(sprintf(__('Showing %1$d-%2$d of %3$d active grids.', 'million-dollar-script'), $from, $to, $total));
        } else {
            echo esc_html__('No active grids match that search.', 'million-dollar-script');
        }
        ?>
    </p>

    <?php if (!empty($grids)) : ?>
        <ul class="mds3-grid-picker-list">
            <?php foreach ($grids as $grid) : ?>
                <li>
                    <div class="mds3-grid-picker-main">
                        <h3><?php echo esc_html($grid['title'] ?? __('Grid', 'million-dollar-script')); ?></h3>
                        <?php if (!empty($grid['description'])) : ?>
                            <p><?php echo esc_html($grid['description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($grid['dimensions']) || !empty($grid['slug'])) : ?>
                            <span class="mds3-grid-picker-meta">
                                <?php if (!empty($grid['dimensions'])) : ?>
                                    <span><?php echo esc_html($grid['dimensions']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($grid['slug'])) : ?>
                                    <span><?php echo esc_html($grid['slug']); ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($grid['url'])) : ?>
                        <a class="button" href="<?php echo esc_url($grid['url']); ?>"><?php echo esc_html($action_label ?? __('Open grid', 'million-dollar-script')); ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($pagination)) : ?>
        <nav class="mds3-grid-picker-pagination" aria-label="<?php echo esc_attr__('Grid pages', 'million-dollar-script'); ?>">
            <?php foreach ($pagination as $item) : ?>
                <?php if ('gap' === ($item['type'] ?? '')) : ?>
                    <span aria-hidden="true"><?php echo wp_kses_post($item['label'] ?? '&hellip;'); ?></span>
                <?php elseif (!empty($item['current'])) : ?>
                    <span aria-current="page"><?php echo esc_html($item['label'] ?? ''); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url($item['url'] ?? ''); ?>"><?php echo esc_html($item['label'] ?? ''); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</section>
