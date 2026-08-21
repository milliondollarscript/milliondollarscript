<?php
/**
 * WP-CLI grid import/export fixture for Million Dollar Script.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/grid-transfer-fixture.php
 */

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Grid\GridTransfer;
use MillionDollarScript\V3\Grid\PackageRepository;
use MillionDollarScript\V3\Grid\PriceRuleRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    throw new RuntimeException('Grid transfer fixture requires an administrator user.');
}
wp_set_current_user(absint($admin[0]));

$assert_same = static function ($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
};

$cleanup_grid = static function ($grid_id) {
    global $wpdb;

    $grid_id = absint($grid_id);
    if (!$grid_id) {
        return;
    }

    foreach (['blocks', 'placements', 'order_items', 'packages', 'price_rules', 'render_jobs'] as $table) {
        if (DB::table_exists(DB::table($table))) {
            $wpdb->delete(DB::table($table), ['grid_id' => $grid_id]);
        }
    }
    (new GridRepository())->delete($grid_id);
};

$source_grid = null;
$second_grid = null;
$imported_grid_ids = [];

try {
    $source_grid = (new GridRepository())->create([
        'title' => 'Grid Transfer Fixture',
        'slug' => 'grid-transfer-fixture',
        'description' => '<p>Transfer fixture</p>',
        'width' => 100,
        'height' => 100,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 2,
        'currency' => 'CAD',
        'renderer_mode' => 'classic',
        'status' => 'active',
    ]);
    if (is_wp_error($source_grid)) {
        throw new RuntimeException('Could not create transfer fixture grid: ' . $source_grid->get_error_message());
    }

    (new PackageRepository())->save([
        'grid_id' => $source_grid->id(),
        'title' => 'Fixture Package',
        'description' => 'Package description',
        'duration_days' => 30,
        'price' => 25,
        'currency' => 'CAD',
        'is_default' => 1,
        'status' => 'active',
        'metadata' => ['source' => 'fixture'],
    ]);

    (new PriceRuleRepository())->save([
        'grid_id' => $source_grid->id(),
        'row_from' => 1,
        'row_to' => 3,
        'col_from' => 2,
        'col_to' => 4,
        'price' => 4.5,
        'currency' => 'CAD',
        'color' => '#2563eb',
        'status' => 'active',
        'metadata' => ['source' => 'fixture'],
    ]);

    $region = (new BlockRepository())->set_region_status($source_grid, [
        'row_from' => 6,
        'row_to' => 7,
        'col_from' => 6,
        'col_to' => 7,
    ], 'unavailable', [
        'note' => 'Fixture unavailable region',
    ]);
    if (is_wp_error($region)) {
        throw new RuntimeException('Could not create transfer fixture availability region: ' . $region->get_error_message());
    }

    global $wpdb;
    $wpdb->insert(DB::table('blocks'), [
        'grid_id' => $source_grid->id(),
        'x' => 50,
        'y' => 50,
        'width' => 10,
        'height' => 10,
        'status' => 'unavailable',
        'metadata' => wp_json_encode(['note' => 'single unavailable block']),
        'created_at' => current_time('mysql', true),
        'updated_at' => current_time('mysql', true),
    ]);

    $source_grid = (new GridRepository())->find($source_grid->id());
    if (!$source_grid) {
        throw new RuntimeException('Transfer fixture source grid could not be reloaded.');
    }

    $second_grid = (new GridRepository())->create([
        'title' => 'Grid Transfer Fixture Secondary',
        'slug' => 'grid-transfer-fixture-secondary',
        'width' => 50,
        'height' => 50,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 1,
        'currency' => 'USD',
        'status' => 'paused',
    ]);
    if (is_wp_error($second_grid)) {
        throw new RuntimeException('Could not create secondary transfer fixture grid: ' . $second_grid->get_error_message());
    }

    $transfer = new GridTransfer();
    $payload = $transfer->export_payload([$source_grid->id(), $second_grid->id()]);
    if (is_wp_error($payload)) {
        throw new RuntimeException('Grid export failed: ' . $payload->get_error_message());
    }

    $assert_same('million-dollar-script', $payload['package'] ?? '', 'Grid export package marker was incorrect.');
    $assert_same('grid-transfer', $payload['type'] ?? '', 'Grid export type marker was incorrect.');
    $assert_same(2, count($payload['grids'] ?? []), 'Grid export should contain two grids.');
    $assert_same(1, absint($payload['grids'][0]['summary']['packages'] ?? 0), 'Grid export package summary was incorrect.');
    $assert_same(1, absint($payload['grids'][0]['summary']['price_rules'] ?? 0), 'Grid export price-rule summary was incorrect.');
    $assert_same(1, absint($payload['grids'][0]['summary']['unavailable_blocks'] ?? 0), 'Grid export unavailable-block summary was incorrect.');
    $assert_same(true, !empty($payload['grids'][0]['grid']['settings']['unavailable_regions']), 'Grid export should keep stored unavailable regions in settings.');

    $invalid = $transfer->validate_payload(['package' => 'not-million-dollar-script']);
    $assert_same(true, is_wp_error($invalid), 'Grid transfer validation should reject non-grid exports.');

    $result = $transfer->import_payload($payload);
    if (is_wp_error($result)) {
        throw new RuntimeException('Grid import failed: ' . $result->get_error_message());
    }
    $assert_same(2, count($result['created'] ?? []), 'Grid import should create two grids.');
    $imported_grid_ids = array_map(static fn($row) => absint($row['id'] ?? 0), $result['created']);
    $imported = (new GridRepository())->find($imported_grid_ids[0]);
    if (!$imported) {
        throw new RuntimeException('Imported grid could not be loaded.');
    }

    $assert_same('Grid Transfer Fixture', (string) $imported->get('title'), 'Imported grid title was incorrect.');
    $assert_same(100, absint($imported->get('width')), 'Imported grid width was incorrect.');
    $assert_same('CAD', (string) $imported->get('currency'), 'Imported grid currency was incorrect.');
    $assert_same('classic', (string) ($imported->settings()['renderer_mode'] ?? ''), 'Imported grid renderer setting was incorrect.');
    $assert_same(true, !empty($imported->settings()['unavailable_regions']), 'Imported grid availability regions were missing.');
    $assert_same(1, count((new PackageRepository())->for_grid($imported->id())), 'Imported grid package count was incorrect.');
    $assert_same(1, count((new PriceRuleRepository())->for_grid($imported->id())), 'Imported grid price-rule count was incorrect.');
    $assert_same(1, count((new BlockRepository())->for_grid($imported->id(), ['unavailable'])), 'Imported grid unavailable block count was incorrect.');
    $imported_second = (new GridRepository())->find($imported_grid_ids[1]);
    if (!$imported_second) {
        throw new RuntimeException('Second imported grid could not be loaded.');
    }
    $assert_same('Grid Transfer Fixture Secondary', (string) $imported_second->get('title'), 'Second imported grid title was incorrect.');
    $assert_same('paused', (string) $imported_second->get('status'), 'Second imported grid status was incorrect.');
} finally {
    foreach ($imported_grid_ids as $grid_id) {
        $cleanup_grid($grid_id);
    }
    if ($source_grid && !is_wp_error($source_grid)) {
        $cleanup_grid($source_grid->id());
    }
    if ($second_grid && !is_wp_error($second_grid)) {
        $cleanup_grid($second_grid->id());
    }
}

WP_CLI::success('Grid transfer fixture passed.');
