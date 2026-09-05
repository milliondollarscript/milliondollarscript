<?php
/**
 * Synchronize placement records with lightweight public WordPress pages.
 *
 * @package MillionDollarScript\V3\Media
 */

namespace MillionDollarScript\V3\Media;

use MillionDollarScript\V3\Migration\MigrationMap;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use prepared statements.

final class AdvertiserPageManager {

    public const POST_TYPE = 'mds_advertiser';

    public function synchronize($placement_id, array $args = []) {
        global $wpdb;

        $placement = $this->public_source(absint($placement_id));
        if (!$placement) {
            return new \WP_Error('million_dollar_script_advertiser_missing', __('The placement could not be found.', 'million-dollar-script'));
        }

        $post_id = absint($placement['public_post_id'] ?? 0);
        if (!$post_id || self::POST_TYPE !== get_post_type($post_id)) {
            $post_id = $this->mapped_post_id(absint($placement['id']));
        }

        $legacy_post = !empty($args['legacy_post_id']) ? get_post(absint($args['legacy_post_id'])) : null;
        if (!$post_id && $legacy_post && 'mds-pixel' === $legacy_post->post_type) {
            $mapped = (new MigrationMap())->get(
                (string) ($args['source_prefix'] ?? $wpdb->prefix),
                'mds-pixel',
                (string) $legacy_post->ID,
                'advertiser_page'
            );
            $mapped_id = absint($mapped['mds3_id'] ?? 0);
            if (self::POST_TYPE === get_post_type($mapped_id)) {
                $mapped_placement_id = absint(get_post_meta($mapped_id, '_million_dollar_script_placement_id', true));
                $mapped_placement = $mapped_placement_id ? $this->public_source($mapped_placement_id) : null;
                if ($mapped_placement_id && $mapped_placement_id !== absint($placement['id']) && $mapped_placement) {
                    // Renewals and historical orders may share one MDS2 ad
                    // post. Its exact legacy URL remains with the first
                    // surviving placement instead of being reassigned.
                    $legacy_post = null;
                } else {
                    $post_id = $mapped_id;
                }
            }
        }
        if (!$post_id && !$legacy_post && (!AdvertiserPageUrls::enabled() || !$this->is_public_source($placement))) {
            return 0;
        }
        $title = $this->title($placement, $legacy_post);
        if ($post_id) {
            // A settings change must never rewrite a live URL as a side effect
            // of an unrelated placement save. Slugs move only through the
            // explicit, previewed migration action.
            $slug = sanitize_title((string) get_post_field('post_name', $post_id));
        } else {
            $slug = $legacy_post && 'mds-pixel' === $legacy_post->post_type
                ? sanitize_title((string) $legacy_post->post_name)
                : AdvertiserPageUrls::build_slug($placement, $placement);
        }
        $post_status = $this->is_public_source($placement) && AdvertiserPageUrls::enabled() ? 'publish' : 'draft';
        $post_data = [
            'post_type' => self::POST_TYPE,
            'post_status' => $post_status,
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => '',
            'post_excerpt' => '',
            'post_author' => 0,
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ];

        if ($post_id) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        if (is_wp_error($result)) {
            return $result;
        }

        $post_id = absint($result);
        // Managed public pages never carry the current administrator as their
        // author; themes must not be able to expose an account identity.
        $wpdb->update($wpdb->posts, ['post_author' => 0], ['ID' => $post_id]);
        clean_post_cache($post_id);
        $linked = $wpdb->update(DB::table('placements'), ['public_post_id' => $post_id], ['id' => absint($placement['id'])]);
        if (false === $linked) {
            return new \WP_Error('million_dollar_script_advertiser_link_failed', __('The advertiser page could not be linked to its placement.', 'million-dollar-script'));
        }
        update_post_meta($post_id, '_million_dollar_script_placement_id', absint($placement['id']));
        update_post_meta($post_id, '_million_dollar_script_managed', 'yes');

        if ($legacy_post && 'mds-pixel' === $legacy_post->post_type) {
            foreach (get_post_meta($legacy_post->ID, '_wp_old_slug', false) as $old_slug) {
                add_post_meta($post_id, '_wp_old_slug', sanitize_title((string) $old_slug), true);
            }
            (new MigrationMap())->remember(
                (string) ($args['source_prefix'] ?? $wpdb->prefix),
                'mds-pixel',
                (string) $legacy_post->ID,
                'advertiser_page',
                $post_id,
                ['placement_id' => absint($placement['id']), 'legacy_slug' => sanitize_title((string) $legacy_post->post_name)]
            );
        }

        return $post_id;
    }

