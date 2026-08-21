<?php
/**
 * Bundled documentation discovery and safe rendering.
 *
 * @package MillionDollarScript\V3\Docs
 */

namespace MillionDollarScript\V3\Docs;

if (!defined('ABSPATH')) {
    exit;
}

final class DocsRegistry {

    private const AUDIENCES = ['admin', 'developer', 'support', 'legal-review'];

    private const TOPICS = [
        'accessibility',
        'agents',
        'analytics',
        'api',
        'automation',
        'bookings',
        'blocks',
        'checkout',
        'commerce',
        'cron',
        'currency',
        'developer',
        'developer-upgrade',
        'emails',
        'extensions',
        'extension-packs',
        'exports',
        'field-help',
        'grids',
        'imagegrid',
        'imports',
        'installation',
        'legal',
        'legal-pages',
        'licensing',
        'llm',
        'localization',
        'maps',
        'mds2-migration',
        'migration',
        'mobile',
        'moderation',
        'openstreetmap',
        'orders',
        'pages',
        'payment-provider',
        'payments',
        'performance',
        'privacy',
        'qr-codes',
        'rendering',
        'rest-api',
        'scanner',
        'security',
        'settings',
        'setup',
        'sponsorboard',
        'standalone-checkout',
        'stats',
        'translation',
        'troubleshooting',
        'updates',
        'uploads',
        'webhooks',
        'woocommerce',
    ];

    private const NAVIGATION_SECTIONS = [
        'getting-started' => ['title' => 'Getting started', 'description' => 'Install Million Dollar Script, run setup, choose checkout, and migrate an existing site.'],
        'grids-orders' => ['title' => 'Grids and orders', 'description' => 'Build grids, place blocks, manage orders, and configure customer email workflows.'],
        'configuration-legal' => ['title' => 'Configuration and legal', 'description' => 'Review settings, URLs, public pages, and the legal documents used by your site.'],
        'integrations' => ['title' => 'Integrations', 'description' => 'Connect checkout, rendering, and other supported services.'],
        'api-development' => ['title' => 'API and development', 'description' => 'Connect trusted clients, update custom integrations, and work with developer-facing APIs.'],
        'troubleshooting' => ['title' => 'Troubleshooting', 'description' => 'Resolve common setup, routing, rendering, checkout, and upgrade problems.'],
        'free-extensions' => ['title' => 'Free extensions', 'description' => 'Add focused workflows that are available without a paid extension license.'],
        'licensed-extensions' => ['title' => 'Licensed extensions', 'description' => 'Open documentation included with the extension licenses available to this site.'],
        'other' => ['title' => 'More documentation', 'description' => 'Browse additional Million Dollar Script guides and product references.'],
    ];

    private const NAVIGATION_SLUG_ORDER = [
        'index' => 10,
        'installation-and-setup' => 20,
        'setup-wizard' => 30,
        'setup' => 40,
        'mds2-migration' => 50,
        'mds3-upgrade-from-mds2' => 60,
        'mds3-mds2-workflow-parity' => 70,
        'upgrade-guide-2-3-5-to-2-6' => 80,
        'grids-and-blocks' => 100,
        'mds3-grid-pricing-and-renderers' => 110,
        'shortcode-reference' => 120,
        'orders' => 130,
        'media-and-draft-recovery' => 140,
        'emails' => 150,
        'order-emails-and-renewals' => 160,
        'list-page-customization' => 170,
        'settings' => 200,
        'routes-and-pages' => 210,
        'pixel-permalinks-and-migration' => 220,
        'dynamic-css-and-theme-modes' => 230,
        'admin-navigation' => 240,
        'logs-and-cron' => 250,
        'backups-and-recovery' => 260,
        'privacy-security-and-accessibility' => 270,
        'legal-pages' => 280,
        'wordpress-integration' => 300,
        'mds3-checkout-and-payments' => 310,
        'woocommerce-integration' => 320,
        'standalone-checkout' => 330,
        'imagegrid' => 340,
        'developer-overview' => 400,
        'api-access' => 410,
        'api-reference' => 420,
        'extension-api' => 430,
        'extension-development' => 440,
        'hooks-reference' => 450,
        'developer-upgrade-notes' => 460,
        'troubleshooting' => 500,
        'troubleshooting-grid-alignment' => 510,
        'known-compatibility-issues' => 520,
        'support' => 530,
    ];

    /**
     * @var array<string,array>|null
     */
    private $packages;

    /**
     * @var string[]
     */
    private $warnings = [];

