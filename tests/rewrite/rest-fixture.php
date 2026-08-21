<?php
/**
 * WP-CLI REST fixture for MDS3.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/rest-fixture.php
 */

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Grid\GridPostType;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Grid\GridStats;
use MillionDollarScript\V3\Grid\PackageRepository;
use MillionDollarScript\V3\Grid\PriceRuleRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderCleanup;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\OrderRenewal;
use MillionDollarScript\V3\Orders\ReservationService;
use MillionDollarScript\V3\Rendering\TileController;
use MillionDollarScript\V3\Rest\ApiKeyRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Setup\OrderExpirationBackfill;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    throw new RuntimeException('REST fixture requires an administrator user.');
}
wp_set_current_user(absint($admin[0]));

$expiration_repository = new OrderRepository();
OrderRepository::invalidate_overview_cache();
$counts_before_backfill_order = $expiration_repository->counts_by_status();
$backfill_expires_at = gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS);
$backfill_order_id = $expiration_repository->create([], [
    'email' => 'expiration-backfill-fixture@example.com',
    'status' => 'paid',
    'metadata' => ['expires_at' => $backfill_expires_at],
]);
if (is_wp_error($backfill_order_id)) {
    throw new RuntimeException($backfill_order_id->get_error_message());
}
$counts_after_backfill_order = $expiration_repository->counts_by_status();
if (absint($counts_after_backfill_order['paid'] ?? 0) !== absint($counts_before_backfill_order['paid'] ?? 0) + 1) {
    throw new RuntimeException('Order overview cache was not invalidated after an order create.');
}
$expiration_repository->update($backfill_order_id, ['status' => 'cancelled']);
$counts_after_status_change = $expiration_repository->counts_by_status();
if (absint($counts_after_status_change['paid'] ?? 0) !== absint($counts_before_backfill_order['paid'] ?? 0)) {
    throw new RuntimeException('Order overview cache was not invalidated after a status change.');
}
$expiration_repository->update($backfill_order_id, ['status' => 'paid']);
$GLOBALS['wpdb']->update(DB::table('orders'), ['expires_at' => null], ['id' => absint($backfill_order_id)]);
$backfill_complete_option = 'mds3_order_expiration_backfill_version';
$backfill_cursor_option = 'mds3_order_expiration_backfill_cursor';
$previous_backfill_complete = get_option($backfill_complete_option, null);
$previous_backfill_cursor = get_option($backfill_cursor_option, null);
$previous_backfill_schedule = wp_next_scheduled(OrderExpirationBackfill::HOOK);
wp_clear_scheduled_hook(OrderExpirationBackfill::HOOK);
delete_option($backfill_complete_option);
update_option($backfill_cursor_option, max(0, absint($backfill_order_id) - 1), false);
OrderExpirationBackfill::maybe_schedule();
if (!wp_next_scheduled(OrderExpirationBackfill::HOOK)) {
    throw new RuntimeException('Existing-order expiration backfill did not schedule bounded cron work.');
}
wp_clear_scheduled_hook(OrderExpirationBackfill::HOOK);
OrderExpirationBackfill::run_scheduled();
$backfilled_order = (new OrderRepository())->find($backfill_order_id);
if ($backfill_expires_at !== (string) ($backfilled_order['expires_at'] ?? '')) {
    throw new RuntimeException('Existing-order expiration backfill did not normalize metadata.');
}
$GLOBALS['wpdb']->delete(DB::table('orders'), ['id' => absint($backfill_order_id)]);
OrderRepository::invalidate_overview_cache();
if (null === $previous_backfill_complete) {
    delete_option($backfill_complete_option);
} else {
    update_option($backfill_complete_option, $previous_backfill_complete, false);
}
if (null === $previous_backfill_cursor) {
    delete_option($backfill_cursor_option);
} else {
    update_option($backfill_cursor_option, $previous_backfill_cursor, false);
}
wp_clear_scheduled_hook(OrderExpirationBackfill::HOOK);
if ($previous_backfill_schedule) {
    wp_schedule_single_event(max(time() + 1, absint($previous_backfill_schedule)), OrderExpirationBackfill::HOOK);
}

$placement_contract_settings = get_option('mds3_settings', []);
$placement_contract_settings = is_array($placement_contract_settings) ? $placement_contract_settings : [];
$placement_contract_grid = null;
$placement_contract_ids = [];
try {
    $placement_contract_grid = (new GridRepository())->create([
        'title' => 'REST Placement Contract Fixture',
        'width' => 10,
        'height' => 10,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 1,
        'currency' => 'USD',
        'status' => 'active',
    ]);
    if (is_wp_error($placement_contract_grid)) {
        throw new RuntimeException('Could not create REST placement contract fixture grid.');
    }

    $placement_path = '/million-dollar-script/v1/grids/' . $placement_contract_grid->id() . '/placements';
    wp_set_current_user(0);
    $unauthorized_placement = new WP_REST_Request('POST', $placement_path);
    $unauthorized_placement->set_body_params(['attachment_id' => 1]);
    if (!rest_do_request($unauthorized_placement)->is_error()) {
        throw new RuntimeException('Placement creation allowed an unauthenticated request.');
    }

    wp_set_current_user(absint($admin[0]));
    $required_settings = wp_parse_args($placement_contract_settings, SettingsSchema::defaults());
    $required_settings['url-optional'] = 'no';
    $required_settings['text-optional'] = 'no';
    update_option('mds3_settings', $required_settings, false);
    $missing_required = new WP_REST_Request('POST', $placement_path);
    $missing_required->set_body_params(['attachment_id' => 1]);
    $missing_required_response = rest_do_request($missing_required);
    if (!$missing_required_response->is_error() || 'million_dollar_script_url_required' !== $missing_required_response->as_error()->get_error_code()) {
        throw new RuntimeException('Placement creation did not enforce the required built-in field contract.');
    }

    $hidden_settings = $required_settings;
    $hidden_settings['url-optional'] = 'hidden';
    $hidden_settings['text-optional'] = 'hidden';
    update_option('mds3_settings', $hidden_settings, false);
    $hidden_placement = new WP_REST_Request('POST', $placement_path);
    $hidden_placement->set_body_params([
        'attachment_id' => 1,
        'x' => 0,
        'y' => 0,
        'width' => 10,
        'height' => 10,
        'link_url' => 'https://ignored.example.test/',
        'popup_text' => 'Ignored hidden value',
        'alt_text' => 'REST contract fixture',
        'status' => 'pending',
    ]);
    $hidden_placement_response = rest_do_request($hidden_placement);
    if ($hidden_placement_response->is_error()) {
        throw new RuntimeException('Placement creation rejected a valid hidden-field request: ' . $hidden_placement_response->as_error()->get_error_message());
    }
    $hidden_placement_id = absint($hidden_placement_response->get_data()['id'] ?? 0);
    $placement_contract_ids[] = $hidden_placement_id;
    $hidden_placement_row = (new PlacementRepository())->find($hidden_placement_id);
    if (!$hidden_placement_row || '' !== (string) ($hidden_placement_row['link_url'] ?? '') || '' !== (string) ($hidden_placement_row['popup_text'] ?? '')) {
        throw new RuntimeException('Placement creation persisted submitted values for hidden built-in fields.');
    }
} finally {
    global $wpdb;

    foreach ($placement_contract_ids as $placement_contract_id) {
        $wpdb->delete(DB::table('placements'), ['id' => absint($placement_contract_id)]);
    }
    if ($placement_contract_grid && !is_wp_error($placement_contract_grid)) {
        (new GridRepository())->delete($placement_contract_grid->id());
    }
    update_option('mds3_settings', $placement_contract_settings, false);
    wp_set_current_user(absint($admin[0]));
}

