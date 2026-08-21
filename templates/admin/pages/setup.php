<?php
/**
 * MDS3 setup admin page.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap mds3-admin">
    <h1><?php esc_html_e('Setup', 'million-dollar-script'); ?></h1>

    <?php if (!empty($_GET['mds3_extension_status']) && !empty($_GET['mds3_extension_message'])) : ?>
        <?php $this->extension_notice(); ?>
    <?php endif; ?>

    <?php if (!empty($_GET['saved'])) : ?>
        <div class="notice notice-success inline"><p><?php esc_html_e('Setup preferences saved.', 'million-dollar-script'); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['extensions_activated'])) : ?>
        <div class="notice notice-success inline">
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: %d: number of activated extensions */
                    _n('%d selected extension was activated.', '%d selected extensions were activated.', absint($_GET['extensions_activated']), 'million-dollar-script'),
                    absint($_GET['extensions_activated'])
                ));
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['extension_errors'])) : ?>
        <div class="notice notice-warning inline">
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: %d: number of skipped extension actions */
                    _n('%d selected extension needs attention before it can be activated.', '%d selected extensions need attention before they can be activated.', absint($_GET['extension_errors']), 'million-dollar-script'),
                    absint($_GET['extension_errors'])
                ));
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['pages'])) : ?>
        <div class="notice notice-success inline"><p><?php esc_html_e('Standard pages are ready.', 'million-dollar-script'); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['pages_error'])) : ?>
        <div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(rawurldecode(wp_unslash($_GET['pages_error'])))); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['starter_site'])) : ?>
        <?php $starter_site_has_errors = !empty($starter_site_result['errors']); ?>
        <?php $starter_site_navigation_needs_review = !empty($starter_site_result['status']['navigation_needs_review']); ?>
        <div class="notice <?php echo esc_attr($starter_site_has_errors ? 'notice-warning' : 'notice-success'); ?> inline">
            <p>
                <?php echo esc_html($starter_site_has_errors
                    ? __('Setup was saved, but some optional starter-site items need attention.', 'million-dollar-script')
                    : ($starter_site_navigation_needs_review
                        ? __('Starter pages are ready. Review the preserved navigation to add any links you want to publish.', 'million-dollar-script')
                        : __('Starter pages and navigation are ready.', 'million-dollar-script'))); ?>
            </p>
            <?php if ($starter_site_has_errors) : ?>
                <ul>
                    <?php foreach ($starter_site_result['errors'] as $message) : ?>
                        <li><?php echo esc_html((string) $message); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['payment_error'])) : ?>
        <div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(rawurldecode(wp_unslash($_GET['payment_error'])))); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['dependency_installed'])) : ?>
        <div class="notice notice-success inline"><p><?php esc_html_e('Plugin dependency installed and activated.', 'million-dollar-script'); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['dependency_error'])) : ?>
        <div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(rawurldecode(wp_unslash($_GET['dependency_error'])))); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['extension_setup_pages'])) : ?>
        <?php
        $page_errors = absint($_GET['setup_pages_errors'] ?? 0);
        $page_skipped = absint($_GET['setup_pages_skipped'] ?? 0);
        $page_notice_class = $page_errors || $page_skipped ? 'notice-warning' : 'notice-success';
        ?>
        <div class="notice <?php echo esc_attr($page_notice_class); ?> inline">
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: created count, 2: updated count */
                    __('Extension pages processed. Created: %1$d. Updated: %2$d.', 'million-dollar-script'),
                    absint($_GET['setup_pages_created'] ?? 0),
                    absint($_GET['setup_pages_updated'] ?? 0)
                ));
                ?>
            </p>
            <?php if (!empty($extension_page_result['skipped']) || !empty($extension_page_result['errors'])) : ?>
                <ul>
                    <?php foreach (array_merge($extension_page_result['skipped'] ?? [], $extension_page_result['errors'] ?? []) as $message) : ?>
                        <li><?php echo esc_html((string) $message); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['extension_legal_pages'])) : ?>
        <?php
        $legal_errors = absint($_GET['legal_pages_errors'] ?? 0);
        $legal_skipped = absint($_GET['legal_pages_skipped'] ?? 0);
        $legal_notice_class = $legal_errors || $legal_skipped ? 'notice-warning' : 'notice-success';
        ?>
        <div class="notice <?php echo esc_attr($legal_notice_class); ?> inline">
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: created count, 2: updated count */
                    __('Extension legal drafts processed. Created: %1$d. Updated: %2$d.', 'million-dollar-script'),
                    absint($_GET['legal_pages_created'] ?? 0),
                    absint($_GET['legal_pages_updated'] ?? 0)
                ));
                ?>
            </p>
            <?php if (!empty($extension_legal_result['skipped']) || !empty($extension_legal_result['errors'])) : ?>
                <ul>
                    <?php foreach (array_merge($extension_legal_result['skipped'] ?? [], $extension_legal_result['errors'] ?? []) as $message) : ?>
                        <li><?php echo esc_html((string) $message); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['mds2_action'])) : ?>
        <?php $this->mds2_action_notice(sanitize_key(wp_unslash($_GET['mds2_action'])), absint($_GET['deactivated'] ?? 0), absint($_GET['skipped'] ?? 0)); ?>
    <?php endif; ?>

    <section class="mds3-card">
        <div class="mds3-setup-intro">
            <div>
                <h2><?php esc_html_e('Launch essentials', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Choose the checkout path, confirm the grid, and create the public pages needed for visitors. Advanced capabilities can stay on their defaults until you need them.', 'million-dollar-script'); ?></p>
            </div>
            <span class="mds3-status-pill <?php echo esc_attr($setup_complete || $setup_ready ? 'is-ready' : 'needs-action'); ?>">
                <?php
                echo esc_html($setup_complete
                    ? __('Saved', 'million-dollar-script')
                    : ($setup_ready ? __('Ready to finish', 'million-dollar-script') : __('Action needed', 'million-dollar-script')));
                ?>
            </span>
        </div>
        <ol class="mds3-setup-steps">
            <li class="<?php echo esc_attr($setup_complete ? 'is-complete' : ($setup_ready ? 'is-ready-to-finish' : 'is-pending')); ?>">
                <h2><?php esc_html_e('Setup Wizard', 'million-dollar-script'); ?></h2>
                <p>
                    <?php
                    echo esc_html($setup_complete
                        ? __('Initial preferences have been saved.', 'million-dollar-script')
                        : ($setup_ready
                            ? __('Your checkout choice is ready. Review the capabilities below, then select Finish setup to save these preferences.', 'million-dollar-script')
                            : __('Choose a checkout provider and complete its required activation steps. You can revisit advanced capabilities later.', 'million-dollar-script')));
                    ?>
                </p>
                <form id="mds3-setup-preferences" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-settings-form">
                    <?php wp_nonce_field('mds3_save_setup'); ?>
                    <input type="hidden" name="action" value="mds3_save_setup" />
                    <?php
                    $this->field('payment_provider', __('Payment Provider', 'million-dollar-script'), 'select', $settings['payment_provider'] ?? $active_payment_provider, '', $payment_options, [
                        'help' => __('Choose standalone/manual checkout, or choose WooCommerce after the WooCommerce plugin and WooCommerce Checkout extension are active.', 'million-dollar-script'),
                        'description' => __('Setup will not save WooCommerce as the provider until both required pieces are ready.', 'million-dollar-script'),
                        'docs' => 'setup',
                    ]);
                    ?>
                    <div data-mds3-woocommerce-setup<?php echo 'woocommerce' === sanitize_key((string) ($settings['payment_provider'] ?? $active_payment_provider)) ? '' : ' hidden'; ?>>
                        <?php
                        \MillionDollarScript\V3\Support\Template::display('admin/partials/setup-payment-dependencies.php', [
                            'payment_dependencies' => $payment_dependencies ?? [],
                            'woocommerce_adapter' => $woocommerce_adapter ?? [],
                        ], $this);
                        ?>
                    </div>
                    <details class="mds3-setup-advanced"<?php echo !empty($_GET['extension_errors']) ? ' open' : ''; ?>>
                        <summary><?php esc_html_e('Site capabilities', 'million-dollar-script'); ?></summary>
                        <p><?php esc_html_e('Classic Pixel Grid is selected by default. Add optional capabilities only when this site needs them.', 'million-dollar-script'); ?></p>
                        <?php $this->setup_extension_selector($extension_choices, $selected_extensions, $extension_setup); ?>
                    </details>
                </form>
            </li>

            <?php $this->mds2_upgrade_step($legacy_plugins, $legacy_source); ?>

            <?php
            if (!empty($extension_onboarding_items)) {
                \MillionDollarScript\V3\Support\Template::display('admin/partials/extension-onboarding-panel.php', [
                    'extension_onboarding_items' => $extension_onboarding_items,
                ], $this);
            }
            ?>

            <li class="is-complete">
                <h2><?php esc_html_e('Core Plugin', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Million Dollar Script database tables and core plugin services are available.', 'million-dollar-script'); ?></p>
            </li>

            <?php if ($grid_enabled) : ?>
                <li class="<?php echo esc_attr($grid ? 'is-complete' : 'is-pending'); ?>">
                    <h2><?php esc_html_e('First Grid', 'million-dollar-script'); ?></h2>
                    <?php if ($grid) : ?>
                        <p>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: 1: grid title, 2: dimensions */
                                __('First available grid: %1$s (%2$s).', 'million-dollar-script'),
                                (string) $grid->get('title', ''),
                                $grid->get('width') . 'x' . $grid->get('height')
                            ));
                            ?>
                        </p>
                        <div class="mds3-setup-page-mode">
                            <h3><?php esc_html_e('Public page mode', 'million-dollar-script'); ?></h3>
                            <p><?php esc_html_e('The first grid page is view-only by default. Visitors place ads from the separate Order Pixels page, so this showcase page can stay read-only.', 'million-dollar-script'); ?></p>
                            <div class="mds3-button-row">
                                <?php $this->grid_public_page_actions($grid, false); ?>
                                <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=mds3-grids&grid_id=' . $grid->id() . '&public_page=1')); ?>"><?php esc_html_e('Public page settings', 'million-dollar-script'); ?></a>
                            </div>
                        </div>
                        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-grids&grid_id=' . $grid->id())); ?>"><?php esc_html_e('Edit grid', 'million-dollar-script'); ?></a></p>
                    <?php else : ?>
                        <p><?php esc_html_e('Create a grid before opening orders to customers.', 'million-dollar-script'); ?></p>
                        <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mds3-grids')); ?>"><?php esc_html_e('Create grid', 'million-dollar-script'); ?></a></p>
                    <?php endif; ?>
                </li>

                <li class="<?php echo esc_attr($missing_pages ? 'is-pending' : 'is-complete'); ?>">
                    <h2><?php esc_html_e('Standard Pages', 'million-dollar-script'); ?></h2>
                    <?php if ($missing_pages) : ?>
                        <p>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %d: missing pages */
                                __('%d standard page roles are missing. Existing assigned and migrated pages will be preserved.', 'million-dollar-script'),
                                count($missing_pages)
                            ));
                            ?>
                        </p>
                        <?php $this->inline_post_button('mds3_ensure_standard_pages', 'mds3_ensure_standard_pages', ['redirect_to' => 'setup'], __('Create missing standard pages', 'million-dollar-script'), 'button-primary'); ?>
                    <?php else : ?>
                        <p><?php esc_html_e('All standard Million Dollar Script page roles are mapped.', 'million-dollar-script'); ?></p>
                        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-migration')); ?>"><?php esc_html_e('Review page map', 'million-dollar-script'); ?></a></p>
                    <?php endif; ?>
                    <div class="mds3-starter-site-option<?php echo !empty($starter_site_status['configured']) ? ' is-configured' : ''; ?>">
                        <div class="mds3-starter-site-option__heading">
                            <h3><?php esc_html_e('Optional starter site', 'million-dollar-script'); ?></h3>
                            <?php if (!empty($starter_site_status['configured'])) : ?>
                                <span class="mds3-inline-status is-ready"><?php echo esc_html(!empty($starter_site_status['navigation_needs_review'])
                                    ? __('Review navigation', 'million-dollar-script')
                                    : __('Ready', 'million-dollar-script')); ?></span>
                            <?php endif; ?>
                        </div>
                        <p><?php esc_html_e('Create editable Blog, Contact, and About pages plus navigation for Home, Blog, Order Pixels, Manage Pixels, Contact, and About. Existing pages, front-page choices, and assigned menus are preserved.', 'million-dollar-script'); ?></p>
                        <label class="mds3-starter-site-option__choice">
                            <input type="checkbox" name="mds3_create_starter_site" value="1" form="mds3-setup-preferences"<?php disabled(!empty($missing_pages)); ?> />
                            <span>
                                <strong><?php echo esc_html(!empty($starter_site_status['configured'])
                                    ? __('Repair missing starter items when setup is saved', 'million-dollar-script')
                                    : __('Create the optional starter site when setup is saved', 'million-dollar-script')); ?></strong>
                                <small><?php esc_html_e('The Contact page shows a clear fallback until the free Contact Form extension is active, then displays the form automatically.', 'million-dollar-script'); ?></small>
                            </span>
                        </label>
                        <?php if (!empty($missing_pages)) : ?>
                            <p class="description"><?php esc_html_e('Create the missing standard pages first to enable this option.', 'million-dollar-script'); ?></p>
                        <?php elseif (!empty($starter_site_status['navigation_needs_review'])) : ?>
                            <p class="description"><?php esc_html_e('Your existing navigation or occupied menu location was preserved. Add the starter pages through Appearance > Editor or Appearance > Menus when you want them in the site header.', 'million-dollar-script'); ?></p>
                        <?php endif; ?>
                    </div>
                </li>

                <li id="mds3-setup-commerce" class="is-complete">
                    <h2><?php esc_html_e('Commerce', 'million-dollar-script'); ?></h2>
                    <?php if ('standalone' !== $active_payment_provider) : ?>
                        <p>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: provider label */
                                __('%s is active as the Million Dollar Script payment provider.', 'million-dollar-script'),
                                (string) ($payment_options[$active_payment_provider] ?? $active_payment_provider)
                            ));
                            ?>
                        </p>
                        <?php if ('woocommerce' === $active_payment_provider) : ?>
                            <?php $checkout_readiness_items = is_array($payment_provider_readiness['items'] ?? null) ? $payment_provider_readiness['items'] : []; ?>
                            <div class="mds3-setup-checkout-review" aria-label="<?php esc_attr_e('WooCommerce checkout readiness', 'million-dollar-script'); ?>">
                                <?php foreach ($checkout_readiness_items as $readiness_item) : ?>
                                    <?php if (!is_array($readiness_item) || empty($readiness_item['label'])) { continue; } ?>
                                    <?php $readiness_item_ready = !empty($readiness_item['ready']); ?>
                                    <div>
                                        <span class="mds3-inline-status <?php echo esc_attr($readiness_item_ready ? 'is-ready' : 'needs-action'); ?>">
                                            <?php echo esc_html($readiness_item_ready ? __('Ready', 'million-dollar-script') : __('Review', 'million-dollar-script')); ?>
                                        </span>
                                        <strong><?php echo esc_html((string) $readiness_item['label']); ?></strong>
                                        <?php if (!empty($readiness_item['description'])) : ?>
                                            <small><?php echo esc_html((string) $readiness_item['description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mds3-button-row">
                                <?php foreach ((array) ($payment_provider_readiness['actions'] ?? []) as $readiness_action) : ?>
                                    <?php if (!is_array($readiness_action) || empty($readiness_action['url']) || empty($readiness_action['label'])) { continue; } ?>
                                    <a class="button<?php echo !empty($readiness_action['primary']) ? ' button-primary' : ''; ?>" href="<?php echo esc_url((string) $readiness_action['url']); ?>"><?php echo esc_html((string) $readiness_action['label']); ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php \MillionDollarScript\Core\Hooks::do('million-dollar-script/setup/payment/provider/actions', $active_payment_provider, $settings); ?>
                    <?php else : ?>
                        <p><?php esc_html_e('Standalone/manual checkout is active. Install and activate a payment provider extension such as WooCommerce or EDD when you want Million Dollar Script to hand checkout to a store or gateway.', 'million-dollar-script'); ?></p>
                        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-extensions')); ?>"><?php esc_html_e('Find payment extensions', 'million-dollar-script'); ?></a></p>
                    <?php endif; ?>
                </li>

                <?php if (!empty($has_mds2_upgrade_context)) : ?>
                    <li class="is-pending">
                        <h2><?php esc_html_e('Million Dollar Script 2 Migration', 'million-dollar-script'); ?></h2>
                        <p><?php esc_html_e('Run a dry run before importing Million Dollar Script 2 banners, pages, orders, media, packages, price zones, and settings.', 'million-dollar-script'); ?></p>
                        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-migration')); ?>"><?php esc_html_e('Open migration', 'million-dollar-script'); ?></a></p>
                    </li>
                <?php endif; ?>
            <?php else : ?>
                <li class="is-complete">
                    <h2><?php esc_html_e('Classic Pixel Grid', 'million-dollar-script'); ?></h2>
                    <p><?php esc_html_e('Classic Pixel Grid is disabled. Grid pages, order checkout, and Million Dollar Script 2 grid migration are hidden until you enable it in the site capabilities above.', 'million-dollar-script'); ?></p>
                </li>
            <?php endif; ?>
        </ol>
        <div class="mds3-setup-finish<?php echo !$setup_complete && $setup_ready ? ' is-ready-to-finish' : ''; ?>">
            <div>
                <h2><?php echo esc_html($setup_complete ? __('Save setup changes', 'million-dollar-script') : ($setup_ready ? __('Ready to finish', 'million-dollar-script') : __('Finish setup', 'million-dollar-script'))); ?></h2>
                <p>
                    <?php
                    echo esc_html($setup_complete
                        ? __('Save whenever you change the checkout provider or site capabilities.', 'million-dollar-script')
                        : ($setup_ready
                            ? __('Select Finish setup to save the checkout provider and site capabilities for this site.', 'million-dollar-script')
                            : __('Complete the checkout requirements above, then save the provider and site capabilities for this site.', 'million-dollar-script')));
                    ?>
                </p>
            </div>
            <button type="submit" form="mds3-setup-preferences" class="button button-primary button-hero">
                <?php echo esc_html($setup_complete ? __('Save setup changes', 'million-dollar-script') : __('Finish setup', 'million-dollar-script')); ?>
            </button>
        </div>

        <?php if ($setup_complete) : ?>
            <?php
            $grid_review_url = $grid
                ? \MillionDollarScript\V3\Grid\GridPostType::page_url($grid->id())
                : '';
            if (!$grid_review_url && $grid) {
                $grid_review_url = admin_url('admin.php?page=mds3-grids&grid_id=' . $grid->id());
            }
            $woocommerce_checkout = 'woocommerce' === $active_payment_provider;
            ?>
            <section class="mds3-setup-next-steps" aria-labelledby="mds3-setup-next-steps-title">
                <div>
                    <h2 id="mds3-setup-next-steps-title"><?php esc_html_e('Setup complete', 'million-dollar-script'); ?></h2>
                    <p>
                        <?php echo esc_html($woocommerce_checkout
                            ? __('Confirm the store checkout details, then continue managing the site from the dashboard.', 'million-dollar-script')
                            : __('Continue to the dashboard to review the grid, orders, pages, and launch status.', 'million-dollar-script')); ?>
                    </p>
                </div>
                <div class="mds3-setup-next-actions">
                    <?php if ($woocommerce_checkout) : ?>
                        <a class="button button-primary button-hero" href="<?php echo esc_url((string) ($payment_provider_readiness['review_url'] ?? admin_url('admin.php?page=mds3-extensions'))); ?>"><?php esc_html_e('Review checkout', 'million-dollar-script'); ?></a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3')); ?>"><?php esc_html_e('Open Dashboard', 'million-dollar-script'); ?></a>
                    <?php else : ?>
                        <a class="button button-primary button-hero" href="<?php echo esc_url(admin_url('admin.php?page=mds3')); ?>"><?php esc_html_e('Open Dashboard', 'million-dollar-script'); ?></a>
                    <?php endif; ?>
                </div>
                <nav class="mds3-setup-secondary-links" aria-label="<?php esc_attr_e('Additional setup destinations', 'million-dollar-script'); ?>">
                    <?php if ($grid_review_url) : ?>
                        <a href="<?php echo esc_url($grid_review_url); ?>"><?php esc_html_e('Review grid', 'million-dollar-script'); ?></a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mds3-extensions')); ?>"><?php esc_html_e('Manage extensions', 'million-dollar-script'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mds3-settings')); ?>"><?php esc_html_e('Review settings', 'million-dollar-script'); ?></a>
                </nav>
            </section>

            <aside class="mds3-setup-extension-upgrade">
                <div>
                    <h2><?php esc_html_e('Extend your site', 'million-dollar-script'); ?></h2>
                    <p><?php esc_html_e('Add advanced campaign tools, automation, and specialized layouts when your site needs more capabilities.', 'million-dollar-script'); ?></p>
                </div>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-extensions')); ?>"><?php esc_html_e('Explore premium extensions', 'million-dollar-script'); ?></a>
            </aside>
        <?php endif; ?>
    </section>
</div>
