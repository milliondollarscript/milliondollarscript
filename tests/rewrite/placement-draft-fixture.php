<?php
/**
 * WP-CLI fixture for server-side placement image drafts.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/placement-draft-fixture.php
 */

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementDraftRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderCleanup;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\ReservationService;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

function mds3_draft_fixture_attachment($label = 'draft') {
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        throw new RuntimeException('Could not resolve uploads directory: ' . $uploads['error']);
    }

    $filename = 'mds3-placement-' . sanitize_file_name($label) . '-' . wp_generate_uuid4() . '.png';
    $path = trailingslashit($uploads['path']) . $filename;
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAIAAAD8GO2jAAAAK0lEQVR4nGNk+M+ABzAwMDCqGqmgmXq1UA0YGRkZVQxqYGBgYFS1AwBKYgJBMqx7ygAAAABJRU5ErkJggg==');
    if (!$png || false === file_put_contents($path, $png)) {
        throw new RuntimeException('Could not write placement draft fixture image.');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'Placement Draft Fixture ' . $label,
        'post_status' => 'inherit',
    ], $path);

    if (is_wp_error($attachment_id) || !$attachment_id) {
        throw new RuntimeException('Could not create placement draft fixture attachment.');
    }

    $metadata = wp_generate_attachment_metadata($attachment_id, $path);
    if (!is_array($metadata) || empty($metadata['width']) || empty($metadata['height'])) {
        $metadata = [
            'width' => 32,
            'height' => 32,
            'file' => ltrim(str_replace(trailingslashit($uploads['basedir']), '', $path), '/'),
        ];
    }
    wp_update_attachment_metadata($attachment_id, $metadata);

    return absint($attachment_id);
}

function mds3_draft_fixture_save_draft(PlacementDraftRepository $drafts, OrderRepository $orders, array $order, array $rect, $attachment_id) {
    $record = new ReflectionMethod($drafts, 'draft_record');
    $record->setAccessible(true);
    $mark = new ReflectionMethod($drafts, 'mark_attachment');
    $mark->setAccessible(true);
    $save = new ReflectionMethod($drafts, 'save_order_draft');
    $save->setAccessible(true);

    $draft = $record->invoke($drafts, absint($attachment_id), $order, $rect);
    $mark->invoke($drafts, absint($attachment_id), $draft);
    $updated = $save->invoke($drafts, $orders, $order, $draft);
    if (is_wp_error($updated)) {
        throw new RuntimeException('Could not save placement draft metadata: ' . $updated->get_error_message());
    }

    return $draft;
}

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    throw new RuntimeException('Placement draft fixture requires an administrator user.');
}
wp_set_current_user(absint($admin[0]));

$grid_repo = new GridRepository();
$orders = new OrderRepository();
$drafts = new PlacementDraftRepository();
$placements = new PlacementRepository();
$grid = null;
$order_id = 0;
$second_order_id = 0;
$attachment_ids = [];

