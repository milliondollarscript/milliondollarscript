<?php
/**
 * Legacy MDS page detection helpers.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait DetectsLegacyPages {

    private $legacy_page_source_grid_ids = null;

    public function page_candidates() {
        $candidates = [];
        $this->merge_metadata_pages($candidates);
        $this->merge_option_pages($candidates);
        $this->merge_content_pages($candidates);

        ksort($candidates);

        $result = [];
        foreach ($candidates as $post_id => $candidate) {
            $candidate['unmodified'] = $this->page_is_unmodified($this->legacy_page_content($post_id));
            $result[] = $candidate;
        }

        return $result;
    }

    /**
     * Original MDS2 content of a page, falling back to the live content on first pass.
     */
    public function legacy_page_content($post_id) {
        $post_id = absint($post_id);
        $original = (string) get_post_meta($post_id, '_mds3_migration_original_content', true);

        return '' !== $original ? $original : (string) get_post_field('post_content', $post_id);
    }

    /**
     * True when a page holds only MDS2 shortcodes/blocks and no author-added content.
     */
    public function page_is_unmodified($content) {
        $content = (string) $content;
        if ('' === trim($content)) {
            return false;
        }

        $stripped = $content;
        if (function_exists('get_shortcode_regex')) {
            $legacy_tags = [
                'milliondollarscript', 'million_dollar_script', 'mds', 'mds_grid',
                'pixel_grid', 'pixel_advertising', 'ad_grid', 'pixel_board',
                'mds_display', 'pixel_display', 'advertisement_grid', 'mds_widget',
            ];
            $stripped = (string) preg_replace('/' . get_shortcode_regex($legacy_tags) . '/s', '', $stripped);
        }
        $stripped = (string) preg_replace('/<!--.*?-->/s', '', $stripped);
        $stripped = (string) strip_tags($stripped);

        return '' === trim($stripped);
    }

    public function pages_report() {
        $candidates = $this->page_candidates();
        $by_type = [];
        foreach ($candidates as $candidate) {
            $type = (string) ($candidate['type'] ?? 'unknown');
            $by_type[$type] = ($by_type[$type] ?? 0) + 1;
        }

        return [
            'count' => count($candidates),
            'by_type' => $by_type,
            'candidates' => $candidates,
        ];
    }

    public function decode_json($value) {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function page_type_from_content($content, $fallback = '') {
        $attrs = $this->content_attrs((string) $content);
        $type = sanitize_key($attrs['type'] ?? $attrs['milliondollarscript_type'] ?? $attrs['mds_type'] ?? $fallback);
        $type_aliases = [
            'users' => 'order',
            'checkout' => 'confirm-order',
            'confirm' => 'confirm-order',
            'write_ad' => 'write-ad',
            'thankyou' => 'thank-you',
        ];
        $type = $type_aliases[$type] ?? $type;

        if ($type && PageRepository::is_valid_type($type)) {
            return $type;
        }

        $content = strtolower((string) $content);
        foreach (PageRepository::TYPES as $candidate) {
            if (str_contains($content, 'type="' . $candidate . '"') || str_contains($content, "type='" . $candidate . "'") || str_contains($content, 'type=' . $candidate)) {
                return $candidate;
            }
        }

        return $fallback && PageRepository::is_valid_type($fallback) ? sanitize_key($fallback) : 'grid';
    }

    public function grid_id_from_content($content) {
        $attrs = $this->content_attrs((string) $content);

        foreach (['grid_id', 'id', 'BID', 'bid', 'milliondollarscript_id', 'mds_id', 'banner_id'] as $key) {
            if (!empty($attrs[$key])) {
                return absint($attrs[$key]);
            }
        }

        if (preg_match('/(?:grid_id|id|BID|bid|milliondollarscript_id|mds_id|banner_id)["\']?\s*(?:=|:)\s*["\']?([0-9]+)/', (string) $content, $matches)) {
            return absint($matches[1]);
        }

        return 0;
    }

    private function merge_metadata_pages(array &$candidates) {
        global $wpdb;

        $table = $this->page_metadata_table();
        if (!DB::table_exists($table)) {
            return;
        }

        $rows = $wpdb->get_results('SELECT * FROM ' . DB::ident($table), ARRAY_A);
        foreach (is_array($rows) ? $rows : [] as $row) {
            $post_id = absint($row['post_id'] ?? 0);
            if (!$post_id || !get_post($post_id)) {
                continue;
            }
            if ($this->is_native_mds3_page($post_id)) {
                continue;
            }

            $config = $this->decode_json($row['page_config'] ?? '');
            $shortcode_attrs = $this->decode_json($row['shortcode_attributes'] ?? '');
            $block_attrs = $this->decode_json($row['block_attributes'] ?? '');
            if ($this->skip_metadata_page_candidate($post_id, $row, $config, $shortcode_attrs, $block_attrs)) {
                continue;
            }

            $type = sanitize_key($row['page_type'] ?? '');
            if (!$type || !PageRepository::is_valid_type($type)) {
                $type = $this->page_type_from_content((string) get_post_field('post_content', $post_id));
            }

            $grid_id = $this->first_absint([
                $config['grid_id'] ?? 0,
                $config['id'] ?? 0,
                $shortcode_attrs['grid_id'] ?? 0,
                $shortcode_attrs['id'] ?? 0,
                $block_attrs['grid_id'] ?? 0,
                $block_attrs['id'] ?? 0,
            ]);

            if (!$grid_id) {
                $grid_id = $this->grid_id_from_content((string) get_post_field('post_content', $post_id));
            }
            if (!$this->page_grid_belongs_to_source($grid_id)) {
                continue;
            }

            $this->merge_candidate($candidates, $post_id, [
                'type' => $type,
                'legacy_grid_id' => $grid_id,
                'sources' => ['metadata_table'],
                'configuration' => $config,
                'legacy_metadata' => $row,
            ]);
        }
    }

    private function merge_option_pages(array &$candidates) {
        foreach (PageRepository::option_aliases() as $type => $aliases) {
            foreach ($aliases as $alias) {
                $value = get_option($alias, null);
                $post_id = absint($value);
                if (!$post_id || !get_post($post_id)) {
                    continue;
                }

                if ($this->is_native_mds3_page($post_id)) {
                    continue;
                }

                if ($this->is_mds3_extension_legal_page($post_id)) {
                    continue;
                }

                $content = (string) get_post_field('post_content', $post_id);
                $grid_id = $this->grid_id_from_content($content);
                if (!$this->page_grid_belongs_to_source($grid_id)) {
                    continue;
                }

                $this->merge_candidate($candidates, $post_id, [
                    'type' => $type,
                    'legacy_grid_id' => $grid_id,
                    'sources' => ['option:' . $alias],
                    'configuration' => ['option_alias' => $alias],
                    'legacy_metadata' => [],
                ]);
            }
        }
    }

    private function merge_content_pages(array &$candidates) {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT DISTINCT p.ID, p.post_title, p.post_name, p.post_content FROM " . DB::ident($wpdb->posts) . ' p'
                . ' LEFT JOIN ' . DB::ident($wpdb->postmeta) . " migrated ON migrated.post_id = p.ID AND migrated.meta_key = '_mds3_migration_source'"
                . " WHERE p.post_type = 'page' AND p.post_status NOT IN ('trash', 'auto-draft')"
                . " AND (p.post_content LIKE '%[milliondollarscript%'"
                . " OR p.post_content LIKE '%[million_dollar_script%'"
                . " OR p.post_content LIKE '%[mds%'"
                . " OR p.post_content LIKE '%[pixel_%'"
                . " OR p.post_content LIKE '%[ad_grid%'"
                . " OR p.post_content LIKE '%[advertisement_grid%'"
                . " OR p.post_content LIKE '%<!-- wp:carbon-fields/million-dollar-script%'"
                . " OR migrated.meta_value = 'mds2') ORDER BY p.ID ASC",
            ARRAY_A
        );

        foreach (is_array($rows) ? $rows : [] as $row) {
            $post_id = absint($row['ID'] ?? 0);
            if (!$post_id) {
                continue;
            }

            if ($this->is_native_mds3_page($post_id)) {
                continue;
            }

            if ($this->is_mds3_extension_legal_page($post_id)) {
                continue;
            }

            $content = (string) ($row['post_content'] ?? '');
            $original_content = (string) get_post_meta($post_id, '_mds3_migration_original_content', true);
            $detection_content = $original_content ?: $content;
            if (!$this->is_source_migrated_page($post_id) && !$this->has_legacy_page_content($detection_content)) {
                continue;
            }

            $type = $this->page_type_from_content($detection_content);
            $grid_id = $this->grid_id_from_content($detection_content);
            if (!$this->page_grid_belongs_to_source($grid_id)) {
                continue;
            }

            $this->merge_candidate($candidates, $post_id, [
                'type' => $type,
                'legacy_grid_id' => $grid_id,
                'sources' => ['content_scan'],
                'configuration' => [
                    'post_name' => (string) ($row['post_name'] ?? ''),
                    'post_title' => (string) ($row['post_title'] ?? ''),
                ],
                'legacy_metadata' => [],
            ]);
        }
    }

    private function merge_candidate(array &$candidates, $post_id, array $candidate) {
        $post_id = absint($post_id);
        $existing = $candidates[$post_id] ?? [
            'post_id' => $post_id,
            'title' => get_the_title($post_id),
            'type' => '',
            'legacy_grid_id' => 0,
            'sources' => [],
            'configuration' => [],
            'legacy_metadata' => [],
        ];

        if (empty($existing['type'])) {
            $existing['type'] = sanitize_key($candidate['type'] ?? 'grid');
        }

        if (empty($existing['legacy_grid_id']) && !empty($candidate['legacy_grid_id'])) {
            $existing['legacy_grid_id'] = absint($candidate['legacy_grid_id']);
        }

        $existing['sources'] = array_values(array_unique(array_merge($existing['sources'], $candidate['sources'] ?? [])));
        $existing['configuration'] = array_merge(is_array($existing['configuration']) ? $existing['configuration'] : [], is_array($candidate['configuration'] ?? null) ? $candidate['configuration'] : []);
        if (!empty($candidate['legacy_metadata'])) {
            $existing['legacy_metadata'] = $candidate['legacy_metadata'];
        }

        $candidates[$post_id] = $existing;
    }

    private function skip_metadata_page_candidate($post_id, array $row, array $config, array $shortcode_attrs, array $block_attrs) {
        if ($this->is_mds3_extension_legal_page($post_id)) {
            return true;
        }

        if ($this->metadata_validation_marks_not_mds_page($row)) {
            return true;
        }

        if (!empty($config['grid_id']) || !empty($config['id']) || !empty($shortcode_attrs) || !empty($block_attrs)) {
            return false;
        }

        $creation_method = sanitize_key((string) ($row['creation_method'] ?? ''));
        if ($creation_method && !in_array($creation_method, ['auto_detected', 'detected'], true)) {
            return false;
        }

        return !$this->metadata_row_has_explicit_mds_marker($row);
    }

    private function metadata_validation_marks_not_mds_page(array $row) {
        $errors = $this->decode_json($row['validation_errors'] ?? '');
        foreach ($errors as $error) {
            if (!is_array($error)) {
                continue;
            }

            if ('not_mds_page' === sanitize_key((string) ($error['type'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private function metadata_row_has_explicit_mds_marker(array $row) {
        $patterns = $this->decode_json($row['detected_patterns'] ?? '');
        $explicit_types = [
            'shortcode',
            'legacy_shortcode',
            'carbon_fields_block',
            'block',
            'custom_block',
            'mds_block',
        ];

        foreach ($patterns as $pattern) {
            if (!is_array($pattern)) {
                continue;
            }

            $type = sanitize_key((string) ($pattern['type'] ?? ''));
            if (in_array($type, $explicit_types, true)) {
                return true;
            }
        }

        return false;
    }

    private function is_mds3_extension_legal_page($post_id) {
        return '' !== (string) get_post_meta(absint($post_id), '_mds3_extension_legal_document', true);
    }

    private function is_native_mds3_page($post_id) {
        global $wpdb;

        $post_id = absint($post_id);
        if (!$post_id) {
            return false;
        }

        if ('mds2' === (string) get_post_meta($post_id, '_mds3_migration_source', true)) {
            $page_source_prefix = (string) get_post_meta($post_id, '_mds3_migration_source_prefix', true);
            if ('' !== $page_source_prefix) {
                return $page_source_prefix !== $this->source_prefix();
            }

            if (DB::table_exists(DB::table('migration_map'))) {
                $mapped_source_prefix = (string) $wpdb->get_var($wpdb->prepare(
                    'SELECT source_prefix FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE entity_type = %s AND legacy_id = %s AND mds3_entity_type = %s AND mds3_id = %d ORDER BY id DESC LIMIT 1',
                    'page',
                    (string) $post_id,
                    'page',
                    $post_id
                ));
                if ('' !== $mapped_source_prefix) {
                    return $mapped_source_prefix !== $this->source_prefix();
                }
            }

            return false;
        }

        if ('' === (string) get_post_meta($post_id, '_mds3_page_type', true)) {
            return false;
        }

        return !metadata_exists('post', $post_id, '_mds3_migration_original_content');
    }

    private function is_source_migrated_page($post_id) {
        if ('mds2' !== (string) get_post_meta(absint($post_id), '_mds3_migration_source', true)) {
            return false;
        }

        return !$this->is_native_mds3_page($post_id);
    }

    private function has_legacy_page_content($content) {
        $content = (string) $content;
        if (preg_match('/<!--\s+wp:carbon-fields\/million-dollar-script(?:\s|\{|\/|-->)/i', $content)) {
            return true;
        }

        if (!function_exists('get_shortcode_regex')) {
            return false;
        }

        $legacy_tags = [
            'milliondollarscript',
            'million_dollar_script',
            'mds',
            'mds_grid',
            'pixel_grid',
            'pixel_advertising',
            'ad_grid',
            'pixel_board',
            'mds_display',
            'pixel_display',
            'advertisement_grid',
            'mds_widget',
        ];

        return 1 === preg_match('/' . get_shortcode_regex($legacy_tags) . '/s', $content);
    }

    private function page_grid_belongs_to_source($legacy_grid_id) {
        $legacy_grid_id = absint($legacy_grid_id);
        if (!$legacy_grid_id) {
            return true;
        }

        $source_grid_ids = $this->legacy_page_source_grid_ids();

        return false === $source_grid_ids || isset($source_grid_ids[$legacy_grid_id]);
    }

    private function legacy_page_source_grid_ids() {
        global $wpdb;

        if (null !== $this->legacy_page_source_grid_ids) {
            return $this->legacy_page_source_grid_ids;
        }

        $table = $this->table('banners');
        if (!DB::table_exists($table)) {
            $this->legacy_page_source_grid_ids = false;

            return $this->legacy_page_source_grid_ids;
        }

        $grid_ids = array_map('absint', (array) $wpdb->get_col('SELECT banner_id FROM ' . DB::ident($table)));
        $this->legacy_page_source_grid_ids = array_fill_keys(array_filter($grid_ids), true);

        return $this->legacy_page_source_grid_ids;
    }

    private function shortcode_attrs($content) {
        if (!function_exists('get_shortcode_regex')) {
            return [];
        }

        $tags = ['milliondollarscript', 'million_dollar_script', 'mds', 'mds_display', 'mds_grid', 'pixel_grid', 'mds3_page'];
        $regex = get_shortcode_regex($tags);
        if (!preg_match('/' . $regex . '/', (string) $content, $matches)) {
            return [];
        }

        $attrs = shortcode_parse_atts($matches[3] ?? '');

        return is_array($attrs) ? $attrs : [];
    }

    private function content_attrs($content) {
        $attrs = $this->shortcode_attrs($content);
        if ($attrs) {
            return $attrs;
        }

        return $this->block_attrs($content);
    }

    private function block_attrs($content) {
        $attrs = [];

        if (function_exists('parse_blocks')) {
            foreach (parse_blocks((string) $content) as $block) {
                $attrs = $this->attrs_from_block($block);
                if ($attrs) {
                    return $attrs;
                }
            }
        }

        if (preg_match('/<!--\s+wp:(?:carbon-fields\/million-dollar-script|milliondollarscript\/[^\s]+|mds\/[^\s]+)\s+({.*?})\s*(?:\/-->|-->)/s', (string) $content, $matches)) {
            $decoded = json_decode((string) ($matches[1] ?? ''), true);
            if (is_array($decoded)) {
                $attrs = $this->flatten_block_attrs($decoded);
            }
        }

        return $attrs;
    }

    private function attrs_from_block(array $block) {
        $name = (string) ($block['blockName'] ?? '');
        if (!$name) {
            return [];
        }

        $matches = 'carbon-fields/million-dollar-script' === $name
            || str_starts_with($name, 'milliondollarscript/')
            || str_starts_with($name, 'mds/');

        if (!$matches) {
            foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
                $attrs = $this->attrs_from_block(is_array($inner_block) ? $inner_block : []);
                if ($attrs) {
                    return $attrs;
                }
            }

            return [];
        }

        return $this->flatten_block_attrs(is_array($block['attrs'] ?? null) ? $block['attrs'] : []);
    }

    private function flatten_block_attrs(array $attrs) {
        $data = is_array($attrs['data'] ?? null) ? $attrs['data'] : [];

        return array_merge($data, $attrs);
    }

    private function first_absint(array $values) {
        foreach ($values as $value) {
            $value = absint($value);
            if ($value) {
                return $value;
            }
        }

        return 0;
    }
}
