<?php
/**
 * Frontend AJAX for grid interactions.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

use MillionDollarScript\V3\Grid\Concerns\BuildsGridAjaxPayloads;
use MillionDollarScript\V3\Grid\Concerns\HandlesGridAjaxRequests;
use MillionDollarScript\V3\Grid\Concerns\HandlesPlacementAjaxSubmission;
use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class GridAjax implements Component {
    use BuildsGridAjaxPayloads;
    use HandlesGridAjaxRequests;
    use HandlesPlacementAjaxSubmission;

    public function register() {
        add_action('wp_ajax_mds3_grid_state', [$this, 'state']);
        add_action('wp_ajax_nopriv_mds3_grid_state', [$this, 'state']);
        add_action('wp_ajax_mds3_reserve_blocks', [$this, 'reserve']);
        add_action('wp_ajax_nopriv_mds3_reserve_blocks', [$this, 'reserve']);
        add_action('wp_ajax_mds3_submit_placement', [$this, 'submit_placement']);
        add_action('wp_ajax_nopriv_mds3_submit_placement', [$this, 'submit_placement']);
        add_action('wp_ajax_mds3_placement_draft_state', [$this, 'placement_draft_state']);
        add_action('wp_ajax_nopriv_mds3_placement_draft_state', [$this, 'placement_draft_state']);
        add_action('wp_ajax_mds3_upload_placement_draft_image', [$this, 'upload_placement_draft_image']);
        add_action('wp_ajax_nopriv_mds3_upload_placement_draft_image', [$this, 'upload_placement_draft_image']);
        add_action('wp_ajax_mds3_remove_placement_draft_image', [$this, 'remove_placement_draft_image']);
        add_action('wp_ajax_nopriv_mds3_remove_placement_draft_image', [$this, 'remove_placement_draft_image']);
        add_action('wp_ajax_mds3_click', [$this, 'click']);
        add_action('wp_ajax_nopriv_mds3_click', [$this, 'click']);
        add_action('million-dollar-script/payment/source/released', [$this, 'cleanup_released_order_draft'], 20, 4);

        // Compatibility endpoint for old shortcode embeds.
        add_action('wp_ajax_mds_get_grid_data', [$this, 'state']);
        add_action('wp_ajax_nopriv_mds_get_grid_data', [$this, 'state']);
    }
}
