<?php
/**
 * Choose which optional pages to create.
 *
 * The standard-pages step always creates the pages a store cannot work without —
 * the Pixel Grid page, Order Pixels, Manage Pixels and Upload. The remaining
 * pages each host a step a site may not use: writing ad copy on its own page,
 * asking a customer to confirm an order before it goes on the grid, showing
 * payment status or a thank-you on their own page, the advertiser list. Those
 * are created only when this form asks for them, so a new site does not end up
 * with pages it never uses.
 *
 * Choosing here never changes what happens to a page that already exists: a page
 * in this site, or an old Million Dollar Script 2 page detected during a
 * migration, is always kept and used. Only creating a page is the choice.
 *
 * Shares its behaviour with the page-upgrade chooser: same parent checkbox, same
 * collapsible items, same script (choice-accordion-script.php).
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Setup\PageOptionAudit;

$create_optional = PageRepository::optional_labels();
$create_already = [];
foreach ($create_optional as $create_type => $create_label) {
    if (PageOptionAudit::is_usable_page(absint(get_option(PageOptionAudit::option_name($create_type), 0)))) {
        $create_already[] = $create_type;
    }
}
$create_parent_id = 'mds3-create-pages-all';
?>
<div class="mds3-upgrade-accordion mds3-create-pages-accordion">
    <p class="description">
        <?php esc_html_e('The pages your store needs are always created. Choose the additional pages you want — each one holds a step you may or may not use. Pages that already exist, here or in the site you are migrating, are used either way.', 'million-dollar-script'); ?>
    </p>
    <?php if ($create_optional && count($create_optional) > count($create_already)) : ?>
        <label class="mds3-upgrade-accordion__parent" for="<?php echo esc_attr($create_parent_id); ?>">
            <input type="checkbox" id="<?php echo esc_attr($create_parent_id); ?>" data-upgrade-parent />
            <strong><?php esc_html_e('Create every additional page', 'million-dollar-script'); ?></strong>
        </label>
    <?php endif; ?>
    <?php foreach ($create_optional as $create_type => $create_label) : ?>
        <?php $create_done = in_array($create_type, $create_already, true); ?>
        <details class="mds3-upgrade-accordion__item<?php echo $create_done ? ' is-locked' : ''; ?>" open>
            <summary>
                <input type="checkbox" name="create_pages[]" value="<?php echo esc_attr($create_type); ?>" data-upgrade-check<?php echo $create_done ? ' checked disabled' : ''; ?> />
                <strong><?php echo esc_html($create_label); ?></strong>
                <em class="mds3-upgrade-accordion__state">
                    <?php if ($create_done) : ?>
                        <?php esc_html_e('Created', 'million-dollar-script'); ?>
                    <?php else : ?>
                        <?php esc_html_e('Not created', 'million-dollar-script'); ?>
                    <?php endif; ?>
                </em>
            </summary>
            <div class="mds3-upgrade-accordion__body">
                <p class="description"><?php echo esc_html(PageRepository::creation_hint($create_type)); ?></p>
            </div>
        </details>
    <?php endforeach; ?>
    <?php \MillionDollarScript\V3\Support\Template::display('admin/partials/choice-accordion-script.php'); ?>
</div>
