<?php
/**
 * WP-CLI fixture for advertiser list shortcode customization.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/advertiser-list-fixture.php
 */

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Pages\PageShortcodes;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

function mds3_advertiser_list_fixture_attachment() {
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        throw new RuntimeException('Could not resolve uploads directory: ' . $uploads['error']);
    }

    $filename = 'mds3-advertiser-list-' . wp_generate_uuid4() . '.png';
    $path = trailingslashit($uploads['path']) . $filename;
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAIAAAD8GO2jAAAAK0lEQVR4nGNk+M+ABzAwMDCqGqmgmXq1UA0YGRkZVQxqYGBgYFS1AwBKYgJBMqx7ygAAAABJRU5ErkJggg==');
    if (!$png || false === file_put_contents($path, $png)) {
        throw new RuntimeException('Could not write advertiser list fixture image.');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'Advertiser List Fixture',
        'post_status' => 'inherit',
    ], $path);

    if (is_wp_error($attachment_id) || !$attachment_id) {
        throw new RuntimeException('Could not create advertiser list fixture attachment.');
    }

    $metadata = wp_generate_attachment_metadata($attachment_id, $path);
    if (!is_array($metadata) || empty($metadata['width']) || empty($metadata['height'])) {
        $metadata = [
            'width' => 32,
            'height' => 32,
            'file' => ltrim(str_replace(trailingslashit($uploads['basedir']), '', $path), '/'),
        ];
    }
    wp_update_attachment_metadata($attachment_id, $metadata);

    return absint($attachment_id);
}

function mds3_advertiser_list_fixture_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    throw new RuntimeException('Advertiser list fixture requires an administrator user.');
}
wp_set_current_user(absint($admin[0]));

(new PageShortcodes())->shortcodes();

$grid_repo = new GridRepository();
$placements = new PlacementRepository();
$grids = [];
$attachment_id = 0;
$placement_ids = [];
$request_backup = $_GET;

