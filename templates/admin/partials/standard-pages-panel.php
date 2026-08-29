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
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mds3_ensure_standard_pages'); ?>
            <input type="hidden" name="action" value="mds3_ensure_standard_pages" />
            <div class="mds3-mds2-page-options">
                <label>
                    <input type="checkbox" name="mds2_replace_modified_pages" value="1" />
                    <?php esc_html_e('Replace a modified Million Dollar Script 2 grid page in place (overwrites its content).', 'million-dollar-script'); ?>
                </label>
                <label class="mds3-mds2-create-new">
                    <input type="checkbox" name="mds2_create_new_pages" value="1" />
                    <?php esc_html_e('For a modified grid page, leave the original and create a new separate page.', 'million-dollar-script'); ?>
                </label>
                <script>
                    (function () {
                        var form = document.currentScript.closest('form');
                        if (!form) {
                            return;
                        }
                        var replace = form.querySelector('[name="mds2_replace_modified_pages"]');
                        var createNew = form.querySelector('.mds3-mds2-create-new');
                        function sync() {
                            if (createNew) {
                                createNew.style.display = replace && replace.checked ? 'none' : '';
                            }
                        }
                        if (replace) {
                            replace.addEventListener('change', sync);
                        }
                        sync();
                    })();
                </script>
            </div>
            <?php submit_button(__('Create missing standard pages', 'million-dollar-script'), 'secondary', 'mds3_submit', false); ?>
        </form>
    <?php endif; ?>
</<?php echo esc_html($tag); ?>>
