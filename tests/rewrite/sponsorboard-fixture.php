<?php
/**
 * WP-CLI SponsorBoard payment-provider fixture for MDS3.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/sponsorboard-fixture.php
 */

use MillionDollarScript\Commerce\Payments;
use MillionDollarScript\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(Repository::class) || !class_exists(Payments::class) || !function_exists('wc_create_order') || !Payments::provider_ready('woocommerce')) {
    echo wp_json_encode(['skipped' => 'sponsorboard_or_woocommerce_unavailable']) . "\n";
    return;
}

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    throw new RuntimeException('SponsorBoard fixture requires an administrator user.');
}
wp_set_current_user(absint($admin[0]));

$original_settings = get_option('mds3_settings', []);
$original_settings = is_array($original_settings) ? $original_settings : [];
$settings = $original_settings;
$settings['payment_provider'] = 'woocommerce';
if (function_exists('get_woocommerce_currency')) {
    $settings['currency'] = get_woocommerce_currency();
}
if (function_exists('get_woocommerce_currency_symbol')) {
    $settings['currency-symbol'] = get_woocommerce_currency_symbol($settings['currency'] ?? '');
}
update_option('mds3_settings', $settings, false);

Repository::maybe_install();

$fixture_user = get_user_by('login', 'mds_sponsorboard_customer');
if (!$fixture_user) {
    $role = function_exists('wp_roles') && wp_roles()->is_role('customer') ? 'customer' : 'subscriber';
    $fixture_user_id = wp_insert_user([
        'user_login' => 'mds_sponsorboard_customer',
        'user_pass' => wp_generate_password(24),
        'user_email' => 'mds-sponsorboard-customer@example.test',
        'display_name' => 'MDS 3.0 SponsorBoard Fixture Customer',
        'role' => $role,
    ]);
    if (is_wp_error($fixture_user_id)) {
        update_option('mds3_settings', $original_settings, false);
        throw new RuntimeException($fixture_user_id->get_error_message());
    }
    $fixture_user = get_userdata(absint($fixture_user_id));
}

$fixture_user_id = $fixture_user ? absint($fixture_user->ID) : 0;
if (!$fixture_user_id) {
    update_option('mds3_settings', $original_settings, false);
    throw new RuntimeException('Could not create or load SponsorBoard fixture customer user.');
}

$board_id = 0;
$slot_id = 0;
$booking_id = 0;
$cancelled_booking_id = 0;
$wc_order_id = 0;

