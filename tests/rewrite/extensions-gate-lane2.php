<?php
/**
 * Parallel lane 2 runner for the MDS3 extension gate.
 *
 * The wrapper writes a second manifest with the heavy fixtures (currently
 * mds-time-capsule, ~85s of post-creation work) and runs this file in a
 * separate WP-CLI process alongside the main lane. Its fixture writes no
 * shared options, so it cannot race the mds-woocommerce mds3_settings writes.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MDS3_GATE_MANIFEST', WP_CONTENT_DIR . '/mds3-extensions-gate-lane2.json');

require __DIR__ . '/extensions-gate.php';
