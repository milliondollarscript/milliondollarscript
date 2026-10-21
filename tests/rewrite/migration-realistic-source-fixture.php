<?php
/**
 * WP-CLI fixture: import a realistic Million Dollar Script 2 site.
 *
 * Unlike migration-fixture.php, which builds the narrow cases it needs, this run
 * uses the shared MDS 2 dataset in tests/rewrite/support/mds2-source.php - the
 * real MDS 2 table definitions and a site shaped like the ones the alpha testers
 * actually run (several order and block statuses, images reachable through each
 * legacy media path, ad records with no image, an order with no ad record at all,
 * and link values that are a bare domain, prose, or longer than the target
 * column). Its purpose is to make migration bugs appear locally instead of only
 * on a client site (Vikunja #30, alpha.7 epic).
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-realistic-source-fixture.php
 */

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

require_once __DIR__ . '/support/mds2-source.php';

$source_prefix = $wpdb->prefix . 'mdsrealistic_';
$charset = $wpdb->get_charset_collate();

// Snapshot only what this run could touch, so cleanup removes what it created and
// never a customer page or attachment.
$baseline_page_ids = array_map('intval', (array) $wpdb->get_col('SELECT ID FROM ' . DB::ident($wpdb->posts) . " WHERE post_type = 'page'"));
$baseline_page_options = [];
foreach (array_keys(PageRepository::standard_labels()) as $baseline_type) {
    $baseline_page_options[$baseline_type] = absint(get_option('mds3_page_' . $baseline_type . '_id', 0));
}
// The run writes settings as it imports, so the site's own settings are put back
// afterwards; and the old plugin's answer about confirming orders is planted below
// and removed again.
$baseline_settings = get_option('mds3_settings', []);
$baseline_settings = is_array($baseline_settings) ? $baseline_settings : false;
$baseline_confirm_option = get_option('_milliondollarscript_confirm-orders');
// The old plugin's answer about manual payments, and one person it let order without paying.
// Both are planted below and removed again.
$baseline_auto_approve = get_option('_milliondollarscript_auto-approve');
$legacy_privileged_user = 0;
$baseline_attachments = [];
foreach (['milliondollarscript/images/mds2-fixture-harbour-lights.png', 'milliondollarscript/images/ridgeline-cycles.png'] as $baseline_relative) {
    $baseline_attachments[] = absint($wpdb->get_var($wpdb->prepare(
        'SELECT post_id FROM ' . DB::ident($wpdb->postmeta) . " WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        '_wp_attached_file',
        $baseline_relative
    )));
}
$baseline_attachments = array_values(array_filter($baseline_attachments));

/**
 * Target row for one legacy entity, found through the migration map. This keeps
 * every assertion about *this* source dataset rather than about whatever else the
 * development database happens to contain.
 */
$mapped = static function ($entity_type, $legacy_id, $target_type, $target_table, $where = 'id') use ($wpdb, $source_prefix) {
    $target_id = absint($wpdb->get_var($wpdb->prepare(
        'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map'))
            . ' WHERE source_prefix = %s AND entity_type = %s AND legacy_id = %s AND mds3_entity_type = %s LIMIT 1',
        $source_prefix,
        $entity_type,
        (string) $legacy_id,
        $target_type
    )));
    if (!$target_id) {
        return null;
    }

    return $wpdb->get_row('SELECT * FROM ' . DB::ident($target_table) . " WHERE {$where} = " . $target_id, ARRAY_A);
};

$any_reason_matches = static function (array $reasons, array $needles) {
    foreach ($reasons as $reason) {
        foreach ($needles as $needle) {
            if (false !== stripos((string) $reason, $needle)) {
                return true;
            }
        }
    }

    return false;
};

$map_rows = static function ($entity_type = '', $legacy_id = '') use ($wpdb, $source_prefix) {
    $sql = 'SELECT * FROM ' . DB::ident(DB::table('migration_map')) . ' WHERE source_prefix = %s';
    $args = [$source_prefix];
    if ($entity_type) {
        $sql .= ' AND entity_type = %s';
        $args[] = $entity_type;
    }
    if ('' !== $legacy_id) {
        $sql .= ' AND legacy_id = %s';
        $args[] = (string) $legacy_id;
    }
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

    return is_array($rows) ? $rows : [];
};