try {
    $board_id = Repository::create_board([
        'title' => 'SponsorBoard Payment Fixture ' . gmdate('YmdHis'),
        'description' => 'Automated payment provider fixture.',
        'status' => 'active',
        'layout' => 'grid',
        'currency' => (string) ($settings['currency'] ?? 'USD'),
        'settings' => [
            'columns' => 1,
            'checkout_enabled' => '1',
            'payment_provider' => 'woocommerce',
            'disclosure_label' => 'Sponsored',
        ],
    ]);
    if (!$board_id) {
        throw new RuntimeException('Could not create SponsorBoard fixture board.');
    }

    $slot_id = Repository::create_slot([
        'board_id' => $board_id,
        'title' => 'Fixture Sponsor Slot',
        'price' => 9.99,
        'currency' => (string) ($settings['currency'] ?? 'USD'),
        'status' => 'available',
        'position' => 1,
        'dimensions' => '600x300',
    ]);
    if (!$slot_id) {
        throw new RuntimeException('Could not create SponsorBoard fixture slot.');
    }

    $booking_result = Repository::create_booking([
        'board_id' => $board_id,
        'slot_id' => $slot_id,
        'user_id' => $fixture_user_id,
        'sponsor_name' => 'Fixture Sponsor',
        'sponsor_email' => sanitize_email((string) ($fixture_user->user_email ?? '')),
        'sponsor_url' => 'https://example.test/sponsor',
        'terms_accepted' => true,
        'status' => 'pending_payment',
        'creative_payload' => [
            'creative_url' => 'https://example.test/creative.png',
            'note' => 'Fixture payment flow.',
        ],
        'metadata' => [
            'payment_provider' => Payments::active_provider_id($settings),
        ],
    ]);
    if (is_wp_error($booking_result)) {
        throw new RuntimeException('Could not create SponsorBoard fixture booking: ' . $booking_result->get_error_message());
    }
    if (!$booking_result || empty($booking_result['booking']['id']) || empty($booking_result['token'])) {
        throw new RuntimeException('Could not create SponsorBoard fixture booking.');
    }

    $booking = is_array($booking_result['booking']) ? $booking_result['booking'] : [];
    $booking_id = absint($booking['id'] ?? 0);
    $slot = Repository::find_slot($slot_id);
    Repository::update_slot($slot_id, array_merge($slot ?: [], ['status' => 'reserved']));

    $checkout = Payments::create_checkout([
        'source' => 'sponsorboard',
        'source_id' => $booking_id,
        'source_key' => (string) $booking_result['token'],
        'user_id' => $fixture_user_id,
        'email' => sanitize_email((string) ($fixture_user->user_email ?? '')),
        'currency' => (string) ($settings['currency'] ?? 'USD'),
        'subtotal' => 9.99,
        'total' => 9.99,
        'items' => [[
            'name' => 'SponsorBoard Fixture Slot',
            'amount' => 9.99,
            'quantity' => 1,
            'metadata' => [
                'sponsorboard_board_id' => $board_id,
                'sponsorboard_slot_id' => $slot_id,
                'sponsorboard_booking_id' => $booking_id,
            ],
        ]],
        'manage_url' => add_query_arg([
            'mds_sponsorboard_manage' => $booking_id,
            'token' => rawurlencode((string) $booking_result['token']),
        ], home_url('/')),
        'metadata' => [
            'sponsorboard_board_id' => $board_id,
            'sponsorboard_slot_id' => $slot_id,
            'sponsorboard_booking_id' => $booking_id,
        ],
    ]);

    if (is_wp_error($checkout) || !is_array($checkout) || 'woocommerce' !== ($checkout['provider'] ?? '') || empty($checkout['provider_order_id'])) {
        throw new RuntimeException('SponsorBoard checkout did not use WooCommerce provider: ' . wp_json_encode($checkout));
    }

    $wc_order_id = absint($checkout['provider_order_id']);
    $metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
    $metadata['payment_provider'] = sanitize_key((string) ($checkout['provider'] ?? ''));
    $metadata['provider_order_id'] = $wc_order_id;
    Repository::update_booking($booking_id, array_merge($booking, [
        'order_id' => $wc_order_id,
        'status' => 'pending_payment',
        'metadata' => $metadata,
    ]));

    do_action('woocommerce_payment_complete', $wc_order_id);

    $paid_booking = Repository::find_booking($booking_id);
    $sold_slot = Repository::find_slot($slot_id);
    if ('paid' !== (string) ($paid_booking['status'] ?? '') || 'sold' !== (string) ($sold_slot['status'] ?? '')) {
        throw new RuntimeException('WooCommerce payment did not sync SponsorBoard booking and slot status.');
    }

    $creative = is_array($paid_booking['creative_payload'] ?? null) ? $paid_booking['creative_payload'] : [];
    $creative['creative_status'] = 'approved';
    $approved_booking = Repository::update_booking($booking_id, array_merge($paid_booking, [
        'status' => 'approved',
        'creative_payload' => $creative,
    ]));
    $public_booking = Repository::public_booking_for_slot($slot_id);
    if (!$approved_booking || !$public_booking || absint($public_booking['id'] ?? 0) !== $booking_id) {
        throw new RuntimeException('Approved SponsorBoard booking did not become publicly renderable.');
    }

    Repository::record_event([
        'board_id' => $board_id,
        'slot_id' => $slot_id,
        'booking_id' => $booking_id,
        'event_type' => 'impression',
        'event_value' => 3,
        'metadata' => ['fixture' => 'sponsorboard'],
    ]);
    Repository::record_event([
        'board_id' => $board_id,
        'slot_id' => $slot_id,
        'booking_id' => $booking_id,
        'event_type' => 'click',
        'event_value' => 1,
        'metadata' => ['fixture' => 'sponsorboard'],
    ]);
    $booking_stats = Repository::booking_event_summary($booking_id);
    $board_stats = Repository::reporting_summary($board_id);
    if (3 !== absint($booking_stats['impressions'] ?? 0) || 1 !== absint($booking_stats['clicks'] ?? 0) || (float) ($board_stats['revenue'] ?? 0) <= 0) {
        throw new RuntimeException('SponsorBoard analytics summary did not include fixture events and revenue.');
    }

    $cancel_slot_id = Repository::create_slot([
        'board_id' => $board_id,
        'title' => 'Fixture Cancellation Slot',
        'price' => 4.99,
        'currency' => (string) ($settings['currency'] ?? 'USD'),
        'status' => 'available',
        'position' => 2,
        'dimensions' => '600x300',
    ]);
    $cancel_booking_result = Repository::create_booking([
        'board_id' => $board_id,
        'slot_id' => $cancel_slot_id,
        'user_id' => $fixture_user_id,
        'sponsor_name' => 'Fixture Cancel Sponsor',
        'sponsor_email' => sanitize_email((string) ($fixture_user->user_email ?? '')),
        'terms_accepted' => true,
        'status' => 'pending_payment',
    ]);
    if (is_wp_error($cancel_booking_result)) {
        throw new RuntimeException('Could not create SponsorBoard cancellation fixture: ' . $cancel_booking_result->get_error_message());
    }
    $cancelled_booking_id = absint($cancel_booking_result['booking']['id'] ?? 0);
    Repository::update_slot($cancel_slot_id, array_merge(Repository::find_slot($cancel_slot_id) ?: [], ['status' => 'reserved']));
    Payments::mark_source_cancelled('sponsorboard', $cancelled_booking_id, ['fixture' => 'sponsorboard']);
    $cancelled_booking = Repository::find_booking($cancelled_booking_id);
    $cancelled_slot = Repository::find_slot($cancel_slot_id);
    if ('cancelled' !== (string) ($cancelled_booking['status'] ?? '') || 'available' !== (string) ($cancelled_slot['status'] ?? '')) {
        throw new RuntimeException('SponsorBoard cancellation did not release the slot.');
    }

    Repository::update_board($board_id, array_merge(Repository::find_board($board_id) ?: [], ['status' => 'archived']));
    update_option('mds3_settings', $original_settings, false);

    echo wp_json_encode([
        'board_id' => $board_id,
        'booking_id' => $booking_id,
        'wc_order_id' => $wc_order_id,
        'booking_status' => (string) ($approved_booking['status'] ?? ''),
        'slot_status' => (string) ($sold_slot['status'] ?? ''),
        'public_booking_id' => absint($public_booking['id'] ?? 0),
        'impressions' => absint($booking_stats['impressions'] ?? 0),
        'clicks' => absint($booking_stats['clicks'] ?? 0),
        'cancelled_booking_id' => $cancelled_booking_id,
        'cancelled_status' => (string) ($cancelled_booking['status'] ?? ''),
        'cancelled_slot_status' => (string) ($cancelled_slot['status'] ?? ''),
    ]) . "\n";
} catch (Throwable $e) {
    if ($board_id) {
        Repository::update_board($board_id, array_merge(Repository::find_board($board_id) ?: [], ['status' => 'archived']));
    }
    update_option('mds3_settings', $original_settings, false);
    throw $e;
}
