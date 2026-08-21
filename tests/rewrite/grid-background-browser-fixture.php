<?php
/**
 * Seed/cleanup helper for the grid-background browser regression.
 */

use MillionDollarScript\V3\Grid\GridRepository;

if (!defined('ABSPATH')) {
    exit;
}

$state_option = 'mds3_grid_background_browser_fixture_state';
$action = sanitize_key((string) getenv('MDS_GRID_BACKGROUND_FIXTURE_ACTION'));
$state = get_option($state_option, []);
$state = is_array($state) ? $state : [];

$cleanup = static function (array $values) {
    if (!empty($values['post_id'])) {
        wp_delete_post(absint($values['post_id']), true);
    }
    if (!empty($values['grid_id'])) {
        (new GridRepository())->delete(absint($values['grid_id']));
    }
    if (!empty($values['attachment_id'])) {
        wp_delete_attachment(absint($values['attachment_id']), true);
    }
};

if ('cleanup' === $action) {
    $cleanup($state);
    delete_option($state_option);
    echo "Grid background browser fixture cleaned.\n";
    return;
}

if ('seed' !== $action) {
    throw new RuntimeException('Set MDS_GRID_BACKGROUND_FIXTURE_ACTION to seed or cleanup.');
}

$cleanup($state);

$upload = wp_upload_bits(
    'mds-grid-background-browser.png',
    null,
    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACAQMAAABIeJ9nAAAAIGNIUk0AAHomAACAhAAA+gAAAIDoAAB1MAAA6mAAADqYAAAXcJy6UTwAAAAGUExURf8AAP///0EdNBEAAAABYktHRAH/Ai3eAAAAB3RJTUUH6ggKAzMZtoBKSAAAACV0RVh0ZGF0ZTpjcmVhdGUAMjAyNi0wOC0xMFQwMzo1MToyNSswMDowMMMoadwAAAAldEVYdGRhdGU6bW9kaWZ5ADIwMjYtMDgtMTBUMDM6NTE6MjUrMDA6MDCyddFgAAAAKHRFWHRkYXRlOnRpbWVzdGFtcAAyMDI2LTA4LTEwVDAzOjUxOjI1KzAwOjAw5WDwvwAAAAxJREFUCNdjYGBgAAAABAABJzQnCgAAAABJRU5ErkJggg==')
);
if (!empty($upload['error'])) {
    throw new RuntimeException((string) $upload['error']);
}

$attachment_id = wp_insert_attachment([
    'post_mime_type' => 'image/png',
    'post_title' => 'Grid background browser fixture',
    'post_status' => 'inherit',
], $upload['file']);
if (is_wp_error($attachment_id) || !$attachment_id) {
    throw new RuntimeException('Could not create the browser background attachment.');
}

require_once ABSPATH . 'wp-admin/includes/image.php';
$metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
if (is_array($metadata)) {
    wp_update_attachment_metadata($attachment_id, $metadata);
}

$grid = (new GridRepository())->create([
    'title' => 'Grid Background Browser Fixture',
    'width' => 200,
    'height' => 200,
    'block_width' => 10,
    'block_height' => 10,
    'status' => 'active',
    'settings' => [
        'renderer_mode' => 'classic',
        'background_color' => '#123456',
        'background_image_id' => $attachment_id,
        'background_image_fit' => 'cover',
        'background_image_position' => 'center',
        'background_image_repeat' => 'no-repeat',
        'background_image_opacity' => 100,
    ],
]);
if (is_wp_error($grid)) {
    wp_delete_attachment($attachment_id, true);
    throw new RuntimeException($grid->get_error_message());
}

$post_id = wp_insert_post([
    'post_title' => 'Grid Background Browser Fixture',
    'post_content' => '[mds_grid id="' . $grid->id() . '" read_only="true"]',
    'post_status' => 'publish',
    'post_type' => 'page',
]);
if (is_wp_error($post_id) || !$post_id) {
    (new GridRepository())->delete($grid->id());
    wp_delete_attachment($attachment_id, true);
    throw new RuntimeException('Could not create the browser background page.');
}

update_option($state_option, [
    'attachment_id' => absint($attachment_id),
    'grid_id' => $grid->id(),
    'post_id' => absint($post_id),
], false);

echo wp_json_encode([
    'admin_url' => admin_url('admin.php?page=mds3-grids&grid_id=' . $grid->id()),
    'attachment_id' => absint($attachment_id),
    'grid_id' => $grid->id(),
    'url' => get_permalink($post_id),
]) . "\n";