    /**
     * Return all valid docs packages.
     *
     * @return array<string,array>
     */
    public function packages() {
        if (null !== $this->packages) {
            return $this->packages;
        }

        $this->warnings = [];
        $packages = [];
        $seen = [];
        foreach ($this->manifest_paths() as $manifest_path) {
            $manifest_real = realpath((string) $manifest_path);
            if (!$manifest_real || isset($seen[$manifest_real])) {
                continue;
            }
            $seen[$manifest_real] = true;
            $package = $this->load_package($manifest_real);
            if ($package) {
                $packages[$package['package']] = $package;
            }
        }

        $packages = $this->sort_packages($packages);

        /**
         * Filters validated bundled docs packages before admin rendering.
         *
         * @param array<string,array> $packages Valid docs packages.
         * @param DocsRegistry        $registry Registry instance.
         */
        $packages = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/docs/packages', $packages, $this);

        $this->packages = is_array($packages) ? $this->sort_packages($packages) : [];

        return $this->packages;
    }

    /**
     * @return string[]
     */
    public function warnings() {
        $this->packages();

        return $this->warnings;
    }

    /**
     * @return array<int,array>
     */
    public function documents($package_slug = '', $search = '') {
        $package_slug = sanitize_key((string) $package_slug);
        $search = strtolower(trim((string) $search));
        $documents = [];

        foreach ($this->packages() as $package) {
            if ($package_slug && $package_slug !== (string) ($package['package'] ?? '')) {
                continue;
            }
            foreach ((array) ($package['docs'] ?? []) as $doc) {
                if ($search && !$this->document_matches($doc, $search)) {
                    continue;
                }
                $documents[] = $doc;
            }
        }

        return $documents;
    }

    /**
     * Group the visible documents using the same MDS 3 topic navigation as the
     * extension-server frontend. Document titles and package coordinates still
     * come from the versioned package manifest.
     *
     * @param array<int,array> $documents Visible documents.
     * @return array<int,array>
     */
    public function navigation_sections(array $documents) {
        $sections = [];
        foreach (self::NAVIGATION_SECTIONS as $id => $definition) {
            $sections[$id] = [
                'id' => $id,
                'title' => __($definition['title'], 'million-dollar-script'),
                'description' => __($definition['description'], 'million-dollar-script'),
                'docs' => [],
                'groups' => [],
            ];
        }

        foreach ($documents as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $section_id = $this->navigation_section_id($doc);
            $sections[$section_id]['docs'][] = $doc;
        }

        foreach ($sections as &$section) {
            usort($section['docs'], function ($left, $right) {
                $left_slug = sanitize_key((string) ($left['id'] ?? ''));
                $right_slug = sanitize_key((string) ($right['id'] ?? ''));
                $left_known = isset(self::NAVIGATION_SLUG_ORDER[$left_slug]);
                $right_known = isset(self::NAVIGATION_SLUG_ORDER[$right_slug]);
                if ($left_known !== $right_known) {
                    return $left_known ? -1 : 1;
                }
                if ($left_known && self::NAVIGATION_SLUG_ORDER[$left_slug] !== self::NAVIGATION_SLUG_ORDER[$right_slug]) {
                    return self::NAVIGATION_SLUG_ORDER[$left_slug] <=> self::NAVIGATION_SLUG_ORDER[$right_slug];
                }
                $package_compare = strcasecmp((string) ($left['package'] ?? ''), (string) ($right['package'] ?? ''));
                if (0 !== $package_compare) {
                    return $package_compare;
                }

                return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
            });
            if (in_array((string) ($section['id'] ?? ''), ['free-extensions', 'licensed-extensions'], true)) {
                $section['groups'] = $this->navigation_groups($section['docs']);
            }
        }
        unset($section);

        return array_values(array_filter($sections, static function ($section) {
            return !empty($section['docs']);
        }));
    }

