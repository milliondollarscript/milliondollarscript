<?php
/**
 * WP-CLI fixture for extension onboarding and legal draft creation.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/extension-onboarding-fixture.php
 */

use MillionDollarScript\V3\Extensions\ExtensionOnboarding;

if (!defined('ABSPATH')) {
    exit;
}

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    throw new RuntimeException('Extension onboarding fixture requires an administrator user.');
}
wp_set_current_user(absint($admin[0]));

$original_pages = get_option(ExtensionOnboarding::LEGAL_PAGES_OPTION, []);
$original_setup_pages = get_option(ExtensionOnboarding::SETUP_PAGES_OPTION, []);
$created_page_ids = [];

add_filter('million-dollar-script/extension/onboarding/items', static function (array $items) {
    $items['fixture-extension'] = [
        'name' => 'Fixture Extension',
        'summary' => 'Fixture setup summary.',
        'priority' => 5,
        'actions' => [
            [
                'label' => 'Fixture settings',
                'url' => admin_url('admin.php?page=fixture-extension'),
                'primary' => true,
            ],
        ],
        'setup_pages' => [
            [
                'slug' => 'directory',
                'title' => 'Fixture Directory',
                'description' => 'Fixture public setup page.',
                'page_slug' => 'fixture-directory',
                'content' => '[fixture_directory]',
            ],
        ],
        'legal_documents' => [
            [
                'slug' => 'terms',
                'title' => 'Fixture Sponsorship Terms',
                'description' => 'Fixture legal draft.',
                'page_slug' => 'fixture-sponsorship-terms',
                'content' => '<h2>Fixture Terms</h2><p onclick="alert(1)">Safe paragraph for {{site_name}} at {{site_url}}. Contact {{contact_email}} or {{contact_method}}.</p><script>alert(1)</script>',
            ],
        ],
    ];

    return $items;
});

// A second declaration that the setup flow was never asked for. An active extension may still put
// its own card in front of a site - the companion plugin does, on purpose - but a card that does
// not ask to always show must not arrive just because some other extension was chosen.
add_filter('million-dollar-script/extension/onboarding/items', static function (array $items) {
    $items['not-selected-extension'] = [
        'name' => 'Not Selected Extension',
        'summary' => 'Should only appear when it is selected or asks to always show.',
        'priority' => 6,
        'actions' => [],
        'setup_pages' => [],
        'legal_documents' => [],
    ];

    return $items;
});

