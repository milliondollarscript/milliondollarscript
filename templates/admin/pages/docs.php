<?php
/**
 * Bundled documentation admin page.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$packages = is_array($packages ?? null) ? $packages : [];
$documents = is_array($documents ?? null) ? $documents : [];
$selected_doc = is_array($selected_doc ?? null) ? $selected_doc : null;
$search = (string) ($search ?? '');
$package_slug = (string) ($package_slug ?? '');
$warnings = is_array($warnings ?? null) ? $warnings : [];
$refresh_status = (string) ($refresh_status ?? '');
$refresh_retry_after = absint($refresh_retry_after ?? 0);
$last_manual_refresh_at = absint($last_manual_refresh_at ?? 0);
$docs_registry = $docs_registry instanceof \MillionDollarScript\V3\Docs\DocsRegistry ? $docs_registry : new \MillionDollarScript\V3\Docs\DocsRegistry();
$selected_key = $selected_doc ? (string) ($selected_doc['key'] ?? '') : '';
$rendered_doc = (string) ($rendered_doc ?? '');
$navigation_sections = $docs_registry->navigation_sections($documents);
?>
<div class="wrap mds3-admin mds3-docs-page">
    <div class="mds3-page-heading">
        <div>
            <p class="mds3-admin-eyebrow"><?php esc_html_e('Bundled Help', 'million-dollar-script'); ?></p>
            <h1><?php esc_html_e('Documentation', 'million-dollar-script'); ?></h1>
            <p><?php esc_html_e('Version-matched guides installed with Million Dollar Script and active extensions.', 'million-dollar-script'); ?></p>
        </div>
        <div class="mds3-docs-refresh">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="million_dollar_script_refresh_docs">
                <input type="hidden" name="package" value="<?php echo esc_attr($package_slug); ?>">
                <input type="hidden" name="doc" value="<?php echo esc_attr($selected_key); ?>">
                <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
                <?php wp_nonce_field('million_dollar_script_refresh_docs'); ?>
                <?php
                echo wp_kses_post(\MillionDollarScript\V3\Admin\FieldHelp::info(
                    __('Fetch the latest entitled guides for this site. Licenses and server content are not changed.', 'million-dollar-script'),
                    __('About refreshing documentation', 'million-dollar-script')
                ));
                ?>
                <button class="button" type="submit"><?php esc_html_e('Refresh documentation', 'million-dollar-script'); ?></button>
            </form>
            <?php if ($last_manual_refresh_at) : ?>
                <p class="mds3-docs-refresh-time" title="<?php echo esc_attr(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_manual_refresh_at)); ?>">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %s: human-readable time since the last manual documentation refresh. */
                        __('Last manually refreshed %s ago.', 'million-dollar-script'),
                        human_time_diff($last_manual_refresh_at, time())
                    ));
                    ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <hr class="wp-header-end">

    <?php if ('refreshed' === $refresh_status) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('The local documentation cache was cleared. The latest guides allowed by this site’s current access are now shown.', 'million-dollar-script'); ?></p>
        </div>
    <?php elseif ('cooldown' === $refresh_status) : ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: %d: seconds until another manual documentation refresh is allowed. */
                    _n(
                        'Documentation was refreshed recently. Try again in %d second.',
                        'Documentation was refreshed recently. Try again in %d seconds.',
                        $refresh_retry_after,
                        'million-dollar-script'
                    ),
                    $refresh_retry_after
                ));
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php foreach ($warnings as $warning) : ?>
        <div class="notice notice-warning inline">
            <p><?php echo esc_html((string) $warning); ?></p>
        </div>
    <?php endforeach; ?>

    <form class="mds3-card mds3-docs-toolbar" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
        <input type="hidden" name="page" value="mds3-docs">
        <label>
            <span><?php esc_html_e('Search docs', 'million-dollar-script'); ?></span>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search titles, topics, and text', 'million-dollar-script'); ?>">
        </label>
        <label>
            <span><?php esc_html_e('Package', 'million-dollar-script'); ?></span>
            <select name="package">
                <option value=""><?php esc_html_e('All packages', 'million-dollar-script'); ?></option>
                <?php foreach ($packages as $package) : ?>
                    <?php $slug = (string) ($package['package'] ?? ''); ?>
                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($package_slug, $slug); ?>>
                        <?php
                        echo esc_html(sprintf(
                            /* translators: 1: package title, 2: version */
                            __('%1$s %2$s', 'million-dollar-script'),
                            $docs_registry->package_navigation_title((string) ($package['title'] ?? ''), $slug),
                            (string) ($package['version'] ?? '')
                        ));
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="button button-primary" type="submit"><?php esc_html_e('Search', 'million-dollar-script'); ?></button>
        <?php if ($search || $package_slug) : ?>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-docs')); ?>"><?php esc_html_e('Reset', 'million-dollar-script'); ?></a>
        <?php endif; ?>
    </form>

    <?php if (!$packages) : ?>
        <section class="mds3-card mds3-docs-empty">
            <div class="mds3-card-heading">
                <div>
                    <h2><?php esc_html_e('No bundled docs found', 'million-dollar-script'); ?></h2>
                    <p><?php esc_html_e('Documentation will appear here when the installed package includes a valid docs manifest.', 'million-dollar-script'); ?></p>
                </div>
            </div>
        </section>
    <?php else : ?>
        <div class="mds3-docs-layout">
            <aside class="mds3-docs-sidebar" aria-label="<?php esc_attr_e('Documentation sections', 'million-dollar-script'); ?>">
                <?php foreach ($navigation_sections as $section) : ?>
                    <section class="mds3-docs-package">
                        <h2><?php echo esc_html((string) ($section['title'] ?? '')); ?></h2>
                        <p><?php echo esc_html((string) ($section['description'] ?? '')); ?></p>
                        <?php if (!empty($section['groups'])) : ?>
                            <div class="mds3-docs-groups">
                                <?php foreach ((array) $section['groups'] as $group) : ?>
                                    <?php $group_heading_id = 'mds3-docs-group-' . sanitize_html_class((string) ($section['id'] ?? 'extensions')) . '-' . sanitize_html_class((string) ($group['package'] ?? 'package')); ?>
                                    <section class="mds3-docs-group">
                                        <h3 id="<?php echo esc_attr($group_heading_id); ?>"><?php echo esc_html((string) ($group['title'] ?? '')); ?></h3>
                                        <nav aria-labelledby="<?php echo esc_attr($group_heading_id); ?>">
                                            <?php foreach ((array) ($group['docs'] ?? []) as $doc) : ?>
                                                <?php $doc_url = $docs_registry->document_url($doc, $search, $package_slug); ?>
                                                <a href="<?php echo esc_url($doc_url); ?>" class="<?php echo $selected_key === (string) ($doc['key'] ?? '') ? 'is-active' : ''; ?>">
                                                    <?php echo esc_html((string) ($doc['nav_title'] ?? $doc['title'] ?? '')); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </nav>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <nav>
                                <?php foreach ((array) ($section['docs'] ?? []) as $doc) : ?>
                                    <?php $doc_url = $docs_registry->document_url($doc, $search, $package_slug); ?>
                                    <a href="<?php echo esc_url($doc_url); ?>" class="<?php echo $selected_key === (string) ($doc['key'] ?? '') ? 'is-active' : ''; ?>">
                                        <?php echo esc_html((string) ($doc['nav_title'] ?? $doc['title'] ?? '')); ?>
                                    </a>
                                <?php endforeach; ?>
                            </nav>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </aside>

            <main class="mds3-docs-reader">
                <?php if (!$selected_doc) : ?>
                    <section class="mds3-card mds3-docs-empty">
                        <div class="mds3-card-heading">
                            <div>
                                <h2><?php esc_html_e('No matching docs', 'million-dollar-script'); ?></h2>
                                <p><?php esc_html_e('Adjust the search or package filter to show more results.', 'million-dollar-script'); ?></p>
                            </div>
                        </div>
                    </section>
                <?php else : ?>
                    <article class="mds3-card mds3-docs-article">
                        <header>
                            <p class="mds3-admin-eyebrow"><?php echo esc_html($docs_registry->package_navigation_title((string) ($selected_doc['package_title'] ?? ''), (string) ($selected_doc['package'] ?? ''))); ?></p>
                            <h2><?php echo esc_html((string) ($selected_doc['title'] ?? '')); ?></h2>
                            <p>
                                <?php
                                echo esc_html(sprintf(
                                    /* translators: 1: docs package version, 2: channel */
                                    __('Version %1$s · %2$s channel', 'million-dollar-script'),
                                    (string) ($selected_doc['version'] ?? ''),
                                    (string) ($selected_doc['channel'] ?? 'main')
                                ));
                                ?>
                            </p>
                        </header>
                        <div class="mds3-docs-content">
                            <?php echo wp_kses($rendered_doc, \MillionDollarScript\V3\Docs\DocsRegistry::allowed_html()); ?>
                        </div>
                    </article>
                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>
</div>
