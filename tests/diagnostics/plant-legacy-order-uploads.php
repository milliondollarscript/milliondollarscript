<?php
/**
 * Stands in for upload files that did not travel with a database dump.
 *
 * For a throwaway copy only — this writes files a real site already has, so never run it
 * where the originals exist. A sanitized SQL dump carries no media, which makes it impossible
 * to exercise the importer's preferred media route: a site's own uploads folder holding the
 * full-size file MDS2 wrote for each order (uploads/milliondollarscript/images/
 * tmp_<order id>_<timestamp>_<random>.<ext>). This helper rebuilds one file per order from
 * the picture bytes the legacy block rows kept, under exactly that name, so the import can
 * be checked against real rows instead of a fixture.
 *
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/diagnostics/plant-legacy-order-uploads.php \
 *       --allow-root --path=<install path> --url=<site url>
 */

if (!defined('ABSPATH')) {
    echo "This script must run inside a WordPress root (wp eval-file).\n";
    exit(1);
}

global $wpdb;

// Same convention as migration-report.php: wp eval-file passes anything after the script
// path as $args, so a site whose legacy tables carry another prefix can name it there
// (MDS_LEGACY_PREFIX in the environment also works). This script reads legacy tables only,
// so it names them directly instead of going through the table helper of this plugin.
$source_prefix = '';
foreach ((array) ($args ?? []) as $maybe) {
    $maybe = trim((string) $maybe, " \t-");
    if (0 === strpos($maybe, 'prefix=')) {
        $maybe = substr($maybe, 7);
    }
    if (preg_match('/^[A-Za-z0-9_]+$/', $maybe)) {
        $source_prefix = $maybe;
        break;
    }
}
if ('' === $source_prefix) {
    $from_env = trim((string) getenv('MDS_LEGACY_PREFIX'));
    if (preg_match('/^[A-Za-z0-9_]+$/', $from_env)) {
        $source_prefix = $from_env;
    }
}
if ('' === $source_prefix) {
    $source_prefix = (string) $wpdb->prefix;
}
if ('' === $source_prefix) {
    echo wp_json_encode(['error' => 'the legacy table prefix resolved to nothing']) . "\n";
    exit(1);
}

$upload = wp_get_upload_dir();
if (!empty($upload['error'])) {
    echo wp_json_encode(['error' => $upload['error']]) . "\n";
    exit(1);
}
$directory = trailingslashit((string) $upload['basedir']) . 'milliondollarscript/images';
if (!wp_mkdir_p($directory)) {
    echo wp_json_encode(['error' => 'could not create ' . $directory]) . "\n";
    exit(1);
}

$rows = $wpdb->get_results(
    'SELECT order_id, MIN(block_id) AS block_id, image_data FROM `' . $source_prefix . 'blocks`'
        . " WHERE order_id > 0 AND image_data <> '' AND file_name = '' GROUP BY order_id",
    ARRAY_A
) ?: [];

$written = 0;
$failed = 0;
foreach ($rows as $row) {
    $bytes = base64_decode((string) $row['image_data'], true);
    if (!$bytes || strlen($bytes) < 32) {
        $failed++;
        continue;
    }
    // The name MDS2 gave an ad upload: FileValidator::generate_secure_filename() called with
    // the order id as the prefix. A stable suffix keeps repeat runs writing the same file.
    $name = sprintf(
        'tmp_%d_%d_%s.png',
        absint($row['order_id']),
        1700000000 + absint($row['block_id']),
        substr(md5('order-' . absint($row['order_id'])), 0, 6)
    );
    if (false === file_put_contents($directory . '/' . $name, $bytes)) {
        $failed++;
        continue;
    }
    $written++;
}

echo wp_json_encode([
    'orders_with_a_picture_in_their_block_rows' => count($rows),
    'files_written' => $written,
    'failed' => $failed,
    'directory' => $directory,
    'note' => 'Run the migration after this to see whether the imported ads use these files.',
]) . "\n";
