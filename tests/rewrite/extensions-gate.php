<?php
/**
 * Extension gate driver: activate every synced MDS extension and run its
 * local workflow fixture (tests/mds3-fixture.php) when present.
 *
 * Usage:
 *   ./scripts/wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/extensions-gate.php
 *
 * The wrapper (scripts/mds3-extensions-gate.sh) writes the slug list to
 * wp-content/mds3-extensions-gate.json before invoking this file.
 */

if (!defined('ABSPATH')) {
    exit;
}

$list_file = defined('MDS3_GATE_MANIFEST') ? MDS3_GATE_MANIFEST : WP_CONTENT_DIR . '/mds3-extensions-gate.json';
if (!file_exists($list_file)) {
    fwrite(STDERR, "extensions-gate: missing $list_file — run scripts/mds3-extensions-gate.sh from the workspace.\n");
    exit(1);
}
$manifest = json_decode((string) file_get_contents($list_file), true);
$slugs = is_array($manifest) ? (array) ($manifest['slugs'] ?? []) : [];
if (!$slugs) {
    fwrite(STDERR, "extensions-gate: no extension slugs listed.\n");
    exit(1);
}

wp_set_current_user(1);
$failures = [];
$passed = 0;

foreach ($slugs as $slug) {
    $slug = (string) $slug;
    $plugin_file = WP_PLUGIN_DIR . "/$slug/$slug.php";
    if (!file_exists($plugin_file)) {
        $failures[] = "$slug — plugin file missing: $plugin_file";
        echo "FAIL $slug (missing)\n";
        continue;
    }

    echo '[' . date('H:i:s') . "] >> $slug: activate\n";
    try {
        $result = activate_plugin("$slug/$slug.php");
        if (is_wp_error($result)) {
            $failures[] = "$slug — activation failed: {$result->get_error_message()}";
            echo "FAIL $slug (activation)\n";
            continue;
        }
    } catch (Throwable $e) {
        $failures[] = "$slug — activation threw " . get_class($e) . ': ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')';
        echo "FAIL $slug (activation)\n";
        continue;
    }

    $fixture = WP_PLUGIN_DIR . "/$slug/tests/mds3-fixture.php";
    if (file_exists($fixture)) {
        echo '[' . date('H:i:s') . "] >> $slug: fixture\n";
        try {
            include $fixture;
        } catch (Throwable $e) {
            $failures[] = "$slug — fixture threw " . get_class($e) . ': ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')';
            echo "FAIL $slug (fixture)\n";
            continue;
        }
    }

    $passed++;
    echo "PASS $slug\n";
}

echo "\n";
if ($failures) {
    fwrite(STDERR, "Extension gate failed (" . count($failures) . "/" . count($slugs) . "):\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Extension gate passed: " . $passed . "/" . count($slugs) . " extensions activated.\n";