$original_currency_settings = get_option('mds3_settings', []);
$original_currency_settings = is_array($original_currency_settings) ? $original_currency_settings : [];
$currency_settings = wp_parse_args($original_currency_settings, SettingsSchema::defaults());
$currency_settings['payment_provider'] = 'standalone';
$currency_settings['currency'] = 'CAD';
$currency_settings['currency-symbol'] = 'C$';
update_option('mds3_settings', $currency_settings, false);
$currency_grid = null;
$currency_order_id = 0;
try {
    $currency_grid = (new GridRepository())->create([
        'title' => 'REST Currency Fixture',
        'width' => 10,
        'height' => 10,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 1,
        'status' => 'active',
    ]);
    if (is_wp_error($currency_grid)) {
        throw new RuntimeException('Could not create currency fixture grid: ' . $currency_grid->get_error_message());
    }
    if ('CAD' !== (string) $currency_grid->get('currency', '')) {
        throw new RuntimeException('Grid creation without explicit currency did not use standalone MDS3 currency settings.');
    }

    $price_rule = (new PriceRuleRepository())->save([
        'grid_id' => $currency_grid->id(),
        'price' => 2.5,
    ]);
    if (is_wp_error($price_rule) || 'CAD' !== (string) ($price_rule['currency'] ?? '')) {
        throw new RuntimeException('Price rule creation without explicit currency did not use standalone MDS3 currency settings.');
    }

    $package = (new PackageRepository())->save([
        'grid_id' => $currency_grid->id(),
        'title' => 'Currency Fixture Package',
        'price' => 9.5,
    ]);
    if (is_wp_error($package) || 'CAD' !== (string) ($package['currency'] ?? '')) {
        throw new RuntimeException('Package creation without explicit currency did not use standalone MDS3 currency settings.');
    }

    $currency_order_id = (new OrderRepository())->create([
        [
            'grid_id' => $currency_grid->id(),
            'item_type' => 'fixture',
            'quantity' => 1,
            'unit_price' => 9.5,
            'total' => 9.5,
        ],
    ], [
        'email' => 'currency-fixture@example.test',
        'status' => 'pending_payment',
    ]);
    if (is_wp_error($currency_order_id) || !absint($currency_order_id)) {
        throw new RuntimeException('Could not create currency fixture order.');
    }
    $currency_order = (new OrderRepository())->find($currency_order_id);
    if ('CAD' !== (string) ($currency_order['currency'] ?? '')) {
        throw new RuntimeException('Order creation without explicit currency did not use standalone MDS3 currency settings.');
    }
} finally {
    global $wpdb;

    if ($currency_order_id) {
        $wpdb->delete(DB::table('order_items'), ['order_id' => absint($currency_order_id)]);
        $wpdb->delete(DB::table('orders'), ['id' => absint($currency_order_id)]);
    }
    if ($currency_grid && !is_wp_error($currency_grid)) {
        $wpdb->delete(DB::table('price_rules'), ['grid_id' => $currency_grid->id()]);
        $wpdb->delete(DB::table('packages'), ['grid_id' => $currency_grid->id()]);
        (new GridRepository())->delete($currency_grid->id());
    }
    update_option('mds3_settings', $original_currency_settings, false);
}

$page_mode_grid = (new GridRepository())->create([
    'title' => 'REST Page Mode Fixture',
    'width' => 1000,
    'height' => 1000,
    'block_width' => 10,
    'block_height' => 10,
    'price_per_block' => 1,
    'currency' => 'USD',
    'status' => 'active',
]);
if (is_wp_error($page_mode_grid)) {
    throw new RuntimeException('Could not create page-mode fixture grid: ' . $page_mode_grid->get_error_message());
}
$page_mode_page_id = 0;
try {
    $page_mode_page_id = GridPostType::ensure_page($page_mode_grid);
    if (is_wp_error($page_mode_page_id) || !absint($page_mode_page_id)) {
        throw new RuntimeException('Could not create a public page for the page-mode fixture.');
    }

    $page_mode_content = (string) get_post_field('post_content', absint($page_mode_page_id));
    if ('read_only' !== GridPostType::page_mode($page_mode_grid->id()) || false === strpos($page_mode_content, 'read_only="true"')) {
        throw new RuntimeException('Setup-created grid pages did not default to read-only mode.');
    }

    $interactive_update = GridPostType::set_page_mode($page_mode_grid, 'interactive');
    if (is_wp_error($interactive_update) || 'interactive' !== GridPostType::page_mode($page_mode_grid->id())) {
        throw new RuntimeException('Grid public page could not be switched to ordering mode.');
    }

    GridPostType::ensure_page($page_mode_grid);
    if ('interactive' !== GridPostType::page_mode($page_mode_grid->id())) {
        throw new RuntimeException('Ensuring an existing grid page reset its explicit ordering mode.');
    }

    $read_only_update = GridPostType::set_page_mode($page_mode_grid, 'read_only');
    if (is_wp_error($read_only_update) || 'read_only' !== GridPostType::page_mode($page_mode_grid->id())) {
        throw new RuntimeException('Grid public page could not be switched back to read-only mode.');
    }
} finally {
    if ($page_mode_page_id && !is_wp_error($page_mode_page_id)) {
        wp_delete_post(absint($page_mode_page_id), true);
    }
    (new GridRepository())->delete($page_mode_grid->id());
}

$archived_grid = (new GridRepository())->create([
    'title' => 'REST Archived Fixture',
    'width' => 10,
    'height' => 10,
    'block_width' => 10,
    'block_height' => 10,
    'price_per_block' => 1,
    'currency' => 'USD',
    'status' => 'archived',
]);
if (is_wp_error($archived_grid)) {
    throw new RuntimeException('Could not create archived REST fixture grid: ' . $archived_grid->get_error_message());
}

wp_set_current_user(0);
foreach (['/blocks', '/placements'] as $suffix) {
    $archived_response = rest_do_request(new WP_REST_Request('GET', '/million-dollar-script/v1/grids/' . $archived_grid->id() . $suffix));
    if (!$archived_response->is_error()) {
        (new GridRepository())->delete($archived_grid->id());
        throw new RuntimeException('Public REST endpoint exposed archived grid data for ' . $suffix . '.');
    }
}
wp_set_current_user(absint($admin[0]));
(new GridRepository())->delete($archived_grid->id());

$api_keys = new ApiKeyRepository();
$api_read_key_id = 0;
$api_limited_key_id = 0;
$api_route_rotate_key_id = 0;
try {
    $api_read_key = $api_keys->create('REST fixture read key', ['core.extension.read'], 20);
    $api_limited_key = $api_keys->create('REST fixture rate key', ['core.extension.read'], 1);
    if (is_wp_error($api_read_key) || is_wp_error($api_limited_key)) {
        throw new RuntimeException('Could not create REST fixture API keys.');
    }
    $api_read_key_id = absint($api_read_key['record']['id'] ?? 0);
    $api_limited_key_id = absint($api_limited_key['record']['id'] ?? 0);

    wp_set_current_user(0);

    $api_unauthenticated = rest_do_request(new WP_REST_Request('GET', '/million-dollar-script/v1/extensions'));
    if (!$api_unauthenticated->is_error()) {
        throw new RuntimeException('Extension catalog REST endpoint allowed an unauthenticated request.');
    }

    $api_allowed_request = new WP_REST_Request('GET', '/million-dollar-script/v1/extensions');
    $api_allowed_request->set_header('X-Million-Dollar-Script-API-Key', (string) ($api_read_key['key'] ?? ''));
    $api_allowed = rest_do_request($api_allowed_request);
    if ($api_allowed->is_error()) {
        throw new RuntimeException('Preferred API key header did not authorize extension catalog reads: ' . $api_allowed->as_error()->get_error_message());
    }
    $api_allowed_data = $api_allowed->get_data();
    if (empty($api_allowed_data['installed']) || !is_array($api_allowed_data['installed'])) {
        throw new RuntimeException('API-key extension catalog response was incomplete.');
    }

    $old_api_read_key = (string) ($api_read_key['key'] ?? '');
    $rotated_key = $api_keys->rotate($api_read_key_id);
    if (is_wp_error($rotated_key) || empty($rotated_key['key'])) {
        throw new RuntimeException('API key rotation failed.');
    }
    $api_read_key['key'] = (string) $rotated_key['key'];

    $api_old_key_request = new WP_REST_Request('GET', '/million-dollar-script/v1/extensions');
    $api_old_key_request->set_header('X-Million-Dollar-Script-API-Key', $old_api_read_key);
    $api_old_key_denied = rest_do_request($api_old_key_request);
    if (!$api_old_key_denied->is_error() || 'mds3_api_key_invalid' !== $api_old_key_denied->as_error()->get_error_code()) {
        throw new RuntimeException('Rotated API key left the old secret active.');
    }

    $api_rotated_request = new WP_REST_Request('GET', '/million-dollar-script/v1/extensions');
    $api_rotated_request->set_header('X-Million-Dollar-Script-API-Key', (string) ($api_read_key['key'] ?? ''));
    $api_rotated_allowed = rest_do_request($api_rotated_request);
    if ($api_rotated_allowed->is_error()) {
        throw new RuntimeException('Rotated API key did not authorize extension catalog reads: ' . $api_rotated_allowed->as_error()->get_error_message());
    }

    $api_shorthand_request = new WP_REST_Request('GET', '/million-dollar-script/v1/extensions');
    $api_shorthand_request->set_header('X-MDS3-API-Key', (string) ($api_read_key['key'] ?? ''));
    $api_shorthand_denied = rest_do_request($api_shorthand_request);
    if (!$api_shorthand_denied->is_error() || 'mds3_api_auth_required' !== $api_shorthand_denied->as_error()->get_error_code()) {
        throw new RuntimeException('Shorthand API key header was accepted.');
    }

    $api_scope_request = new WP_REST_Request('GET', '/million-dollar-script/v1/orders');
    $api_scope_request->set_header('X-Million-Dollar-Script-API-Key', (string) ($api_read_key['key'] ?? ''));
    $api_scope_denied = rest_do_request($api_scope_request);
    if (!$api_scope_denied->is_error() || 'mds3_api_key_scope_denied' !== $api_scope_denied->as_error()->get_error_code()) {
        throw new RuntimeException('Read-scoped API key was not denied on order reads.');
    }

    $api_rate_first = new WP_REST_Request('GET', '/million-dollar-script/v1/extensions');
    $api_rate_first->set_header('X-Million-Dollar-Script-API-Key', (string) ($api_limited_key['key'] ?? ''));
    $api_rate_first_response = rest_do_request($api_rate_first);
    if ($api_rate_first_response->is_error()) {
        throw new RuntimeException('Rate-limited API key failed before exhausting its limit.');
    }
    $api_rate_second = new WP_REST_Request('GET', '/million-dollar-script/v1/extensions');
    $api_rate_second->set_header('X-Million-Dollar-Script-API-Key', (string) ($api_limited_key['key'] ?? ''));
    $api_rate_second_response = rest_do_request($api_rate_second);
    if (!$api_rate_second_response->is_error() || 'mds3_api_key_rate_limited' !== $api_rate_second_response->as_error()->get_error_code()) {
        throw new RuntimeException('API key hourly rate limit was not enforced.');
    }

    $audit_rows = $api_keys->recent_audit_logs(20);
    $api_allowed_audited = false;
    $api_denied_audited = false;
    foreach ($audit_rows as $audit_row) {
        if ($api_read_key_id === absint($audit_row['key_id'] ?? 0) && 'allowed' === sanitize_key($audit_row['decision'] ?? '')) {
            $api_allowed_audited = true;
        }
        if ($api_limited_key_id === absint($audit_row['key_id'] ?? 0) && 'denied' === sanitize_key($audit_row['decision'] ?? '')) {
            $api_denied_audited = true;
        }
    }
    if (!$api_allowed_audited || !$api_denied_audited) {
        throw new RuntimeException('API key allowed and denied decisions were not audited.');
    }

    wp_set_current_user(absint($admin[0]));
    $api_route_rotate_key = $api_keys->create('REST fixture route rotate key', ['core.extension.read'], 20);
    if (is_wp_error($api_route_rotate_key)) {
        throw new RuntimeException('Could not create REST route rotation key.');
    }
    $api_route_rotate_key_id = absint($api_route_rotate_key['record']['id'] ?? 0);
    $api_route_rotate_request = new WP_REST_Request('POST', '/million-dollar-script/v1/api/keys/' . $api_route_rotate_key_id . '/rotate');
    $api_route_rotate_response = rest_do_request($api_route_rotate_request);
    if ($api_route_rotate_response->is_error()) {
        throw new RuntimeException('API key rotation REST route failed: ' . $api_route_rotate_response->as_error()->get_error_message());
    }
    $api_route_rotate_data = $api_route_rotate_response->get_data();
    if (empty($api_route_rotate_data['key']) || absint($api_route_rotate_data['record']['id'] ?? 0) !== $api_route_rotate_key_id) {
        throw new RuntimeException('API key rotation REST route response was incomplete.');
    }
    wp_set_current_user(0);
} finally {
    if ($api_read_key_id) {
        $api_keys->revoke($api_read_key_id);
    }
    if ($api_limited_key_id) {
        $api_keys->revoke($api_limited_key_id);
    }
    if ($api_route_rotate_key_id) {
        $api_keys->revoke($api_route_rotate_key_id);
    }
    wp_set_current_user(absint($admin[0]));
}