try {
    $grid = $grid_repo->create([
        'title' => 'Placement Draft Fixture',
        'width' => 100,
        'height' => 100,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 1,
        'status' => 'active',
    ]);
    if (is_wp_error($grid)) {
        throw new RuntimeException('Could not create placement draft fixture grid: ' . $grid->get_error_message());
    }

    $reservation = (new ReservationService())->reserve($grid, [
        ['row' => 0, 'col' => 0],
    ], [
        'email' => 'placement-draft-fixture@example.test',
    ]);
    if (is_wp_error($reservation)) {
        throw new RuntimeException('Could not reserve fixture block: ' . $reservation->get_error_message());
    }
    $order_id = absint($reservation['order']['id'] ?? 0);
    $order = $orders->find($order_id);
    $rect = $orders->item_rect($order_id);
    if (!$order || !$rect) {
        throw new RuntimeException('Fixture order did not create a placement rect.');
    }

    $attachment_ids[] = $draft_attachment_id = mds3_draft_fixture_attachment('current');
    $draft = mds3_draft_fixture_save_draft($drafts, $orders, $order, $rect, $draft_attachment_id);
    $refreshed_order = $orders->find($order_id);
    $current = $drafts->current($refreshed_order);
    if (!$current || absint($current['attachment_id'] ?? 0) !== $draft_attachment_id) {
        throw new RuntimeException('Current draft was not restored for the matching order.');
    }
    $payload = $drafts->payload($current);
    if (empty($payload['source']['url']) || absint($payload['width'] ?? 0) !== absint($rect['width'] ?? 0)) {
        throw new RuntimeException('Draft payload did not expose safe preview metadata.');
    }

    $second = (new ReservationService())->reserve($grid, [
        ['row' => 1, 'col' => 1],
    ], [
        'email' => 'placement-draft-second@example.test',
    ]);
    if (is_wp_error($second)) {
        throw new RuntimeException('Could not reserve second fixture block: ' . $second->get_error_message());
    }
    $second_order_id = absint($second['order']['id'] ?? 0);
    if ($drafts->current($orders->find($second_order_id))) {
        throw new RuntimeException('Draft image was visible to a different order.');
    }

    if ($placements->for_order($order_id)) {
        throw new RuntimeException('Draft image created a public placement before final save.');
    }

    $wrong_remove = $drafts->remove($orders, $refreshed_order, 'wrong-token');
    if (!is_wp_error($wrong_remove)) {
        throw new RuntimeException('Removing a draft with the wrong token should fail.');
    }

    $consumed = $drafts->consume($orders, $refreshed_order, $draft_attachment_id, (string) ($draft['token'] ?? ''));
    if (!$consumed || $drafts->current($orders->find($order_id))) {
        throw new RuntimeException('Consuming a draft did not clear order draft metadata.');
    }
    if ('' !== (string) get_post_meta($draft_attachment_id, '_mds3_placement_draft_token', true)) {
        throw new RuntimeException('Consuming a draft did not clear attachment draft ownership marks.');
    }

    $placement_id = $placements->create([
        'grid_id' => $grid->id(),
        'block_id' => absint($rect['block_id'] ?? 0),
        'order_id' => $order_id,
        'attachment_id' => $draft_attachment_id,
        'x' => absint($rect['x'] ?? 0),
        'y' => absint($rect['y'] ?? 0),
        'width' => absint($rect['width'] ?? 1),
        'height' => absint($rect['height'] ?? 1),
        'fit_mode' => 'cover',
        'status' => 'pending',
    ]);
    if (is_wp_error($placement_id)) {
        throw new RuntimeException('Consumed draft attachment could not be used for a placement: ' . $placement_id->get_error_message());
    }

    $attachment_ids[] = $removed_attachment_id = mds3_draft_fixture_attachment('remove');
    $remove_draft = mds3_draft_fixture_save_draft($drafts, $orders, $orders->find($second_order_id), $orders->item_rect($second_order_id), $removed_attachment_id);
    $removed = $drafts->remove($orders, $orders->find($second_order_id), (string) ($remove_draft['token'] ?? ''));
    if (!$removed || 'attachment' === get_post_type($removed_attachment_id)) {
        throw new RuntimeException('Removing a verified draft did not delete the draft attachment.');
    }

    $third = (new ReservationService())->reserve($grid, [
        ['row' => 2, 'col' => 2],
    ], [
        'email' => 'placement-draft-stale@example.test',
    ]);
    if (is_wp_error($third)) {
        throw new RuntimeException('Could not reserve stale fixture block: ' . $third->get_error_message());
    }
    $third_order_id = absint($third['order']['id'] ?? 0);
    $attachment_ids[] = $stale_attachment_id = mds3_draft_fixture_attachment('stale');
    mds3_draft_fixture_save_draft($drafts, $orders, $orders->find($third_order_id), $orders->item_rect($third_order_id), $stale_attachment_id);
    update_post_meta($stale_attachment_id, '_mds3_placement_draft_expires_at', time() - 60);
    $cleanup = (new OrderCleanup())->run(20);
    if (empty($cleanup['drafts_deleted']) || 'attachment' === get_post_type($stale_attachment_id)) {
        throw new RuntimeException('Order cleanup did not delete stale draft attachments.');
    }
} finally {
    global $wpdb;

    foreach ([$order_id, $second_order_id, isset($third_order_id) ? $third_order_id : 0] as $cleanup_order_id) {
        if (!$cleanup_order_id) {
            continue;
        }
        $wpdb->delete(DB::table('placements'), ['order_id' => absint($cleanup_order_id)]);
        $wpdb->delete(DB::table('order_items'), ['order_id' => absint($cleanup_order_id)]);
        $wpdb->delete(DB::table('orders'), ['id' => absint($cleanup_order_id)]);
    }
    if ($grid && !is_wp_error($grid)) {
        $wpdb->delete(DB::table('blocks'), ['grid_id' => $grid->id()]);
        $grid_repo->delete($grid->id());
    }
    foreach (array_unique(array_map('absint', $attachment_ids)) as $attachment_id) {
        if ($attachment_id && 'attachment' === get_post_type($attachment_id)) {
            wp_delete_attachment($attachment_id, true);
        }
    }
}

echo wp_json_encode([
    'status' => 'passed',
]) . "\n";
