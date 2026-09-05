<?php
/**
 * MDS 3.0 cache maintenance admin actions.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Rendering\TileController;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesCacheAdminActions {

    public function clear_cache() {
        check_admin_referer('mds3_clear_cache');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'million-dollar-script'));
        }

        global $wpdb;

        $delete_tile_files = !empty($_POST['mds3_delete_tile_files']);
        $tile_dirs_removed = 0;
        $grids_rotated = 0;
        $repository = new GridRepository();

        // Rotating each grid's cache key (its updated_at feeds the tile cache
        // key) gives every grid new tile URLs, so browsers and proxies stop
        // serving stale tiles and re-download the existing tile files.
        foreach ($repository->all() as $grid) {
            $grid_id = absint($grid->id());
            if (!$grid_id) {
                continue;
            }

            $old_key = TileController::cache_key($grid);

            // Bump updated_at; if it already matches the current second the
            // key would not change, so push one second ahead.
            $now = current_time('mysql', true);
            if (sanitize_text_field((string) $grid->get('updated_at', '')) === $now) {
                $now = gmdate('Y-m-d H:i:s', time() + 1);
            }
            $wpdb->update(DB::table('grids'), ['updated_at' => $now], ['id' => $grid_id]);

            $fresh = $repository->find($grid_id);
            $new_key = $fresh ? TileController::cache_key($fresh) : $old_key;

            // Keep the cached tiles: move them under the new key so the
            // server serves existing files instead of regenerating them.
            // File deletion below removes them when explicitly requested.
            if (!$delete_tile_files && $old_key !== $new_key) {
                TileController::move_tile_cache($grid_id, $old_key, $new_key);
            }
            $grids_rotated++;
        }

        if ($delete_tile_files) {
            $tile_dirs_removed = TileController::clear_tile_cache();
        }

        // Short-lived plugin transients: admin summaries, previews, result
        // notices, and maintenance locks.
        $prefix = $wpdb->esc_like('_transient_mds3_');
        $transients_cleared = (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $prefix . '%',
            '_transient_timeout_' . $prefix . '%'
        ));

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-system-status',
            'mds3_cache_cleared' => 1,
            'mds3_grids_rotated' => absint($grids_rotated),
            'mds3_transients_cleared' => absint($transients_cleared),
            'mds3_tile_dirs_removed' => absint($tile_dirs_removed),
        ], admin_url('admin.php')));
        exit;
    }
}