$bulk_grid = (new GridRepository())->create([
    'title' => 'REST Bulk Region Fixture',
    'width' => 1000,
    'height' => 1000,
    'block_width' => 10,
    'block_height' => 10,
    'price_per_block' => 1,
    'currency' => 'USD',
    'status' => 'active',
]);
if (is_wp_error($bulk_grid)) {
    throw new RuntimeException('Could not create bulk region fixture grid: ' . $bulk_grid->get_error_message());
}

$bulk_blocks = new BlockRepository();
$bulk_region = ['row_from' => 10, 'row_to' => 29, 'col_from' => 10, 'col_to' => 39];
$bulk_table_count_before = (int) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
    'SELECT COUNT(*) FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d',
    $bulk_grid->id()
));
$bulk_first = $bulk_blocks->set_region_status($bulk_grid, $bulk_region, 'unavailable', ['note' => 'bulk fixture']);
$bulk_second = $bulk_blocks->set_region_status($bulk_grid, $bulk_region, 'unavailable', ['note' => 'bulk fixture']);
$bulk_table_count_after = (int) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
    'SELECT COUNT(*) FROM ' . DB::ident(DB::table('blocks')) . ' WHERE grid_id = %d',
    $bulk_grid->id()
));

if (is_wp_error($bulk_first) || is_wp_error($bulk_second)) {
    (new GridRepository())->delete($bulk_grid->id());
    throw new RuntimeException('Bulk unavailable region update failed.');
}
if (600 !== absint($bulk_first['changed'] ?? 0) || 0 !== absint($bulk_second['changed'] ?? 0)) {
    (new GridRepository())->delete($bulk_grid->id());
    throw new RuntimeException('Bulk unavailable region changed counts did not account for existing virtual regions.');
}
if ($bulk_table_count_after !== $bulk_table_count_before) {
    (new GridRepository())->delete($bulk_grid->id());
    throw new RuntimeException('Bulk unavailable region update materialized per-cell block rows.');
}

$bulk_regions = $bulk_blocks->unavailable_regions($bulk_grid);
$matching_bulk_regions = array_values(array_filter($bulk_regions, function ($region) use ($bulk_region) {
    return absint($region['row_from'] ?? 0) === $bulk_region['row_from']
        && absint($region['row_to'] ?? 0) === $bulk_region['row_to']
        && absint($region['col_from'] ?? 0) === $bulk_region['col_from']
        && absint($region['col_to'] ?? 0) === $bulk_region['col_to'];
}));
if (1 !== count($matching_bulk_regions)) {
    (new GridRepository())->delete($bulk_grid->id());
    throw new RuntimeException('Duplicate unavailable region apply created duplicate stored regions.');
}

$bulk_available = $bulk_blocks->set_region_status($bulk_grid, $bulk_region, 'available');
if (is_wp_error($bulk_available) || 600 !== absint($bulk_available['changed'] ?? 0) || $bulk_blocks->coordinate_in_stored_unavailable_region($bulk_grid, 12, 12)) {
    (new GridRepository())->delete($bulk_grid->id());
    throw new RuntimeException('Bulk unavailable region could not be removed as a virtual region.');
}
(new GridRepository())->delete($bulk_grid->id());

$grid = (new GridRepository())->create([
    'title' => 'REST Reservation Fixture ' . wp_generate_uuid4(),
    'width' => 1000,
    'height' => 1000,
    'block_width' => 10,
    'block_height' => 10,
    'price_per_block' => 1,
    'currency' => 'USD',
    'status' => 'active',
]);
if (is_wp_error($grid)) {
    throw new RuntimeException('Could not create reservation fixture grid: ' . $grid->get_error_message());
}

$geometry = $grid->geometry();
$blocks = new BlockRepository();
$coord = null;

for ($attempt = 0; $attempt < 100; $attempt++) {
    $row = ($attempt + 41) % max(1, $geometry->rows());
    $col = ($attempt + 53) % max(1, $geometry->columns());
    $block = $blocks->materialize($grid, $row, $col);
    if (!is_wp_error($block) && 'available' === ($block['status'] ?? '')) {
        $coord = ['row' => $row, 'col' => $col, 'block_id' => absint($block['id'])];
        break;
    }
}

if (!$coord) {
    throw new RuntimeException('REST fixture could not find an available block.');
}

$original_settings = get_option('mds3_settings', []);
$settings = wp_parse_args(is_array($original_settings) ? $original_settings : [], SettingsSchema::defaults());
$settings['payment_provider'] = 'standalone';
$settings['checkout-url'] = 'https://payments.example.test/checkout?amount=%AMOUNT%&currency=%CURRENCY%&quantity=%QUANTITY%&order=%ORDERID&user=%USERID%&grid=%GRID%&pixel=%PIXELID%';
$settings['thank-you-page'] = 'https://payments.example.test/thanks';
$settings['expire-orders'] = 'yes';
$settings['minutes-unconfirmed'] = 60;
$settings['minutes-confirmed'] = 60;
update_option('mds3_settings', $settings);

$captured_mail = [];
$mail_filter = static function ($return, $atts) use (&$captured_mail) {
    $captured_mail[] = is_array($atts) ? $atts : [];

    return true;
};
add_filter('pre_wp_mail', $mail_filter, 10, 2);

