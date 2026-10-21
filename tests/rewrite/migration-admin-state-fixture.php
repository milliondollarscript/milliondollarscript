<?php
/**
 * WP-CLI fixture: the migration screens must describe what actually happened.
 *
 * Vikunja #30 section 4 and #31 items 1, 3 and 5. The deactivate notice, the
 * setup-wizard step, the dry-run screen and the dashboard gate each carried one
 * fixed story regardless of the recorded import state, so a site that had really
 * finished an import was told its data had not been imported and could not reach
 * its own dashboard.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-admin-state-fixture.php
 *
 * The setup-gate half runs in its own process because WordPress' redirect ends
 * the script; run both cases:
 *   wp eval-file ...migration-admin-state-fixture.php gate_none  # must stop early (held in the wizard)
 *   wp eval-file ...migration-admin-state-fixture.php gate_run   # must print GATE_PASSTHROUGH
 */

use MillionDollarScript\V3\Admin\Admin;
use MillionDollarScript\V3\Migration\DryRun;
use MillionDollarScript\V3\Setup\LegacyPlugin;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;
use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

$fixture_case = strtolower((string) (isset($args[0]) ? $args[0] : (getenv('MDS_FIXTURE_CASE') ?: '')));

$administrator_ids = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$administrator_ids) {
    fwrite(STDERR, "MDS3-SKIP: needs an administrator user on this site.\n");
    return;
}

$wpdb = $GLOBALS['wpdb'];
$runs_name = DB::table('migration_runs');
// $wpdb->insert() takes a bare table name and quotes it itself; only query
// strings need DB::ident().
$runs_table = DB::ident($runs_name);

$original_user_id = get_current_user_id();

// A fresh install has nothing to migrate, and the setup step is deliberately absent
// on such a site - so a fixture that reads the step has to put the site in the
// state the step exists for. Two MDS2 tables are enough to be a real legacy
// source: the page-config table the old plugin always creates, and an orders table
// with rows in it. LegacyPlugin caches its answer per request, so these exist for
// the whole run and are dropped again below.
$legacy_tables = array($wpdb->prefix . 'mds_page_config', $wpdb->prefix . 'mds_orders');
$wpdb->query('CREATE TABLE IF NOT EXISTS ' . DB::ident($legacy_tables[0]) . ' (id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id))');
$wpdb->query('CREATE TABLE IF NOT EXISTS ' . DB::ident($legacy_tables[1]) . ' (id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id))');
$wpdb->query('INSERT IGNORE INTO ' . DB::ident($legacy_tables[1]) . ' (id) VALUES (1), (2), (3)');
$original_choice = get_option(LegacyPlugin::CHOICE_OPTION, false);
$original_setup_complete = get_option('mds3_setup_complete', false);

$read_runs = static function () use ($runs_table, $wpdb) {
    $rows = $wpdb->get_results('SELECT * FROM ' . $runs_table, ARRAY_A);

    return is_array($rows) ? $rows : [];
};

$snapshot_runs = $read_runs();

$private_call = static function (Admin $scope, $method, array $args = []) {
    $method = new ReflectionMethod(Admin::class, $method);
    $method->setAccessible(true);

    return $method->invokeArgs($scope, $args);
};

/**
 * Render a private admin partial or page through the plugin's own renderer so
 * the assertions run against the markup the admin actually sees.
 */
$capture = static function (Admin $scope, $method, array $args = []) use ($private_call) {
    ob_start();
    $private_call($scope, $method, $args);

    return (string) ob_get_clean();
};

$delete_runs = static function () use ($runs_table, $wpdb) {
    $wpdb->query('DELETE FROM ' . $runs_table);
};

$restore_runs = static function (array $rows) use ($runs_table, $runs_name, $wpdb) {
    $wpdb->query('DELETE FROM ' . $runs_table);
    foreach ($rows as $row) {
        $wpdb->insert($runs_name, $row);
    }
};

