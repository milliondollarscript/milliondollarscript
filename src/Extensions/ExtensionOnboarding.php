<?php
/**
 * Extension setup/onboarding declarations and draft legal page creation.
 *
 * @package MillionDollarScript\V3\Extensions
 */

namespace MillionDollarScript\V3\Extensions;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

final class ExtensionOnboarding {

    public const LEGAL_PAGES_OPTION = 'mds3_extension_legal_pages';
    public const SETUP_PAGES_OPTION = 'mds3_extension_setup_pages';

    /**
     * Return normalized onboarding items for selected/active extensions.
     *
     * Extensions declare items through the `million-dollar-script/extension/onboarding/items`
     * filter. Core owns only the shared rendering and draft-page workflow.
     *
     * @param array $selected_slugs Selected setup slugs.
     * @return array
     */
    public function items(array $selected_slugs = []) {
        $selected_slugs = $this->slug_list($selected_slugs);
        $catalog = (new ExtensionCatalog())->catalog();
        $items = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/onboarding/items', [], [
            'selected_slugs' => $selected_slugs,
            'catalog' => $catalog,
        ]);
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = sanitize_key((string) ($item['slug'] ?? (is_string($key) ? $key : '')));
            if (!$slug) {
                continue;
            }

            if ($selected_slugs && empty($item['always_show']) && !in_array($slug, $selected_slugs, true)) {
                continue;
            }

            $item = $this->normalize_item($slug, $item);
            if ($item) {
                $normalized[] = $item;
            }
        }

        usort($normalized, static function ($a, $b) {
            $priority = (int) ($a['priority'] ?? 50) <=> (int) ($b['priority'] ?? 50);
            if (0 !== $priority) {
                return $priority;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $normalized;
    }

    /**
     * Flatten legal document declarations.
     *
     * @param array $selected_slugs Selected setup slugs.
     * @return array
     */
    public function legal_documents(array $selected_slugs = []) {
        $documents = [];
        foreach ($this->items($selected_slugs) as $item) {
            foreach ($item['legal_documents'] as $document) {
                $documents[$document['slug']] = $document;
            }
        }

        return $documents;
    }

    /**
     * Flatten recommended extension page declarations.
     *
     * @param array $selected_slugs Selected setup slugs.
     * @return array
     */
    public function setup_pages(array $selected_slugs = []) {
        $pages = [];
        foreach ($this->items($selected_slugs) as $item) {
            foreach ($item['setup_pages'] as $page) {
                $pages[$page['slug']] = $page;
            }
        }

        return $pages;
    }

    /**
     * Create or update selected draft legal pages.
     *
     * @param array $document_slugs Requested document slugs.
     * @param array $selected_slugs Selected setup slugs.
     * @return array
     */
    public function create_legal_pages(array $document_slugs, array $selected_slugs = []) {
        $requested = $this->slug_list($document_slugs);
        $documents = $this->legal_documents($selected_slugs);
        $created_pages = $this->created_pages();
        $result = [
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'errors' => [],
        ];

        if (!$requested) {
            $result['errors'][] = __('Select at least one draft page to create.', 'million-dollar-script');

            return $result;
        }

        foreach ($requested as $slug) {
            if (empty($documents[$slug])) {
                $result['skipped'][] = sprintf(
                    /* translators: %s: legal document slug */
                    __('Skipped unknown legal document: %s.', 'million-dollar-script'),
                    $slug
                );
                continue;
            }

            $document = $documents[$slug];
            $existing = $this->stored_page($slug);
            if ($existing && 'page' === (string) $existing->post_type && 'trash' !== (string) $existing->post_status) {
                if (!in_array((string) $existing->post_status, ['draft', 'auto-draft'], true)) {
                    $result['skipped'][] = sprintf(
                        /* translators: %s: legal document title */
                        __('%s already has a non-draft page. Review it manually before changing published content.', 'million-dollar-script'),
                        $document['title']
                    );
                    continue;
                }

                $updated = wp_update_post([
                    'ID' => (int) $existing->ID,
                    'post_title' => $document['title'],
                    'post_name' => $document['page_slug'],
                    'post_content' => $this->document_content($document),
                    'post_excerpt' => $document['description'],
                    'post_status' => 'draft',
                    'post_type' => 'page',
                ], true);

                if (is_wp_error($updated)) {
                    $result['errors'][] = $updated->get_error_message();
                    continue;
                }

                $created_pages[$slug] = (int) $updated;
                $this->mark_legal_page((int) $updated, $document, $slug);
                $result['updated'][] = [
                    'slug' => $slug,
                    'page_id' => (int) $updated,
                    'title' => $document['title'],
                ];
                continue;
            }

            $page_id = wp_insert_post([
                'post_title' => $document['title'],
                'post_name' => $document['page_slug'],
                'post_content' => $this->document_content($document),
                'post_excerpt' => $document['description'],
                'post_status' => 'draft',
                'post_type' => 'page',
                'meta_input' => [
                    '_mds3_extension_slug' => $document['extension_slug'],
                    '_mds3_extension_legal_document' => $slug,
                ],
            ], true);

            if (is_wp_error($page_id)) {
                $result['errors'][] = $page_id->get_error_message();
                continue;
            }

            $created_pages[$slug] = (int) $page_id;
            $this->mark_legal_page((int) $page_id, $document, $slug);
            $result['created'][] = [
                'slug' => $slug,
                'page_id' => (int) $page_id,
                'title' => $document['title'],
            ];
        }

        update_option(self::LEGAL_PAGES_OPTION, $created_pages, false);

        return $result;
    }

    /**
     * Create or update selected recommended extension pages.
     *
     * @param array $page_slugs Requested page slugs.
     * @param array $selected_slugs Selected setup slugs.
     * @return array
     */
    public function create_setup_pages(array $page_slugs, array $selected_slugs = []) {
        $requested = $this->slug_list($page_slugs);
        $pages = $this->setup_pages($selected_slugs);
        $created_pages = $this->setup_created_pages();
        $result = [
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'errors' => [],
        ];

        if (!$requested) {
            $result['errors'][] = __('Select at least one extension page to create.', 'million-dollar-script');

            return $result;
        }

        foreach ($requested as $slug) {
            if (empty($pages[$slug])) {
                $result['skipped'][] = sprintf(
                    /* translators: %s: extension page slug */
                    __('Skipped unknown extension page: %s.', 'million-dollar-script'),
                    $slug
                );
                continue;
            }

            $page = $pages[$slug];
            $existing = $this->stored_setup_page($slug);
            if ($existing && 'page' === (string) $existing->post_type && 'trash' !== (string) $existing->post_status) {
                if (!in_array((string) $existing->post_status, ['draft', 'auto-draft'], true)) {
                    $result['skipped'][] = sprintf(
                        /* translators: %s: extension page title */
                        __('%s already has a published or non-draft page. Existing content was preserved.', 'million-dollar-script'),
                        $page['title']
                    );
                    continue;
                }

                $updated = wp_update_post([
                    'ID' => (int) $existing->ID,
                    'post_title' => $page['title'],
                    'post_name' => $page['page_slug'],
                    'post_content' => $this->setup_page_content($page),
                    'post_excerpt' => $page['description'],
                    'post_status' => $page['post_status'],
                    'post_type' => 'page',
                ], true);

                if (is_wp_error($updated)) {
                    $result['errors'][] = $updated->get_error_message();
                    continue;
                }

                $created_pages[$slug] = (int) $updated;
                $this->mark_setup_page((int) $updated, $page, $slug);
                $result['updated'][] = [
                    'slug' => $slug,
                    'page_id' => (int) $updated,
                    'title' => $page['title'],
                ];
                continue;
            }

            $page_id = wp_insert_post([
                'post_title' => $page['title'],
                'post_name' => $page['page_slug'],
                'post_content' => $this->setup_page_content($page),
                'post_excerpt' => $page['description'],
                'post_status' => $page['post_status'],
                'post_type' => 'page',
                'meta_input' => [
                    '_mds3_extension_slug' => $page['extension_slug'],
                    '_mds3_extension_setup_page' => $slug,
                ],
            ], true);

            if (is_wp_error($page_id)) {
                $result['errors'][] = $page_id->get_error_message();
                continue;
            }

            $created_pages[$slug] = (int) $page_id;
            $this->mark_setup_page((int) $page_id, $page, $slug);
            $result['created'][] = [
                'slug' => $slug,
                'page_id' => (int) $page_id,
                'title' => $page['title'],
            ];
        }

        update_option(self::SETUP_PAGES_OPTION, $created_pages, false);

        return $result;
    }

    private function normalize_item($slug, array $item) {
        $name = sanitize_text_field((string) ($item['name'] ?? $slug));
        $summary = sanitize_textarea_field((string) ($item['summary'] ?? $item['description'] ?? ''));
        $actions = $this->normalize_actions($item['actions'] ?? []);
        $setup_pages = $this->normalize_setup_pages($slug, $item['setup_pages'] ?? $item['pages'] ?? []);
        $legal_documents = $this->normalize_legal_documents($slug, $item['legal_documents'] ?? $item['legal_docs'] ?? []);

        if (!$summary && !$actions && !$setup_pages && !$legal_documents) {
            return null;
        }

        return [
            'slug' => $slug,
            'name' => $name ?: $slug,
            'summary' => $summary,
            'priority' => absint($item['priority'] ?? 50),
            'actions' => $actions,
            'setup_pages' => $setup_pages,
            'legal_documents' => $legal_documents,
        ];
    }

    private function normalize_actions($actions) {
        if (!is_array($actions)) {
            return [];
        }

        $normalized = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $url = esc_url_raw((string) ($action['url'] ?? ''));
            $label = sanitize_text_field((string) ($action['label'] ?? ''));
            if (!$url || !$label) {
                continue;
            }

            $normalized[] = [
                'label' => $label,
                'url' => $url,
                'description' => sanitize_textarea_field((string) ($action['description'] ?? '')),
                'primary' => !empty($action['primary']),
            ];
        }

        return $normalized;
    }

    private function normalize_setup_pages($extension_slug, $pages) {
        if (!is_array($pages)) {
            return [];
        }

        $normalized = [];
        foreach ($pages as $key => $page) {
            if (!is_array($page)) {
                continue;
            }

            $local_slug = sanitize_key((string) ($page['slug'] ?? (is_string($key) ? $key : '')));
            $title = sanitize_text_field((string) ($page['title'] ?? ''));
            $content = wp_kses_post((string) ($page['content'] ?? ''));
            if (!$local_slug || !$title || !$content) {
                continue;
            }

            $slug = sanitize_key($extension_slug . '-' . $local_slug);
            $page_slug = sanitize_title((string) ($page['page_slug'] ?? $slug));
            $existing = $this->stored_setup_page($slug);
            if (!$existing && function_exists('get_page_by_path')) {
                $existing_by_slug = get_page_by_path($page_slug);
                if ($existing_by_slug instanceof \WP_Post) {
                    $existing = $existing_by_slug;
                }
            }

            $page_status = $existing ? (string) $existing->post_status : '';
            $page_id = $existing ? (int) $existing->ID : 0;
            $post_status = sanitize_key((string) ($page['post_status'] ?? 'publish'));
            if (!in_array($post_status, ['publish', 'draft'], true)) {
                $post_status = 'publish';
            }

            $normalized[] = [
                'slug' => $slug,
                'extension_slug' => $extension_slug,
                'local_slug' => $local_slug,
                'title' => $title,
                'description' => sanitize_textarea_field((string) ($page['description'] ?? '')),
                'content' => $content,
                'page_slug' => $page_slug,
                'post_status' => $post_status,
                'page_id' => $page_id,
                'page_status' => $page_status,
                'edit_url' => $page_id && function_exists('get_edit_post_link') ? esc_url_raw((string) get_edit_post_link($page_id, 'raw')) : '',
                'default_checked' => !array_key_exists('default_checked', $page) || !empty($page['default_checked']),
            ];
        }

        return $normalized;
    }

    private function setup_page_content(array $page) {
        return wp_kses_post((string) ($page['content'] ?? ''));
    }

    private function normalize_legal_documents($extension_slug, $documents) {
        if (!is_array($documents)) {
            return [];
        }

        $normalized = [];
        foreach ($documents as $key => $document) {
            if (!is_array($document)) {
                continue;
            }

            $local_slug = sanitize_key((string) ($document['slug'] ?? (is_string($key) ? $key : '')));
            $title = sanitize_text_field((string) ($document['title'] ?? ''));
            $content = wp_kses_post((string) ($document['content'] ?? ''));
            if (!$local_slug || !$title || !$content) {
                continue;
            }

            $slug = sanitize_key($extension_slug . '-' . $local_slug);
            $existing = $this->stored_page($slug);
            $page_status = $existing ? (string) $existing->post_status : '';
            $page_id = $existing ? (int) $existing->ID : 0;

            $normalized[] = [
                'slug' => $slug,
                'extension_slug' => $extension_slug,
                'local_slug' => $local_slug,
                'title' => $title,
                'description' => sanitize_textarea_field((string) ($document['description'] ?? '')),
                'content' => $content,
                'page_slug' => sanitize_title((string) ($document['page_slug'] ?? $slug)),
                'page_id' => $page_id,
                'page_status' => $page_status,
                'edit_url' => $page_id && function_exists('get_edit_post_link') ? esc_url_raw((string) get_edit_post_link($page_id, 'raw')) : '',
                'default_checked' => !array_key_exists('default_checked', $document) || !empty($document['default_checked']),
            ];
        }

        return $normalized;
    }

    private function document_content(array $document) {
        $disclaimer = sprintf(
            '<p><strong>%s</strong> %s</p>',
            esc_html__('Important:', 'million-dollar-script'),
            esc_html__('This draft is not legal advice. Before publishing, verify the facts, fees, data flows, jurisdictions, and consumer notices with qualified counsel.', 'million-dollar-script')
        );
        $content = $this->replace_document_tokens((string) ($document['content'] ?? ''));

        return $disclaimer . "\n\n" . wp_kses_post($content);
    }

    private function replace_document_tokens($content) {
        $replacements = \MillionDollarScript\Core\Hooks::apply(
            'million-dollar-script/extension/legal/document/replacements',
            $this->document_replacements(),
            $content,
            $this
        );

        if (!is_array($replacements) || !$replacements) {
            return (string) $content;
        }

        return strtr((string) $content, $replacements);
    }

    private function document_replacements() {
        $site_name = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
        $site_url = function_exists('home_url') ? (string) home_url('/') : '';
        $contact_method = $this->contact_method();
        $privacy_url = function_exists('get_privacy_policy_url') ? (string) get_privacy_policy_url() : '';
        $settings = function_exists('get_option') ? get_option('mds3_settings', []) : [];
        $settings = is_array($settings) ? $settings : [];
        if (function_exists('wp_parse_args')) {
            $settings = wp_parse_args($settings, SettingsSchema::defaults());
        } else {
            $settings = array_merge(SettingsSchema::defaults(), $settings);
        }

        return [
            '{{site_name}}' => esc_html($site_name ?: __('this site', 'million-dollar-script')),
            '{{site_url}}' => esc_url($site_url),
            '{{contact_email}}' => $contact_method,
            '{{contact_method}}' => $contact_method,
            '{{privacy_policy_url}}' => esc_url($privacy_url),
            '{{currency_code}}' => esc_html(Currency::current_code($settings)),
            '{{currency_symbol}}' => esc_html(Currency::current_symbol($settings)),
            '{{generated_date}}' => esc_html(function_exists('date_i18n') ? date_i18n(get_option('date_format')) : gmdate('Y-m-d')),
        ];
    }

    private function contact_method() {
        $contact_url = $this->contact_page_url();
        if ($contact_url) {
            return sprintf(
                '<a href="%s">%s</a>',
                esc_url($contact_url),
                esc_html__('the contact page', 'million-dollar-script')
            );
        }

        return esc_html__('the site administrator or the contact page listed on this site', 'million-dollar-script');
    }

    private function contact_page_url() {
        $filtered = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/legal/contact/page/url', '');
        $filtered = esc_url_raw((string) $filtered);
        if ($filtered) {
            return $filtered;
        }

        if (!function_exists('get_page_by_path') || !function_exists('get_permalink')) {
            return '';
        }

        $page = get_page_by_path('contact');
        if ($page instanceof \WP_Post && 'publish' === (string) $page->post_status) {
            return (string) get_permalink($page);
        }

        global $wpdb;
        if (!$wpdb || empty($wpdb->posts)) {
            return '';
        }

        $like = '%' . $wpdb->esc_like('[mds_contact_form') . '%';
        $page_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
            'page',
            'publish',
            $like
        ));

        return $page_id > 0 ? (string) get_permalink($page_id) : '';
    }

    private function mark_legal_page($page_id, array $document, $document_slug) {
        $page_id = absint($page_id);
        if (!$page_id) {
            return;
        }

        update_post_meta($page_id, '_mds3_extension_slug', sanitize_key((string) ($document['extension_slug'] ?? '')));
        update_post_meta($page_id, '_mds3_extension_legal_document', sanitize_key((string) $document_slug));
        $this->remove_grid_page_metadata($page_id);
    }

    private function mark_setup_page($page_id, array $page, $page_slug) {
        $page_id = absint($page_id);
        if (!$page_id) {
            return;
        }

        update_post_meta($page_id, '_mds3_extension_slug', sanitize_key((string) ($page['extension_slug'] ?? '')));
        update_post_meta($page_id, '_mds3_extension_setup_page', sanitize_key((string) $page_slug));
        $this->remove_grid_page_metadata($page_id);
    }

    private function remove_grid_page_metadata($page_id) {
        $page_id = absint($page_id);
        if (!$page_id) {
            return;
        }

        foreach ([
            '_mds3_page_type',
            '_mds3_grid_id',
            '_mds3_migration_source',
            '_mds3_migration_original_content',
            '_mds3_migration_original_title',
        ] as $meta_key) {
            delete_post_meta($page_id, $meta_key);
        }

        global $wpdb;

        if (DB::table_exists(DB::table('pages'))) {
            $wpdb->delete(DB::table('pages'), ['post_id' => $page_id], ['%d']);
        }

        if (DB::table_exists(DB::table('migration_map'))) {
            $wpdb->delete(DB::table('migration_map'), [
                'entity_type' => 'page',
                'legacy_id' => (string) $page_id,
                'mds3_entity_type' => 'page',
            ], ['%s', '%s', '%s']);
            $wpdb->delete(DB::table('migration_map'), [
                'mds3_entity_type' => 'page',
                'mds3_id' => $page_id,
            ], ['%s', '%d']);
        }
    }

    private function stored_page($document_slug) {
        $page_id = absint($this->created_pages()[$document_slug] ?? 0);
        if (!$page_id || !function_exists('get_post')) {
            return null;
        }

        $post = get_post($page_id);

        return $post instanceof \WP_Post ? $post : null;
    }

    private function stored_setup_page($page_slug) {
        $page_id = absint($this->setup_created_pages()[$page_slug] ?? 0);
        if (!$page_id || !function_exists('get_post')) {
            return null;
        }

        $post = get_post($page_id);

        return $post instanceof \WP_Post ? $post : null;
    }

    private function created_pages() {
        $pages = get_option(self::LEGAL_PAGES_OPTION, []);
        if (!is_array($pages)) {
            return [];
        }

        $normalized = [];
        foreach ($pages as $slug => $page_id) {
            $slug = sanitize_key((string) $slug);
            $page_id = absint($page_id);
            if ($slug && $page_id) {
                $normalized[$slug] = $page_id;
            }
        }

        return $normalized;
    }

    private function setup_created_pages() {
        $pages = get_option(self::SETUP_PAGES_OPTION, []);
        if (!is_array($pages)) {
            return [];
        }

        $normalized = [];
        foreach ($pages as $slug => $page_id) {
            $slug = sanitize_key((string) $slug);
            $page_id = absint($page_id);
            if ($slug && $page_id) {
                $normalized[$slug] = $page_id;
            }
        }

        return $normalized;
    }

    private function slug_list(array $values) {
        $slugs = [];
        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $slug = sanitize_key((string) $value);
            if ($slug) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }
}