// Moved aside for the run and put back afterwards; empty when there was nothing to move.
$paused_media = '';

try {
    // A previous run of this fixture leaves its own migration state behind, and a
    // migration reads that state. Reset only what this source prefix owns - the
    // map rows carry its entity ids, so nothing outside this dataset is touched.
    if (DB::table_exists(DB::table('migration_map'))) {
        $previous_grid_ids = $wpdb->get_col($wpdb->prepare(
            'SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map')) . " WHERE source_prefix = %s AND entity_type = 'banner' AND mds3_entity_type = 'grid'",
            $source_prefix
        ));
        foreach ($previous_grid_ids as $previous_grid_id) {
            $previous_grid_id = absint($previous_grid_id);
            if (!$previous_grid_id) {
                continue;
            }
            $previous_order_ids = $wpdb->get_col($wpdb->prepare(
                'SELECT DISTINCT order_id FROM ' . DB::ident(DB::table('order_items')) . ' WHERE grid_id = %d',
                $previous_grid_id
            ));
            foreach ($previous_order_ids as $previous_order_id) {
                (new BlockRepository())->release_by_order(absint($previous_order_id));
                foreach (['order_items', 'placements'] as $fixture_table) {
                    if (DB::table_exists(DB::table($fixture_table))) {
                        $wpdb->delete(DB::table($fixture_table), ['order_id' => absint($previous_order_id)]);
                    }
                }
                $wpdb->delete(DB::table('orders'), ['id' => absint($previous_order_id)]);
            }
            foreach (['placements', 'blocks', 'packages', 'price_rules'] as $fixture_table) {
                if (DB::table_exists(DB::table($fixture_table))) {
                    $wpdb->delete(DB::table($fixture_table), ['grid_id' => $previous_grid_id]);
                }
            }
        }
        $wpdb->delete(DB::table('migration_map'), ['source_prefix' => $source_prefix]);
    }
    foreach (array_keys(PageRepository::standard_labels()) as $option_type) {
        $option_id = absint(get_option('mds3_page_' . $option_type . '_id', 0));
        if ($option_id && !in_array($option_id, $baseline_page_ids, true)) {
            if (DB::table_exists(DB::table('pages'))) {
                $wpdb->delete(DB::table('pages'), ['post_id' => $option_id]);
            }
            wp_delete_post($option_id, true);
            delete_option('mds3_page_' . $option_type . '_id');
        }
    }
    mds2_source_delete_advertisers($source_prefix);

    mds2_source_create_schema($source_prefix);
    $seed = mds2_source_seed($source_prefix);
    $seed['created_attachments'] = array_values(array_diff($seed['created_attachments'], $baseline_attachments));

    // A migration recovers an order's artwork by looking for its file name in the
    // uploads directory, and this workspace serves more than one local site from
    // one uploads directory. Files another site planted would decide what this
    // dataset can recover - an order with no image of its own picked up a leftover
    // tmp_5_*.png here - so the second legacy image directory is set aside for the
    // run and put back afterwards. This dataset plants its files under
    // milliondollarscript/images, which it owns.
    $uploads_basedir = (string) (wp_upload_dir()['basedir'] ?? '');
    if ($uploads_basedir !== '' && is_dir($uploads_basedir . '/mds-secure/images')) {
        rename($uploads_basedir . '/mds-secure/images', $uploads_basedir . '/mds-secure/images.mdspause');
        $paused_media = $uploads_basedir . '/mds-secure/images.mdspause';
    }

    // A Million Dollar Script 2 site that switched the confirmation step off. Most
    // customer dumps have no such row, and an absent row means the step was on;
    // either way the migrated site keeps the flow its customers already knew
    // (llm-task 2bc92787).
    update_option('_milliondollarscript_confirm-orders', 'no', false);
    // The same site took payment outside the checkout - a bank transfer that arrived days later,
    // with the ad already up. The old plugin kept that choice under the same name MDS3 uses, and
    // a migrated site that silently started reviewing every order would be a change nobody asked
    // for. Starting this run from the shipped default keeps the assertion about the migration,
    // not about whatever an earlier test left in the settings.
    update_option('_milliondollarscript_auto-approve', 'yes', false);
    // And one person the old site allowed to order without paying. The flag was a word - "privileged" -
    // which explains nothing, so what matters is that the behaviour follows the person across.
    $legacy_privileged_user = wp_insert_user([
        'user_login' => 'mds3_legacy_invoiced_person',
        'user_email' => 'legacy-invoiced@example.test',
        'role' => 'customer',
    ]);
    if (is_wp_error($legacy_privileged_user)) {
        throw new RuntimeException('Could not create the person the old site let order without paying.');
    }
    update_user_meta(absint($legacy_privileged_user), '_milliondollarscript_privileged', '1', false);
    // Start from what this site looks like when nobody has chosen anything here, so the
    // run shows what a migration carries rather than what an earlier test left behind.
    $start_settings = (array) get_option('mds3_settings', []);
    $start_settings['confirm-orders'] = SettingsSchema::defaults()['confirm-orders'];
    $start_settings['auto-approve'] = SettingsSchema::defaults()['auto-approve'];
    update_option('mds3_settings', $start_settings, false);

    $result = (new Importer())->import($source_prefix);

    $imported_settings = get_option('mds3_settings', []);
    $imported_settings = is_array($imported_settings) ? $imported_settings : [];
    if ('no' !== ($imported_settings['confirm-orders'] ?? '')) {
        throw new RuntimeException(
            'the migrated site did not keep the answer the old site gave about confirming orders: '
            . var_export($imported_settings['confirm-orders'] ?? null, true)
        );
    }

    // The report says so too, in words a site owner can read, and says where the
    // answer came from - the two plugins default this option differently.
    $reported_settings = array_values(array_filter(
        (array) ($result['verification']['settings_outcomes'] ?? []),
        static function ($outcome) {
            return is_array($outcome) && 'confirm-orders' === ($outcome['key'] ?? '');
        }
    ));
    if (!$reported_settings) {
        throw new RuntimeException('the migration report said nothing about how this site handles orders');
    }
    $reported_setting = $reported_settings[0];
    if ('no' !== ($reported_setting['value'] ?? '') || 'mds2' !== ($reported_setting['source'] ?? '') || '' === trim((string) ($reported_setting['note'] ?? ''))) {
        throw new RuntimeException('the migration report mis-described where the order confirmation choice came from: ' . wp_json_encode($reported_setting));
    }

    // Manual payments are the other half of the same question: what happens to an order whose
    // money arrives later. The old site's answer has to be the new site's answer, and the report
    // has to say so, because a site owner cannot see either one from the settings screen alone.
    if ('yes' !== ($imported_settings['auto-approve'] ?? '')) {
        throw new RuntimeException('the migrated site did not keep the answer the old site gave about manual payments: ' . var_export($imported_settings['auto-approve'] ?? null, true));
    }
    $manual_outcomes = array_values(array_filter(
        (array) ($result['verification']['settings_outcomes'] ?? []),
        static function ($outcome) {
            return is_array($outcome) && 'order-without-payment' === ($outcome['key'] ?? '');
        }
    ));
    if (!$manual_outcomes) {
        throw new RuntimeException('the migration report said nothing about the people who could order without paying');
    }
    $manual_outcome = $manual_outcomes[0];
    if ('mds2' !== ($manual_outcome['source'] ?? '') || '' === trim((string) ($manual_outcome['note'] ?? ''))) {
        throw new RuntimeException('the migration report mis-described where the ability to order without paying came from: ' . wp_json_encode($manual_outcome));
    }
    if (!(new \MillionDollarScript\V3\Orders\PaymentBypass())->allowed(absint($legacy_privileged_user))) {
        throw new RuntimeException('the person the old site let order without paying cannot here - the flag was left behind');
    }

    $report = is_array($result['verification'] ?? null) ? $result['verification'] : [];
    $repairs = array_column((array) ($report['repairs'] ?? []), 'reason');
    $skips = array_column((array) ($report['skips'] ?? $report['skipped'] ?? []), 'reason');

    // What the run actually did, printed rather than hidden: the point of the
    // realistic dataset is to show which branches real data reaches.
    $observed = [
        'seed' => [
            'orders' => $seed['order_count'],
            'blocks' => $seed['block_count'],
            'advertisers' => count($seed['advertisers']),
        ],
        'imported' => $result['imported'] ?? [],
        'skipped' => $result['skipped'] ?? ($report['skipped'] ?? []),
        'repair_count' => count($repairs),
        'repairs' => array_slice($repairs, 0, 12),
        'warning_count' => count((array) ($report['warnings'] ?? [])),
    ];

    // Orders and their placements. The guarantee added in 96666e5e is not that an
    // order always gets a placement - a placement needs an image - it is that a
    // row which does not arrive is always reported. So an unreported absence is a
    // failure, and a reported one is data the fixture expects and prints.
    $expected_without_placement = [
        // No image anywhere: the ad record has none, the blocks have no image_data
        // and no file_name. This is the image-recovery question (task ab7457c2),
        // reproduced from MDS 2-shaped data rather than guessed at.
        5 => 'pier9: link only, no image in the record or the block rows',
        6 => 'empty advertiser: nothing to place',
        7 => 'pier9 again: three blocks, still no image',
        8 => 'order with ad_id NULL and no block media',
    ];
    $missing_orders = [];
    $unreported_absences = [];
    $alt_text_over_limit = [];
    $link_urls = [];
    $images = [];
    $reported_skips = [];
    foreach ((array) ($report['skipped'] ?? []) as $skip) {
        if (is_array($skip) && 'placement' === (string) ($skip['entity'] ?? '')) {
            $reported_skips[(int) ($skip['source_id'] ?? 0)] = (string) ($skip['reason'] ?? '');
        }
    }
    foreach ($seed['orders'] as $order) {
        $legacy_order_id = $order['order_id'];
        $target_order = $mapped('order', $legacy_order_id, 'order', DB::table('orders'));
        if (!$target_order) {
            $missing_orders[] = $legacy_order_id;
            continue;
        }
        $placement_rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, link_url, alt_text, attachment_id FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = %d',
            absint($target_order['id'])
        ), ARRAY_A);
        if (!is_array($placement_rows) || !$placement_rows) {
            if (!isset($reported_skips[$legacy_order_id])) {
                $unreported_absences[] = $legacy_order_id;
            }
            continue;
        }
        foreach ($placement_rows as $placement_row) {
            if (function_exists('mb_strlen') ? mb_strlen((string) $placement_row['alt_text']) > 500 : strlen((string) $placement_row['alt_text']) > 500) {
                $alt_text_over_limit[] = $placement_row['id'];
            }
            $link_urls['order-' . $legacy_order_id] = (string) $placement_row['link_url'];
            $images['order-' . $legacy_order_id] = absint($placement_row['attachment_id']);
        }
    }

    if ($missing_orders) {
        echo wp_json_encode(['missing_orders' => $missing_orders, 'observed' => $observed], JSON_PRETTY_PRINT) . "\n";
        throw new RuntimeException('Legacy orders produced no MDS3 order row and were not reported: ' . implode(', ', $missing_orders));
    }
    if ($unreported_absences) {
        echo wp_json_encode(['unreported_absences' => $unreported_absences, 'observed' => $observed], JSON_PRETTY_PRINT) . "\n";
        throw new RuntimeException('An order produced no placement and the run did not report why: ' . implode(', ', $unreported_absences));
    }
    foreach ($expected_without_placement as $legacy_order_id => $why) {
        if (isset($images['order-' . $legacy_order_id])) {
            throw new RuntimeException('Order ' . $legacy_order_id . ' produced a placement where the dataset expects none (' . $why . ').');
        }
    }
    foreach ([1, 2, 3, 4, 9] as $legacy_order_id) {
        if (!isset($images['order-' . $legacy_order_id])) {
            throw new RuntimeException('Order ' . $legacy_order_id . ' lost its placement although its image was recoverable.');
        }
    }
    if ($alt_text_over_limit) {
        throw new RuntimeException('A placement kept alt_text longer than its column: ' . implode(', ', $alt_text_over_limit));
    }

    // Blocks follow the same rule as orders: every legacy block row must have a
    // target row. Whether it became a placement is a separate question with its
    // own report; the block itself must not disappear.
    $mapped_block_count = count($map_rows('block'));
    if ($mapped_block_count !== $seed['block_count']) {
        echo wp_json_encode(['mapped_blocks' => $mapped_block_count, 'seeded_blocks' => $seed['block_count'], 'observed' => $observed], JSON_PRETTY_PRINT) . "\n";
        throw new RuntimeException('Legacy blocks without a target block: ' . ($seed['block_count'] - $mapped_block_count));
    }

    // The specific link and copy shapes the client site produced.
    $ridgeline_alt = (string) $wpdb->get_var($wpdb->prepare(
        'SELECT alt_text FROM ' . DB::ident(DB::table('placements')) . ' WHERE order_id = (SELECT mds3_id FROM ' . DB::ident(DB::table('migration_map'))
            . " WHERE source_prefix = %s AND entity_type = 'order' AND legacy_id = '2' AND mds3_entity_type = 'order' LIMIT 1) LIMIT 1",
        $source_prefix
    ));
    if (function_exists('mb_strlen') ? mb_strlen($ridgeline_alt) : strlen($ridgeline_alt) > 500) {
        throw new RuntimeException('The over-long legacy ad copy was stored in alt_text without a repair.');
    }

    // Coverage: the run must say what it left behind, not only that it wrote
    // rows. This is the ask in #30 - a run reported everything as verified while
    // parts of the old site had no counterpart, so the report has to disagree
    // with itself being all-clear. The counts here are derived from the source
    // tables and from the placement rows above, independently of how the plugin
    // computes them.
    $coverage = is_array($report['coverage'] ?? null) ? $report['coverage'] : [];
    if (!$coverage) {
        throw new RuntimeException('The verification report carries no coverage checks.');
    }

    $source_order_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::ident($source_prefix . 'orders'));
    if (absint($coverage['orders']['source'] ?? 0) !== $source_order_count) {
        throw new RuntimeException('Coverage counts the source orders wrongly: ' . absint($coverage['orders']['source'] ?? 0) . ' for ' . $source_order_count . '.');
    }
    if (absint($coverage['orders']['placed'] ?? 0) !== count($images)) {
        throw new RuntimeException('Coverage counts the orders holding a place on the grid wrongly: ' . absint($coverage['orders']['placed'] ?? 0) . ' for ' . count($images) . '.');
    }
    if (absint($coverage['orders']['reported'] ?? 0) !== count($reported_skips)) {
        throw new RuntimeException('Coverage does not match the absences this run reported: ' . absint($coverage['orders']['reported'] ?? 0) . ' for ' . count($reported_skips) . '.');
    }
    if (absint($coverage['orders']['unexplained'] ?? 0) > 0) {
        echo wp_json_encode(['coverage' => $coverage, 'observed' => $observed], JSON_PRETTY_PRINT) . "\n";
        throw new RuntimeException('Orders are missing from the grid and nothing in the report says why: ' . absint($coverage['orders']['unexplained']));
    }

    $with_attachment = count(array_filter($images));
    if (absint($coverage['images']['attached'] ?? 0) !== $with_attachment) {
        throw new RuntimeException('Coverage counts the ads showing an image wrongly: ' . absint($coverage['images']['attached'] ?? 0) . ' for ' . $with_attachment . '.');
    }
    if (absint($coverage['images']['expected'] ?? 0) < 3) {
        throw new RuntimeException('Coverage found no orders whose old rows carried an image, which means the check is not reading the source.');
    }
    if (absint($coverage['images']['short'] ?? 0) > 0) {
        echo wp_json_encode(['coverage' => $coverage], JSON_PRETTY_PRINT) . "\n";
        throw new RuntimeException('An ad that had an image in the old data reached the grid without one.');
    }
    if (absint($coverage['canvas']['outside'] ?? 0) !== 0) {
        echo wp_json_encode(['canvas_examples' => $coverage['canvas']['examples'] ?? []], JSON_PRETTY_PRINT) . "\n";
        throw new RuntimeException('A placement falls outside the grid without the source data asking for it.');
    }

    // A block whose stored position sits off its grid while its own block number names a
    // position on that grid is the shape the customer's data has: their selection past an
    // edge left x outside the canvas, and their own grid drew the ad at the cell the block
    // number says (Core/include/output_grid.php in that plugin walks the cells). The import
    // follows the block number, and says so.
    $undrawn_block = $wpdb->get_row(
        'SELECT block_id, banner_id, order_id FROM ' . DB::ident($source_prefix . 'blocks')
            . " WHERE order_id > 0 AND status = 'sold' ORDER BY block_id ASC LIMIT 1",
        ARRAY_A
    );
    if (!$undrawn_block) {
        throw new RuntimeException('The dataset has no sold block to move outside the grid.');
    }
    // The grid this dataset seeds: 20 columns of 728px, 10 rows of 90px.
    $columns = 20;
    $cell_w = 728;
    $cell_h = 90;
    $drawn_x = (absint($undrawn_block['block_id']) % $columns) * $cell_w;
    $drawn_y = intdiv(absint($undrawn_block['block_id']), $columns) * $cell_h;
    $wpdb->query($wpdb->prepare(
        'UPDATE ' . DB::ident($source_prefix . 'blocks') . ' SET x = 999999 WHERE block_id = %d',
        absint($undrawn_block['block_id'])
    ));
    $undrawn_run = (new Importer())->import($source_prefix);
    $undrawn_coverage = is_array($undrawn_run['verification']['coverage'] ?? null) ? $undrawn_run['verification']['coverage'] : [];
    $undrawn_repairs = array_column((array) ($undrawn_run['verification']['repairs'] ?? []), 'reason');
    $moved_block = $mapped('block', absint($undrawn_block['banner_id']) . ':' . absint($undrawn_block['block_id']), 'block', DB::table('blocks'));
    if (!$moved_block || absint($moved_block['x']) !== $drawn_x || absint($moved_block['y']) !== $drawn_y) {
        echo wp_json_encode(['block' => $moved_block, 'expected' => ['x' => $drawn_x, 'y' => $drawn_y]], JSON_PRETTY_PRINT) . "\n";
        throw new RuntimeException('A block stored off its grid was not placed where that site drew it.');
    }
    if (!$any_reason_matches($undrawn_repairs, ['could not show', 'off the grid'])) {
        echo wp_json_encode(['repairs' => $undrawn_repairs], JSON_PRETTY_PRINT) . "\n";
        throw new RuntimeException('A position the grid could never draw was corrected without saying so.');
    }
    if (absint($undrawn_coverage['canvas']['outside'] ?? 0) !== 0) {
        echo wp_json_encode(['canvas_examples' => $undrawn_coverage['canvas']['examples'] ?? []], JSON_PRETTY_PRINT) . "\n";
        throw new RuntimeException('The corrected position still reads as outside the grid.');
    }

    // A stored position that stays inside the grid is left alone. That is what a site that
    // resized a grid looks like - every row disagrees with its block number there - and in
    // that case the stored position is the only thing that can still be right. Use a cell
    // inside this grid that no other block occupies, so the test is about the choice and not
    // about a rejected write.
    $free_cell = null;
    for ($row_index = 9; $row_index >= 0 && null === $free_cell; $row_index--) {
        for ($column_index = 19; $column_index >= 0 && null === $free_cell; $column_index--) {
            $candidate_x = $column_index * $cell_w;
            $candidate_y = $row_index * $cell_h;
            if ($candidate_x === $drawn_x && $candidate_y === $drawn_y) {
                continue;
            }
            $occupied = $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d AND x = %d AND y = %d AND id <> %d LIMIT 1',
                absint($moved_block['grid_id']),
                $candidate_x,
                $candidate_y,
                absint($moved_block['id'])
            ));
            if (!$occupied) {
                $free_cell = ['x' => $candidate_x, 'y' => $candidate_y];
            }
        }
    }
    if (!$free_cell) {
        throw new RuntimeException('The dataset leaves no free cell inside the grid to test a stored position with.');
    }
    $wpdb->query($wpdb->prepare(
        'UPDATE ' . DB::ident($source_prefix . 'blocks') . ' SET x = %d, y = %d WHERE block_id = %d AND banner_id = %d',
        $free_cell['x'],
        $free_cell['y'],
        absint($undrawn_block['block_id']),
        absint($undrawn_block['banner_id'])
    ));
    $resized_run = (new Importer())->import($source_prefix);
    $resized_repairs = array_column((array) ($resized_run['verification']['repairs'] ?? []), 'reason');
    $kept_block = $mapped('block', absint($undrawn_block['banner_id']) . ':' . absint($undrawn_block['block_id']), 'block', DB::table('blocks'));
    if (!$kept_block || absint($kept_block['x']) !== $free_cell['x'] || absint($kept_block['y']) !== $free_cell['y']) {
        echo wp_json_encode(['block' => $kept_block, 'stored_inside' => $free_cell, 'block_number_would_be' => ['x' => $drawn_x, 'y' => $drawn_y]], JSON_PRETTY_PRINT) . "\n";
        throw new RuntimeException('A position inside the grid was moved because its block number disagreed.');
    }
    if ($any_reason_matches($resized_repairs, ['could not show', 'off the grid'])) {
        echo wp_json_encode(['repairs' => $resized_repairs], JSON_PRETTY_PRINT) . "\n";
        throw new RuntimeException('A grid that was resized was reported as a position that could not be drawn.');
    }

    // Second run: the map must hold, so nothing doubles. And a choice this site made
    // after the first import has to survive a re-import - the old site said "no", and
    // it must not silently put the step back.
    $chosen_settings = (array) get_option('mds3_settings', []);
    $chosen_settings['confirm-orders'] = 'yes';
    // A site owner who turned manual payments off after the first import made a decision. The old
    // site still says "yes"; a second import must not take that back away from them.
    $chosen_settings['auto-approve'] = 'no';
    update_option('mds3_settings', $chosen_settings, false);

    $again = (new Importer())->import($source_prefix);

    $after_choice = get_option('mds3_settings', []);
    $after_choice = is_array($after_choice) ? $after_choice : [];
    if ('yes' !== ($after_choice['confirm-orders'] ?? '')) {
        throw new RuntimeException('re-running the migration overwrote the choice this site had made: ' . var_export($after_choice['confirm-orders'] ?? null, true));
    }
    if ('no' !== ($after_choice['auto-approve'] ?? '')) {
        throw new RuntimeException('re-running the migration turned manual payments back on against this site’s choice: ' . var_export($after_choice['auto-approve'] ?? null, true));
    }
    $again_notes = array_values(array_filter(
        (array) ($again['verification']['settings_outcomes'] ?? []),
        static function ($outcome) {
            return is_array($outcome) && 'confirm-orders' === ($outcome['key'] ?? '');
        }
    ));
    if (!$again_notes || 'mds3' !== ($again_notes[0]['source'] ?? '')) {
        throw new RuntimeException('the second migration report did not say the choice was this site\'s own: ' . wp_json_encode($again_notes));
    }

    // What the report page does with that record is asserted in
    // migration-admin-state-fixture.php, which renders the admin's own markup.
    $duplicate_map = [];
    foreach (['order', 'block', 'placement'] as $entity_type) {
        foreach ($map_rows($entity_type) as $map_row) {
            $key = $map_row['entity_type'] . ':' . $map_row['legacy_id'];
            $duplicate_map[$key] = ($duplicate_map[$key] ?? 0) + 1;
        }
    }
    $duplicated = array_keys(array_filter($duplicate_map, static function ($count) {
        return $count > 1;
    }));

    echo wp_json_encode([
        'imported' => $result['imported'] ?? [],
        'coverage' => $coverage,
        'corrected_position' => $undrawn_coverage['canvas'] ?? [],
        'kept_resized_position' => $free_cell,
        'orders_without_placement' => $reported_skips,
        'expected_absences' => $expected_without_placement,
        'link_urls' => $link_urls,
        'attachment_ids' => $images,
        'expected_attachments' => [
            'from_ad_record' => $seed['attached_attachment_id'],
            'from_block_file_name' => $seed['path_only_attachment_id'],
        ],
        'second_run' => $again['imported'] ?? [],
        'duplicated_map_keys' => $duplicated,
    ], JSON_PRETTY_PRINT) . "\n";

    if ($duplicated) {
        throw new RuntimeException('Re-import created duplicate map rows: ' . implode(', ', array_slice($duplicated, 0, 5)));
    }

    echo "migration-realistic-source fixture passed\n";
} finally {
    if ($paused_media !== '' && is_dir($paused_media)) {
        $media_target = dirname($paused_media) . '/images';
        if (!is_dir($media_target)) {
            rename($paused_media, $media_target);
        } else {
            // The run made the directory itself; put the kept files back into it.
            foreach ((array) glob($paused_media . '/*') as $kept_file) {
                @rename($kept_file, $media_target . '/' . basename($kept_file));
            }
            @rmdir($paused_media);
        }
    }
    mds2_source_cleanup($source_prefix, isset($seed) && is_array($seed) ? $seed : []);

    if (false === $baseline_settings) {
        delete_option('mds3_settings');
    } else {
        update_option('mds3_settings', $baseline_settings, false);
    }
    if (false === $baseline_confirm_option) {
        delete_option('_milliondollarscript_confirm-orders');
    } else {
        update_option('_milliondollarscript_confirm-orders', $baseline_confirm_option, false);
    }
    if (false === $baseline_auto_approve) {
        delete_option('_milliondollarscript_auto-approve');
    } else {
        update_option('_milliondollarscript_auto-approve', $baseline_auto_approve, false);
    }
    if (absint($legacy_privileged_user)) {
        $wpdb->delete($wpdb->usermeta, ['user_id' => absint($legacy_privileged_user)]);
        $wpdb->delete($wpdb->users, ['ID' => absint($legacy_privileged_user)]);
    }

    // Remove the pages and options this run created; anything that existed before
    // the run is restored as it was.
    foreach ($baseline_page_options as $option_type => $baseline_option) {
        $option_key = 'mds3_page_' . $option_type . '_id';
        $current_option = absint(get_option($option_key, 0));
        if ($baseline_option) {
            if ($current_option !== $baseline_option) {
                update_option($option_key, $baseline_option, false);
            }
            continue;
        }
        if ($current_option) {
            if (!in_array($current_option, $baseline_page_ids, true) && get_post($current_option)) {
                wp_delete_post($current_option, true);
            }
            delete_option($option_key);
        }
    }
    $mapped_page_ids = array_filter(array_map(static fn($type) => absint(get_option('mds3_page_' . $type . '_id', 0)), array_keys(PageRepository::standard_labels())));
    foreach (array_diff(array_map('intval', (array) $wpdb->get_col('SELECT ID FROM ' . DB::ident($wpdb->posts) . " WHERE post_type = 'page'")), $baseline_page_ids) as $new_page_id) {
        if (in_array($new_page_id, $mapped_page_ids, true) || !get_post_meta($new_page_id, '_mds3_page_type', true)) {
            continue;
        }
        if (DB::table_exists(DB::table('pages'))) {
            $wpdb->delete(DB::table('pages'), ['post_id' => $new_page_id]);
        }
        wp_delete_post($new_page_id, true);
    }
}