$insert_run = static function ($mode, $status, array $totals = [], array $report = ['warnings' => [], 'repairs' => []]) use ($runs_name, $wpdb) {
    $now = current_time('mysql', true);
    $wpdb->insert($runs_name, [
        'source_prefix' => $wpdb->prefix . 'mds_',
        'mode' => $mode,
        'status' => $status,
        'totals' => wp_json_encode($totals),
        'report' => wp_json_encode($report),
        'started_at' => $now,
        'completed_at' => 'completed' === $status ? $now : null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $wpdb->insert_id;
};

$read_run = static function (int $run_id) use ($runs_table, $wpdb) {
    $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $runs_table . ' WHERE id = %d', $run_id), ARRAY_A);
    if (!is_array($row)) {
        throw new RuntimeException('The fixture could not read back the run it inserted.');
    }
    $row['totals'] = json_decode((string) ($row['totals'] ?? ''), true) ?: [];
    $row['report'] = json_decode((string) ($row['report'] ?? ''), true) ?: [];

    return $row;
};

wp_set_current_user(absint($administrator_ids[0]));
update_option('mds3_setup_complete', 'no', false);
delete_option(LegacyPlugin::CHOICE_OPTION);
// The step panel renders only with a migration context; the fixture supplies one
// instead of installing a Million Dollar Script 2 plugin.
$_GET['mds2_action'] = 'kept';
$delete_runs();

