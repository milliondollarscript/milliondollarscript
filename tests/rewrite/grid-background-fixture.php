<?php
/**
 * WordPress fixture for per-grid background presentation.
 *
 * Run with:
 * ./scripts/wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/grid-background-fixture.php
 */

if (!defined('ABSPATH')) {
    exit;
}

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Grid\GridTransfer;

$failures = [];
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$attachment_id = 0;
$grid_ids = [];

try {
    $upload = wp_upload_bits(
        'mds-grid-background-fixture.png',
        null,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACAQMAAABIeJ9nAAAAIGNIUk0AAHomAACAhAAA+gAAAIDoAAB1MAAA6mAAADqYAAAXcJy6UTwAAAAGUExURf8AAP///0EdNBEAAAABYktHRAH/Ai3eAAAAB3RJTUUH6ggKAzMZtoBKSAAAACV0RVh0ZGF0ZTpjcmVhdGUAMjAyNi0wOC0xMFQwMzo1MToyNSswMDowMMMoadwAAAAldEVYdGRhdGU6bW9kaWZ5ADIwMjYtMDgtMTBUMDM6NTE6MjUrMDA6MDCyddFgAAAAKHRFWHRkYXRlOnRpbWVzdGFtcAAyMDI2LTA4LTEwVDAzOjUxOjI1KzAwOjAw5WDwvwAAAAxJREFUCNdjYGBgAAAABAABJzQnCgAAAABJRU5ErkJggg==')
    );
    if (!empty($upload['error'])) {
        throw new RuntimeException((string) $upload['error']);
    }

    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'Grid background fixture',
        'post_status' => 'inherit',
    ], $upload['file']);
    if (is_wp_error($attachment_id) || !$attachment_id) {
        throw new RuntimeException('Could not create the background fixture attachment.');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
    if (is_array($metadata)) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    $repo = new GridRepository();
    $grid = $repo->create([
        'title' => 'Background fixture grid',
        'width' => 1000,
        'height' => 1000,
        'block_width' => 10,
        'block_height' => 10,
        'settings' => [
            'background_color' => '#123456',
            'background_image_id' => $attachment_id,
            'background_image_fit' => 'contain',
            'background_image_position' => 'bottom-right',
            'background_image_repeat' => 'repeat-x',
            'background_image_opacity' => 42,
        ],
    ]);
    if (is_wp_error($grid)) {
        throw new RuntimeException($grid->get_error_message());
    }
    $grid_ids[] = $grid->id();

    $data = $grid->to_array();
    $assert('#123456' === ($data['settings']['background_color'] ?? ''), 'Grid background color should remain in the grid settings payload.');
    $assert(wp_get_attachment_image_url($attachment_id, 'full') === ($data['background_image']['url'] ?? ''), 'The public grid payload should expose the validated local attachment URL.');
    $assert('contain' === ($data['background_image']['fit'] ?? ''), 'The public grid payload should preserve image fit.');
    $assert('bottom-right' === ($data['background_image']['position'] ?? ''), 'The public grid payload should preserve image position.');
    $assert('repeat-x' === ($data['background_image']['repeat'] ?? ''), 'The public grid payload should preserve image repeat.');
    $assert(42 === ($data['background_image']['opacity'] ?? null), 'The public grid payload should preserve bounded image opacity.');

    $updated = $repo->update($grid->id(), [
        'background_image_id' => $attachment_id,
        'background_image_fit' => 'unsafe-value',
        'background_image_position' => 'unsafe-value',
        'background_image_repeat' => 'unsafe-value',
        'background_image_opacity' => 999,
    ]);
    $updated_settings = $updated->settings();
    $assert('cover' === ($updated_settings['background_image_fit'] ?? ''), 'Invalid image fit should fall back safely.');
    $assert('center' === ($updated_settings['background_image_position'] ?? ''), 'Invalid image position should fall back safely.');
    $assert('no-repeat' === ($updated_settings['background_image_repeat'] ?? ''), 'Invalid image repeat should fall back safely.');
    $assert(100 === ($updated_settings['background_image_opacity'] ?? null), 'Image opacity should be capped at 100.');

    $transfer = new GridTransfer();
    $payload = $transfer->export_payload([$grid->id()]);
    if (is_wp_error($payload)) {
        throw new RuntimeException($payload->get_error_message());
    }
    $exported_settings = $payload['grids'][0]['grid']['settings'] ?? [];
    $assert(!isset($exported_settings['background_image_id']), 'Grid exports should not contain a site-specific attachment ID.');
    $assert(wp_get_attachment_image_url($attachment_id, 'full') === ($exported_settings['background_image_url'] ?? ''), 'Grid exports should identify a same-site background by URL.');

    $imported = $transfer->import_payload($payload);
    if (is_wp_error($imported)) {
        throw new RuntimeException($imported->get_error_message());
    }
    $imported_id = absint($imported['created'][0]['id'] ?? 0);
    $grid_ids[] = $imported_id;
    $imported_grid = $repo->find($imported_id);
    $assert($imported_grid && $attachment_id === absint($imported_grid->settings()['background_image_id'] ?? 0), 'A same-site grid import should reconnect the existing background attachment by URL.');

    $missing = $repo->update($grid->id(), ['background_image_id' => 999999999]);
    $assert($missing && 0 === absint($missing->settings()['background_image_id'] ?? 0), 'A missing or deleted attachment should fall back without a public image URL.');
    $assert([] === ($missing->to_array()['background_image'] ?? null), 'A missing background attachment should produce an empty public background payload.');
} catch (Throwable $throwable) {
    $failures[] = $throwable->getMessage();
} finally {
    $repo = new GridRepository();
    foreach (array_unique(array_filter($grid_ids)) as $grid_id) {
        $repo->delete($grid_id);
    }
    if ($attachment_id) {
        wp_delete_attachment($attachment_id, true);
    }
}

if ($failures) {
    fwrite(STDERR, "Grid background fixture failures:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Grid background fixture passed.\n";