try {
    $grid = $grid_repo->create([
        'title' => 'Advertiser List Fixture',
        'width' => 1000,
        'height' => 1000,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 1,
        'status' => 'active',
    ]);
    if (is_wp_error($grid)) {
        throw new RuntimeException('Could not create advertiser list fixture grid: ' . $grid->get_error_message());
    }
    $grids[] = $grid;

    $second_grid = $grid_repo->create([
        'title' => 'Advertiser List Second Grid',
        'width' => 1000,
        'height' => 1000,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 1,
        'status' => 'active',
    ]);
    if (is_wp_error($second_grid)) {
        throw new RuntimeException('Could not create second advertiser list fixture grid: ' . $second_grid->get_error_message());
    }
    $grids[] = $second_grid;

    $attachment_id = mds3_advertiser_list_fixture_attachment();
    $placement_id = $placements->create([
        'grid_id' => $grid->id(),
        'attachment_id' => $attachment_id,
        'x' => 0,
        'y' => 0,
        'width' => 10,
        'height' => 10,
        'fit_mode' => 'cover',
        'link_url' => 'https://example.com/sponsor',
        'alt_text' => 'Fixture Sponsor',
        'popup_text' => '<strong>Priority placement</strong> Shared campaign marker',
        'status' => 'active',
    ]);
    if (is_wp_error($placement_id)) {
        throw new RuntimeException('Could not create advertiser list fixture placement: ' . $placement_id->get_error_message());
    }
    $placement_ids[] = absint($placement_id);

    $second_placement_id = $placements->create([
        'grid_id' => $second_grid->id(),
        'attachment_id' => $attachment_id,
        'x' => 0,
        'y' => 0,
        'width' => 10,
        'height' => 10,
        'fit_mode' => 'cover',
        'link_url' => 'https://second.example.com/sponsor',
        'alt_text' => 'Second Fixture Sponsor',
        'popup_text' => 'Shared campaign marker for the second grid',
        'status' => 'active',
    ]);
    if (is_wp_error($second_placement_id)) {
        throw new RuntimeException('Could not create second advertiser list fixture placement: ' . $second_placement_id->get_error_message());
    }
    $placement_ids[] = absint($second_placement_id);

    $pending_placement_id = $placements->create([
        'grid_id' => $second_grid->id(),
        'attachment_id' => $attachment_id,
        'x' => 10,
        'y' => 0,
        'width' => 10,
        'height' => 10,
        'fit_mode' => 'cover',
        'link_url' => 'https://pending.example.com',
        'alt_text' => 'Pending Private Fixture Sponsor',
        'popup_text' => 'Not publicly visible',
        'status' => 'pending',
    ]);
    if (is_wp_error($pending_placement_id)) {
        throw new RuntimeException('Could not create pending advertiser list fixture placement: ' . $pending_placement_id->get_error_message());
    }
    $placement_ids[] = absint($pending_placement_id);

    add_filter('million-dollar-script/advertiser/list/columns', static function ($columns) {
        $columns['tier'] = 'Tier';
        return $columns;
    });
    add_filter('million-dollar-script/advertiser/list/item/values', static function ($values) {
        $values['tier'] = '<script>alert(1)</script>Gold';
        return $values;
    });
    add_filter('million-dollar-script/advertiser/list/search/placement/ids', static function ($ids, $search) use (&$placement_ids) {
        return 'gold' === strtolower(trim((string) $search)) ? $placement_ids : $ids;
    }, 10, 2);

    $accordion = do_shortcode('[mds3_page type="list" grid_id="' . $grid->id() . '" list_layout="accordion" list_columns="title,url,popup,tier" list_search="yes" list_accent_color="#2563eb" list_background_color="javascript:bad"]');
    mds3_advertiser_list_fixture_assert(false !== strpos($accordion, 'mds3-advertiser-layout-accordion'), 'Accordion list layout did not render.');
    mds3_advertiser_list_fixture_assert(false !== strpos($accordion, 'name="mds3_advertiser_search"'), 'Advertiser list search did not render.');
    mds3_advertiser_list_fixture_assert(false !== strpos($accordion, 'Fixture Sponsor'), 'Advertiser title did not render.');
    mds3_advertiser_list_fixture_assert(false === strpos($accordion, 'Second Fixture Sponsor'), 'Grid-scoped advertiser list leaked another grid.');
    mds3_advertiser_list_fixture_assert(false !== strpos($accordion, 'example.com'), 'Advertiser URL did not render.');
    mds3_advertiser_list_fixture_assert(false !== strpos($accordion, 'Priority placement'), 'Advertiser popup text did not render.');
    mds3_advertiser_list_fixture_assert(false !== strpos($accordion, 'Gold'), 'Filtered custom advertiser column did not render.');
    mds3_advertiser_list_fixture_assert(false === strpos($accordion, '<script'), 'Filtered custom advertiser value was not sanitized.');
    mds3_advertiser_list_fixture_assert(false === strpos($accordion, 'javascript:bad'), 'Unsafe list background color was rendered.');

    $cards = do_shortcode('[mds3_page type="list" grid_id="' . $grid->id() . '" list_layout="cards" list_columns="image,title,url" list_search="no"]');
    mds3_advertiser_list_fixture_assert(false !== strpos($cards, 'mds3-advertiser-list--cards'), 'Cards list layout did not render.');
    mds3_advertiser_list_fixture_assert(false === strpos($cards, 'data-mds3-advertiser-search'), 'Disabled advertiser search still rendered.');

    $fallback = do_shortcode('[mds3_page type="list" grid_id="' . $grid->id() . '" list_layout="bad" list_columns="bad"]');
    mds3_advertiser_list_fixture_assert(false !== strpos($fallback, 'mds3-advertiser-layout-list'), 'Invalid list layout did not fall back to list.');
    mds3_advertiser_list_fixture_assert(false !== strpos($fallback, 'mds3-advertiser-thumb'), 'Invalid list columns did not fall back to default columns.');

    $_GET = ['mds3_advertiser_search' => 'Fixture Sponsor'];
    $all_grids = do_shortcode('[mds3_page type="list" list_layout="list" list_columns="title,url"]');
    mds3_advertiser_list_fixture_assert(false === strpos($all_grids, 'mds3-grid-picker-panel'), 'No-grid advertiser list still rendered a grid picker.');
    mds3_advertiser_list_fixture_assert(false !== strpos($all_grids, 'Advertiser List Fixture'), 'All-grid list did not label the first grid.');
    mds3_advertiser_list_fixture_assert(false !== strpos($all_grids, 'Advertiser List Second Grid'), 'All-grid list did not label the second grid.');
    mds3_advertiser_list_fixture_assert(false !== strpos($all_grids, 'Fixture Sponsor') && false !== strpos($all_grids, 'Second Fixture Sponsor'), 'All-grid list did not include active advertisers from both grids.');
    mds3_advertiser_list_fixture_assert(false === strpos($all_grids, 'Pending Private Fixture Sponsor'), 'All-grid list exposed a non-active placement.');

    $per_page_filter = static function () {
        return 1;
    };
    add_filter('million-dollar-script/advertiser/list/per/page', $per_page_filter);
    $_GET = ['mds3_advertiser_search' => 'Shared campaign marker'];
    $paginated = do_shortcode('[mds3_page type="list" list_layout="list" list_columns="title,url"]');
    mds3_advertiser_list_fixture_assert(1 === substr_count($paginated, 'data-mds3-advertiser-item'), 'Advertiser list did not enforce bounded server pagination.');
    mds3_advertiser_list_fixture_assert(false !== strpos($paginated, 'mds3-advertiser-pagination'), 'Advertiser list pagination controls did not render.');
    remove_filter('million-dollar-script/advertiser/list/per/page', $per_page_filter);

    $_GET = ['mds3_advertiser_search' => 'Second Fixture Sponsor'];
    $searched = do_shortcode('[mds3_page type="list" list_columns="title,url"]');
    mds3_advertiser_list_fixture_assert(false !== strpos($searched, 'Second Fixture Sponsor') && false === strpos($searched, '>Fixture Sponsor<'), 'All-grid advertiser search did not filter public placement fields.');

    $_GET = ['mds3_advertiser_search' => 'Gold'];
    $extension_searched = do_shortcode('[mds3_page type="list" list_columns="title,tier"]');
    mds3_advertiser_list_fixture_assert(false !== strpos($extension_searched, 'Gold'), 'Extension-owned advertiser search IDs were not included.');

    echo "Advertiser list fixture passed.\n";
} finally {
    global $wpdb;

    $_GET = $request_backup;
    foreach ($placement_ids as $fixture_placement_id) {
        $wpdb->delete(DB::table('placements'), ['id' => absint($fixture_placement_id)]);
    }
    foreach (array_reverse($grids) as $fixture_grid) {
        $grid_repo->delete($fixture_grid->id());
    }
    if ($attachment_id) {
        wp_delete_attachment($attachment_id, true);
    }
}
