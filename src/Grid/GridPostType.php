<?php
/**
 * Lightweight grid page post type.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class GridPostType implements Component {

    public function register() {
        add_action('init', [$this, 'post_type']);
        add_filter('the_content', [$this, 'content']);
    }

    public function post_type() {
        register_post_type('mds3_grid_page', [
            'labels' => [
                'name' => __('Grid Pages', 'million-dollar-script'),
                'singular_name' => __('Grid Page', 'million-dollar-script'),
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'thumbnail'],
            'has_archive' => false,
            'rewrite' => ['slug' => 'grids'],
            'show_in_rest' => true,
        ]);

        if ('yes' === get_option('mds3_ensure_grid_pages', 'no')) {
            foreach ((new GridRepository())->all() as $grid) {
                if ('archived' !== $grid->get('status')) {
                    self::ensure_page($grid);
                }
            }
            delete_option('mds3_ensure_grid_pages');
            update_option('mds3_flush_rewrite_rules', 'yes', false);
        }

        if ('yes' === get_option('mds3_flush_rewrite_rules', 'no')) {
            flush_rewrite_rules(false);
            delete_option('mds3_flush_rewrite_rules');
        }
    }

    public function content($content) {
        if ((!is_singular('mds3_grid_page') && !is_singular('page')) || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post_id = absint(get_the_ID());
        if (self::is_extension_legal_page($post_id)) {
            return $content;
        }

        if (has_shortcode((string) $content, 'mds_grid') || has_shortcode((string) $content, 'mds3_page')) {
            return $content;
        }

        $page_type = sanitize_key((string) get_post_meta($post_id, '_mds3_page_type', true));
        if ($page_type && 'grid' !== $page_type) {
            return $content;
        }

        $grid_id = absint(get_post_meta($post_id, '_mds3_grid_id', true));
        if (!$grid_id) {
            return $content;
        }

        return $content . do_shortcode(self::shortcode($grid_id, true));
    }

    public static function ensure_page(Grid $grid) {
        $existing = self::page_id($grid->id());
        if ($existing) {
            if (!absint(get_option('mds3_page_grid_id', 0))) {
                update_option('mds3_page_grid_id', $existing, false);
            }
            return $existing;
        }

        $post_id = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => (string) $grid->get('title', __('Grid', 'million-dollar-script')),
            'post_name' => sanitize_title((string) $grid->get('slug', 'grid-' . $grid->id())),
            'post_content' => self::shortcode($grid->id(), true),
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        update_post_meta($post_id, '_mds3_grid_id', $grid->id());
        update_post_meta($post_id, '_mds3_page_type', 'grid');
        if (!absint(get_option('mds3_page_grid_id', 0))) {
            update_option('mds3_page_grid_id', absint($post_id), false);
        }

        return absint($post_id);
    }

    public static function page_id($grid_id) {
        $query = [
            'post_type' => ['page', 'mds3_grid_page'],
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'fields' => 'ids',
            'posts_per_page' => 20,
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_mds3_grid_id',
                    'value' => absint($grid_id),
                    'compare' => '=',
                ],
                self::extension_legal_page_exclusion(),
            ],
        ];

        $typed = get_posts(array_merge($query, [
            'posts_per_page' => 1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_mds3_grid_id',
                    'value' => absint($grid_id),
                    'compare' => '=',
                ],
                [
                    'key' => '_mds3_page_type',
                    'value' => 'grid',
                    'compare' => '=',
                ],
                self::extension_legal_page_exclusion(),
            ],
        ]));

        if ($typed) {
            return absint($typed[0]);
        }

        $existing = get_posts($query);
        foreach ($existing as $post_id) {
            if (self::is_extension_legal_page($post_id)) {
                continue;
            }

            $content = (string) get_post_field('post_content', absint($post_id));
            if ('mds3_grid_page' === get_post_type($post_id) || has_shortcode($content, 'mds_grid') || false !== strpos($content, 'type="grid"') || false !== strpos($content, "type='grid'")) {
                return absint($post_id);
            }
        }

        return 0;
    }

    public static function page_url($grid_id) {
        $post_id = self::page_id($grid_id);

        return $post_id ? get_permalink($post_id) : '';
    }

    public static function page_edit_url($grid_id) {
        $post_id = self::page_id($grid_id);

        return $post_id ? get_edit_post_link($post_id, '') : '';
    }

    public static function shortcode($grid_id, $read_only = true, $renderer = '') {
        $atts = [
            'id' => absint($grid_id),
            'read_only' => $read_only ? 'true' : 'false',
        ];
        $renderer = sanitize_key((string) $renderer);
        if ($renderer) {
            $atts['renderer'] = $renderer;
        }

        return self::build_shortcode('mds_grid', $atts);
    }

    public static function page_mode($grid_id) {
        $post_id = self::page_id($grid_id);
        if (!$post_id) {
            return '';
        }

        $content = (string) get_post_field('post_content', $post_id);
        if (preg_match('/\[(?:mds_grid|pixel_grid|mds3_page)\b[^\]]*\bread_only\s*=\s*["\']?false["\']?/i', $content)) {
            return 'interactive';
        }

        return 'read_only';
    }

    public static function set_page_mode(Grid $grid, $mode) {
        $post_id = self::ensure_page($grid);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $read_only = 'interactive' !== sanitize_key((string) $mode);
        $content = (string) get_post_field('post_content', absint($post_id));
        $updated = self::replace_grid_shortcode_mode($content, $grid->id(), $read_only);
        if ($updated === $content) {
            $updated = trim($content);
            $updated .= ('' === $updated ? '' : "\n\n") . self::shortcode($grid->id(), $read_only);
        }

        return wp_update_post([
            'ID' => absint($post_id),
            'post_content' => $updated,
        ], true);
    }

    private static function replace_grid_shortcode_mode($content, $grid_id, $read_only) {
        $tags = ['mds_grid', 'pixel_grid', 'mds3_page'];
        $pattern = get_shortcode_regex($tags);
        $replaced = false;

        return preg_replace_callback('/' . $pattern . '/s', static function ($matches) use ($grid_id, $read_only, &$replaced) {
            if ($replaced) {
                return $matches[0];
            }

            $tag = (string) ($matches[2] ?? '');
            $atts = shortcode_parse_atts((string) ($matches[3] ?? ''));
            $atts = is_array($atts) ? $atts : [];

            if ('mds3_page' === $tag) {
                $type = sanitize_key((string) ($atts['type'] ?? 'grid'));
                $shortcode_grid_id = absint($atts['grid_id'] ?? ($atts['id'] ?? 0));
                if ('grid' !== $type || ($shortcode_grid_id && $shortcode_grid_id !== absint($grid_id))) {
                    return $matches[0];
                }
                $atts['type'] = 'grid';
                $atts['grid_id'] = absint($grid_id);
                unset($atts['id']);
            } else {
                $shortcode_grid_id = absint($atts['id'] ?? 0);
                if ($shortcode_grid_id && $shortcode_grid_id !== absint($grid_id)) {
                    return $matches[0];
                }
                $atts['id'] = absint($grid_id);
            }

            $atts['read_only'] = $read_only ? 'true' : 'false';
            $replaced = true;

            return self::build_shortcode($tag, $atts);
        }, (string) $content);
    }

    private static function build_shortcode($tag, array $atts) {
        $parts = [];
        foreach ($atts as $key => $value) {
            if (is_int($key) || is_array($value) || is_object($value)) {
                continue;
            }
            $parts[] = sanitize_key((string) $key) . '="' . esc_attr((string) $value) . '"';
        }

        return '[' . sanitize_key((string) $tag) . ($parts ? ' ' . implode(' ', $parts) : '') . ']';
    }

    public static function is_extension_legal_page($post_id) {
        return '' !== (string) get_post_meta(absint($post_id), '_mds3_extension_legal_document', true);
    }

    private static function extension_legal_page_exclusion() {
        return [
            'key' => '_mds3_extension_legal_document',
            'compare' => 'NOT EXISTS',
        ];
    }
}
