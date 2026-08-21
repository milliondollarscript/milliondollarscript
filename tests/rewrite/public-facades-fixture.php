<?php
/**
 * WordPress fixture for stable extension-facing facades.
 */

use MillionDollarScript\Commerce\Currency;
use MillionDollarScript\Commerce\Payments;
use MillionDollarScript\Commerce\Sources;
use MillionDollarScript\Core\ApiAccess;
use MillionDollarScript\Core\Database;
use MillionDollarScript\Core\Grids;
use MillionDollarScript\Core\Hooks;
use MillionDollarScript\Core\Orders;
use MillionDollarScript\Core\Runtime;
use MillionDollarScript\Core\Settings;
use MillionDollarScript\Extensions\CleanupPolicy;
use MillionDollarScript\Extensions\Registry;
use MillionDollarScript\Extensions\Support;
use MillionDollarScript\Media\Placements;
use MillionDollarScript\Rendering\Estimate;

if (!defined('ABSPATH')) {
    exit(1);
}

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(Runtime::is_ready(), 'Stable Runtime facade did not report a booted core.');
$assert(
    defined('MILLION_DOLLAR_SCRIPT_VERSION') && (string) MILLION_DOLLAR_SCRIPT_VERSION === Runtime::version(),
    'Stable Runtime facade returned a version that does not match the installed package.'
);
$assert('1' === Runtime::api_version(), 'Stable Runtime facade returned the wrong API major.');
$assert(str_ends_with(Runtime::file(), '/million-dollar-script.php'), 'Stable Runtime file metadata is invalid.');
$assert(str_ends_with(Runtime::path('assets/mds3/css/admin.css'), '/assets/mds3/css/admin.css'), 'Stable Runtime path join failed.');
$assert(str_contains(Runtime::url('assets/mds3/css/admin.css'), '/assets/mds3/css/admin.css'), 'Stable Runtime URL join failed.');
$assert(str_ends_with(Runtime::url('assets/mds3/'), '/assets/mds3/'), 'Stable Runtime URL removed a requested directory separator.');
$assert(Runtime::path('../wp-config.php') === Runtime::path(), 'Stable Runtime allowed a traversal path.');

$grid = Grids::first_active();
if ($grid) {
    $assert($grid->id() > 0, 'Stable grid value object did not expose an ID.');
    $assert(is_array($grid->to_array()), 'Stable grid value object did not expose array data.');

    $legacy_grid = (new \MDS\Core\Grid())->get($grid->id());
    $assert($legacy_grid && $legacy_grid->get_id() === $grid->id(), 'Legacy grid adapter did not delegate to the stable facade.');
}

$assert(str_ends_with(Database::table('orders'), 'mds3_orders'), 'Stable Database facade returned the wrong table.');
$assert(is_array(Settings::all()), 'Stable Settings facade did not return an array.');
$assert('CAD' === Currency::normalize_code('cad', 'USD'), 'Stable Currency facade failed normalization.');
$recurring_adapters = Payments::recurring_adapters();
$assert(is_array($recurring_adapters), 'Recurring payment facade did not return an adapter array.');
foreach ($recurring_adapters as $provider_id => $adapter) {
    $assert(
        $provider_id === ($adapter['provider'] ?? '') && is_array($adapter['capabilities'] ?? null),
        'Recurring payment facade returned an invalid registered adapter.'
    );
}
$commerce_sources = Sources::all();
$assert(is_array($commerce_sources), 'Commerce source facade did not return a source array.');
foreach ($commerce_sources as $source_id => $source) {
    $assert(
        $source_id === ($source['id'] ?? '') && is_array($source['modes'] ?? null),
        'Commerce source facade returned an invalid registered source.'
    );
}

$estimate = Estimate::grid(['width' => 1000, 'height' => 1000]);
$assert(1.0 === (float) ($estimate['grid_megapixels'] ?? 0), 'Stable rendering estimate facade returned the wrong grid size.');