    /**
     * Keep extension documents visibly associated with their owning package.
     *
     * @param array<int,array> $documents Extension documents in navigation order.
     * @return array<int,array>
     */
    private function navigation_groups(array $documents) {
        $groups = [];
        foreach ($documents as $doc) {
            if (!is_array($doc)) {
                continue;
            }

            $package_slug = sanitize_key((string) ($doc['package'] ?? ''));
            if ('' === $package_slug) {
                continue;
            }
            if (!isset($groups[$package_slug])) {
                $groups[$package_slug] = [
                    'package' => $package_slug,
                    'title' => $this->package_navigation_title(
                        (string) ($doc['package_title'] ?? ''),
                        $package_slug
                    ),
                    'docs' => [],
                ];
            }
            $groups[$package_slug]['docs'][] = $doc;
        }

        uasort($groups, static function ($left, $right) {
            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return array_values($groups);
    }

    private function navigation_section_id(array $doc) {
        $package = sanitize_key((string) ($doc['package'] ?? ''));
        if ($package && 'million-dollar-script' !== $package) {
            $access = sanitize_key((string) ($doc['access_level'] ?? ''));
            return empty($doc['license_required']) && in_array($access, ['', 'public'], true)
                ? 'free-extensions'
                : 'licensed-extensions';
        }

        $slug = sanitize_key((string) ($doc['id'] ?? ''));
        if (in_array($slug, ['index', 'installation-and-setup', 'setup-wizard', 'setup', 'mds2-migration', 'mds3-upgrade-from-mds2', 'mds3-mds2-workflow-parity', 'upgrade-guide-2-3-5-to-2-6'], true)) {
            return 'getting-started';
        }
        if (in_array($slug, ['grids-and-blocks', 'mds3-grid-pricing-and-renderers', 'orders', 'media-and-draft-recovery', 'order-emails-and-renewals', 'emails', 'list-page-customization', 'shortcode-reference'], true)) {
            return 'grids-orders';
        }
        if (in_array($slug, ['settings', 'routes-and-pages', 'pixel-permalinks-and-migration', 'dynamic-css-and-theme-modes', 'admin-navigation', 'logs-and-cron', 'legal-pages', 'privacy-security-and-accessibility', 'backups-and-recovery'], true)) {
            return 'configuration-legal';
        }
        if (in_array($slug, ['standalone-checkout', 'mds3-checkout-and-payments', 'woocommerce-integration', 'imagegrid', 'wordpress-integration'], true)) {
            return 'integrations';
        }
        if (in_array($slug, ['api-access', 'api-reference', 'extension-api', 'developer-overview', 'extension-development', 'hooks-reference', 'developer-upgrade-notes'], true)) {
            return 'api-development';
        }
        if (in_array($slug, ['troubleshooting', 'troubleshooting-grid-alignment', 'known-compatibility-issues', 'support'], true)) {
            return 'troubleshooting';
        }

        return 'other';
    }

    /**
     * @return array|null
     */
    public function find($doc_key = '', $package_slug = '', $search = '') {
        $documents = $this->documents($package_slug, $search);
        $doc_key = sanitize_text_field((string) $doc_key);
        if ($doc_key) {
            foreach ($documents as $doc) {
                if ($doc_key === (string) ($doc['key'] ?? '') || $doc_key === (string) ($doc['id'] ?? '')) {
                    return $doc;
                }
            }
        }

        return $documents ? $documents[0] : null;
    }

    public function read(array $doc) {
        if (!empty($doc['remote'])) {
            return (new RemoteDocsClient())->read_document($doc);
        }

        $path = (string) ($doc['path'] ?? '');
        $docs_dir = (string) ($doc['docs_dir'] ?? '');
        $real = $path ? realpath($path) : false;
        $root = $docs_dir ? realpath($docs_dir) : false;
        if (!$real || !$root || !$this->path_is_inside($real, $root) || !is_readable($real)) {
            return '';
        }

        return (string) file_get_contents($real);
    }

    public function render_markdown($markdown) {
        $lines = preg_split('/\R/', (string) $markdown);
        if (!is_array($lines)) {
            return '';
        }

        $html = '';
        $in_code = false;
        $code_language = 'plaintext';
        $code = [];

        for ($index = 0, $line_count = count($lines); $index < $line_count; $index++) {
            $line = $lines[$index];
            $raw = rtrim((string) $line);
            if (preg_match('/^\s*```([^`]*)$/', $raw, $fence)) {
                if ($in_code) {
                    $html .= $this->render_code_block($code, $code_language);
                    $code = [];
                    $in_code = false;
                    $code_language = 'plaintext';
                } else {
                    $in_code = true;
                    $code_language = $this->normalize_code_language((string) ($fence[1] ?? ''));
                }
                continue;
            }

            if ($in_code) {
                $code[] = $raw;
                continue;
            }

            $trimmed = trim($raw);
            if ('' === $trimmed) {
                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $matches)) {
                $level = min(4, max(2, strlen($matches[1]) + 1));
                $html .= '<h' . $level . '>' . $this->inline_markdown($matches[2]) . '</h' . $level . '>';
                continue;
            }

            if ($this->is_table_start($lines, $index)) {
                $html .= $this->render_table($lines, $index);
                $index--;
                continue;
            }

            $list_item = $this->parse_list_item($raw);
            if ($list_item) {
                $html .= $this->render_list($lines, $index, $list_item['indent'], $list_item['type']);
                $index--;
                continue;
            }

            if (str_starts_with($trimmed, '>')) {
                $html .= '<blockquote><p>' . $this->inline_markdown(ltrim(substr($trimmed, 1))) . '</p></blockquote>';
                continue;
            }

            if (preg_match('/^-{3,}$/', $trimmed)) {
                $html .= '<hr>';
                continue;
            }

            $html .= '<p>' . $this->inline_markdown($trimmed) . '</p>';
        }

        if ($in_code) {
            $html .= $this->render_code_block($code, $code_language);
        }

        return wp_kses($html, self::allowed_html());
    }

    private function render_code_block(array $lines, $language) {
        $language = $this->normalize_code_language($language);
        $class = 'language-' . $language;

        return '<pre class="' . esc_attr($class) . '"><code class="' . esc_attr($class) . '">' . esc_html(implode("\n", $lines)) . '</code></pre>';
    }

    private function normalize_code_language($language) {
        $language = strtolower(trim((string) $language));
        $language = preg_replace('/\s.*$/', '', $language);
        $language = preg_replace('/[^a-z0-9_+-]/', '', (string) $language);
        $aliases = [
            'html' => 'markup',
            'js' => 'javascript',
            'md' => 'markdown',
            'sh' => 'bash',
            'shell' => 'bash',
            'yml' => 'yaml',
        ];

        return $aliases[$language] ?? ($language ?: 'plaintext');
    }

    private function parse_list_item($line) {
        $line = str_replace("\t", '    ', (string) $line);
        if (!preg_match('/^(\s*)([-*+]|\d+[.)])\s+(.+)$/', $line, $matches)) {
            return null;
        }

        return [
            'indent' => strlen((string) $matches[1]),
            'type' => preg_match('/^\d/', (string) $matches[2]) ? 'ol' : 'ul',
            'content' => (string) $matches[3],
        ];
    }

    private function render_list(array $lines, &$index, $indent, $type) {
        $html = '<' . $type . '>';
        $line_count = count($lines);

        while ($index < $line_count) {
            $item = $this->parse_list_item($lines[$index]);
            if (!$item || $item['indent'] !== $indent || $item['type'] !== $type) {
                break;
            }

            $html .= '<li>' . $this->inline_markdown($item['content']);
            $index++;

            while ($index < $line_count) {
                $nested = $this->parse_list_item($lines[$index]);
                if (!$nested || $nested['indent'] <= $indent) {
                    break;
                }
                $html .= $this->render_list($lines, $index, $nested['indent'], $nested['type']);
            }

            $html .= '</li>';
        }

        return $html . '</' . $type . '>';
    }

    private function is_table_start(array $lines, $index) {
        if (!isset($lines[$index], $lines[$index + 1])) {
            return false;
        }

        $header = $this->split_table_row($lines[$index]);
        $separator = $this->split_table_row($lines[$index + 1]);
        if (count($header) < 1 || count($header) !== count($separator) || !str_contains((string) $lines[$index], '|')) {
            return false;
        }

        foreach ($separator as $cell) {
            if (!preg_match('/^:?-{3,}:?$/', trim((string) $cell))) {
                return false;
            }
        }

        return true;
    }

    private function render_table(array $lines, &$index) {
        $headers = $this->split_table_row($lines[$index]);
        $separators = $this->split_table_row($lines[$index + 1]);
        $alignments = array_map([$this, 'table_alignment'], $separators);
        $column_count = count($headers);
        $index += 2;

        $html = '<div class="mds3-docs-table-scroll" role="region" aria-label="' . esc_attr__('Documentation table', 'million-dollar-script') . '" tabindex="0">';
        $html .= '<table><thead><tr>';
        foreach ($headers as $column => $header) {
            $class = 'mds3-docs-table-align-' . $alignments[$column];
            $html .= '<th scope="col" class="' . esc_attr($class) . '">' . $this->inline_markdown(trim((string) $header)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        $line_count = count($lines);
        while ($index < $line_count) {
            $raw = rtrim((string) $lines[$index]);
            if ('' === trim($raw) || !str_contains($raw, '|')) {
                break;
            }

            $cells = array_slice(array_pad($this->split_table_row($raw), $column_count, ''), 0, $column_count);
            $html .= '<tr>';
            foreach ($cells as $column => $cell) {
                $class = 'mds3-docs-table-align-' . $alignments[$column];
                $html .= '<td class="' . esc_attr($class) . '">' . $this->inline_markdown(trim((string) $cell)) . '</td>';
            }
            $html .= '</tr>';
            $index++;
        }

        return $html . '</tbody></table></div>';
    }

    private function split_table_row($line) {
        $line = trim((string) $line);
        $cells = [];
        $cell = '';
        $escaped = false;
        $in_code = false;
        $length = strlen($line);

        for ($position = 0; $position < $length; $position++) {
            $character = $line[$position];
            if ($escaped) {
                $cell .= $character;
                $escaped = false;
                continue;
            }
            if ('\\' === $character) {
                $escaped = true;
                continue;
            }
            if ('`' === $character) {
                $in_code = !$in_code;
                $cell .= $character;
                continue;
            }
            if ('|' === $character && !$in_code) {
                $cells[] = $cell;
                $cell = '';
                continue;
            }
            $cell .= $character;
        }

        if ($escaped) {
            $cell .= '\\';
        }
        $cells[] = $cell;

        if (str_starts_with($line, '|') && isset($cells[0]) && '' === trim((string) $cells[0])) {
            array_shift($cells);
        }
        if (str_ends_with($line, '|') && $cells && '' === trim((string) end($cells))) {
            array_pop($cells);
        }

        return array_values($cells);
    }

    private function table_alignment($separator) {
        $separator = trim((string) $separator);
        if (str_starts_with($separator, ':') && str_ends_with($separator, ':')) {
            return 'center';
        }
        if (str_ends_with($separator, ':')) {
            return 'right';
        }

        return 'left';
    }

    public function strip_leading_title_heading($html, $title) {
        $title = $this->normalize_heading_text($title);
        if ('' === $title || !preg_match('/^\s*<h([2-4])>(.*?)<\/h\1>\s*/is', (string) $html, $matches)) {
            return (string) $html;
        }

        $heading = $this->normalize_heading_text(wp_strip_all_tags((string) $matches[2]));
        if ($heading !== $title) {
            return (string) $html;
        }

        return (string) substr((string) $html, strlen($matches[0]));
    }

    public function highlight_search_terms($html, $search) {
        $terms = $this->search_terms($search);
        if (!$terms) {
            return (string) $html;
        }

        $pattern = '/(' . implode('|', array_map(static function ($term) {
            return preg_quote($term, '/');
        }, $terms)) . ')/iu';

        return (string) preg_replace_callback('/>([^<]+)</u', static function ($matches) use ($pattern) {
            $text = preg_replace_callback($pattern, static function ($term_match) {
                return '<mark class="mds3-docs-highlight">' . (string) $term_match[0] . '</mark>';
            }, (string) $matches[1]);

            return '>' . $text . '<';
        }, (string) $html);
    }

    private function normalize_heading_text($value) {
        $charset = function_exists('get_bloginfo') ? (get_bloginfo('charset') ?: 'UTF-8') : 'UTF-8';
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, $charset);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return strtolower((string) $value);
    }

    public static function allowed_html() {
        return [
            'a' => [
                'href' => true,
                'target' => true,
                'rel' => true,
            ],
            'blockquote' => [],
            'br' => [],
            'code' => [
                'class' => true,
            ],
            'div' => [
                'aria-label' => true,
                'class' => true,
                'role' => true,
                'tabindex' => true,
            ],
            'em' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'hr' => [],
            'li' => [],
            'mark' => [
                'class' => true,
            ],
            'ol' => [],
            'p' => [],
            'pre' => [
                'class' => true,
            ],
            'strong' => [],
            'table' => [],
            'tbody' => [],
            'td' => [
                'class' => true,
            ],
            'th' => [
                'class' => true,
                'scope' => true,
            ],
            'thead' => [],
            'tr' => [],
            'ul' => [],
        ];
    }

    public function url($doc_id = '', $package_slug = '', $fallback_url = '') {
        $doc_id = sanitize_key((string) $doc_id);
        $package_slug = sanitize_key((string) $package_slug);
        $doc = null;
        if ($doc_id) {
            foreach ($this->packages() as $package) {
                if ($package_slug && $package_slug !== (string) ($package['package'] ?? '')) {
                    continue;
                }
                foreach ((array) ($package['docs'] ?? []) as $candidate) {
                    if ($doc_id === (string) ($candidate['id'] ?? '')) {
                        $doc = $candidate;
                        break 2;
                    }
                }
            }
        }

        if (!$doc && $fallback_url) {
            return function_exists('esc_url_raw') ? esc_url_raw((string) $fallback_url) : (string) $fallback_url;
        }

        $args = ['page' => 'mds3-docs'];
        if ($doc) {
            $args['package'] = (string) ($doc['package'] ?? '');
            $args['doc'] = (string) ($doc['key'] ?? '');
        }

        $admin_url = function_exists('admin_url') ? admin_url('admin.php') : 'admin.php';

        return function_exists('add_query_arg') ? add_query_arg($args, $admin_url) : $admin_url . '?' . http_build_query($args);
    }

    /**
     * Return the central Documentation URL for an available package.
     *
     * The package filter is intentionally left unchanged so the full docs
     * navigation remains available after following an extension link.
     *
     * @param string $package_slug Docs package slug.
     * @param string $doc_id       Optional document ID within the package.
     * @return string
     */
    public function package_url($package_slug, $doc_id = '') {
        $package_slug = sanitize_key((string) $package_slug);
        $doc_id = sanitize_key((string) $doc_id);
        $package = $this->packages()[$package_slug] ?? null;
        if (!is_array($package) || empty($package['docs']) || !is_array($package['docs'])) {
            return '';
        }

        $doc = null;
        foreach ($package['docs'] as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            if (!$doc) {
                $doc = $candidate;
            }
            if ($doc_id && $doc_id === (string) ($candidate['id'] ?? '')) {
                $doc = $candidate;
                break;
            }
        }

        return is_array($doc) ? $this->document_url($doc) : '';
    }

    public function document_url(array $doc, $search = '', $package_filter = '') {
        $args = [
            'page' => 'mds3-docs',
            'doc' => (string) ($doc['key'] ?? ''),
        ];

        $search = trim((string) $search);
        if ('' !== $search) {
            $args['s'] = $search;
        }

        $package_filter = sanitize_key((string) $package_filter);
        if ('' !== $package_filter) {
            $args['package'] = $package_filter;
        }

        $admin_url = function_exists('admin_url') ? admin_url('admin.php') : 'admin.php';

        return function_exists('add_query_arg') ? add_query_arg($args, $admin_url) : $admin_url . '?' . http_build_query($args);
    }

    /**
     * @return string[]
     */
    private function manifest_paths() {
        $paths = [];
        $core_manifest = MILLION_DOLLAR_SCRIPT_PATH . 'docs/manifest.json';
        if (is_readable($core_manifest)) {
            $paths[] = $core_manifest;
        }

        $active_plugins = array_merge(
            (array) get_option('active_plugins', []),
            array_keys((array) get_site_option('active_sitewide_plugins', []))
        );
        foreach ($active_plugins as $plugin_file) {
            $plugin_file = trim(str_replace('\\', '/', (string) $plugin_file), '/');
            if (!$plugin_file || str_contains($plugin_file, '..')) {
                continue;
            }

            $plugin_dir = dirname($plugin_file);
            $candidate = trailingslashit(WP_PLUGIN_DIR) . ('.' === $plugin_dir ? '' : trailingslashit($plugin_dir)) . 'docs/manifest.json';
            if (is_readable($candidate)) {
                $paths[] = $candidate;
            }
        }

        /**
         * Filters package-owned docs manifest paths.
         *
         * Extensions can add a manifest path when their docs are not located in
         * the default active-plugin docs/ directory.
         *
         * @param string[]     $paths    Candidate manifest paths.
         * @param DocsRegistry $registry Registry instance.
         */
        $paths = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/docs/manifest/paths', $paths, $this);

        return array_values(array_filter(array_map('strval', is_array($paths) ? $paths : [])));
    }

    /**
     * Keep the core Million Dollar Script package pinned ahead of extension docs.
     *
     * @param array<string,array> $packages Docs packages keyed by package slug.
     * @return array<string,array>
     */
    private function sort_packages(array $packages) {
        uasort($packages, static function ($a, $b) {
            $a_is_core = 'million-dollar-script' === (string) ($a['package'] ?? '');
            $b_is_core = 'million-dollar-script' === (string) ($b['package'] ?? '');
            if ($a_is_core !== $b_is_core) {
                return $a_is_core ? -1 : 1;
            }

            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return $packages;
    }

    /**
     * Return a concise package label for documentation navigation.
     *
     * Plugin names keep their full branding elsewhere. The docs browser already
     * establishes the Million Dollar Script context, so repeating the product
     * name makes extension labels harder to scan.
     *
     * @param string $title Package title.
     * @param string $slug  Package slug fallback.
     * @return string
     */
    public function package_navigation_title($title, $slug = '') {
        $title = trim(sanitize_text_field((string) $title));
        $slug = sanitize_key((string) $slug);
        if ('' === $title) {
            $title = $slug ? ucwords(str_replace('-', ' ', preg_replace('/^mds-/', '', $slug))) : '';
        }
        if ('million-dollar-script' === $slug || 0 === strcasecmp($title, 'Million Dollar Script')) {
            return $title ?: 'Million Dollar Script';
        }

        foreach (['Million Dollar Script - ', 'Million Dollar Script ', 'MDS '] as $prefix) {
            if (0 !== stripos($title, $prefix)) {
                continue;
            }

            $candidate = trim((string) substr($title, strlen($prefix)));
            if ('' !== $candidate) {
                return $candidate;
            }
        }

        return $title;
    }

    private function navigation_title($title, $package_title) {
        $title = trim(sanitize_text_field((string) $title));
        $package_title = trim(sanitize_text_field((string) $package_title));
        if ('' === $title) {
            return '';
        }

        $prefixes = array_values(array_filter(array_unique([
            $package_title,
            'Million Dollar Script',
        ])));

        foreach ($prefixes as $prefix) {
            $prefix = trim((string) $prefix);
            if ('' === $prefix || 0 !== stripos($title, $prefix)) {
                continue;
            }

            $candidate = trim((string) substr($title, strlen($prefix)));
            $candidate = ltrim($candidate, " \t\n\r\0\x0B:-");
            if ('' === $candidate) {
                continue;
            }

            if (preg_match('/^2(\s|$)/', $candidate)) {
                return 'MDS ' . $candidate;
            }

            return $candidate;
        }

        return $title;
    }

    /**
     * @return array|null
     */
    private function load_package($manifest_path) {
        $decoded = json_decode((string) file_get_contents($manifest_path), true);
        if (!is_array($decoded)) {
            $this->warnings[] = sprintf(
                /* translators: %s: docs manifest path */
                __('Docs manifest could not be parsed: %s', 'million-dollar-script'),
                $manifest_path
            );
            return null;
        }

        $docs_dir = dirname($manifest_path);
        $errors = $this->validate_manifest($decoded, $docs_dir);
        if ($errors) {
            $this->warnings[] = sprintf(
                /* translators: 1: docs package, 2: validation errors */
                __('Docs manifest skipped for %1$s: %2$s', 'million-dollar-script'),
                sanitize_text_field((string) ($decoded['package'] ?? basename(dirname($docs_dir)))),
                implode(' ', $errors)
            );
            return null;
        }

        $package_slug = sanitize_key((string) $decoded['package']);
        $package = [
            'package' => $package_slug,
            'type' => sanitize_key((string) $decoded['type']),
            'version' => sanitize_text_field((string) $decoded['version']),
            'channel' => sanitize_key((string) $decoded['channel']),
            'title' => sanitize_text_field((string) $decoded['title']),
            'docs_dir' => $docs_dir,
            'manifest_path' => $manifest_path,
            'docs' => [],
        ];

        foreach ($decoded['docs'] as $doc) {
            $id = sanitize_key((string) $doc['id']);
            $file = str_replace('\\', '/', (string) $doc['file']);
            $path = realpath($docs_dir . DIRECTORY_SEPARATOR . $file);
            if (!$path) {
                continue;
            }
            $package['docs'][] = [
                'id' => $id,
                'key' => $package_slug . ':' . $id,
                'title' => sanitize_text_field((string) $doc['title']),
                'nav_title' => $this->navigation_title((string) $doc['title'], (string) $package['title']),
                'file' => $file,
                'path' => $path,
                'docs_dir' => $docs_dir,
                'audience' => array_values(array_map('sanitize_key', (array) $doc['audience'])),
                'topics' => array_values(array_map('sanitize_key', (array) $doc['topics'])),
                'package' => $package_slug,
                'package_title' => $package['title'],
                'package_type' => $package['type'],
                'version' => $package['version'],
                'channel' => $package['channel'],
            ];
        }

        return $package;
    }

    /**
     * @return string[]
     */
    private function validate_manifest(array $manifest, $docs_dir) {
        $errors = [];
        if (1 !== ($manifest['schema'] ?? null)) {
            $errors[] = __('schema must be 1.', 'million-dollar-script');
        }
        if (!$this->non_empty_string($manifest['package'] ?? null)) {
            $errors[] = __('package is required.', 'million-dollar-script');
        }
        if (!in_array(($manifest['type'] ?? null), ['core', 'extension'], true)) {
            $errors[] = __('type must be core or extension.', 'million-dollar-script');
        }
        if (!$this->non_empty_string($manifest['version'] ?? null)) {
            $errors[] = __('version is required.', 'million-dollar-script');
        }
        if (!in_array(($manifest['channel'] ?? null), ['main', 'beta', 'alpha'], true)) {
            $errors[] = __('channel must be main, beta, or alpha.', 'million-dollar-script');
        }
        if (!$this->non_empty_string($manifest['title'] ?? null)) {
            $errors[] = __('title is required.', 'million-dollar-script');
        }
        if (empty($manifest['docs']) || !is_array($manifest['docs'])) {
            $errors[] = __('docs must be a non-empty array.', 'million-dollar-script');
            return $errors;
        }

        $docs_root = realpath($docs_dir);
        if (!$docs_root) {
            $errors[] = __('docs directory was not readable.', 'million-dollar-script');
            return $errors;
        }

        $ids = [];
        foreach (array_values($manifest['docs']) as $index => $doc) {
            $prefix = 'docs[' . $index . '] ';
            if (!is_array($doc)) {
                $errors[] = $prefix . __('must be an object.', 'million-dollar-script');
                continue;
            }
            $id = (string) ($doc['id'] ?? '');
            if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $id)) {
                $errors[] = $prefix . __('id must be URL-safe.', 'million-dollar-script');
            } elseif (isset($ids[$id])) {
                $errors[] = $prefix . __('id is duplicated.', 'million-dollar-script');
            } else {
                $ids[$id] = true;
            }
            if (!$this->non_empty_string($doc['title'] ?? null)) {
                $errors[] = $prefix . __('title is required.', 'million-dollar-script');
            }

            $file = (string) ($doc['file'] ?? '');
            if (!$this->safe_relative_file($file)) {
                $errors[] = $prefix . __('file must stay inside docs/.', 'million-dollar-script');
            } elseif (!str_ends_with(strtolower($file), '.md')) {
                $errors[] = $prefix . __('file must be Markdown.', 'million-dollar-script');
            } else {
                $path = realpath($docs_root . DIRECTORY_SEPARATOR . $file);
                if (!$path || !is_file($path) || !$this->path_is_inside($path, $docs_root)) {
                    $errors[] = $prefix . __('file was not found inside docs/.', 'million-dollar-script');
                }
            }

            foreach (['audience' => self::AUDIENCES, 'topics' => self::TOPICS] as $field => $allowed) {
                if (empty($doc[$field]) || !is_array($doc[$field])) {
                    $errors[] = $prefix . sprintf(
                        /* translators: %s: docs manifest field */
                        __('%s must be a non-empty array.', 'million-dollar-script'),
                        $field
                    );
                    continue;
                }
                foreach ($doc[$field] as $value) {
                    if (!is_string($value) || !in_array($value, $allowed, true)) {
                        $errors[] = $prefix . sprintf(
                            /* translators: 1: docs manifest field, 2: unsupported value */
                            __('%1$s contains unsupported value %2$s.', 'million-dollar-script'),
                            $field,
                            is_scalar($value) ? (string) $value : gettype($value)
                        );
                    }
                }
            }
        }

        return $errors;
    }

    private function document_matches(array $doc, $search) {
        $haystack = strtolower(implode(' ', [
            (string) ($doc['title'] ?? ''),
            (string) ($doc['package_title'] ?? ''),
            implode(' ', (array) ($doc['topics'] ?? [])),
            wp_strip_all_tags($this->read($doc)),
        ]));

        return str_contains($haystack, $search);
    }

    private function search_terms($search) {
        $search = trim(wp_strip_all_tags((string) $search));
        if ('' === $search) {
            return [];
        }

        $terms = [$search];
        foreach (preg_split('/\s+/u', $search) ?: [] as $term) {
            $term = trim((string) $term);
            if (strlen($term) >= 2) {
                $terms[] = $term;
            }
        }

        $terms = array_values(array_unique(array_filter($terms, static function ($term) {
            return '' !== trim((string) $term);
        })));
        usort($terms, static function ($a, $b) {
            return strlen((string) $b) <=> strlen((string) $a);
        });

        return $terms;
    }

    private function inline_markdown($text) {
        $text = wp_strip_all_tags((string) $text, true);
        $text = esc_html((string) $text);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', (string) $text);
        $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', (string) $text);
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function ($matches) {
            $url = esc_url(html_entity_decode((string) $matches[2], ENT_QUOTES));
            if (!$url) {
                return (string) $matches[1];
            }

            return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . (string) $matches[1] . '</a>';
        }, (string) $text);

        return wp_kses($text, self::allowed_html());
    }

    private function non_empty_string($value) {
        return is_string($value) && '' !== trim($value);
    }

    private function safe_relative_file($file) {
        $file = (string) $file;
        if ('' === trim($file) || str_contains($file, "\0") || str_contains($file, '\\') || str_starts_with($file, '/') || str_starts_with($file, '~')) {
            return false;
        }
        foreach (explode('/', $file) as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment) {
                return false;
            }
        }

        return true;
    }

    private function path_is_inside($path, $directory) {
        $path = rtrim((string) $path, DIRECTORY_SEPARATOR);
        $directory = rtrim((string) $directory, DIRECTORY_SEPARATOR);

        return $path === $directory || str_starts_with($path, $directory . DIRECTORY_SEPARATOR);
    }
}
