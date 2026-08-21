<?php
/**
 * Extension onboarding and page setup panel.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_actionable = static function (array $entry) {
    return empty($entry['page_id']) || in_array((string) ($entry['page_status'] ?? ''), ['draft', 'auto-draft'], true);
};

$page_entries = [];
$legal_entries = [];
$shortcuts = [];

foreach ($extension_onboarding_items as $item) {
    $extension_name = sanitize_text_field((string) ($item['name'] ?? ''));

    foreach (($item['actions'] ?? []) as $action) {
        if (!is_array($action)) {
            continue;
        }

        $shortcuts[] = [
            'extension_name' => $extension_name,
            'label' => sanitize_text_field((string) ($action['label'] ?? '')),
            'primary' => !empty($action['primary']),
            'url' => esc_url_raw((string) ($action['url'] ?? '')),
        ];
    }

    foreach (($item['setup_pages'] ?? []) as $page) {
        if (is_array($page)) {
            $page['extension_name'] = $extension_name;
            $page_entries[] = $page;
        }
    }

    foreach (($item['legal_documents'] ?? []) as $document) {
        if (is_array($document)) {
            $document['extension_name'] = $extension_name;
            $legal_entries[] = $document;
        }
    }
}

$count_missing = static function (array $entries) {
    $count = 0;
    foreach ($entries as $entry) {
        if (empty($entry['page_id'])) {
            $count++;
        }
    }

    return $count;
};

$count_actionable = static function (array $entries) use ($is_actionable) {
    $count = 0;
    foreach ($entries as $entry) {
        if ($is_actionable((array) $entry)) {
            $count++;
        }
    }

    return $count;
};

$count_checked = static function (array $entries) use ($is_actionable) {
    $count = 0;
    foreach ($entries as $entry) {
        $entry = (array) $entry;
        if ($is_actionable($entry) && !empty($entry['default_checked'])) {
            $count++;
        }
    }

    return $count;
};

$categories = [
    [
        'actionable_count' => $count_actionable($page_entries),
        'checked_count' => $count_checked($page_entries),
        'description' => __('Create these pages when you want extension shortcodes available from normal WordPress pages.', 'million-dollar-script'),
        'entries' => $page_entries,
        'input_name' => 'mds3_extension_setup_pages[]',
        'key' => 'recommended',
        'label' => __('Recommended pages', 'million-dollar-script'),
        'missing_count' => $count_missing($page_entries),
    ],
    [
        'actionable_count' => $count_actionable($legal_entries),
        'checked_count' => $count_checked($legal_entries),
        'description' => __('Create draft legal pages for review before publishing. These drafts are not legal advice.', 'million-dollar-script'),
        'entries' => $legal_entries,
        'input_name' => 'mds3_extension_legal_documents[]',
        'key' => 'legal',
        'label' => __('Legal draft pages', 'million-dollar-script'),
        'missing_count' => $count_missing($legal_entries),
    ],
];

$has_actionable_pages = $categories[0]['actionable_count'] > 0;
$has_actionable_documents = $categories[1]['actionable_count'] > 0;
$missing_pages = $categories[0]['missing_count'];
$missing_documents = $categories[1]['missing_count'];
?>
<li class="<?php echo esc_attr($missing_pages || $missing_documents ? 'is-pending' : 'is-complete'); ?>">
    <h2><?php esc_html_e('Extension Setup', 'million-dollar-script'); ?></h2>
    <p><?php esc_html_e('Select whole categories or expand a category to choose individual extension pages. Published pages are shown for review but are not overwritten by setup.', 'million-dollar-script'); ?></p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds3-extension-onboarding-form" data-mds3-extension-onboarding>
        <?php wp_nonce_field('mds3_create_extension_setup_pages', '_mds3_extension_setup_pages_nonce'); ?>
        <?php wp_nonce_field('mds3_create_extension_legal_pages', '_mds3_extension_legal_pages_nonce'); ?>

        <div class="mds3-extension-onboarding-overview" aria-label="<?php esc_attr_e('Extension setup summary', 'million-dollar-script'); ?>">
            <div>
                <strong><?php echo esc_html(number_format_i18n(count($extension_onboarding_items))); ?></strong>
                <span><?php esc_html_e('Active extensions', 'million-dollar-script'); ?></span>
            </div>
            <div>
                <strong><?php echo esc_html(number_format_i18n(count($page_entries))); ?></strong>
                <span><?php esc_html_e('Recommended pages', 'million-dollar-script'); ?></span>
            </div>
            <div>
                <strong><?php echo esc_html(number_format_i18n(count($legal_entries))); ?></strong>
                <span><?php esc_html_e('Legal drafts', 'million-dollar-script'); ?></span>
            </div>
        </div>

        <div class="mds3-extension-onboarding-categories">
            <?php foreach ($categories as $category) : ?>
                <?php
                $category_entries = $category['entries'];
                if (!$category_entries) {
                    continue;
                }
                $category_key = sanitize_key((string) $category['key']);
                $category_actionable = absint($category['actionable_count']);
                $category_checked = $category_actionable > 0 && absint($category['checked_count']) === $category_actionable;
                ?>
                <details class="mds3-extension-onboarding-category" data-mds3-extension-category="<?php echo esc_attr($category_key); ?>">
                    <summary class="mds3-extension-onboarding-category-heading">
                        <span class="mds3-extension-onboarding-category-select">
                            <input type="checkbox" data-mds3-extension-category-toggle="<?php echo esc_attr($category_key); ?>" aria-label="<?php echo esc_attr(sprintf(
                                /* translators: %s: category label */
                                __('Select all %s', 'million-dollar-script'),
                                $category['label']
                            )); ?>"<?php checked($category_checked); ?><?php disabled($category_actionable < 1); ?> />
                            <span>
                                <strong><?php echo esc_html($category['label']); ?></strong>
                                <small><?php echo esc_html($category['description']); ?></small>
                            </span>
                        </span>
                        <span class="mds3-extension-onboarding-category-meta">
                            <span class="mds3-inline-status">
                                <?php echo esc_html(sprintf(
                                    /* translators: %d: page count */
                                    _n('%d page', '%d pages', count($category_entries), 'million-dollar-script'),
                                    count($category_entries)
                                )); ?>
                            </span>
                            <?php if ($category['missing_count'] > 0) : ?>
                                <span class="mds3-inline-status needs-action">
                                    <?php echo esc_html(sprintf(
                                        /* translators: %d: missing page count */
                                        _n('%d missing', '%d missing', absint($category['missing_count']), 'million-dollar-script'),
                                        absint($category['missing_count'])
                                    )); ?>
                                </span>
                            <?php elseif ($category_actionable > 0) : ?>
                                <span class="mds3-inline-status"><?php esc_html_e('Drafts editable', 'million-dollar-script'); ?></span>
                            <?php else : ?>
                                <span class="mds3-inline-status is-ready"><?php esc_html_e('Mapped', 'million-dollar-script'); ?></span>
                            <?php endif; ?>
                        </span>
                    </summary>

                    <div class="mds3-extension-onboarding-page-grid">
                        <?php foreach ($category_entries as $entry) : ?>
                            <?php
                            $entry = is_array($entry) ? $entry : [];
                            $can_create_or_update = $is_actionable($entry);
                            $status_label = '';
                            if (!empty($entry['page_id'])) {
                                $status_label = sprintf(
                                    /* translators: %s: WordPress post status */
                                    __('Current page: %s', 'million-dollar-script'),
                                    (string) (($entry['page_status'] ?? '') ?: __('unknown', 'million-dollar-script'))
                                );
                            }
                            ?>
                            <article class="mds3-extension-onboarding-page-card<?php echo !$can_create_or_update ? ' is-locked' : ''; ?>">
                                <label class="mds3-extension-onboarding-option">
                                    <input type="checkbox" name="<?php echo esc_attr($category['input_name']); ?>" value="<?php echo esc_attr($entry['slug'] ?? ''); ?>" data-mds3-extension-category-item="<?php echo esc_attr($category_key); ?>"<?php checked(!empty($entry['default_checked']) && $can_create_or_update); ?><?php disabled(!$can_create_or_update); ?> />
                                    <span>
                                        <?php if (!empty($entry['extension_name'])) : ?>
                                            <small class="mds3-extension-onboarding-extension-name"><?php echo esc_html($entry['extension_name']); ?></small>
                                        <?php endif; ?>
                                        <strong><?php echo esc_html($entry['title'] ?? ''); ?></strong>
                                        <?php if (!empty($entry['description'])) : ?>
                                            <small><?php echo esc_html($entry['description']); ?></small>
                                        <?php endif; ?>
                                        <?php if ($status_label) : ?>
                                            <small>
                                                <?php echo esc_html($status_label); ?>
                                                <?php if (!empty($entry['edit_url'])) : ?>
                                                    <a href="<?php echo esc_url($entry['edit_url']); ?>"><?php esc_html_e('Edit page', 'million-dollar-script'); ?></a>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>

        <?php if ($shortcuts) : ?>
            <details class="mds3-extension-onboarding-shortcuts">
                <summary class="mds3-extension-onboarding-shortcuts-heading">
                    <span class="mds3-extension-onboarding-shortcuts-copy">
                        <strong><?php esc_html_e('Extension shortcuts', 'million-dollar-script'); ?></strong>
                        <small><?php esc_html_e('Open the settings and management screens added by your active extensions.', 'million-dollar-script'); ?></small>
                    </span>
                    <span class="mds3-inline-status"><?php echo esc_html(number_format_i18n(count($shortcuts))); ?></span>
                </summary>
                <div class="mds3-extension-onboarding-shortcut-grid">
                    <?php foreach ($shortcuts as $shortcut) : ?>
                        <?php if (empty($shortcut['url']) || empty($shortcut['label'])) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <a class="button <?php echo !empty($shortcut['primary']) ? 'button-primary' : ''; ?>" href="<?php echo esc_url($shortcut['url']); ?>">
                            <?php echo esc_html($shortcut['extension_name'] ? $shortcut['extension_name'] . ': ' . $shortcut['label'] : $shortcut['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>

        <?php if ($has_actionable_pages || $has_actionable_documents) : ?>
            <div class="mds3-extension-onboarding-actions">
                <p><?php esc_html_e('Use category checkboxes to select everything in that group, or expand a group and choose individual pages.', 'million-dollar-script'); ?></p>
                <div class="mds3-button-row">
                    <?php if ($has_actionable_pages) : ?>
                        <button type="submit" class="button <?php echo !$has_actionable_documents ? 'button-primary' : ''; ?>" name="action" value="mds3_create_extension_setup_pages"><?php esc_html_e('Create or update selected recommended pages', 'million-dollar-script'); ?></button>
                    <?php endif; ?>
                    <?php if ($has_actionable_documents) : ?>
                        <button type="submit" class="button <?php echo !$has_actionable_pages ? 'button-primary' : ''; ?>" name="action" value="mds3_create_extension_legal_pages"><?php esc_html_e('Create or update selected legal drafts', 'million-dollar-script'); ?></button>
                    <?php endif; ?>
                    <?php if ($has_actionable_pages && $has_actionable_documents) : ?>
                        <button type="submit" class="button button-primary" name="action" value="mds3_create_extension_onboarding_pages"><?php esc_html_e('Create or update all selected items', 'million-dollar-script'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </form>
</li>