try {
    if ('gate_none' === $fixture_case || 'gate_run' === $fixture_case) {
        if ('gate_run' === $fixture_case) {
            $insert_run('import', 'completed', ['orders' => 3, 'blocks' => 6]);
        }
        $admin = new Admin();
        $_GET['page'] = 'mds3-grid';
        $private_call($admin, 'maybe_redirect_to_setup');
        // Only reached when the gate let the admin through.
        echo "GATE_PASSTHROUGH\n";
        echo "migration-admin-state gate case passed\n";
        exit;
    }

    $admin = new Admin();

    // 1. No import has ever run: the notice must not claim a finished import,
    // and the step must stay pending with its original guidance.
    if (null !== $private_call($admin, 'latest_completed_migration_import')) {
        throw new RuntimeException('Expected no completed import in an empty runs table.');
    }

    $notice = $capture($admin, 'mds2_action_notice', ['deactivated', 1, 0]);
    if (false === strpos($notice, 'No import has been run yet')) {
        throw new RuntimeException('Deactivate notice did not describe a site that has never imported.');
    }
    if (false === strpos($notice, '1 Million Dollar Script 2 plugin was deactivated')) {
        throw new RuntimeException('Deactivate notice lost the plugin count.');
    }

    $step = $capture($admin, 'mds2_upgrade_step', [
        [['plugin_file' => 'milliondollarscript-two/milliondollarscript-two.php', 'name' => 'Million Dollar Script 2', 'active' => true]],
        ['has_data' => true, 'rows' => 39, 'source_prefix' => $wpdb->prefix . 'mds_'],
    ]);
    if (false === strpos($step, 'is-pending') || false === strpos($step, 'Nothing is imported or deleted until you start the import.')) {
        throw new RuntimeException('The setup step changed its guidance before any import ran.');
    }
    // The coloured edge is not enough: the step has to say what it is (Vikunja
    // #31 recommendation 1).
    if (false === strpos($step, 'mds3-setup-step-status is-pending') || false === strpos($step, '>Pending<')) {
        throw new RuntimeException('An unfinished migration step did not state its state in words.');
    }

    // 2. An import started and stopped: the notice must point at the unfinished
    // run instead of saying nothing was imported.
    $started_run = $insert_run('import', 'failed', ['orders' => 1]);
    $notice = $capture($admin, 'mds2_action_notice', ['deactivated', 1, 0]);
    if (false === strpos($notice, 'did not finish') || false === strpos($notice, 'run ' . $started_run)) {
        throw new RuntimeException('Deactivate notice did not name the unfinished import.');
    }

    // 2b. A run left in a running state is an import that stopped, not one that is
    // running. Opening the screen must not start work, and the screen must say so
    // in words (Vikunja #31 item 3).
    $stopped_run = $insert_run('import', 'running', ['orders' => 4], [
        'warnings' => [],
        'repairs' => [],
        'job' => [
            'stage_index' => 4,
            'source_totals' => ['orders' => 101, 'blocks' => 2446],
            'processed' => ['orders' => 62],
        ],
    ]);

    unset($_GET['started'], $_GET['continued'], $_GET['retried']);
    $panel = $capture($admin, 'migration_recovery_panel', [$read_run($stopped_run), true]);
    if (false !== strpos($panel, 'data-auto-run')) {
        throw new RuntimeException('Opening the migration screen started an unfinished import by itself.');
    }
    if (false === strpos($panel, 'Nothing runs until you choose Process next batch')
        || false === strpos($panel, 'Stopped, waiting to continue')) {
        throw new RuntimeException('A stopped import was still presented as if it were running.');
    }
    if (false === strpos($panel, 'data-mds3-migration-auto-retry')
        || preg_match('/data-mds3-migration-auto-retry"[^>]*checked/', $panel)) {
        throw new RuntimeException('The opt-in to keep going on its own is missing or on by default.');
    }
    if (false !== strpos($panel, 'data-mds3-migration-job')) {
        throw new RuntimeException('A stopped import was left wired to run without a click.');
    }

    // The request that came from the continue control is the one that works.
    $_GET['continued'] = '1';
    $panel = $capture($admin, 'migration_recovery_panel', [$read_run($stopped_run), true]);
    if (false === strpos($panel, 'data-auto-run="1"') || false === strpos($panel, 'Processing batches now')) {
        throw new RuntimeException('Continuing an import did not take over the batches for that request.');
    }
    unset($_GET['continued']);

    // A deliberate pause is never taken over, not even straight after a click.
    $paused_run = $insert_run('import', 'paused', ['orders' => 4]);
    $_GET['continued'] = '1';
    $panel = $capture($admin, 'migration_recovery_panel', [$read_run($paused_run), true]);
    if (false !== strpos($panel, 'data-auto-run') || false !== strpos($panel, 'data-mds3-migration-job')) {
        throw new RuntimeException('A paused import resumed itself.');
    }
    unset($_GET['continued']);

    $delete_runs();
    // must read as complete with the run's own counts, and the dashboard gate
    // must let the admin through.
    $completed_run = $insert_run('import', 'completed', [
        'settings' => 12,
        'grids' => 1,
        'packages' => 2,
        'price_rules' => 2,
        'orders' => 3,
        'blocks' => 6,
        'order_items' => 4,
        'placements' => 2,
        'pages' => 5,
        'skipped' => 1,
        'repaired' => 7,
        'warnings' => 2,
    ]);

    $notice = $capture($admin, 'mds2_action_notice', ['deactivated', 1, 0]);
    if (false === strpos($notice, 'import run ' . $completed_run) || false === strpos($notice, 'imported data is unaffected')) {
        throw new RuntimeException('Deactivate notice still claimed the data was not imported after a completed import.');
    }
    if (false !== strpos($notice, 'was not imported')) {
        throw new RuntimeException('Deactivate notice kept the fixed "was not imported" sentence.');
    }

    $step = $capture($admin, 'mds2_upgrade_step', [
        [['plugin_file' => 'milliondollarscript-two/milliondollarscript-two.php', 'name' => 'Million Dollar Script 2', 'active' => true]],
        ['has_data' => true, 'rows' => 39, 'source_prefix' => $wpdb->prefix . 'mds_'],
    ]);
    if (false === strpos($step, 'is-complete')) {
        throw new RuntimeException('A completed import did not complete the setup step.');
    }
    if (false === strpos($step, 'mds3-setup-step-status is-complete') || false === strpos($step, '>Completed<')) {
        throw new RuntimeException('A completed migration step did not state its state in words.');
    }
    if (false === strpos($step, 'has been imported into Million Dollar Script')
        || false === strpos($step, 'Import run ' . $completed_run)
        || false === strpos($step, '37 records imported')
        || false === strpos($step, '1 skipped')
        || false === strpos($step, '7 repaired')
        || false === strpos($step, '2 warnings')) {
        throw new RuntimeException('The setup step did not report the completed import and its counts.');
    }
    if (false !== strpos($step, 'Nothing is imported or deleted until you start the import.')) {
        throw new RuntimeException('The setup step still promised that nothing had been imported.');
    }
    if (false !== strpos($step, 'without importing')) {
        throw new RuntimeException('The deactivate control still said "without importing" after an import completed.');
    }

    // 4. A dry run must announce its own result and that it changed nothing.
    $fixture_prefix = $wpdb->prefix . 'mds_fixture_src_';
    $fixture_orders = $wpdb->prefix . 'mds_fixture_src_orders';
    $fixture_blocks = $wpdb->prefix . 'mds_fixture_src_blocks';
    $wpdb->query('CREATE TABLE ' . DB::ident($fixture_orders) . ' (id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned, PRIMARY KEY (id))');
    $wpdb->query('CREATE TABLE ' . DB::ident($fixture_blocks) . ' (id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned, PRIMARY KEY (id))');
    $wpdb->query('INSERT INTO ' . DB::ident($fixture_orders) . ' (id,user_id) VALUES (1,1),(2,1),(3,2)');
    $wpdb->query('INSERT INTO ' . DB::ident($fixture_blocks) . ' (id,user_id) VALUES (1,1),(2,2)');

    $report = (new DryRun())->report($fixture_prefix);
    $found_tables = array_filter($report['tables'], static function ($table) use ($fixture_prefix) {
        return !empty($table['exists']) && 0 === strpos(strtolower((string) ($table['table'] ?? '')), strtolower($fixture_prefix));
    });
    if (2 !== count($found_tables)) {
        throw new RuntimeException('Fixture source tables were not found by the dry run.');
    }

    $page_vars = [
        'grid_enabled' => true,
        'latest' => null,
        'legacy_plugins' => [],
        'legacy_source' => ['has_data' => true, 'rows' => 5, 'source_prefix' => $fixture_prefix],
        'report' => $report,
        'source_prefix' => $fixture_prefix,
    ];

    $_GET = ['page' => 'mds3-migration', 'mds3_dry_run' => '1'];
    $rendered = Template::render('admin/pages/migration.php', $page_vars, $admin);
    if (false === strpos($rendered, 'Dry run complete. Nothing was imported, changed, or deleted.')) {
        throw new RuntimeException('Running the dry run produced no confirmation.');
    }
    if (false === strpos($rendered, '2 Million Dollar Script 2 tables hold 5 rows')) {
        throw new RuntimeException('The dry run confirmation did not state what it found.');
    }

    $_GET = ['page' => 'mds3-migration'];
    $rendered = Template::render('admin/pages/migration.php', $page_vars, $admin);
    if (false !== strpos($rendered, 'Dry run complete.')) {
        throw new RuntimeException('The dry run confirmation appeared on a page load that did not run one.');
    }

    // 5. A finished import offers the way out of the migration screen.
    $_GET = ['page' => 'mds3-migration'];
    $run_row = $wpdb->get_row('SELECT * FROM ' . $runs_table . ' WHERE id = ' . $completed_run, ARRAY_A);
    $run_row['totals'] = json_decode((string) $run_row['totals'], true) ?: [];
    $run_row['report'] = json_decode((string) $run_row['report'], true) ?: [];
    $rendered = $capture($admin, 'migration_recovery_panel', [$run_row, true]);
    if (false === strpos($rendered, 'page=mds3"') || false === strpos($rendered, 'Open the dashboard')) {
        throw new RuntimeException('A completed import gave no way to leave the migration screen.');
    }

    // An unfinished import must not offer to leave the migration screen.
    $unfinished_run = $insert_run('import', 'running', ['orders' => 1]);
    $unfinished_row = $wpdb->get_row('SELECT * FROM ' . $runs_table . ' WHERE id = ' . $unfinished_run, ARRAY_A);
    $unfinished_row['totals'] = json_decode((string) $unfinished_row['totals'], true) ?: [];
    $unfinished_row['report'] = json_decode((string) $unfinished_row['report'], true) ?: [];
    $unfinished = $capture($admin, 'migration_recovery_panel', [$unfinished_row, true]);
    if (false !== strpos($unfinished, 'Open the dashboard')) {
        throw new RuntimeException('An unfinished import offered to leave the migration screen.');
    }
    if (false === strpos($unfinished, 'Process next batch')) {
        throw new RuntimeException('An unfinished import lost its continue control.');
    }

    // 6. A run that lost records without saying so must not read as finished.
    // The numbers are invented here; what matters is that a coverage shortfall in
    // the stored report reaches the run summary, the report page, and the setup
    // step next to the control that switches the older plugin off.
    $shortfall_run = $insert_run('import', 'completed', ['orders' => 62, 'placements' => 62], [
        'warnings' => [],
        'repairs' => [],
        'coverage' => [
            'orders' => ['source' => 101, 'placed' => 62, 'reported' => 0, 'unexplained' => 39],
            'images' => ['expected' => 40, 'attached' => 40, 'short' => 0],
            'canvas' => ['placements' => 62, 'outside' => 0, 'examples' => []],
        ],
    ]);
    $shortfall_row = $wpdb->get_row('SELECT * FROM ' . $runs_table . ' WHERE id = ' . $shortfall_run, ARRAY_A);
    $shortfall_row['totals'] = json_decode((string) $shortfall_row['totals'], true) ?: [];
    $shortfall_row['report'] = json_decode((string) $shortfall_row['report'], true) ?: [];

    $summary = $private_call($admin, 'migration_run_summary', [$shortfall_row]);
    if (empty($summary['coverage_short']) || 39 !== (int) $summary['coverage_short']) {
        throw new RuntimeException('A run with unexplained absences did not count them.');
    }
    if (false === strpos((string) $summary['coverage_note'], '39 of 101 orders have no place on the new grid')) {
        throw new RuntimeException('The coverage summary did not name the orders left without a place: ' . (string) $summary['coverage_note']);
    }
    if ('Review coverage' !== (string) ($summary['status_label'] ?? '')) {
        throw new RuntimeException('A run that left records behind was still presented as ' . (string) ($summary['status_label'] ?? 'completed') . '.');
    }

    $report_page = $capture($admin, 'migration_verification_report', [$shortfall_row]);
    if (false === strpos($report_page, 'What the import left behind') || false === strpos($report_page, '39 missing')) {
        throw new RuntimeException('The verification report did not show the coverage shortfall.');
    }

    $step_with_shortfall = $capture($admin, 'mds2_upgrade_step', [
        [['plugin_file' => 'milliondollarscript-two/milliondollarscript-two.php', 'name' => 'Million Dollar Script 2', 'active' => true]],
        ['has_data' => true, 'rows' => 202, 'source_prefix' => $wpdb->prefix . 'mds_'],
    ]);
    if (false === strpos($step_with_shortfall, 'Deactivating Million Dollar Script 2 now leaves those records')) {
        throw new RuntimeException('The setup step offered to switch the older plugin off without mentioning the missing records. Rendered '
            . strlen($step_with_shortfall) . ' characters; names the shortfall: '
            . (false !== strpos($step_with_shortfall, 'no place on the new grid') ? 'yes' : 'no')
            . '; offers to deactivate: ' . (false !== strpos($step_with_shortfall, 'Deactivate Million Dollar Script 2') ? 'yes' : 'no')
            . '; shows a completed run: ' . (false !== strpos($step_with_shortfall, 'Import run') ? 'yes' : 'no'));
    }

    // 7. A setting that came with the site's own history has to be named in the report, in
    // words. The two plugins do not default Ask Customers To Confirm Orders the same way, so
    // a site that suddenly asks - or stops asking - deserves to be told which, and where to
    // change it. (llm-task 2bc92787)
    $carried_run = $insert_run('import', 'completed', ['orders' => 3, 'placements' => 3], [
        'warnings' => [],
        'repairs' => [],
        'skipped' => [],
        'settings_outcomes' => [[
            'key' => 'confirm-orders',
            'value' => 'yes',
            'source' => 'mds2_default',
            'note' => SettingsSchema::describe_confirm_orders_choice([], []),
        ]],
        'order_status_outcomes' => [
            'counts' => [
                'paid' => 2,
                'pending_payment' => 3,
                'expired' => 1,
            ],
            'confirmed_without_payment' => 3,
        ],
    ]);
    $carried_row = $wpdb->get_row('SELECT * FROM ' . $runs_table . ' WHERE id = ' . $carried_run, ARRAY_A);
    $carried_row['totals'] = json_decode((string) $carried_row['totals'], true) ?: [];
    $carried_row['report'] = json_decode((string) $carried_row['report'], true) ?: [];

    $carried_page = $capture($admin, 'migration_verification_report', [$carried_row]);
    if (false === strpos($carried_page, 'How This Site Handles Orders') || false === strpos($carried_page, 'asked customers to check each order by default')) {
        throw new RuntimeException('The verification report did not say how the migrated site handles order confirmation.');
    }
    if (false === strpos($carried_page, 'Settings &gt; Orders')) {
        throw new RuntimeException('The verification report did not say where to change the order confirmation setting.');
    }

    // 8. The same page states what the migrated orders arrived as, and explains the ones
    // that came from the older plugin's confirmation step instead of leaving an owner to
    // work out why ads they expected to see are not on the grid. (llm-task 90dd3ff4)
    if (false === strpos($carried_page, 'Orders by State')) {
        throw new RuntimeException('The verification report did not list the orders by state.');
    }
    if (false === strpos($carried_page, 'Awaiting payment') || false === strpos($carried_page, '<td>3</td>')) {
        throw new RuntimeException('The verification report did not give the count of orders still awaiting payment.');
    }
    if (false === strpos($carried_page, 'left at the confirmation step by the site that was migrated')) {
        throw new RuntimeException('The verification report did not explain the orders left at the confirmation step.');
    }
    if (false === strpos($carried_page, 'Paid')) {
        throw new RuntimeException('The verification report did not name the states in words an owner can read.');
    }

    echo "migration-admin-state fixture passed\n";
} finally {
    $restore_runs($snapshot_runs);
    if (false === $original_choice) {
        delete_option(LegacyPlugin::CHOICE_OPTION);
    } else {
        update_option(LegacyPlugin::CHOICE_OPTION, $original_choice, false);
    }
    if (false === $original_setup_complete) {
        delete_option('mds3_setup_complete');
    } else {
        update_option('mds3_setup_complete', $original_setup_complete, false);
    }
    wp_set_current_user($original_user_id);
    if (isset($fixture_orders)) {
        $wpdb->query('DROP TABLE IF EXISTS ' . DB::ident($fixture_orders));
        $wpdb->query('DROP TABLE IF EXISTS ' . DB::ident($fixture_blocks));
    }
    foreach ($legacy_tables as $legacy_table) {
        $wpdb->query('DROP TABLE IF EXISTS ' . DB::ident($legacy_table));
    }
    if (get_current_user_id() !== $original_user_id) {
        wp_set_current_user($original_user_id);
    }
    unset($_GET['mds3_dry_run'], $_GET['page'], $_GET['mds2_action']);
}