    public function synchronize_batch($limit = 100, $offset_id = 0) {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . DB::ident(DB::table('placements')) . ' WHERE id > %d ORDER BY id ASC LIMIT %d',
            absint($offset_id),
            max(1, min(500, absint($limit)))
        ));
        $last_id = 0;
        foreach (is_array($ids) ? $ids : [] as $id) {
            $last_id = absint($id);
            $this->synchronize($last_id);
        }

        return ['processed' => count($ids), 'last_id' => $last_id, 'complete' => count($ids) < $limit];
    }

    public function public_post_id($placement_id) {
        $placement = $this->public_source(absint($placement_id));
        if (!$placement || !$this->is_public_source($placement) || !AdvertiserPageUrls::enabled()) {
            return 0;
        }

        $post_id = absint($placement['public_post_id'] ?? 0);

        return $post_id && 'publish' === get_post_status($post_id) && self::POST_TYPE === get_post_type($post_id) ? $post_id : 0;
    }

    public function public_url($placement_id) {
        $post_id = $this->public_post_id($placement_id);

        return $post_id ? (string) get_permalink($post_id) : '';
    }

    /**
     * Public URLs of legacy MDS2 pixel pages still mapped to these placements.
     *
     * Migrated sites keep their MDS2 advertiser posts live while MDS 3.0's own
     * advertiser pages are disabled, so popups can still offer the full page.
     *
     * @param array $placement_ids MDS 3.0 placement IDs.
     * @return array<int,string> Placement ID to public legacy URL.
     */
    public function legacy_public_urls(array $placement_ids) {
        global $wpdb;

        $placement_ids = array_values(array_unique(array_filter(array_map('absint', $placement_ids))));
        if (!$placement_ids || !post_type_exists('mds-pixel')) {
            return [];
        }

        $legacy_by_placement = [];
        $map_rows = $wpdb->get_results($wpdb->prepare(
            'SELECT mds3_id, metadata FROM ' . DB::ident(DB::table('migration_map')) .
            ' WHERE entity_type = %s AND mds3_entity_type = %s AND mds3_id IN (' . str_repeat('%d,', count($placement_ids) - 1) . '%d)',
            array_merge(['placement', 'placement'], $placement_ids)
        ), ARRAY_A);
        foreach (is_array($map_rows) ? $map_rows : [] as $row) {
            $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
            $legacy_id = absint(is_array($metadata) ? ($metadata['legacy_ad_id'] ?? 0) : 0);
            $placement_id = absint($row['mds3_id']);
            if ($legacy_id && $placement_id && empty($legacy_by_placement[$placement_id])) {
                $legacy_by_placement[$placement_id] = $legacy_id;
            }
        }
        if (!$legacy_by_placement) {
            return [];
        }

        $legacy_ids = array_values(array_unique(array_values($legacy_by_placement)));
        $posts = get_posts([
            'post_type' => 'mds-pixel',
            'post_status' => 'publish',
            'post__in' => $legacy_ids,
            'orderby' => 'ID',
            'order' => 'ASC',
            'posts_per_page' => count($legacy_ids),
            'no_found_rows' => true,
        ]);

        $urls = [];
        foreach ($posts as $post) {
            $permalink = get_permalink($post);
            if (!$permalink) {
                continue;
            }
            foreach ($legacy_by_placement as $placement_id => $legacy_id) {
                if ($legacy_id === (int) $post->ID && empty($urls[$placement_id])) {
                    $urls[$placement_id] = $permalink;
                }
            }
        }

        return $urls;
    }

    public function preview_slug_migration($limit = 5000) {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT p.*, g.slug grid_slug, g.title grid_title FROM ' . DB::ident(DB::table('placements')) . ' p '
            . 'INNER JOIN ' . DB::ident(DB::table('grids')) . ' g ON g.id = p.grid_id '
            . 'WHERE p.public_post_id IS NOT NULL ORDER BY p.id ASC LIMIT %d',
            max(1, min(5000, absint($limit)))
        ), ARRAY_A);
        $changed = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $post = get_post(absint($row['public_post_id'] ?? 0));
            if ($post && AdvertiserPageUrls::build_slug($row, $row) !== (string) $post->post_name) {
                ++$changed;
            }
        }

        return ['checked' => count($rows), 'changed' => $changed, 'truncated' => count($rows) >= $limit];
    }

    public function migrate_slugs($limit = 250, $after_id = 0) {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT p.*, g.slug grid_slug, g.title grid_title FROM ' . DB::ident(DB::table('placements')) . ' p '
            . 'INNER JOIN ' . DB::ident(DB::table('grids')) . ' g ON g.id = p.grid_id '
            . 'WHERE p.public_post_id IS NOT NULL AND p.id > %d ORDER BY p.id ASC LIMIT %d',
            absint($after_id),
            max(1, min(500, absint($limit)))
        ), ARRAY_A);
        $changed = 0;
        $last_id = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $last_id = absint($row['id'] ?? 0);
            $post = get_post(absint($row['public_post_id'] ?? 0));
            if (!$post) {
                continue;
            }
            $slug = AdvertiserPageUrls::build_slug($row, $row);
            if ($slug === (string) $post->post_name) {
                continue;
            }
            add_post_meta($post->ID, '_wp_old_slug', sanitize_title((string) $post->post_name), true);
            wp_update_post(['ID' => $post->ID, 'post_name' => $slug]);
            ++$changed;
        }

        return ['processed' => count($rows), 'changed' => $changed, 'last_id' => $last_id, 'complete' => count($rows) < $limit];
    }

    public function public_source($placement_id) {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT p.*, g.title grid_title, g.slug grid_slug, g.status grid_status, o.status order_status '
            . 'FROM ' . DB::ident(DB::table('placements')) . ' p '
            . 'INNER JOIN ' . DB::ident(DB::table('grids')) . ' g ON g.id = p.grid_id '
            . 'LEFT JOIN ' . DB::ident(DB::table('orders')) . ' o ON o.id = p.order_id '
            . 'WHERE p.id = %d LIMIT 1',
            absint($placement_id)
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function is_public_source(array $placement) {
        if ('active' !== sanitize_key((string) ($placement['status'] ?? '')) || 'active' !== sanitize_key((string) ($placement['grid_status'] ?? ''))) {
            return false;
        }

        $order_status = sanitize_key((string) ($placement['order_status'] ?? ''));

        return empty($placement['order_id']) || in_array($order_status, ['paid', 'renew_paid'], true);
    }

    private function mapped_post_id($placement_id) {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'meta_key' => '_million_dollar_script_placement_id',
            'meta_value' => absint($placement_id),
        ]);

        return $posts ? absint($posts[0]) : 0;
    }

    private function title(array $placement, $legacy_post = null) {
        $resolved = AdvertiserPageTitle::resolve($placement, $legacy_post);

        return (string) $resolved['title'];
    }
}