try {
$stats_grid = (new GridRepository())->create([
    'title' => 'REST Stats Fixture',
    'width' => 20,
    'height' => 10,
    'block_width' => 10,
    'block_height' => 10,
    'price_per_block' => 1,
    'currency' => 'USD',
    'status' => 'active',
]);
if (is_wp_error($stats_grid)) {
    throw new RuntimeException('Could not create stats fixture grid: ' . $stats_grid->get_error_message());
}
$stats_order_id = 0;
$stats_pending_order_id = 0;
$stats_alt_grid = null;
try {
    $stats_reservation = (new ReservationService())->reserve($stats_grid, [['row' => 0, 'col' => 0]], [
        'email' => 'stats-fixture@example.test',
    ]);
    if (is_wp_error($stats_reservation)) {
        throw new RuntimeException('Stats fixture reservation failed: ' . $stats_reservation->get_error_message());
    }

    $stats_order_id = absint($stats_reservation['order']['id'] ?? 0);
    if (!$stats_order_id || !Payments::mark_source_status('mds-grid', $stats_order_id, 'paid', ['source' => 'fixture'])) {
        throw new RuntimeException('Stats fixture order could not be marked paid.');
    }

    $stats_paid_unpublished = (new GridStats())->public_inventory($stats_grid, $settings, 'blocks');
    if (
        0 !== absint($stats_paid_unpublished['sold_blocks'] ?? 0) ||
        1 !== absint($stats_paid_unpublished['reserved_blocks'] ?? 0) ||
        1 !== absint($stats_paid_unpublished['available_blocks'] ?? 0)
    ) {
        throw new RuntimeException('Paid unpublished inventory was counted incorrectly in public grid stats.');
    }

    $stats_rect = (new OrderRepository())->item_rect($stats_order_id);
    $stats_placement_id = (new PlacementRepository())->create([
        'grid_id' => $stats_grid->id(),
        'order_id' => $stats_order_id,
        'attachment_id' => 1,
        'x' => absint($stats_rect['x'] ?? 0),
        'y' => absint($stats_rect['y'] ?? 0),
        'width' => absint($stats_rect['width'] ?? 10),
        'height' => absint($stats_rect['height'] ?? 10),
        'status' => 'active',
    ]);
    if (is_wp_error($stats_placement_id)) {
        throw new RuntimeException('Stats fixture placement could not be created: ' . $stats_placement_id->get_error_message());
    }

    $stats_published = (new GridStats())->public_inventory($stats_grid, $settings, 'blocks');
    if (
        1 !== absint($stats_published['sold_blocks'] ?? 0) ||
        0 !== absint($stats_published['reserved_blocks'] ?? 0) ||
        1 !== absint($stats_published['available_blocks'] ?? 0)
    ) {
        throw new RuntimeException('Published active placement inventory was counted incorrectly in public grid stats.');
    }

    $stats_order = (new OrderRepository())->find($stats_order_id);
    $stats_provider = sanitize_key((string) ($stats_order['commerce_provider'] ?? '')) ?: 'standalone';
    $stats_order_rows = (new OrderRepository())->query([
        'grid_id' => $stats_grid->id(),
        'status' => 'paid',
        'provider' => $stats_provider,
        'search' => 'REST Stats Fixture',
        'date_from' => gmdate('Y-m-d'),
        'date_to' => gmdate('Y-m-d'),
        'orderby' => 'grid',
        'order' => 'asc',
        'limit' => 5,
    ]);
    if (
        1 !== count($stats_order_rows) ||
        absint($stats_order_rows[0]['id'] ?? 0) !== $stats_order_id ||
        false === strpos((string) ($stats_order_rows[0]['grid_ids'] ?? ''), (string) $stats_grid->id()) ||
        false === strpos((string) ($stats_order_rows[0]['grid_titles'] ?? ''), 'REST Stats Fixture')
    ) {
        throw new RuntimeException('Order repository grid/status/provider/search/date filters did not return the expected order summary row.');
    }

    if (1 !== (new OrderRepository())->count([
        'grid_id' => $stats_grid->id(),
        'status' => 'paid',
        'provider' => $stats_provider,
    ])) {
        throw new RuntimeException('Order repository count did not honor grid, status, and provider filters.');
    }

    if ((new OrderRepository())->query([
        'grid_id' => $stats_grid->id(),
        'status' => 'reserved',
        'provider' => $stats_provider,
        'limit' => 5,
    ])) {
        throw new RuntimeException('Order repository status filter returned rows for a non-matching status.');
    }

    $tile_level_zero = TileController::tile_dimensions_for_level($stats_grid, 0, 1, 10);
    $tile_level_one = TileController::tile_dimensions_for_level($stats_grid, 1, 1, 10);
    if (
        1 !== absint($tile_level_zero['columns'] ?? 0) ||
        1 !== absint($tile_level_zero['rows'] ?? 0) ||
        1 !== absint($tile_level_zero['count'] ?? 0) ||
        2 !== absint($tile_level_one['columns'] ?? 0) ||
        1 !== absint($tile_level_one['rows'] ?? 0) ||
        2 !== absint($tile_level_one['count'] ?? 0)
    ) {
        throw new RuntimeException('Tile bounds calculation did not match expected grid dimensions.');
    }

    $tile_template = TileController::public_tile_url_template($stats_grid, 'fixture-cache-key', 'webp');
    if (
        false === strpos($tile_template, '{z}/{x}/{y}.webp') ||
        false === strpos($tile_template, 'mds3-tiles/grid-' . $stats_grid->id())
    ) {
        throw new RuntimeException('Public tile URL template did not preserve expected tile placeholders.');
    }

    $stats_pending_reservation = (new ReservationService())->reserve($stats_grid, [['row' => 0, 'col' => 1]], [
        'email' => 'stats-pending-fixture@example.test',
    ]);
    if (is_wp_error($stats_pending_reservation)) {
        throw new RuntimeException('Stats pending-payment fixture reservation failed: ' . $stats_pending_reservation->get_error_message());
    }

    $stats_pending_order_id = absint($stats_pending_reservation['order']['id'] ?? 0);
    if (!$stats_pending_order_id) {
        throw new RuntimeException('Stats pending-payment fixture did not create an order.');
    }

    (new OrderRepository())->update($stats_pending_order_id, ['status' => 'pending_payment']);
    $stats_with_pending = (new GridStats())->public_inventory($stats_grid, $settings, 'blocks');
    if (
        1 !== absint($stats_with_pending['sold_blocks'] ?? 0) ||
        1 !== absint($stats_with_pending['reserved_blocks'] ?? 0) ||
        0 !== absint($stats_with_pending['available_blocks'] ?? 0)
    ) {
        throw new RuntimeException('Pending-payment inventory was incorrectly counted as sold or available in public grid stats.');
    }

    $stats_alt_grid = (new GridRepository())->create([
        'title' => 'REST Stats Fixture Alternate',
        'width' => 10,
        'height' => 10,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 1,
        'currency' => 'USD',
        'status' => 'active',
    ]);
    if (is_wp_error($stats_alt_grid)) {
        throw new RuntimeException('Could not create alternate stats fixture grid: ' . $stats_alt_grid->get_error_message());
    }

    $stats_alt_inventory = (new GridStats())->public_inventory($stats_alt_grid, $settings, 'blocks');
    if (
        absint($stats_alt_inventory['grid_id'] ?? 0) !== $stats_alt_grid->id() ||
        0 !== absint($stats_alt_inventory['sold_blocks'] ?? 0) ||
        0 !== absint($stats_alt_inventory['reserved_blocks'] ?? 0) ||
        1 !== absint($stats_alt_inventory['available_blocks'] ?? 0)
    ) {
        throw new RuntimeException('Alternate grid stats did not stay scoped to the active grid.');
    }

    Payments::mark_source_status('mds-grid', $stats_order_id, 'expired', [
        'source' => 'fixture',
        'release_inventory' => false,
    ]);
    $stats_expired = (new GridStats())->public_inventory($stats_grid, $settings, 'blocks');
    if (
        0 !== absint($stats_expired['sold_blocks'] ?? 0) ||
        2 !== absint($stats_expired['reserved_blocks'] ?? 0) ||
        0 !== absint($stats_expired['available_blocks'] ?? 0)
    ) {
        throw new RuntimeException('Expired retained and pending-payment inventory was counted incorrectly in public grid stats.');
    }
} finally {
    if ($stats_pending_order_id) {
        $GLOBALS['wpdb']->delete(DB::table('placements'), ['order_id' => absint($stats_pending_order_id)]);
        $GLOBALS['wpdb']->delete(DB::table('order_items'), ['order_id' => absint($stats_pending_order_id)]);
        $GLOBALS['wpdb']->delete(DB::table('orders'), ['id' => absint($stats_pending_order_id)]);
    }
    if ($stats_order_id) {
        $GLOBALS['wpdb']->delete(DB::table('placements'), ['order_id' => absint($stats_order_id)]);
        $GLOBALS['wpdb']->delete(DB::table('order_items'), ['order_id' => absint($stats_order_id)]);
        $GLOBALS['wpdb']->delete(DB::table('orders'), ['id' => absint($stats_order_id)]);
    }
    $GLOBALS['wpdb']->delete(DB::table('blocks'), ['grid_id' => $stats_grid->id()]);
    (new GridRepository())->delete($stats_grid->id());
    if ($stats_alt_grid && !is_wp_error($stats_alt_grid)) {
        $GLOBALS['wpdb']->delete(DB::table('blocks'), ['grid_id' => $stats_alt_grid->id()]);
        (new GridRepository())->delete($stats_alt_grid->id());
    }
}

$guest_coord = null;
for ($attempt = 0; $attempt < 100; $attempt++) {
    $row = ($attempt + 31) % max(1, $geometry->rows());
    $col = ($attempt + 37) % max(1, $geometry->columns());
    $block = $blocks->materialize($grid, $row, $col);
    if (!is_wp_error($block) && 'available' === ($block['status'] ?? '')) {
        $guest_coord = ['row' => $row, 'col' => $col, 'block_id' => absint($block['id'])];
        break;
    }
}
if (!$guest_coord) {
    throw new RuntimeException('REST fixture could not find an available guest-order block.');
}

wp_set_current_user(0);
$settings['accounts-optional'] = 'no';
update_option('mds3_settings', $settings);
$guest_disabled = (new ReservationService())->reserve($grid, [['row' => $guest_coord['row'], 'col' => $guest_coord['col']]], [
    'email' => 'guest-disabled@example.com',
]);
if (!is_wp_error($guest_disabled) || 'mds3_login_required' !== $guest_disabled->get_error_code()) {
    throw new RuntimeException('Guest reservations were not blocked when Allow Guest Orders was disabled.');
}

$settings['accounts-optional'] = 'yes';
update_option('mds3_settings', $settings);
$guest_missing_email = (new ReservationService())->reserve($grid, [['row' => $guest_coord['row'], 'col' => $guest_coord['col']]]);
if (!is_wp_error($guest_missing_email) || 'mds3_customer_email_required' !== $guest_missing_email->get_error_code()) {
    throw new RuntimeException('Guest reservations without an email address were accepted.');
}

$guest_invalid_email = (new ReservationService())->reserve($grid, [['row' => $guest_coord['row'], 'col' => $guest_coord['col']]], [
    'email' => 'not-an-email',
]);
if (!is_wp_error($guest_invalid_email) || 'mds3_invalid_customer_email' !== $guest_invalid_email->get_error_code()) {
    throw new RuntimeException('Guest reservations with an invalid email address were accepted.');
}

$guest_allowed = (new ReservationService())->reserve($grid, [['row' => $guest_coord['row'], 'col' => $guest_coord['col']]], [
    'email' => 'guest-reservation-fixture@example.com',
]);
if (is_wp_error($guest_allowed)) {
    throw new RuntimeException('Guest reservation with a valid email address failed: ' . $guest_allowed->get_error_message());
}
$guest_order = is_array($guest_allowed['order'] ?? null) ? $guest_allowed['order'] : [];
if (
    'guest-reservation-fixture@example.com' !== sanitize_email((string) ($guest_order['email'] ?? '')) ||
    0 !== absint($guest_order['user_id'] ?? 0)
) {
    throw new RuntimeException('Guest reservation did not preserve the customer email without assigning a WordPress user.');
}
wp_set_current_user(absint($admin[0]));
update_option('mds3_settings', $settings);

$invalid_reserve = new WP_REST_Request('POST', '/million-dollar-script/v1/grids/' . $grid->id() . '/reservations');
$invalid_reserve->set_body_params([
    'blocks' => [
        ['row' => -1, 'col' => $coord['col']],
    ],
    'email' => 'invalid-coordinate@example.com',
]);
$invalid_response = rest_do_request($invalid_reserve);
if (!$invalid_response->is_error()) {
    throw new RuntimeException('REST reservation accepted an out-of-bounds negative coordinate.');
}

$reserve = new WP_REST_Request('POST', '/million-dollar-script/v1/grids/' . $grid->id() . '/reservations');
$reserve->set_body_params([
    'blocks' => [
        ['row' => $coord['row'], 'col' => $coord['col']],
    ],
    'email' => 'fixture@example.com',
]);

$reserve_response = rest_do_request($reserve);
if ($reserve_response->is_error()) {
    throw new RuntimeException($reserve_response->as_error()->get_error_message());
}

$reservation = $reserve_response->get_data();
$order_id = absint($reservation['order']['id'] ?? 0);
if (!$order_id || empty($reservation['order_key']) || empty($reservation['blocks'])) {
    throw new RuntimeException('REST reservation response was incomplete.');
}

$checkout = $reservation['checkout'] ?? [];
if ('standalone' !== ($checkout['provider'] ?? '') || empty($checkout['checkout_url'])) {
    throw new RuntimeException('REST standalone reservation did not return a checkout URL.');
}
if (false !== strpos((string) $checkout['checkout_url'], '%')) {
    throw new RuntimeException('REST standalone checkout URL did not replace legacy MDS2 placeholders.');
}
if (empty($checkout['after_upload_url']) || (string) $checkout['after_upload_url'] !== (string) $checkout['checkout_url']) {
    throw new RuntimeException('REST standalone checkout payload did not use the configured checkout URL after upload.');
}
$checkout_parts = wp_parse_url($checkout['checkout_url']);
parse_str($checkout_parts['query'] ?? '', $checkout_query);
if (
    (string) $checkout_query['amount'] !== number_format((float) ($reservation['order']['total'] ?? 0), 2, '.', '') ||
    (string) $checkout_query['currency'] !== (string) ($reservation['order']['currency'] ?? '') ||
    (string) $checkout_query['quantity'] !== '1' ||
    (string) $checkout_query['order'] !== (string) $order_id ||
    (string) $checkout_query['grid'] !== (string) $grid->id() ||
    empty($checkout_query['pixel'])
) {
    throw new RuntimeException('REST standalone checkout URL did not map MDS2 placeholder values.');
}
$_GET['mds3_order_id'] = $order_id;
$_GET['mds3_order_key'] = $reservation['order_key'];
$reserved_summary_html = do_shortcode('[mds3_page type="thank-you" grid_id="' . absint($grid->id()) . '"]');
unset($_GET['mds3_order_id'], $_GET['mds3_order_key']);
if (false === strpos($reserved_summary_html, 'selected blocks are held until') || false === strpos($reserved_summary_html, 'Continue payment')) {
    throw new RuntimeException('Reserved order summary did not explain the customer cleanup window.');
}

$second_coord = null;
for ($attempt = 0; $attempt < 100; $attempt++) {
    $row = ($attempt + 59) % max(1, $geometry->rows());
    $col = ($attempt + 67) % max(1, $geometry->columns());
    $block = $blocks->materialize($grid, $row, $col);
    if (!is_wp_error($block) && 'available' === ($block['status'] ?? '')) {
        $second_coord = ['row' => $row, 'col' => $col, 'block_id' => absint($block['id'])];
        break;
    }
}
if (!$second_coord) {
    throw new RuntimeException('REST fixture could not find a second available block for reservation isolation.');
}
$second_reserve = new WP_REST_Request('POST', '/million-dollar-script/v1/grids/' . $grid->id() . '/reservations');
$second_reserve->set_body_params([
    'blocks' => [
        ['row' => $second_coord['row'], 'col' => $second_coord['col']],
    ],
    'email' => 'second-reservation-fixture@example.com',
]);
$second_response = rest_do_request($second_reserve);
if ($second_response->is_error()) {
    throw new RuntimeException($second_response->as_error()->get_error_message());
}
$second_reservation = $second_response->get_data();
$second_order_id = absint($second_reservation['order']['id'] ?? 0);
if (!$second_order_id || $second_order_id === $order_id) {
    throw new RuntimeException('Separate REST reservation attempts were merged into one order.');
}
$first_items = (new OrderRepository())->items($order_id);
$second_items = (new OrderRepository())->items($second_order_id);
if (
    1 !== count($first_items) ||
    1 !== count($second_items) ||
    absint($first_items[0]['block_id'] ?? 0) === absint($second_items[0]['block_id'] ?? 0)
) {
    throw new RuntimeException('Separate REST reservation attempts did not keep distinct order items.');
}
$mask_query_start = (int) $GLOBALS['wpdb']->num_queries;
$item_masks = (new OrderRepository())->item_masks([$order_id, $second_order_id, $order_id, 0]);
$mask_query_count = (int) $GLOBALS['wpdb']->num_queries - $mask_query_start;
$first_item_metadata = json_decode((string) ($first_items[0]['metadata'] ?? ''), true);
$second_item_metadata = json_decode((string) ($second_items[0]['metadata'] ?? ''), true);
if (1 !== $mask_query_count) {
    throw new RuntimeException('Bulk placement masks did not load two orders in one bounded query.');
}
if (
    1 !== count($item_masks[$order_id] ?? []) ||
    1 !== count($item_masks[$second_order_id] ?? []) ||
    absint($item_masks[$order_id][0]['x'] ?? -1) !== absint($first_item_metadata['x'] ?? -2) ||
    absint($item_masks[$second_order_id][0]['y'] ?? -1) !== absint($second_item_metadata['y'] ?? -2)
) {
    throw new RuntimeException('Bulk placement masks did not preserve the stored order-item rectangles.');
}
$empty_mask_query_start = (int) $GLOBALS['wpdb']->num_queries;
if ([] !== (new OrderRepository())->item_masks([]) || $empty_mask_query_start !== (int) $GLOBALS['wpdb']->num_queries) {
    throw new RuntimeException('Empty bulk placement-mask lookup should not query the database.');
}
$second_block = $GLOBALS['wpdb']->get_row(
    $GLOBALS['wpdb']->prepare(
        'SELECT status, order_id FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d',
        $second_coord['block_id']
    ),
    ARRAY_A
);
if ('reserved' !== ($second_block['status'] ?? '') || absint($second_block['order_id'] ?? 0) !== $second_order_id) {
    throw new RuntimeException('Second REST reservation block was not linked to its own order.');
}

$settings['checkout-url'] = '';
update_option('mds3_settings', $settings);
$fallback_coord = null;
for ($attempt = 0; $attempt < 100; $attempt++) {
    $row = ($attempt + 71) % max(1, $geometry->rows());
    $col = ($attempt + 83) % max(1, $geometry->columns());
    $block = $blocks->materialize($grid, $row, $col);
    if (!is_wp_error($block) && 'available' === ($block['status'] ?? '')) {
        $fallback_coord = ['row' => $row, 'col' => $col];
        break;
    }
}
if (!$fallback_coord) {
    throw new RuntimeException('REST fixture could not find an available fallback-checkout block.');
}

$fallback_reserve = new WP_REST_Request('POST', '/million-dollar-script/v1/grids/' . $grid->id() . '/reservations');
$fallback_reserve->set_body_params([
    'blocks' => [
        ['row' => $fallback_coord['row'], 'col' => $fallback_coord['col']],
    ],
    'email' => 'fallback-fixture@example.com',
]);
$fallback_response = rest_do_request($fallback_reserve);
if ($fallback_response->is_error()) {
    throw new RuntimeException($fallback_response->as_error()->get_error_message());
}
$fallback_reservation = $fallback_response->get_data();
$fallback_order_id = absint($fallback_reservation['order']['id'] ?? 0);
$fallback_checkout = $fallback_reservation['checkout'] ?? [];
if (!$fallback_order_id || !empty($fallback_checkout['checkout_url'])) {
    throw new RuntimeException('REST no-URL standalone checkout returned an external checkout URL.');
}
if (empty($fallback_checkout['after_upload_url']) || false === strpos((string) $fallback_checkout['after_upload_url'], 'mds3_order_id=' . $fallback_order_id)) {
    throw new RuntimeException('REST no-URL standalone checkout did not fall back to the order-specific thank-you URL.');
}
$fallback_items = (new OrderRepository())->items($fallback_order_id);
$fallback_block_id = absint($fallback_items[0]['block_id'] ?? 0);
$failed = new WP_REST_Request('PATCH', '/million-dollar-script/v1/orders/' . $fallback_order_id);
$failed->set_body_params(['status' => 'failed']);
$failed_response = rest_do_request($failed);
if ($failed_response->is_error()) {
    throw new RuntimeException($failed_response->as_error()->get_error_message());
}
$failed_order = (new OrderRepository())->find($fallback_order_id);
$failed_block = $GLOBALS['wpdb']->get_row(
    $GLOBALS['wpdb']->prepare(
        'SELECT status, order_id FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d',
        $fallback_block_id
    ),
    ARRAY_A
);
if ('failed' !== ($failed_order['status'] ?? '') || 'available' !== ($failed_block['status'] ?? '') || 0 !== absint($failed_block['order_id'] ?? 0)) {
    throw new RuntimeException('REST failed order state did not preserve failure status and release reserved inventory.');
}

if (-1 !== SettingsSchema::sanitize('minutes-unconfirmed', '-1')) {
    throw new RuntimeException('Order timing setting sanitization did not preserve the MDS2 immediate-cleanup value.');
}

$cleanup_coord = null;
for ($attempt = 0; $attempt < 100; $attempt++) {
    $row = ($attempt + 91) % max(1, $geometry->rows());
    $col = ($attempt + 97) % max(1, $geometry->columns());
    $block = $blocks->materialize($grid, $row, $col);
    if (!is_wp_error($block) && 'available' === ($block['status'] ?? '')) {
        $cleanup_coord = ['row' => $row, 'col' => $col];
        break;
    }
}
if (!$cleanup_coord) {
    throw new RuntimeException('REST fixture could not find an available cleanup-checkout block.');
}

$cleanup_settings = $settings;
$cleanup_settings['expire-orders'] = 'yes';
$cleanup_settings['minutes-unconfirmed'] = 1;
$cleanup_settings['minutes-confirmed'] = 43200;
$cleanup_settings['minutes-renew'] = 0;
$cleanup_settings['minutes-cancel'] = 0;
update_option('mds3_settings', $cleanup_settings);

$cleanup_reserve = new WP_REST_Request('POST', '/million-dollar-script/v1/grids/' . $grid->id() . '/reservations');
$cleanup_reserve->set_body_params([
    'blocks' => [
        ['row' => $cleanup_coord['row'], 'col' => $cleanup_coord['col']],
    ],
    'email' => 'cleanup-fixture@example.com',
]);
$cleanup_response = rest_do_request($cleanup_reserve);
if ($cleanup_response->is_error()) {
    throw new RuntimeException($cleanup_response->as_error()->get_error_message());
}
$cleanup_reservation = $cleanup_response->get_data();
$cleanup_order_id = absint($cleanup_reservation['order']['id'] ?? 0);
$cleanup_items = (new OrderRepository())->items($cleanup_order_id);
$cleanup_block_id = absint($cleanup_items[0]['block_id'] ?? 0);
if (!$cleanup_order_id || !$cleanup_block_id) {
    throw new RuntimeException('Cleanup fixture reservation did not include an order and block.');
}

$stale_time = gmdate('Y-m-d H:i:s', time() - (10 * MINUTE_IN_SECONDS));
$GLOBALS['wpdb']->update(
    DB::table('orders'),
    ['created_at' => $stale_time, 'updated_at' => $stale_time],
    ['id' => $cleanup_order_id],
    ['%s', '%s'],
    ['%d']
);
$GLOBALS['wpdb']->update(
    DB::table('blocks'),
    ['reserved_until' => $stale_time, 'updated_at' => $stale_time],
    ['id' => $cleanup_block_id],
    ['%s', '%s'],
    ['%d']
);

$cleanup_result = (new OrderCleanup())->run(10);
$cleanup_order = (new OrderRepository())->find($cleanup_order_id);
$cleanup_block = $GLOBALS['wpdb']->get_row(
    $GLOBALS['wpdb']->prepare(
        'SELECT status, order_id FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d',
        $cleanup_block_id
    ),
    ARRAY_A
);
if (
    absint($cleanup_result['expired'] ?? 0) < 1 ||
    'expired' !== ($cleanup_order['status'] ?? '') ||
    'available' !== ($cleanup_block['status'] ?? '') ||
    0 !== absint($cleanup_block['order_id'] ?? 0)
) {
    throw new RuntimeException('Order cleanup did not expire stale reservations and release reserved inventory.');
}
$cleanup_notice_sent = false;
foreach ($captured_mail as $mail) {
    if ('cleanup-fixture@example.com' === sanitize_email((string) ($mail['to'] ?? '')) && false !== strpos((string) ($mail['subject'] ?? ''), 'Order Expired')) {
        $cleanup_notice_sent = true;
        break;
    }
}
if (!$cleanup_notice_sent) {
    throw new RuntimeException('Order cleanup did not send an expiration notice for a stale reservation.');
}

$detail = rest_do_request(new WP_REST_Request('GET', '/million-dollar-script/v1/orders/' . $order_id));
if ($detail->is_error()) {
    throw new RuntimeException($detail->as_error()->get_error_message());
}
$detail_data = $detail->get_data();
if (empty($detail_data['items']) || empty($detail_data['placement_rect'])) {
    throw new RuntimeException('REST order detail did not include items and placement rectangle.');
}

$paid_mail_start = count($captured_mail);
$paid = new WP_REST_Request('PATCH', '/million-dollar-script/v1/orders/' . $order_id);
$paid->set_body_params(['status' => 'paid']);
$paid_response = rest_do_request($paid);
if ($paid_response->is_error()) {
    throw new RuntimeException($paid_response->as_error()->get_error_message());
}

$order = (new OrderRepository())->find($order_id);
$block_after = $GLOBALS['wpdb']->get_row(
    $GLOBALS['wpdb']->prepare(
        'SELECT status FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d',
        $coord['block_id']
    ),
    ARRAY_A
);

if ('paid' !== ($order['status'] ?? '') || 'sold' !== ($block_after['status'] ?? '')) {
    throw new RuntimeException('REST order state update did not mark order paid and block sold.');
}
$paid_notice_sent = false;
foreach (array_slice($captured_mail, $paid_mail_start) as $mail) {
    if ('fixture@example.com' === sanitize_email((string) ($mail['to'] ?? '')) && false !== strpos((string) ($mail['subject'] ?? ''), 'Order Paid')) {
        $paid_notice_sent = true;
        break;
    }
}
if (!$paid_notice_sent) {
    throw new RuntimeException('Paid order status change did not send the MDS3 paid order notice.');
}

$publish_settings = wp_parse_args((array) get_option('mds3_settings', []), SettingsSchema::defaults());
$publish_settings['email-admin-publish-notify'] = 'yes';
update_option('mds3_settings', $publish_settings);
$publish_mail_start = count($captured_mail);
\MillionDollarScript\Core\Hooks::do('million-dollar-script/placement/saved', [
    'status' => 'active',
    'grid_id' => $grid->id(),
    'link_url' => 'https://advertiser.example.test/',
    'alt_text' => 'Fixture placement',
], $order, []);
$publish_notice_sent = false;
foreach (array_slice($captured_mail, $publish_mail_start) as $mail) {
    if (sanitize_email(get_bloginfo('admin_email')) === sanitize_email((string) ($mail['to'] ?? '')) && false !== strpos((string) ($mail['subject'] ?? ''), 'Placement Published')) {
        $publish_notice_sent = true;
        break;
    }
}
if (!$publish_notice_sent) {
    throw new RuntimeException('Active placement save did not send the MDS3 placement published admin notice.');
}
$publish_settings['email-admin-publish-notify'] = 'no';
update_option('mds3_settings', $publish_settings);

$term_coord = null;
for ($attempt = 0; $attempt < 100; $attempt++) {
    $row = ($attempt + 113) % max(1, $geometry->rows());
    $col = ($attempt + 127) % max(1, $geometry->columns());
    $block = $blocks->materialize($grid, $row, $col);
    if (!is_wp_error($block) && 'available' === ($block['status'] ?? '')) {
        $term_coord = ['row' => $row, 'col' => $col, 'block' => $block];
        break;
    }
}
if (!$term_coord) {
    throw new RuntimeException('REST fixture could not find an available paid-term-expiry block.');
}

$term_block = $blocks->reserve($term_coord['block'], 0, 60);
if (is_wp_error($term_block)) {
    throw new RuntimeException('Could not reserve paid-term-expiry block: ' . $term_block->get_error_message());
}
$term_order_id = (new OrderRepository())->create([
    [
        'grid_id' => $grid->id(),
        'block_id' => absint($term_block['id']),
        'item_type' => 'block',
        'quantity' => 1,
        'unit_price' => 1,
        'total' => 1,
        'metadata' => [
            'x' => absint($term_block['x']),
            'y' => absint($term_block['y']),
            'width' => absint($term_block['width']),
            'height' => absint($term_block['height']),
        ],
    ],
], [
    'email' => 'paid-expiry-fixture@example.com',
    'status' => 'reserved',
    'block_status' => 'reserved',
    'currency' => 'USD',
    'commerce_provider' => 'standalone',
    'metadata' => ['duration_days' => 1],
]);
if (is_wp_error($term_order_id)) {
    throw new RuntimeException($term_order_id->get_error_message());
}

$term_paid = new WP_REST_Request('PATCH', '/million-dollar-script/v1/orders/' . absint($term_order_id));
$term_paid->set_body_params(['status' => 'paid']);
$term_paid_response = rest_do_request($term_paid);
if ($term_paid_response->is_error()) {
    throw new RuntimeException($term_paid_response->as_error()->get_error_message());
}
$term_order = (new OrderRepository())->find($term_order_id);
$term_metadata = json_decode((string) ($term_order['metadata'] ?? ''), true);
if (empty($term_metadata['term_started_at']) || empty($term_metadata['expires_at'])) {
    throw new RuntimeException('Paid duration order did not receive term metadata.');
}
if ((string) ($term_order['expires_at'] ?? '') !== (string) $term_metadata['expires_at']) {
    throw new RuntimeException('Paid duration order did not normalize its expiration timestamp.');
}

$term_cleanup_settings = $settings;
$term_cleanup_settings['expire-orders'] = 'yes';
$term_cleanup_settings['minutes-unconfirmed'] = 0;
$term_cleanup_settings['minutes-confirmed'] = 0;
$term_cleanup_settings['minutes-renew'] = 60;
$term_cleanup_settings['minutes-cancel'] = 0;
$term_cleanup_settings['checkout-url'] = home_url('/fixture-renewal-checkout/');
$term_cleanup_settings['payment_provider'] = 'standalone';
$term_cleanup_settings['email-user-renewal-reminder'] = 'yes';
$term_cleanup_settings['email-admin-renewal-reminder'] = 'no';
$term_cleanup_settings['email-user-order-expired'] = 'yes';
$term_cleanup_settings['email-admin-order-expired'] = 'no';
$term_cleanup_settings['renewal-reminder-days-1'] = 7;
$term_cleanup_settings['renewal-reminder-days-2'] = 3;
$term_cleanup_settings['renewal-reminder-days-3'] = 1;
update_option('mds3_settings', $term_cleanup_settings);

$term_metadata['expires_at'] = gmdate('Y-m-d H:i:s', time() + (2 * DAY_IN_SECONDS));
(new OrderRepository())->update($term_order_id, ['metadata' => $term_metadata]);
$term_order = (new OrderRepository())->find($term_order_id);
if ((string) ($term_order['expires_at'] ?? '') !== (string) $term_metadata['expires_at']) {
    throw new RuntimeException('Order metadata update did not synchronize the normalized expiration timestamp.');
}
$expiring_orders = (new OrderRepository())->query([
    'expiration_state' => 'expiring_soon',
    'limit' => 200,
]);
if (!in_array(absint($term_order_id), array_map(static fn($order) => absint($order['id'] ?? 0), $expiring_orders), true)) {
    throw new RuntimeException('Normalized expiration filter omitted a paid order expiring soon.');
}
$reminder_mail_start = count($captured_mail);
$term_reminder_cleanup = (new OrderCleanup())->run(500);
if (absint($term_reminder_cleanup['reminders'] ?? 0) < 1) {
    throw new RuntimeException('Paid duration cleanup did not send a due renewal reminder.');
}
$reminder_notice_sent = false;
foreach (array_slice($captured_mail, $reminder_mail_start) as $mail) {
    if ('paid-expiry-fixture@example.com' === sanitize_email((string) ($mail['to'] ?? '')) && false !== strpos((string) ($mail['subject'] ?? ''), 'Expire Soon')) {
        $reminder_notice_sent = true;
        break;
    }
}
if (!$reminder_notice_sent) {
    throw new RuntimeException('Renewal reminder was not sent to the MDS3 order customer.');
}
$duplicate_mail_start = count($captured_mail);
$duplicate_reminder_cleanup = (new OrderCleanup())->run(500);
if (0 !== absint($duplicate_reminder_cleanup['reminders'] ?? 0) || count($captured_mail) !== $duplicate_mail_start) {
    throw new RuntimeException('Renewal reminder was sent more than once for the same reminder threshold.');
}

$term_order = (new OrderRepository())->find($term_order_id);
$term_metadata = json_decode((string) ($term_order['metadata'] ?? ''), true);
$term_metadata = is_array($term_metadata) ? $term_metadata : [];
$term_metadata['expires_at'] = gmdate('Y-m-d H:i:s', time() - (10 * MINUTE_IN_SECONDS));
(new OrderRepository())->update($term_order_id, ['metadata' => $term_metadata]);
$term_expired_mail_start = count($captured_mail);
$term_cleanup = (new OrderCleanup())->run(500);
$term_expired_order = (new OrderRepository())->find($term_order_id);
$term_expired_block = $GLOBALS['wpdb']->get_row(
    $GLOBALS['wpdb']->prepare(
        'SELECT status, order_id FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d',
        absint($term_block['id'])
    ),
    ARRAY_A
);
if (
    absint($term_cleanup['paid_expired'] ?? 0) < 1 ||
    'expired' !== ($term_expired_order['status'] ?? '') ||
    'reserved' !== ($term_expired_block['status'] ?? '') ||
    absint($term_expired_block['order_id'] ?? 0) !== absint($term_order_id)
) {
    throw new RuntimeException('Paid duration cleanup did not expire the order while retaining inventory for renewal.');
}
$term_expired_notice_sent = false;
foreach (array_slice($captured_mail, $term_expired_mail_start) as $mail) {
    if ('paid-expiry-fixture@example.com' === sanitize_email((string) ($mail['to'] ?? '')) && false !== strpos((string) ($mail['subject'] ?? ''), 'Order Expired')) {
        $term_expired_notice_sent = true;
        break;
    }
}
if (!$term_expired_notice_sent) {
    throw new RuntimeException('Paid duration cleanup did not send an expiration notice.');
}

$_GET['mds3_order_id'] = $term_order_id;
$_GET['mds3_order_key'] = $term_expired_order['order_key'] ?? '';
$renewal_html = do_shortcode('[mds3_page type="manage" grid_id="' . absint($grid->id()) . '"]');
unset($_GET['mds3_order_id'], $_GET['mds3_order_key']);
if (false === strpos($renewal_html, 'Renew placement') || false !== strpos($renewal_html, 'name="image"')) {
    throw new RuntimeException('Expired paid order manage page did not show the renewal action.');
}

$renewal = (new OrderRenewal())->start($term_expired_order, ['source' => 'fixture']);
if (is_wp_error($renewal)) {
    throw new RuntimeException($renewal->get_error_message());
}
$renewal_checkout = is_array($renewal['checkout'] ?? null) ? $renewal['checkout'] : [];
$renewal_order = (new OrderRepository())->find($term_order_id);
$renewal_metadata = json_decode((string) ($renewal_order['metadata'] ?? ''), true);
if (
    empty($renewal_checkout['checkout_url']) ||
    false === strpos((string) $renewal_checkout['checkout_url'], 'mds3_order_id=' . absint($term_order_id)) ||
    'expired' !== ($renewal_order['status'] ?? '') ||
    empty($renewal_metadata['renewal_started_at']) ||
    empty($renewal_metadata['renewal_terms'])
) {
    throw new RuntimeException('Renewal checkout did not preserve the expired order and prepare a new payment attempt.');
}
$renewal_metadata['legacy_order_id'] = 777;
$renewal_metadata['mds_fields'] = [
    'mds3_fixture_custom_name' => [
        'label' => 'Custom name',
        'type' => 'text',
        'value' => 'Legacy Custom Name',
        'formatted_value' => 'Legacy Custom Name',
    ],
];
(new OrderRepository())->update($term_order_id, ['metadata' => $renewal_metadata]);
$term_cleanup_settings['order-completed-renewal-content'] = '<p>Legacy renewal %ORIGINAL_ORDER_ID% %VIEW_URL% %MDS3_FIXTURE_CUSTOM_NAME% %DEADLINE%</p>';
update_option('mds3_settings', $term_cleanup_settings);

$term_renewed_mail_start = count($captured_mail);
$term_renew_paid = new WP_REST_Request('PATCH', '/million-dollar-script/v1/orders/' . absint($term_order_id));
$term_renew_paid->set_body_params(['status' => 'paid']);
$term_renew_paid_response = rest_do_request($term_renew_paid);
if ($term_renew_paid_response->is_error()) {
    throw new RuntimeException($term_renew_paid_response->as_error()->get_error_message());
}
$term_renewed_order = (new OrderRepository())->find($term_order_id);
$term_renewed_metadata = json_decode((string) ($term_renewed_order['metadata'] ?? ''), true);
$term_renewed_block = $GLOBALS['wpdb']->get_row(
    $GLOBALS['wpdb']->prepare(
        'SELECT status, order_id FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d',
        absint($term_block['id'])
    ),
    ARRAY_A
);
if (
    'paid' !== ($term_renewed_order['status'] ?? '') ||
    'sold' !== ($term_renewed_block['status'] ?? '') ||
    absint($term_renewed_block['order_id'] ?? 0) !== absint($term_order_id) ||
    empty($term_renewed_metadata['expires_at']) ||
    strtotime((string) $term_renewed_metadata['expires_at']) <= time() ||
    !empty($term_renewed_metadata['renewal_started_at'])
) {
    throw new RuntimeException('Renewed order did not start a fresh paid term and reactivate inventory.');
}
$renewal_paid_notice_sent = false;
$renewal_paid_notice_body = '';
foreach (array_slice($captured_mail, $term_renewed_mail_start) as $mail) {
    if ('paid-expiry-fixture@example.com' === sanitize_email((string) ($mail['to'] ?? '')) && false !== strpos((string) ($mail['subject'] ?? ''), 'Renewal Paid')) {
        $renewal_paid_notice_sent = true;
        $renewal_paid_notice_body = (string) ($mail['message'] ?? '');
        break;
    }
}
if (!$renewal_paid_notice_sent) {
    throw new RuntimeException('Renewal payment did not send the MDS3 renewal paid notice.');
}
if (
    false === strpos($renewal_paid_notice_body, 'Legacy Custom Name') ||
    false === strpos($renewal_paid_notice_body, '777') ||
    false === strpos($renewal_paid_notice_body, 'admin.php?page=mds3-orders') ||
    false !== strpos($renewal_paid_notice_body, '%MDS3_FIXTURE_CUSTOM_NAME%') ||
    false !== strpos($renewal_paid_notice_body, '%ORIGINAL_ORDER_ID%')
) {
    throw new RuntimeException('Renewal paid email did not replace legacy and custom-field placeholders.');
}

$term_renewed_metadata['expires_at'] = gmdate('Y-m-d H:i:s', time() - (10 * MINUTE_IN_SECONDS));
(new OrderRepository())->update($term_order_id, ['metadata' => $term_renewed_metadata]);
$term_cleanup = (new OrderCleanup())->run(500);
$term_expired_order = (new OrderRepository())->find($term_order_id);
if ('expired' !== ($term_expired_order['status'] ?? '')) {
    throw new RuntimeException('Renewed paid order did not expire again for the renewal-window release check.');
}

$term_cleanup_settings['minutes-renew'] = -1;
update_option('mds3_settings', $term_cleanup_settings);
$term_release = (new OrderCleanup())->run(500);
$term_cancelled_order = (new OrderRepository())->find($term_order_id);
$term_released_block = $GLOBALS['wpdb']->get_row(
    $GLOBALS['wpdb']->prepare(
        'SELECT status, order_id FROM ' . DB::ident(DB::table('blocks')) . ' WHERE id = %d',
        absint($term_block['id'])
    ),
    ARRAY_A
);
if (
    absint($term_release['cancelled'] ?? 0) < 1 ||
    'cancelled' !== ($term_cancelled_order['status'] ?? '') ||
    'available' !== ($term_released_block['status'] ?? '') ||
    0 !== absint($term_released_block['order_id'] ?? 0)
) {
    throw new RuntimeException('Expired paid order did not release inventory after the renewal window.');
}
} finally {
    remove_filter('pre_wp_mail', $mail_filter, 10);
    update_option('mds3_settings', $original_settings);

    $fixture_order_ids = array_map('absint', (array) $GLOBALS['wpdb']->get_col(
        $GLOBALS['wpdb']->prepare(
            'SELECT DISTINCT order_id FROM ' . DB::ident(DB::table('order_items')) . ' WHERE grid_id = %d',
            $grid->id()
        )
    ));
    $GLOBALS['wpdb']->delete(DB::table('placements'), ['grid_id' => $grid->id()]);
    $GLOBALS['wpdb']->delete(DB::table('order_items'), ['grid_id' => $grid->id()]);
    foreach ($fixture_order_ids as $fixture_order_id) {
        $remaining_items = (int) $GLOBALS['wpdb']->get_var(
            $GLOBALS['wpdb']->prepare(
                'SELECT COUNT(*) FROM ' . DB::ident(DB::table('order_items')) . ' WHERE order_id = %d',
                $fixture_order_id
            )
        );
        if (0 === $remaining_items) {
            $GLOBALS['wpdb']->delete(DB::table('orders'), ['id' => $fixture_order_id]);
        }
    }
    foreach (['blocks', 'packages', 'price_rules', 'pages', 'render_jobs'] as $fixture_table) {
        $GLOBALS['wpdb']->delete(DB::table($fixture_table), ['grid_id' => $grid->id()]);
    }
    (new GridRepository())->delete($grid->id());
}

echo wp_json_encode([
    'grid_id' => $grid->id(),
    'order_id' => $order_id,
    'status' => $order['status'],
    'block_status' => $block_after['status'],
]) . "\n";