$registered_before = get_option('mds3_registered_extensions', []);
$cleanup_registry_before = get_option('mds3_extension_cleanup_registry', []);
$cleanup_included_before = get_option('mds3_extension_cleanup_included', []);
$settings_before = get_option('mds3_settings', []);
$assert(Registry::register([
    'id' => 'public-facade-fixture',
    'name' => 'Public Facade Fixture',
    'cleanup' => [
        'description' => 'Fixture-owned settings and records.',
    ],
]), 'Stable Registry facade rejected valid metadata.');
$assert(isset(Registry::registered()['public-facade-fixture']), 'Stable Registry facade did not persist metadata.');
$assert(isset(CleanupPolicy::registered()['public-facade-fixture']), 'Stable Registry facade did not register extension cleanup metadata.');
$assert(CleanupPolicy::is_included('public-facade-fixture'), 'Registered extension cleanup was not selected by default.');
$assert(!CleanupPolicy::allows_cleanup('public-facade-fixture'), 'Extension cleanup ignored the disabled core parent setting.');
update_option('mds3_settings', array_merge(is_array($settings_before) ? $settings_before : [], ['delete_data_on_uninstall' => 'yes']), false);
$assert(CleanupPolicy::allows_cleanup('public-facade-fixture'), 'Extension cleanup was not allowed when both policy levels were enabled.');
\MillionDollarScript\V3\Extensions\ExtensionCleanupPolicy::save_inclusions([]);
$assert(!CleanupPolicy::allows_cleanup('public-facade-fixture'), 'Excluded extension cleanup remained allowed.');
$assert(!CleanupPolicy::allows_cleanup('unknown-extension'), 'Unknown extension cleanup did not fail closed.');
update_option('mds3_registered_extensions', is_array($registered_before) ? $registered_before : [], false);
update_option('mds3_extension_cleanup_registry', is_array($cleanup_registry_before) ? $cleanup_registry_before : [], false);
update_option('mds3_extension_cleanup_included', is_array($cleanup_included_before) ? $cleanup_included_before : [], false);
update_option('mds3_settings', is_array($settings_before) ? $settings_before : [], false);

wp_register_script('mds-public-facade-browser-fixture', false, [], Runtime::version(), true);
$assert(Support::add_browser_config('mds-public-facade-browser-fixture', 'fixture', 'config', ['ready' => true]), 'Stable browser config helper rejected valid data.');
$before_scripts = wp_scripts()->get_data('mds-public-facade-browser-fixture', 'before');
$before_scripts = is_array($before_scripts) ? implode("\n", $before_scripts) : (string) $before_scripts;
$assert(str_contains($before_scripts, 'window.MillionDollarScript.extensions["fixture"]["config"]'), 'Stable browser config helper did not publish extension state.');

$autoload_dir = trailingslashit(sys_get_temp_dir()) . 'mds-extension-autoload-' . wp_generate_uuid4();
$assert(wp_mkdir_p($autoload_dir), 'Could not create the extension autoloader fixture directory.');
$assert(
    false !== file_put_contents(
        $autoload_dir . '/Standard.php',
        "<?php\nnamespace MillionDollarScript\\Tests\\ExtensionAutoload;\nfinal class Standard {}\n"
    ),
    'Could not create the standard extension autoloader fixture.'
);
$assert(
    false !== file_put_contents(
        $autoload_dir . '/LegacyClass.php',
        "<?php\nnamespace MillionDollarScript\\Tests\\ExtensionAutoload;\nfinal class Legacy_Class {}\n"
    ),
    'Could not create the legacy extension autoloader fixture.'
);
$assert(
    Support::register_autoloader('MillionDollarScript\\Tests\\ExtensionAutoload', $autoload_dir),
    'Stable extension autoloader helper rejected a readable source directory.'
);
$assert(class_exists('MillionDollarScript\\Tests\\ExtensionAutoload\\Standard'), 'Stable extension autoloader did not resolve a PSR-4 class.');
$assert(class_exists('MillionDollarScript\\Tests\\ExtensionAutoload\\Legacy_Class'), 'Stable extension autoloader did not resolve an underscore-named class.');
unlink($autoload_dir . '/Standard.php');
unlink($autoload_dir . '/LegacyClass.php');
rmdir($autoload_dir);

$current_user_id = get_current_user_id();
wp_set_current_user(0);
$denied = ApiAccess::authorize(new WP_REST_Request('POST', '/million-dollar-script/v1/settings'), 'settings.write', 'wp_capability');
$assert(is_wp_error($denied), 'Stable API authorization facade converted a denied request into success.');
wp_set_current_user($current_user_id);