try {
    $onboarding = new ExtensionOnboarding();
    $items = $onboarding->items(['fixture-extension']);
    $slugs = array_values(array_map(static function (array $item) {
        return (string) ($item['slug'] ?? '');
    }, $items));

    // The card that was asked for has to be there, and the one that was not must not be. Anything
    // else present can only be an extension that declared itself always worth showing; that is the
    // companion's deliberate behaviour, and counting exactly one card would fight it.
    if (!in_array('fixture-extension', $slugs, true)) {
        throw new RuntimeException('The selected extension was not offered in its own onboarding items: ' . wp_json_encode($slugs));
    }
    if (in_array('not-selected-extension', $slugs, true)) {
        throw new RuntimeException('An extension that was not selected was offered anyway: ' . wp_json_encode($slugs));
    }
    $declared = (array) apply_filters('million-dollar-script/extension/onboarding/items', [], [
        'selected_slugs' => ['fixture-extension'],
        'catalog' => [],
    ]);
    $always_show = [];
    foreach ($declared as $key => $item) {
        if (is_array($item) && !empty($item['always_show'])) {
            $always_show[] = (string) ($item['slug'] ?? (string) $key);
        }
    }
    $unexpected = [];
    foreach ($slugs as $slug) {
        if ('fixture-extension' === $slug) {
            continue;
        }
        if (!in_array((string) $slug, $always_show, true)) {
            $unexpected[] = $slug;
        }
    }
    if ($unexpected) {
        throw new RuntimeException('Onboarding offered cards that were neither selected nor declared always-shown: ' . wp_json_encode($unexpected));
    }

    $requested = null;
    foreach ($items as $item) {
        if ('fixture-extension' === (string) ($item['slug'] ?? '')) {
            $requested = $item;
            break;
        }
    }
    if ('Fixture Extension' !== (string) ($requested['name'] ?? '')) {
        throw new RuntimeException('Onboarding item was not normalized with the expected name.');
    }

    $setup_pages = $items[0]['setup_pages'] ?? [];
    if (1 !== count($setup_pages) || 'fixture-extension-directory' !== (string) ($setup_pages[0]['slug'] ?? '')) {
        throw new RuntimeException('Setup page slug was not normalized as expected.');
    }

    $missing_page_result = $onboarding->create_setup_pages([], ['fixture-extension']);
    if (empty($missing_page_result['errors'])) {
        throw new RuntimeException('Creating setup pages without selected pages should return an error.');
    }

    $page_result = $onboarding->create_setup_pages(['fixture-extension-directory'], ['fixture-extension']);
    if (1 !== count($page_result['created'] ?? [])) {
        throw new RuntimeException('Expected one created extension setup page.');
    }

    $setup_page_id = absint($page_result['created'][0]['page_id'] ?? 0);
    $created_page_ids[] = $setup_page_id;
    $setup_post = get_post($setup_page_id);
    if (!$setup_post || 'page' !== (string) $setup_post->post_type || 'publish' !== (string) $setup_post->post_status) {
        throw new RuntimeException('Extension setup page was not created as a published page.');
    }
    if ('[fixture_directory]' !== trim((string) $setup_post->post_content)) {
        throw new RuntimeException('Extension setup page content was not preserved.');
    }

    $stored_setup_pages = get_option(ExtensionOnboarding::SETUP_PAGES_OPTION, []);
    if (absint($stored_setup_pages['fixture-extension-directory'] ?? 0) !== $setup_page_id) {
        throw new RuntimeException('Extension setup page mapping was not stored.');
    }

    $documents = $items[0]['legal_documents'] ?? [];
    if (1 !== count($documents) || 'fixture-extension-terms' !== (string) ($documents[0]['slug'] ?? '')) {
        throw new RuntimeException('Legal document slug was not normalized as expected.');
    }

    $missing_result = $onboarding->create_legal_pages([], ['fixture-extension']);
    if (empty($missing_result['errors'])) {
        throw new RuntimeException('Creating legal drafts without selected documents should return an error.');
    }

    $result = $onboarding->create_legal_pages(['fixture-extension-terms'], ['fixture-extension']);
    if (1 !== count($result['created'] ?? [])) {
        throw new RuntimeException('Expected one created draft legal page.');
    }

    $page_id = absint($result['created'][0]['page_id'] ?? 0);
    $created_page_ids[] = $page_id;
    $post = get_post($page_id);
    if (!$post || 'page' !== (string) $post->post_type || 'draft' !== (string) $post->post_status) {
        throw new RuntimeException('Legal document page was not created as a draft page.');
    }

    $content = (string) $post->post_content;
    if (false === strpos($content, 'not legal advice')) {
        throw new RuntimeException('Legal draft did not include the required non-legal-advice disclaimer.');
    }
    if (false !== stripos($content, '<script') || false !== stripos($content, 'onclick=')) {
        throw new RuntimeException('Legal draft content was not sanitized.');
    }
    if (false !== strpos($content, '{{site_name}}') || false !== strpos($content, '{{site_url}}') || false !== strpos($content, '{{contact_email}}') || false !== strpos($content, '{{contact_method}}')) {
        throw new RuntimeException('Legal draft placeholders were not replaced.');
    }
    if (false === strpos($content, get_bloginfo('name'))) {
        throw new RuntimeException('Legal draft did not include the current site name.');
    }
    if (false !== strpos($content, get_option('admin_email'))) {
        throw new RuntimeException('Legal draft should not expose the admin email address.');
    }
    if (false === strpos($content, 'site administrator') && false === strpos($content, 'contact page')) {
        throw new RuntimeException('Legal draft did not include contact-page or site-administrator guidance.');
    }

    $second_result = $onboarding->create_legal_pages(['fixture-extension-terms'], ['fixture-extension']);
    if (1 !== count($second_result['updated'] ?? [])) {
        throw new RuntimeException('Expected existing draft legal page to be updated on second run.');
    }

    $stored = get_option(ExtensionOnboarding::LEGAL_PAGES_OPTION, []);
    if (absint($stored['fixture-extension-terms'] ?? 0) !== $page_id) {
        throw new RuntimeException('Legal page mapping was not stored.');
    }
} finally {
    foreach (array_filter(array_map('absint', $created_page_ids)) as $page_id) {
        wp_delete_post($page_id, true);
    }
    update_option(ExtensionOnboarding::LEGAL_PAGES_OPTION, is_array($original_pages) ? $original_pages : [], false);
    update_option(ExtensionOnboarding::SETUP_PAGES_OPTION, is_array($original_setup_pages) ? $original_setup_pages : [], false);
    wp_set_current_user(0);
}

echo "Extension onboarding fixture passed.\n";
