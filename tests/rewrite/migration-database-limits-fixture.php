<?php
/**
 * WP-CLI fixture: a write that loses content is reported, however the loss happens.
 *
 * There are two ways the database can keep less than we sent, and they look different
 * from inside the migration:
 *
 * 1. The statement is refused. A value that does not fit the column (or a character the
 *    column's character set cannot hold) fails, and WordPress reports the field. The
 *    write helpers record it as a rejected row. This is what $wpdb->insert()/update()
 *    do on the tested WordPress and MariaDB versions, in and out of strict mode.
 * 2. The statement succeeds and the database only warns. "Data truncated for column"
 *    (1265) and "Incorrect string value" (1366) leave a row holding less than the older
 *    site had, with no error for PHP to read. Nothing else would notice: the totals
 *    would say zero warnings.
 *
 * Half 2 is reached by a statement written without prepared values - which is how the
 * database truncates quietly - so the fixture writes one that way and hands the result
 * to the same reporter the import uses. Both halves name the column, count it, and say
 * what to change. A character outside the column's character set does not get that far
 * on the tested versions: WordPress refuses it, which is half 1, and the useful answer
 * is the pre-flight notice in section 5 that names such a column before anything runs.
 * (1366 detection stays as the safety net for versions that do let it through.)
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-database-limits-fixture.php
 */

use MillionDollarScript\V3\Migration\DryRun;
use MillionDollarScript\V3\Migration\Importer;
use MillionDollarScript\V3\Migration\LegacySource;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$fails = [];
$check = function ($cond, $msg) use (&$fails) {
    if (!$cond) {
        $fails[] = $msg;
    }
};

$call = static function ($scope, $class, $method, array $args = []) {
    $method = new ReflectionMethod($class, $method);
    $method->setAccessible(true);

    return $method->invokeArgs($scope, $args);
};

$state = static function ($scope, $property) {
    $property = new ReflectionProperty($scope, $property);
    $property->setAccessible(true);

    return $property->getValue($scope);
};

$wide_char = "\u{1F697}"; // A character a utf8mb3 column cannot hold.
$oversized = str_repeat('tile and stone yard ', 4000); // ~80,000 bytes: over a TEXT column.

$table = $wpdb->prefix . 'mdsdblimit_text';
$wide_table = $wpdb->prefix . 'mdsdblimit_utf8';
$sql_mode = (string) $wpdb->get_var('SELECT @@SESSION.sql_mode');

$drop = static function ($name) use ($wpdb) {
    $wpdb->query('DROP TABLE IF EXISTS ' . DB::ident($name));
};

$drop($table);
$drop($wide_table);

