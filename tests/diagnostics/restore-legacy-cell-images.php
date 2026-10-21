<?php
/**
 * Diagnostic for a copy of a site whose media files did not travel with it — a sanitized
 * SQL dump, or a database restored without uploads. The migrated attachments then point at
 * picture files that are absent, so every ad shows as broken.
 *
 * MDS2 kept a copy of each ad picture inside its legacy block rows (image_data), so this
 * writes those bytes to the file path each migrated attachment already claims. The result is
 * the customer's real art at legacy cell size - usually a few pixels across, which is exactly
 * the case a display defect shows up in.
 *
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/diagnostics/restore-legacy-cell-images.php \
 *       --allow-root --path=<install path> --url=<site url>
 *
 * This WRITES files into uploads/. It does not change any table except attachment meta.
 * On a site that has its real uploads, running it changes nothing: an existing file is left
 * alone.
 */
global $wpdb;

$DB = '\\MillionDollarScript\\V3\\Support\\DB';
$uploads = wp_get_upload_dir();
$written = 0;
$skipped = 0;

$placements = $wpdb->get_results($wpdb->prepare(
    'SELECT p.id, p.order_id, p.attachment_id FROM ' . $DB::table('placements') . ' p'
    . ' WHERE p.status = %s ORDER BY p.id ASC',
    'active'
), ARRAY_A);
foreach ($placements as $placement) {
    $attachment_id = (int) $wpdb->get_var($wpdb->prepare('SELECT attachment_id FROM ' . $DB::table('placements') . ' WHERE id = %d', $placement['id']));
    if (!$attachment_id) {
        $skipped++;
        continue;
    }
    $file = (string) get_post_meta($attachment_id, '_wp_attached_file', true);
    if ('' === $file) {
        $skipped++;
        continue;
    }
    $target = trailingslashit($uploads['basedir']) . $file;
    if (file_exists($target)) {
        continue;
    }

    // Which legacy order this placement came from, and the picture that order's
    // blocks carried.
    $legacy_order = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT legacy_id FROM ' . $DB::table('migration_map') . " WHERE mds3_entity_type = 'order' AND mds3_id = %d LIMIT 1",
        $placement['order_id']
    ));
    $row = $legacy_order ? $wpdb->get_row($wpdb->prepare(
        'SELECT image_data, mime_type FROM wp_mds_blocks WHERE order_id = %d AND image_data IS NOT NULL AND image_data <> %s ORDER BY block_id ASC LIMIT 1',
        $legacy_order,
        ''
    ), ARRAY_A) : null;
    if (!$row) {
        $skipped++;
        continue;
    }

    $payload = (string) $row['image_data'];
    $mime = (string) ($row['mime_type'] ?: 'image/png');
    if (preg_match('#^data:([^;]+);base64,(.*)$#s', $payload, $match)) {
        $mime = $match[1];
        $payload = $match[2];
    }
    $bytes = base64_decode(preg_replace('/\s+/', '', $payload), true);
    if (!$bytes) {
        $skipped++;
        continue;
    }
    if (!wp_mkdir_p(dirname($target))) {
        $skipped++;
        continue;
    }
    if (false === file_put_contents($target, $bytes)) {
        $skipped++;
        continue;
    }
    update_post_meta($attachment_id, '_wp_attachment_is_image', '1');
    $written++;
}

echo json_encode(['placements' => count($placements), 'files_written' => $written, 'skipped' => $skipped, 'basedir' => $uploads['basedir']]) . "\n";
$missing = 0;
foreach ($wpdb->get_col('SELECT meta_value FROM ' . $wpdb->postmeta . " WHERE meta_key = '_wp_attached_file' AND post_id IN (SELECT attachment_id FROM " . $DB::table('placements') . ' WHERE attachment_id > 0)') as $file) {
    if (!file_exists(trailingslashit($uploads['basedir']) . $file)) {
        $missing++;
    }
}
echo json_encode(['still_missing' => $missing]) . "\n";