global $wpdb;
$order_id = absint($wpdb->get_var('SELECT id FROM ' . Database::ident(Database::table('orders')) . ' ORDER BY id DESC LIMIT 1'));
if ($order_id) {
    $order = Orders::find($order_id);
    $assert(is_array($order) && absint($order['id'] ?? 0) === $order_id, 'Stable Orders facade did not return array data.');
}

$customer_order_id = Orders::create([], [
    'user_id' => $current_user_id,
    'email' => 'public-facade-owner@example.test',
    'status' => 'pending_payment',
]);
$assert(!is_wp_error($customer_order_id) && $customer_order_id > 0, 'Stable Orders facade could not create a customer fixture.');
$second_customer_order_id = Orders::create([], [
    'user_id' => $current_user_id,
    'email' => 'public-facade-owner@example.test',
    'status' => 'reserved',
]);
$assert(!is_wp_error($second_customer_order_id) && $second_customer_order_id > 0, 'Stable Orders facade could not create a second customer fixture.');
$principal = [
    'user_id' => $current_user_id,
    'email' => 'public-facade-owner@example.test',
];
$customer_page = Orders::query_for_principal($principal, ['limit' => 10]);
$assert(!is_wp_error($customer_page) && absint($customer_page['total'] ?? 0) >= 2, 'Customer order query did not return owned orders.');
$cursor_page = Orders::query_for_principal($principal, ['limit' => 1, 'pagination' => 'cursor']);
$assert(
    !is_wp_error($cursor_page) &&
    1 === count($cursor_page['items'] ?? []) &&
    !empty($cursor_page['has_more']) &&
    !empty($cursor_page['next_cursor']) &&
    !array_key_exists('total', $cursor_page),
    'Customer cursor query did not return a bounded page without an implicit exact count.'
);
$cursor_next_page = Orders::query_for_principal($principal, [
    'limit' => 1,
    'pagination' => 'cursor',
    'cursor' => $cursor_page['next_cursor'] ?? '',
]);
$assert(
    !is_wp_error($cursor_next_page) &&
    1 === count($cursor_next_page['items'] ?? []) &&
    absint($cursor_next_page['items'][0]['id'] ?? 0) !== absint($cursor_page['items'][0]['id'] ?? 0),
    'Customer cursor query repeated the previous keyset page.'
);
$invalid_cursor_page = Orders::query_for_principal($principal, ['pagination' => 'cursor', 'cursor' => 'invalid']);
$assert(is_wp_error($invalid_cursor_page), 'Customer cursor query accepted an invalid token.');
$customer_detail = Orders::find_for_principal($customer_order_id, $principal);
$assert(is_array($customer_detail) && absint($customer_detail['id'] ?? 0) === $customer_order_id, 'Customer order detail did not enforce the owner principal.');
$assert(!array_key_exists('order_key', $customer_detail) && !array_key_exists('email', $customer_detail), 'Customer order payload exposed private credentials.');
$denied_customer_detail = Orders::find_for_principal($customer_order_id, ['email' => 'unrelated@example.test']);
$assert(null === $denied_customer_detail, 'Customer order detail allowed an unrelated principal.');
$missing_principal = Orders::query_for_principal([], []);
$assert(is_wp_error($missing_principal), 'Customer order query accepted an empty principal.');
$wpdb->delete(Database::table('orders'), ['id' => absint($customer_order_id)]);
$wpdb->delete(Database::table('orders'), ['id' => absint($second_customer_order_id)]);

$facades = [
    Runtime::class,
    ApiAccess::class,
    Hooks::class,
    Grids::class,
    Orders::class,
    Database::class,
    Settings::class,
    Currency::class,
    Payments::class,
    Sources::class,
    Registry::class,
    Support::class,
    Placements::class,
    Estimate::class,
];
foreach ($facades as $facade) {
    $reflection = new ReflectionClass($facade);
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        $signature = (string) $method->getReturnType();
        foreach ($method->getParameters() as $parameter) {
            $signature .= ' ' . (string) $parameter->getType();
        }
        $assert(!str_contains($signature, 'MillionDollarScript\\V3'), 'Facade signature leaked an internal V3 type.');
    }
}

echo wp_json_encode([
    'runtime' => Runtime::version(),
    'api' => Runtime::api_version(),
    'grid_id' => $grid ? $grid->id() : 0,
    'order_id' => $order_id,
    'facades' => count($facades),
]);