try {
    $wpdb->query(
        'CREATE TABLE ' . DB::ident($table) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            note TEXT NOT NULL
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    // A column that cannot hold every character an ad can contain - what a site built
    // before utf8mb4 became WordPress' default still has.
    $wpdb->query(
        'CREATE TABLE ' . DB::ident($wide_table) . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            note TEXT CHARACTER SET utf8mb3 NOT NULL
        )"
    );

    $importer = new Importer();

    // ---- 1. The statement is refused: the record is named, with the reason. ----
    foreach (['' => 'non-strict', 'STRICT_TRANS_TABLES' => 'strict'] as $mode => $mode_label) {
        $wpdb->query($wpdb->prepare('SET SESSION sql_mode = %s', $mode));
        $source_id = 'legacy-limit-' . $mode_label;
        $refused_id = (int) $call($importer, Importer::class, 'migration_insert', [$table, ['note' => $oversized], 'ad_copy', $source_id]);

        $skipped = (array) $state($importer, 'migration_skips');
        $reasons = [];
        foreach ($skipped as $skip) {
            if ($source_id === (string) ($skip['source_id'] ?? '')) {
                $reasons[] = (string) ($skip['reason'] ?? '');
            }
        }

        $check(0 === $refused_id, sprintf('%s mode: an over-long value was written anyway', $mode_label));
        $check([] !== $reasons, sprintf('%s mode: the rejected record is not named in the report', $mode_label));
        $check(false !== stripos(implode(' ', $reasons), 'too long'), sprintf('%s mode: the report does not say the value was too long: %s', $mode_label, wp_json_encode($reasons)));

        $rejections = (array) $state($importer, 'database_rejections');
        $entry = $rejections[$table . '.note'] ?? null;
        $check(null !== $entry, sprintf('%s mode: the rejected value was not counted', $mode_label));
        $check('not stored' === (string) ($entry['kind'] ?? ''), sprintf('%s mode: the count does not say the value was not stored: %s', $mode_label, wp_json_encode($entry)));
    }
    $wpdb->query($wpdb->prepare('SET SESSION sql_mode = %s', $sql_mode));

    $check(2 === absint($rejections[$table . '.note']['count'] ?? 0), sprintf('the same column should count both refusals, saw %s', wp_json_encode($rejections[$table . '.note'] ?? null)));

    // ---- 2. The statement succeeds and only warns: the shortening is reported. ----
    $wpdb->query(
        'INSERT INTO ' . DB::ident($table) . " (note) VALUES ('" . $oversized . "')"
    );
    $quiet_id = absint($wpdb->insert_id);
    // Immediately after the write: SHOW WARNINGS describes the last statement only.
    $call($importer, Importer::class, 'report_lost_database_write', ['ad_copy', 'legacy-77', $table, 'insert']);

    $stored_bytes = (int) $wpdb->get_var($wpdb->prepare('SELECT LENGTH(note) FROM ' . DB::ident($table) . ' WHERE id = %d', $quiet_id));

    $check($quiet_id > 0, 'the database did not accept the plain statement at all: ' . (string) $wpdb->last_error);
    $check(strlen($oversized) > $stored_bytes && $stored_bytes > 0, sprintf('the database did not quietly shorten the value (%d bytes in, %d bytes back)', strlen($oversized), $stored_bytes));

    $repairs = (array) $state($importer, 'migration_repairs');
    $shortened = [];
    foreach ($repairs as $repair) {
        if ('legacy-77' === (string) ($repair['source_id'] ?? '')) {
            $shortened[] = (string) ($repair['reason'] ?? '');
        }
    }
    $joined_shortened = implode(' ', $shortened);
    $check([] !== $shortened, 'nothing in the report names the shortened value: ' . wp_json_encode($repairs));
    $check(1 === preg_match('/truncated|too long|incorrect string value/i', $joined_shortened), 'the report does not repeat the database reason: ' . wp_json_encode($shortened));
    $check(false !== strpos($joined_shortened, 'note'), 'the report does not name the column: ' . wp_json_encode($shortened));

    // ---- 3. A character set that cannot hold the value: both halves. ----
    $emoji_row = 'Family-run tile & stone yard ' . $wide_char . ' delivery on the north side';
    $emoji_id = (int) $call($importer, Importer::class, 'migration_insert', [$wide_table, ['note' => $emoji_row], 'ad_name', 'legacy-79']);
    $check(0 === $emoji_id, 'the prepared write holding a character outside the column character set was accepted anyway');
    $skipped = (array) $state($importer, 'migration_skips');
    $character_refused = [];
    foreach ($skipped as $skip) {
        if ('legacy-79' === (string) ($skip['source_id'] ?? '')) {
            $character_refused[] = (string) ($skip['reason'] ?? '');
        }
    }
    $check([] !== $character_refused, 'the record whose characters the column cannot hold is not named in the report');

    // On the tested WordPress and MariaDB versions, a value containing a character the
    // column's character set cannot hold is refused before the statement reaches the
    // database - so the record is reported as not stored, not quietly emptied. What a
    // narrow character set needs, then, is knowing about it before the import runs,
    // which is what the pre-flight covers in section 5.
    $rejections = (array) $state($importer, 'database_rejections');
    $check(isset($rejections[$wide_table . '.note']), 'the character-set refusal was not counted: ' . wp_json_encode($rejections));
    $check('not stored' === (string) ($rejections[$wide_table . '.note']['kind'] ?? ''), 'the character-set refusal is not described as a value that was not stored: ' . wp_json_encode($rejections[$wide_table . '.note'] ?? null));

    // ---- 4. The run-level line makes the counts visible without reading records. ----
    $summary = (string) $call($importer, Importer::class, 'database_rejection_warning', []);
    $check('' !== $summary, 'the run-level line is empty while values were lost');
    foreach ($rejections as $key => $entry) {
        $check(false !== strpos($summary, $key . ' (' . absint($entry['count']) . ')'), 'the run-level line does not name ' . $key . ' with its count: ' . $summary);
    }
    $check(false !== strpos($summary, (string) array_sum(array_map(static function ($entry) {
        return absint($entry['count'] ?? 0);
    }, $rejections))), 'the run-level line does not name the total count: ' . $summary);

    // ---- 5. The pre-flight says which tables cannot hold these characters. ----
    $source = new LegacySource();
    $capability = $source->database_capability_report([$wide_table, $table]);

    $check(false === ($capability['tables'][$wide_table]['stores_4_byte_characters'] ?? true), 'the pre-flight calls a utf8mb3 table able to hold every character: ' . wp_json_encode($capability['tables'][$wide_table] ?? null));
    $check(true === ($capability['tables'][$table]['stores_4_byte_characters'] ?? false), 'the pre-flight calls a utf8mb4 table unable to hold every character: ' . wp_json_encode($capability['tables'][$table] ?? null));
    $check(in_array($wide_table, (array) ($capability['tables_without_4_byte_support'] ?? []), true), 'the pre-flight does not name the table that drops characters');
    $check(isset($capability['tables'][$wide_table]['columns_without_4_byte_support']['note']), 'the pre-flight does not name the narrow column: ' . wp_json_encode($capability['tables'][$wide_table] ?? null));
    $check(false === ($capability['stores_4_byte_characters'] ?? true), 'the pre-flight reports the site can hold every character when one of its tables cannot');
    $check('utf8mb4' === (string) ($capability['connection']['character_set_connection'] ?? ''), 'the pre-flight does not read the connection character set: ' . wp_json_encode($capability['connection'] ?? null));

    $notices = (array) $call(new DryRun(), DryRun::class, 'capability_warnings', [$capability]);
    $joined_notices = implode(' ', $notices);
    $check(false !== stripos($joined_notices, 'character set'), 'the dry run gives no actionable notice for a table that drops characters: ' . wp_json_encode($notices));
    $check(false !== strpos($joined_notices, $wide_table), 'the dry run does not name the affected table: ' . wp_json_encode($notices));

    echo wp_json_encode([
        'sent_bytes' => strlen($oversized),
        'quietly_stored_bytes' => $stored_bytes,
        'refusal_counts' => $rejections,
        'last_error' => (string) $wpdb->last_error,
        'shortened_reported' => (bool) $shortened,
        'character_refused_reported' => (bool) $character_refused,
        'run_level_line' => $summary,
        'dry_run_notice' => $notices,
    ]) . PHP_EOL;
} finally {
    $wpdb->query($wpdb->prepare('SET SESSION sql_mode = %s', $sql_mode));
    $drop($table);
    $drop($wide_table);
}

if ($fails) {
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

echo 'MDS3 database write limits fixture passed.' . PHP_EOL;
exit(0);
