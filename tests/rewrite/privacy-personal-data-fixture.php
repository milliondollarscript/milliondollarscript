<?php
/**
 * Runtime fixture: what a person sees when they ask for their data, and what is
 * gone afterwards.
 *
 * Run from the workspace root:
 *   ./scripts/wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/privacy-personal-data-fixture.php --allow-root
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

$GLOBALS['mds3_privacy_failures'] = array();

function mds3_privacy_assert($condition, $message)
{
    if (!$condition) {
        $GLOBALS['mds3_privacy_failures'][] = $message;
    }
}

function mds3_privacy_count($table, $column, $value)
{
    global $wpdb;

    return absint($wpdb->get_var(
        $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . DB::ident(DB::table($table)) . ' WHERE ' . $column . ' = %d',
            absint($value)
        )
    ));
}

/**
 * Fixture cleanup only: the same table names the code under test writes.
 */
function mds3_privacy_forget($table, $column, $value)
{
    global $wpdb;

    $wpdb->query(
        $wpdb->prepare(
            'DELETE FROM ' . DB::ident(DB::table($table)) . ' WHERE ' . $column . ' = %d',
            absint($value)
        )
    );
}

if (!is_plugin_active('million-dollar-script/million-dollar-script.php')) {
    echo "PRIVACY PERSONAL DATA FIXTURE FAILED\n- Million Dollar Script 3.0 must be active.\n";
    exit(1);
}

/**
 * The finding this fixture protects: core used to hold orders, placements and
 * uploads without ever telling WordPress it had them, so a request for personal
 * data came back empty and an erasure removed nothing.
 */
$exporters = apply_filters('wp_privacy_personal_data_exporters', array());
$erasers = apply_filters('wp_privacy_personal_data_erasers', array());
mds3_privacy_assert(isset($exporters['million-dollar-script']['callback']), 'Core does not register a personal-data exporter.');
mds3_privacy_assert(isset($erasers['million-dollar-script']['callback']), 'Core does not register a personal-data eraser.');
if (!empty($GLOBALS['mds3_privacy_failures'])) {
    echo "PRIVACY PERSONAL DATA FIXTURE FAILED\n";
    foreach ($GLOBALS['mds3_privacy_failures'] as $failure) {
        echo '- ' . $failure . "\n";
    }
    exit(1);
}

$export = $exporters['million-dollar-script']['callback'];
$erase = $erasers['million-dollar-script']['callback'];

$token = 'mds3-priv-' . strtolower(wp_generate_uuid4());
$email_guest = $token . '-guest@example.test';
$email_shared = $token . '-shared@example.test';
$email_member = $token . '-member@example.test';
$field_answer = "Rosal's Bakery - " . $token;
$ad_text_guest = 'Ad text of the guest - ' . $token;
$ad_text_shared = 'Ad text that must survive - ' . $token;

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    echo "PRIVACY PERSONAL DATA FIXTURE FAILED\n- An administrator is required.\n";
    exit(1);
}
$previous_user = get_current_user_id();
wp_set_current_user(absint($admin[0]));

$settings_option = 'mds3_settings';
$settings_backup = get_option($settings_option, null);
$settings = is_array($settings_backup) ? $settings_backup : [];
$settings['mds-pixel-template'] = 'yes';
update_option($settings_option, $settings, false);

$grids = new GridRepository();
$orders = new OrderRepository();
$placements = new PlacementRepository();
$pages = new AdvertiserPageManager();

$created = ['grid' => 0, 'orders' => [], 'placements' => [], 'attachments' => [], 'posts' => [], 'user' => 0];

