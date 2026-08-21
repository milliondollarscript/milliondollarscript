<?php
/**
 * WP-CLI fixture for native Million Dollar Script blocks.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/blocks-fixture.php
 */

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

function mds3_blocks_fixture_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    throw new RuntimeException('Blocks fixture requires an administrator user.');
}
wp_set_current_user(absint($admin[0]));

$registry = \WP_Block_Type_Registry::get_instance();
foreach (['mds/grid', 'mds/stats', 'mds/page'] as $block_name) {
    mds3_blocks_fixture_assert($registry->is_registered($block_name), $block_name . ' was not registered.');
    $block_type = $registry->get_registered($block_name);
    mds3_blocks_fixture_assert('million-dollar-script' === $block_type->category, $block_name . ' was not registered in the Million Dollar Script category.');
    mds3_blocks_fixture_assert(in_array('MDS', $block_type->keywords, true), $block_name . ' does not include the MDS search keyword.');
    mds3_blocks_fixture_assert(in_array('Million Dollar Script', $block_type->keywords, true), $block_name . ' does not include the Million Dollar Script search keyword.');
}

$grid_repo = new GridRepository();
$grid = null;
$placement_id = 0;

try {
    $grid = $grid_repo->create([
        'title' => 'Blocks Fixture Grid',
        'width' => 1000,
        'height' => 1000,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 1,
        'status' => 'active',
    ]);
    if (is_wp_error($grid)) {
        throw new RuntimeException('Could not create blocks fixture grid: ' . $grid->get_error_message());
    }

    $placement_id = (new PlacementRepository())->create([
        'grid_id' => $grid->id(),
        'attachment_id' => 1,
        'x' => 0,
        'y' => 0,
        'width' => 10,
        'height' => 10,
        'link_url' => 'https://example.com/blocks-fixture',
        'alt_text' => 'Blocks Fixture Sponsor',
        'popup_text' => 'Block render fixture placement',
        'status' => 'active',
    ]);
    if (is_wp_error($placement_id)) {
        throw new RuntimeException('Could not create blocks fixture placement: ' . $placement_id->get_error_message());
    }

    $render = static function ($name, array $attrs) {
        return render_block([
            'blockName' => $name,
            'attrs' => $attrs,
            'innerBlocks' => [],
            'innerHTML' => '',
            'innerContent' => [],
        ]);
    };

    $grid_html = $render('mds/grid', [
        'id' => $grid->id(),
        'readOnly' => true,
        'width' => '100%',
        'height' => '{height}',
        'renderer' => 'classic',
    ]);
    mds3_blocks_fixture_assert(false !== strpos($grid_html, 'mds3-grid-shell'), 'Grid block did not render a grid shell.');
    mds3_blocks_fixture_assert(false !== strpos($grid_html, 'mds3-grid-inline-stats'), 'Grid block did not inherit the grid stats display.');

    $grid_without_stats_html = $render('mds/grid', [
        'id' => $grid->id(),
        'readOnly' => true,
        'width' => '100%',
        'height' => '{height}',
        'renderer' => 'classic',
        'showStats' => 'hide',
    ]);
    mds3_blocks_fixture_assert(false === strpos($grid_without_stats_html, 'mds3-grid-inline-stats'), 'Grid block did not hide stats when requested.');

    $order_html = $render('mds/page', [
        'type' => 'order',
        'id' => $grid->id(),
        'width' => '100%',
        'height' => '{height}',
        'renderer' => 'classic',
        'showStats' => 'hide',
    ]);
    mds3_blocks_fixture_assert(false !== strpos($order_html, 'mds3-grid-shell'), 'Order page block did not render an interactive grid shell.');
    mds3_blocks_fixture_assert(false !== strpos($order_html, 'data-read-only="false"'), 'Order page block was not forced interactive.');
    mds3_blocks_fixture_assert(false === strpos($order_html, 'mds3-grid-inline-stats'), 'Order page block did not pass through the hidden stats setting.');

    $stats_html = $render('mds/page', [
        'type' => 'stats',
        'id' => $grid->id(),
        'unit' => 'blocks',
        'width' => '280px',
        'numberColor' => '#2563eb',
    ]);
    mds3_blocks_fixture_assert(false !== strpos($stats_html, 'mds3-page-panel-stats'), 'Stats page block did not render the stats panel.');

    $list_html = $render('mds/page', [
        'type' => 'list',
        'id' => $grid->id(),
        'listLayout' => 'cards',
        'listColumns' => 'title,url,popup',
        'listSearch' => false,
    ]);
    mds3_blocks_fixture_assert(false !== strpos($list_html, 'mds3-advertiser-layout-cards'), 'Advertiser list page block did not render the cards layout.');
    mds3_blocks_fixture_assert(false !== strpos($list_html, 'Blocks Fixture Sponsor'), 'Advertiser list page block did not render active placements.');
    mds3_blocks_fixture_assert(false === strpos($list_html, 'data-mds3-advertiser-search'), 'Advertiser search rendered even when disabled.');

    $manage_html = $render('mds/page', [
        'type' => 'manage',
    ]);
    mds3_blocks_fixture_assert(false !== strpos($manage_html, 'mds3-order-list-panel') || false !== strpos($manage_html, 'mds3-order-summary-panel'), 'Manage page block did not render a management panel.');

    echo "Blocks fixture passed.\n";
} finally {
    global $wpdb;

    if ($placement_id) {
        $wpdb->delete(DB::table('placements'), ['id' => absint($placement_id)]);
    }
    if ($grid) {
        $grid_repo->delete($grid->id());
    }
}
