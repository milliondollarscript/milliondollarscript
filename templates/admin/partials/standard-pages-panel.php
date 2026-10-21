<?php
/**
 * Standard pages panel.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$tag = $standalone ? 'section' : 'div';
$class = $standalone ? 'mds3-card' : 'mds3-standard-pages-panel';
?>
<<?php echo esc_html($tag); ?> class="<?php echo esc_attr($class); ?>">
    <h2><?php esc_html_e('Standard Pages', 'million-dollar-script'); ?></h2>
    <?php if (!$grid_enabled) : ?>
        <p><?php esc_html_e('Classic Pixel Grid is disabled, so Million Dollar Script grid, checkout, upload, and account pages are not created yet.', 'million-dollar-script'); ?></p>
        <p><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>"><?php esc_html_e('Enable Classic Pixel Grid', 'million-dollar-script'); ?></a></p>
    <?php else : ?>
        <p><?php esc_html_e('Create or repair the standard Million Dollar Script page set. Existing assigned or migrated pages are preserved; only missing page roles are created as normal WordPress Pages.', 'million-dollar-script'); ?></p>
        <?php if (!empty($page_rows)) : ?>
            <?php
            $page_missing = [];
            foreach ($page_rows as $page_row) {
                if (!empty($page_row['missing'])) {
                    $page_missing[] = (string) $page_row['label'];
                }
            }
            ?>
            <h3 class="mds3-standard-pages-panel__title">
                <?php esc_html_e('Pages your store needs', 'million-dollar-script'); ?>
            </h3>
            <?php if ($page_missing) : ?>
                <p class="description">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %s: comma-separated list of missing page roles */
                        __('Not created yet: %s. The button below creates those pages and leaves every page that already exists alone.', 'million-dollar-script'),
                        implode(', ', $page_missing)
                    ));
                    ?>
                </p>
            <?php else : ?>
                <p class="description"><?php esc_html_e('Every page your store needs is already created. Adding pages below is your choice.', 'million-dollar-script'); ?></p>
            <?php endif; ?>
            <ul class="mds3-page-role-list">
                <?php
                foreach ($page_rows as $page_row) :
                    $page_is_missing = !empty($page_row['missing']);
                    ?>
                    <li class="mds3-page-role-list__item<?php echo $page_is_missing ? ' is-missing' : ''; ?>">
                        <span class="mds3-inline-status <?php echo $page_is_missing ? 'needs-action' : 'is-ready'; ?>">
                            <?php echo esc_html($page_is_missing ? __('Not created', 'million-dollar-script') : __('Created', 'million-dollar-script')); ?>
                        </span>
                        <strong><?php echo esc_html($page_row['label']); ?></strong>
                        <?php if (!$page_is_missing) : ?>
                            <span class="mds3-page-role-list__page">
                                <a href="<?php echo esc_url($page_row['edit_url']); ?>"><?php echo esc_html($page_row['title'] ? $page_row['title'] : __('(untitled)', 'million-dollar-script')); ?></a>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form id="mds3-ensure-standard-pages" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mds3_ensure_standard_pages'); ?>
            <input type="hidden" name="action" value="mds3_ensure_standard_pages" />
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to ?? 'migration'); ?>" />
            <?php \MillionDollarScript\V3\Support\Template::display('admin/partials/mds2-page-upgrade-accordion.php', [
                'candidates' => $candidates ?? [],
                'field' => 'mds2_upgrade_pages',
                'form_ids' => $form_ids ?? 'mds3-ensure-standard-pages',
            ]); ?>
            <h3 class="mds3-standard-pages-panel__title">
                <?php esc_html_e('Extra pages you can add', 'million-dollar-script'); ?>
            </h3>
            <?php \MillionDollarScript\V3\Support\Template::display('admin/partials/create-pages-accordion.php'); ?>
            <?php submit_button(__('Create missing standard pages', 'million-dollar-script'), 'secondary', 'mds3_submit', false); ?>
        </form>
    <?php endif; ?>
</<?php echo esc_html($tag); ?>>
