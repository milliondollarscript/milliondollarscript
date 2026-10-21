<?php
/**
 * Diagnostic: run the MDS2 -> MDS3 migration against the legacy tables on this site,
 * exactly as the migration wizard runs it, and print what this build makes of them.
 *
 * This WRITES: it imports (or re-imports) on the current site. Run it on a copy, or on a
 * site where a migration run is expected.
 *
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/diagnostics/migration-report.php \
 *       --allow-root --path=<install path> --url=<site url>
 *
 * On a local reproduction of a tester's dump, the workspace runbook drives it from
 * scripts/migration-repro/README.md. A tester who offered to "run any diagnostic scripts you
 * provide" (Vikunja #30) can run this on their own staging site.
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;

use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Support\DB;

// The legacy table prefix is whatever this WordPress uses; wp eval-file passes anything
// after the script path as $args, so a site with a different prefix can name one.
$prefix = isset($args[0]) && '' !== $args[0] ? $args[0] : $wpdb->prefix . 'mds_';
echo "legacy table prefix: {$prefix}\n";

if (!is_plugin_active('million-dollar-script/million-dollar-script.php')) {
    echo "MDS3 is not active on this site\n";
    exit(1);
}

$state = function () {
    global $wpdb;
    $out = [];
    foreach ([ 'grids', 'orders', 'order_items', 'blocks', 'placements', 'pages', 'migration_map', 'migration_runs' ] as $t) {
        $table = DB::table($t);
        $out[$t] = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s', $table)) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : -1;
    }
    return $out;
};

echo "BEFORE: " . json_encode($state()) . "\n";

$started = microtime(true);
$result = (new Importer())->import($prefix);
$elapsed = round(microtime(true) - $started, 1);

if (is_wp_error($result)) {
    echo "import failed: " . $result->get_error_message() . "\n";
    exit(1);
}

// import() returns the counts plus a verification summary; the item-by-item detail (what
// was repaired, skipped, which page was created for which ad) is stored in the run row, and
// that is what the migration screen shows the site owner. Read the same record.
$run_id = isset($result['run_id']) ? (int) $result['run_id'] : 0;
$stored = $run_id ? (array) json_decode((string) $wpdb->get_var($wpdb->prepare(
    'SELECT report FROM ' . DB::table('migration_runs') . ' WHERE id = %d',
    $run_id
)), true) : [];

$report = is_array($result) ? $result : [];
$pick = static function ($key) use ($report, $stored) {
    if (isset($report[$key])) {
        return $report[$key];
    }
    return isset($stored[$key]) ? $stored[$key] : null;
};

echo "ELAPSED: {$elapsed}s  run_id: {$run_id}\n";
echo "TOTALS: " . json_encode($pick('imported')) . "\n";
echo "VERIFICATION: " . json_encode($pick('verification')) . "\n";

foreach ([ 'warnings', 'skipped' ] as $key) {
    $rows = $pick($key) ?: [];
    echo strtoupper($key) . " (" . count($rows) . "): " . implode(' | ', array_slice(array_map(static function ($r) {
        return is_string($r) ? $r : json_encode($r);
    }, $rows), 0, 8)) . "\n";
}

$repairs = $pick('repairs') ?: [];
$by_reason = [];
foreach ($repairs as $repair) {
    $reason = isset($repair['reason']) ? preg_replace('/\(\d+ by \d+ pixels\)|\(\d+ characters\)/', '(n)', (string) $repair['reason']) : '?';
    $key = ($repair['entity'] ?? '?') . ' :: ' . substr($reason, 0, 110);
    $by_reason[$key] = ($by_reason[$key] ?? 0) + 1;
}
echo "REPAIRS (" . count($repairs) . " by reason):\n";
foreach ($by_reason as $reason => $count) {
    echo "  {$count}  {$reason}\n";
}

$outcomes = $pick('page_outcomes') ?: [];
$by_outcome = [];
foreach ($outcomes as $outcome) {
    $by_outcome[$outcome['outcome'] ?? '?'] = ($by_outcome[$outcome['outcome'] ?? '?'] ?? 0) + 1;
}
echo "PAGE OUTCOMES: " . json_encode($by_outcome) . "\n";

echo "AFTER: " . json_encode($state()) . "\n";

// Where the migrated site actually lives, so it can be opened in a browser.
echo "MIGRATED GRID PAGES:\n";
$grids = $wpdb->get_results('SELECT id, title, slug, width, height, block_width, block_height, price_per_block, status FROM ' . DB::table('grids') . ' ORDER BY id ASC', ARRAY_A);
foreach ($grids as $grid) {
    $sold = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DB::table('blocks') . ' WHERE grid_id = %d AND status = %s', $grid['id'], 'sold'));
    $page = $wpdb->get_row($wpdb->prepare('SELECT post_id FROM ' . DB::table('pages') . ' WHERE grid_id = %d ORDER BY id ASC LIMIT 1', $grid['id']), ARRAY_A);
    echo "  grid {$grid['id']} {$grid['title']} {$grid['width']}x{$grid['height']} cells of {$grid['block_width']}px price={$grid['price_per_block']} status={$grid['status']} sold={$sold}"
        . " page=" . ($page ? get_permalink($page['post_id']) : 'none') . "\n";
}

echo "PLACEMENT IMAGES:\n";
$images = $wpdb->get_results('SELECT p.id, p.attachment_id, a.guid, a.post_mime_type FROM ' . DB::table('placements') . ' p LEFT JOIN ' . $wpdb->posts . " a ON a.ID = p.attachment_id WHERE a.ID IS NOT NULL ORDER BY p.id ASC LIMIT 6", ARRAY_A);
foreach ($images as $image) {
    echo "  placement {$image['id']} attachment={$image['attachment_id']} file={$image['guid']} mime={$image['post_mime_type']}\n";
}

echo "MIGRATION RUN ROWS (a site can hold runs made by several builds):\n";
$runs = $wpdb->get_results('SELECT id, mode, status, plugin_version, started_at, completed_at FROM ' . DB::table('migration_runs') . ' ORDER BY id DESC LIMIT 5', ARRAY_A);
foreach ($runs as $run) {
    echo "  run {$run['id']} mode={$run['mode']} status={$run['status']} build=" . ($run['plugin_version'] !== '' ? $run['plugin_version'] : 'older than this table') . " {$run['started_at']} -> {$run['completed_at']}\n";
}

echo "ADVERTISER PAGES (what a visitor can open):\n";
$ad_pages = $wpdb->get_results('SELECT p.id AS placement_id, p.public_post_id, a.post_name, a.post_status, SUBSTRING(a.post_title,1,40) AS title FROM ' . DB::table('placements') . ' p INNER JOIN ' . $wpdb->posts . ' a ON a.ID = p.public_post_id ORDER BY p.id ASC LIMIT 4', ARRAY_A);
foreach ($ad_pages as $ad_page) {
    echo "  placement {$ad_page['placement_id']} page {$ad_page['public_post_id']} [{$ad_page['post_status']}] slug={$ad_page['post_name']} title={$ad_page['title']} url=" . get_permalink($ad_page['public_post_id']) . "\n";
}

echo "PHP ERRORS THIS REQUEST (wp-content/debug.log tail):\n";
$log = WP_CONTENT_DIR . '/debug.log';
if (is_readable($log)) {
    $tail = explode("\n", (string) file_get_contents($log, false, null, max(0, filesize($log) - 4000)));
    $mine = array_values(array_filter($tail, static function ($line) {
        return false !== stripos($line, 'mds') || false !== stripos($line, 'migration') || false !== stripos($line, 'Warning') || false !== stripos($line, 'Fatal');
    }));
    echo implode("\n", array_slice($mine, -12)) . "\n";
}