try {
    $grid = $grids->create([
        'title' => 'Privacy Fixture ' . $token,
        'width' => 1000,
        'height' => 1000,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 10,
        'status' => 'active',
    ]);
    if (is_wp_error($grid)) {
        throw new RuntimeException('Could not create the fixture grid: ' . $grid->get_error_message());
    }
    $created['grid'] = absint($grid->id());

    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        throw new RuntimeException('Uploads are unavailable: ' . $uploads['error']);
    }
    $shared_image_id = 0;
    foreach ([$ad_text_guest, $ad_text_shared] as $index => $marker) {
        $path = trailingslashit($uploads['path']) . 'mds3-privacy-' . $token . '-' . $index . '.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAIAAAD8GO2jAAAAK0lEQVR4nGNk+M+ABzAwMDCqGqmgmXq1UA0YGRkZVQxqYGBgYFS1AwBKYgJBMqx7ygAAAABJRU5ErkJggg==');
        if (!$png || false === file_put_contents($path, $png)) {
            throw new RuntimeException('Could not write the fixture image.');
        }
        $attachment_id = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'Privacy Fixture', 'post_status' => 'inherit'], $path);
        if (is_wp_error($attachment_id)) {
            throw new RuntimeException('Could not create the fixture attachment.');
        }
        wp_update_attachment_metadata(absint($attachment_id), ['width' => 32, 'height' => 32, 'file' => ltrim(str_replace(trailingslashit($uploads['basedir']), '', $path), '/')]);
        $created['attachments'][] = absint($attachment_id);
        if (0 === $index) {
            // The guest's image is reused by the second person, which is the case
            // an erasure must not delete.
            $shared_image_id = absint($attachment_id);
        }
    }
    $guest_image = $created['attachments'][0];
    $other_image = $created['attachments'][1];

    $order_guest = $orders->create(
        [['grid_id' => $created['grid'], 'item_type' => 'block', 'quantity' => 1, 'unit_price' => 10, 'total' => 10]],
        [
            'email' => $email_guest,
            'user_id' => 0,
            'status' => 'paid',
            'total' => 10,
            'commerce_provider' => 'woocommerce',
            'commerce_order_id' => 'WC-' . $token,
            'metadata' => ['mds_fields' => ['Company name' => $field_answer]],
        ]
    );
    $order_shared = $orders->create(
        [['grid_id' => $created['grid'], 'item_type' => 'block', 'quantity' => 1, 'unit_price' => 10, 'total' => 10]],
        ['email' => $email_shared, 'user_id' => 0, 'status' => 'paid', 'total' => 10]
    );
    foreach ([$order_guest, $order_shared] as $id) {
        if (is_wp_error($id) || !$id) {
            throw new RuntimeException('Could not create a fixture order.');
        }
        $created['orders'][] = absint($id);
    }

    $placement_guest = $placements->create([
        'grid_id' => $created['grid'],
        'order_id' => $order_guest,
        'attachment_id' => $guest_image,
        'x' => 0,
        'y' => 0,
        'width' => 10,
        'height' => 10,
        'link_url' => 'https://example.test/' . $token,
        'alt_text' => 'Guest fixture artwork',
        'popup_text' => $ad_text_guest,
        'status' => 'active',
    ]);
    $placement_shared = $placements->create([
        'grid_id' => $created['grid'],
        'order_id' => $order_shared,
        'attachment_id' => $guest_image,
        'x' => 10,
        'y' => 0,
        'width' => 10,
        'height' => 10,
        'link_url' => 'https://example.test/other',
        'alt_text' => 'Shared fixture artwork',
        'popup_text' => $ad_text_shared,
        'status' => 'active',
    ]);
    foreach ([$placement_guest, $placement_shared] as $id) {
        if (is_wp_error($id) || !$id) {
            throw new RuntimeException('Could not create a fixture placement.');
        }
        $created['placements'][] = absint($id);
    }

    // A record that answers to an account rather than to an address typed in.
    $member = wp_insert_user([
        'user_login' => $token . '-member',
        'user_email' => $email_member,
        'role' => 'subscriber',
    ]);
    if (is_wp_error($member)) {
        throw new RuntimeException('Could not create the fixture user: ' . $member->get_error_message());
    }
    $created['user'] = absint($member);
    $order_member = $orders->create(
        [['grid_id' => $created['grid'], 'item_type' => 'block', 'quantity' => 1, 'unit_price' => 10, 'total' => 10]],
        ['email' => '', 'user_id' => absint($member), 'status' => 'paid', 'total' => 10]
    );
    if (is_wp_error($order_member) || !$order_member) {
        throw new RuntimeException('Could not create the member fixture order.');
    }
    $created['orders'][] = absint($order_member);

    $pages->synchronize(absint($placement_guest));
    $ad_page_id = absint($placements->find(absint($placement_guest))['public_post_id'] ?? 0);
    if ($ad_page_id) {
        $created['posts'][] = $ad_page_id;
    }

    // --- What a person is told about their own data.

    $reported = call_user_func($export, $email_guest, 1);
    $as_text = wp_json_encode($reported);
    mds3_privacy_assert(is_array($reported) && !empty($reported['data']), 'The export returned nothing for an order placed without an account.');
    mds3_privacy_assert(false !== strpos((string) $as_text, $field_answer), 'The export left out the answer typed into an extension field.');
    mds3_privacy_assert(false !== strpos((string) $as_text, $ad_text_guest), 'The export left out the ad text that was published.');
    mds3_privacy_assert(false !== strpos((string) $as_text, 'WC-' . $token), 'The export left out the payment reference.');
    mds3_privacy_assert(false === strpos((string) $as_text, $ad_text_shared), 'The export showed another person’s ad.');
    mds3_privacy_assert(!empty($reported['done']), 'The export did not report itself finished after one short page.');

    $reported_member = call_user_func($export, $email_member, 1);
    mds3_privacy_assert(
        false !== strpos((string) wp_json_encode($reported_member), 'mds-order-' . $order_member),
        'An order placed while signed in was not matched from the address on the account.'
    );

    // --- What is left afterwards.

    $removed = call_user_func($erase, $email_guest, 1);
    mds3_privacy_assert(is_array($removed) && !empty($removed['items_removed']), 'The eraser reported that nothing was removed.');
    mds3_privacy_assert(!empty($removed['done']), 'The eraser did not report itself done.');
    mds3_privacy_assert(!empty($removed['messages']), 'The eraser gave the site no account of what it removed.');

    mds3_privacy_assert(0 === mds3_privacy_count('orders', 'id', $order_guest), 'The order row survived an erasure request.');
    mds3_privacy_assert(0 === mds3_privacy_count('order_items', 'order_id', $order_guest), 'The order lines survived an erasure request.');
    mds3_privacy_assert(0 === mds3_privacy_count('placements', 'id', $placement_guest), 'The placement survived an erasure request.');
    if ($ad_page_id) {
        mds3_privacy_assert('trash' !== get_post_status($ad_page_id) && !get_post($ad_page_id), 'The advertiser page for the removed ad is still there.');
    }
    mds3_privacy_assert(null !== get_post($guest_image), 'An image another person’s ad is still using was deleted.');
    mds3_privacy_assert(1 === mds3_privacy_count('orders', 'id', $order_shared), 'The other person’s order was removed as well.');
    mds3_privacy_assert(1 === mds3_privacy_count('placements', 'order_id', $order_shared), 'The other person’s placement was removed as well.');

    // A site that must keep a record takes it out of the list it is handed, and the
    // request reports the record as retained rather than finished.
    $keep = static function ($ids) {
        return array();
    };
    add_filter('million-dollar-script/privacy/erase/order_ids', $keep);
    $held_back = call_user_func($erase, $email_shared, 1);
    remove_filter('million-dollar-script/privacy/erase/order_ids', $keep);
    mds3_privacy_assert(1 === mds3_privacy_count('orders', 'id', $order_shared), 'A record held back by the site’s filter was deleted anyway.');
    mds3_privacy_assert(!empty($held_back['items_retained']), 'An erasure that kept something did not say so.');

    // Now the same person with nothing holding them back: the shared image goes too,
    // because after this no placement points at it.
    $gone = call_user_func($erase, $email_shared, 1);
    mds3_privacy_assert(!empty($gone['items_removed']), 'The second erasure removed nothing.');
    mds3_privacy_assert(0 === mds3_privacy_count('orders', 'id', $order_shared), 'The order survived the second erasure.');
    mds3_privacy_assert(null === get_post($guest_image), 'The shared image was kept after the last ad using it was erased.');

    $member_removed = call_user_func($erase, $email_member, 1);
    mds3_privacy_assert(!empty($member_removed['items_removed']), 'An order held only by an account was not erased.');
    mds3_privacy_assert(0 === mds3_privacy_count('orders', 'id', $order_member), 'The account’s order survived an erasure request.');
} catch (Throwable $error) {
    $GLOBALS['mds3_privacy_failures'][] = 'Fixture error: ' . $error->getMessage();
}

foreach ($created['posts'] as $post_id) {
    wp_delete_post($post_id, true);
}
foreach ($created['placements'] as $placement_id) {
    mds3_privacy_forget('placements', 'id', $placement_id);
}
foreach ($created['orders'] as $order_id) {
    mds3_privacy_forget('order_items', 'order_id', $order_id);
    mds3_privacy_forget('orders', 'id', $order_id);
}
foreach ($created['attachments'] as $attachment_id) {
    wp_delete_attachment($attachment_id, true);
}
if ($created['grid']) {
    $grids->delete($created['grid']);
}
if ($created['user']) {
    wp_delete_user($created['user']);
}
if (null === $settings_backup) {
    delete_option($settings_option);
} else {
    update_option($settings_option, $settings_backup, false);
}
wp_set_current_user($previous_user);

if (!empty($GLOBALS['mds3_privacy_failures'])) {
    echo "PRIVACY PERSONAL DATA FIXTURE FAILED\n";
    foreach ($GLOBALS['mds3_privacy_failures'] as $failure) {
        echo '- ' . $failure . "\n";
    }
    exit(1);
}

echo "PRIVACY PERSONAL DATA FIXTURE PASSED\n";
