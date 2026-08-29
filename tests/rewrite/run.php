<?php
/**
 * MDS3 runtime smoke tests.
 */

define('ABSPATH', __DIR__ . '/../../../../');
define('MILLION_DOLLAR_SCRIPT_SRC_PATH', __DIR__ . '/../../src/');
define('MILLION_DOLLAR_SCRIPT_BASENAME', 'million-dollar-script/million-dollar-script.php');
define('MILLION_DOLLAR_SCRIPT_FILE', __DIR__ . '/../../million-dollar-script.php');
define('MILLION_DOLLAR_SCRIPT_PATH', dirname(MILLION_DOLLAR_SCRIPT_FILE) . '/');
define('MILLION_DOLLAR_SCRIPT_URL', 'https://example.test/wp-content/plugins/million-dollar-script/');
if (!defined('MILLION_DOLLAR_SCRIPT_VERSION')) {
    define('MILLION_DOLLAR_SCRIPT_VERSION', '3.0.0-test');
}
if (!defined('MILLION_DOLLAR_SCRIPT_DISTRIBUTION')) {
    define('MILLION_DOLLAR_SCRIPT_DISTRIBUTION', 'direct');
}

require_once MILLION_DOLLAR_SCRIPT_SRC_PATH . 'Autoload.php';
\MillionDollarScript\V3\Autoload::register(MILLION_DOLLAR_SCRIPT_SRC_PATH);

class MDS3_SQLite_Test_Database {
    public $prefix = 'wp_';
    public $last_error = '';

    public function get_var() {
        return 1;
    }
}
class MDS3_MySQL_Test_Database extends MDS3_SQLite_Test_Database {}

$original_wpdb = $GLOBALS['wpdb'] ?? null;
$GLOBALS['wpdb'] = new MDS3_SQLite_Test_Database();
if ("JSON_EXTRACT(metadata, '$.expires_at')" !== \MillionDollarScript\V3\Support\DB::json_scalar('metadata', "'$.expires_at'")) {
    throw new RuntimeException('Expected SQLite JSON scalar expressions to omit MySQL JSON_UNQUOTE().');
}
$order_summary_method = new ReflectionMethod(\MillionDollarScript\V3\Orders\OrderRepository::class, 'order_summary_selects');
$order_summary_method->setAccessible(true);
$sqlite_order_summary = $order_summary_method->invoke(new \MillionDollarScript\V3\Orders\OrderRepository());
if (false !== strpos($sqlite_order_summary, ' SEPARATOR ') || false === strpos($sqlite_order_summary, "GROUP_CONCAT(grid_id, ',')")) {
    throw new RuntimeException('Expected SQLite order summaries to use native ordered GROUP_CONCAT syntax.');
}
$GLOBALS['wpdb'] = new MDS3_MySQL_Test_Database();
if ("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.expires_at'))" !== \MillionDollarScript\V3\Support\DB::json_scalar('metadata', "'$.expires_at'")) {
    throw new RuntimeException('Expected MySQL JSON scalar expressions to retain JSON_UNQUOTE().');
}
$mysql_order_summary = $order_summary_method->invoke(new \MillionDollarScript\V3\Orders\OrderRepository());
if (false === strpos($mysql_order_summary, "ORDER BY g_ids.id SEPARATOR ','")) {
    throw new RuntimeException('Expected MySQL order summaries to retain ordered GROUP_CONCAT syntax.');
}
if (null === $original_wpdb) {
    unset($GLOBALS['wpdb']);
} else {
    $GLOBALS['wpdb'] = $original_wpdb;
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class($class, $fallback = '') {
        $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $class);

        return '' !== $sanitized ? $sanitized : (string) $fallback;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback) {
        $GLOBALS['mds3_test_filters'][(string) $hook][] = $callback;

        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback) {
        return add_filter($hook, $callback);
    }
}

if (!function_exists('has_filter')) {
    function has_filter($hook) {
        return !empty($GLOBALS['mds3_test_filters'][(string) $hook]);
    }
}

if (!function_exists('has_action')) {
    function has_action($hook) {
        return has_filter($hook);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        foreach (($GLOBALS['mds3_test_filters'][(string) $hook] ?? []) as $callback) {
            if (is_callable($callback)) {
                $value = call_user_func($callback, $value, ...$args);
            }
        }

        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        foreach (($GLOBALS['mds3_test_filters'][(string) $hook] ?? []) as $callback) {
            if (is_callable($callback)) {
                call_user_func($callback, ...$args);
            }
        }
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value, $flags = 0, $depth = 512) {
        return json_encode($value, $flags, $depth);
    }
}

if (!function_exists('wp_add_inline_script')) {
    function wp_add_inline_script($handle, $data, $position = 'after') {
        $GLOBALS['mds3_test_inline_scripts'][(string) $handle][(string) $position][] = (string) $data;

        return true;
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = [], $override = false) {
        $GLOBALS['mds3_test_rest_routes'][] = [(string) $namespace, (string) $route, $args, (bool) $override];

        return true;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $args = get_object_vars($args);
        } elseif (!is_array($args)) {
            parse_str((string) $args, $parsed);
            $args = $parsed;
        }

        return array_merge((array) $defaults, (array) $args);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct($code = '', $message = '', $data = []) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) {
        return esc_html(__($text, $domain));
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text, $remove_breaks = false) {
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', (string) $text);
        $text = strip_tags((string) $text);

        return $remove_breaks ? preg_replace('/[\r\n\t ]+/', ' ', $text) : $text;
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($value) {
        $value = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', (string) $value);

        return preg_replace('/\s+on[a-z]+\s*=\s*"[^"]*"/i', '', (string) $value);
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        $title = strtolower(trim((string) $title));
        $title = preg_replace('/[^a-z0-9]+/', '-', $title);

        return trim((string) $title, '-');
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url, $protocols = null) {
        return trim((string) $url);
    }
}

if (!isset($GLOBALS['mds3_test_options'])) {
    $GLOBALS['mds3_test_options'] = [];
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return array_key_exists($option, $GLOBALS['mds3_test_options']) ? $GLOBALS['mds3_test_options'][$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        $GLOBALS['mds3_test_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        unset($GLOBALS['mds3_test_options'][$option]);
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        unset($GLOBALS['mds3_test_transients'][$transient]);
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return $GLOBALS['mds3_test_transients'][$transient] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        $GLOBALS['mds3_test_transients'][$transient] = $value;
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        if (array_key_exists((string) $capability, $GLOBALS['mds3_test_capabilities'] ?? [])) {
            return (bool) $GLOBALS['mds3_test_capabilities'][(string) $capability];
        }

        return false;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return 'valid-rest-nonce' === $nonce && 'wp_rest' === $action;
    }
}

if (!function_exists('get_site_transient')) {
    function get_site_transient($transient) {
        return false;
    }
}

if (!function_exists('get_plugins')) {
    function get_plugins($plugin_folder = '') {
        return $GLOBALS['mds3_test_plugins'] ?? [];
    }
}

if (!function_exists('is_plugin_active')) {
    function is_plugin_active($plugin) {
        return in_array((string) $plugin, $GLOBALS['mds3_test_active_plugins'] ?? [], true);
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return ltrim(str_replace('\\', '/', (string) $file), '/');
    }
}

if (!function_exists('activate_plugin')) {
    function activate_plugin($plugin, $redirect = '', $network_wide = false, $silent = false) {
        $GLOBALS['mds3_test_activation_silent'] = (bool) $silent;
        $GLOBALS['mds3_test_active_plugins'][] = (string) $plugin;
        $GLOBALS['mds3_test_active_plugins'] = array_values(array_unique($GLOBALS['mds3_test_active_plugins']));
        if ('woocommerce/woocommerce.php' === $plugin && !$silent) {
            $GLOBALS['mds3_test_transients']['_wc_activation_redirect'] = true;
        }

        return null;
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        return ['response' => ['code' => 503], 'body' => ''];
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        if (!empty($GLOBALS['mds3_test_remote_post_queue'])) {
            return array_shift($GLOBALS['mds3_test_remote_post_queue']);
        }

        return ['response' => ['code' => 503], 'body' => ''];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return is_array($response) ? (int) ($response['response']['code'] ?? 0) : 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return is_array($response) ? (string) ($response['body'] ?? '') : '';
    }
}

if (!function_exists('wp_max_upload_size')) {
    function wp_max_upload_size() {
        return 5 * 1024 * 1024;
    }
}

if (!function_exists('size_format')) {
    function size_format($bytes) {
        return (string) $bytes;
    }
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0) {
        return number_format((float) $number, (int) $decimals, '.', ',');
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        $separator = false === strpos((string) $url, '?') ? '?' : '&';

        return (string) $url . $separator . http_build_query((array) $args);
    }
}

if (!function_exists('wp_check_filetype_and_ext')) {
    function wp_check_filetype_and_ext($file, $filename, $mimes = []) {
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        $allowed = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        return [
            'ext' => array_key_exists($extension, $allowed) ? $extension : false,
            'type' => $allowed[$extension] ?? false,
            'proper_filename' => false,
        ];
    }
}

if (!function_exists('wp_getimagesize')) {
    function wp_getimagesize($filename) {
        return getimagesize($filename);
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '') {
        return 'https://example.test/wp-json/' . ltrim((string) $path, '/');
    }
}

if (!class_exists('WooCommerce')) {
    class WooCommerce {}
}

if (!function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency() {
        return 'cad';
    }
}

if (!function_exists('get_woocommerce_currency_symbol')) {
    function get_woocommerce_currency_symbol($currency = '') {
        return 'C$';
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url) {
        return parse_url($url);
    }
}

function mds3_assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$public_facades = [
    \MillionDollarScript\Core\Runtime::class,
    \MillionDollarScript\Core\Hooks::class,
    \MillionDollarScript\Core\Grid::class,
    \MillionDollarScript\Core\Grids::class,
    \MillionDollarScript\Core\Orders::class,
    \MillionDollarScript\Core\Database::class,
    \MillionDollarScript\Core\Settings::class,
    \MillionDollarScript\Core\ApiAccess::class,
    \MillionDollarScript\Commerce\Currency::class,
    \MillionDollarScript\Commerce\Payments::class,
    \MillionDollarScript\Media\OriginalImage::class,
    \MillionDollarScript\Media\OriginalAttachmentResolver::class,
    \MillionDollarScript\Media\Placements::class,
    \MillionDollarScript\Rendering\Estimate::class,
    \MillionDollarScript\Extensions\Admin::class,
    \MillionDollarScript\Extensions\Registry::class,
    \MillionDollarScript\Extensions\Support::class,
];
foreach ($public_facades as $facade) {
    mds3_assert_same(true, class_exists($facade), 'Expected public facade to autoload: ' . $facade);
    $reflection = new ReflectionClass($facade);
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        $signature = (string) $method->getReturnType();
        foreach ($method->getParameters() as $parameter) {
            $signature .= ' ' . (string) $parameter->getType();
        }
        mds3_assert_same(false, str_contains($signature, 'MillionDollarScript\\V3'), 'Public facade signature leaked an internal V3 type: ' . $facade . '::' . $method->getName());
    }
}

mds3_assert_same('3.0.0-test', \MillionDollarScript\Core\Runtime::version(), 'Expected stable runtime version metadata.');
mds3_assert_same('1', \MillionDollarScript\Core\Runtime::api_version(), 'Expected stable API major version.');
mds3_assert_same(MILLION_DOLLAR_SCRIPT_FILE, \MillionDollarScript\Core\Runtime::file(), 'Expected stable runtime file metadata.');
mds3_assert_same(MILLION_DOLLAR_SCRIPT_PATH . 'assets/admin.css', \MillionDollarScript\Core\Runtime::path('assets/admin.css'), 'Expected safe relative runtime paths.');
mds3_assert_same(MILLION_DOLLAR_SCRIPT_PATH, \MillionDollarScript\Core\Runtime::path('../outside.php'), 'Expected runtime path traversal to be rejected.');
mds3_assert_same(MILLION_DOLLAR_SCRIPT_URL . 'assets/admin.css', \MillionDollarScript\Core\Runtime::url('assets/admin.css'), 'Expected stable runtime URLs.');
mds3_assert_same(MILLION_DOLLAR_SCRIPT_URL . 'assets/mds3/', \MillionDollarScript\Core\Runtime::url('assets/mds3/'), 'Expected runtime directory URLs to preserve their trailing separator.');

$stable_action_calls = 0;
add_action('million-dollar-script/fixture/action', static function ($value) use (&$stable_action_calls) {
    $stable_action_calls += (int) $value;
});
\MillionDollarScript\Core\Hooks::do('million-dollar-script/fixture/action', 1);
mds3_assert_same(1, $stable_action_calls, 'Expected stable action to run exactly once.');

add_filter('million-dollar-script/fixture/filter', static function ($value) {
    return $value . '-stable';
});
mds3_assert_same(
    'start-stable',
    \MillionDollarScript\Core\Hooks::apply('million-dollar-script/fixture/filter', 'start'),
    'Expected the stable filter to preserve the filtered value.'
);
mds3_assert_same(
    ['mds_register_extensions'],
    \MillionDollarScript\Core\Hooks::legacy_aliases('million-dollar-script/register/extensions'),
    'Expected the explicit Million Dollar Script 2 extension registration alias.'
);
mds3_assert_same(
    [],
    \MillionDollarScript\Core\Hooks::legacy_aliases('million-dollar-script/fixture/filter'),
    'Expected no automatic pre-release hook alias generation.'
);

$pre_release_registration_calls = 0;
$mds2_registration_calls = 0;
add_action('mds3_register_extensions', static function () use (&$pre_release_registration_calls) {
    $pre_release_registration_calls++;
});
add_action('mds_register_extensions', static function () use (&$mds2_registration_calls) {
    $mds2_registration_calls++;
});
\MillionDollarScript\Core\Hooks::do('million-dollar-script/register/extensions');
mds3_assert_same(0, $pre_release_registration_calls, 'Expected the pre-release registration hook to remain unused.');
mds3_assert_same(1, $mds2_registration_calls, 'Expected Million Dollar Script 2 registration compatibility action.');

add_filter('million-dollar-script/extensions/fixture/value', static function ($value) {
    return $value . '-stable-extension';
});
add_filter('mds_fixture_value', static function ($value) {
    return $value . '-legacy-extension';
});
mds3_assert_same(
    'start-stable-extension-legacy-extension',
    \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/fixture/value', ['mds_fixture_value'], 'start'),
    'Expected extension-owned compatibility filters to preserve stable-first ordering.'
);

$rest_api = new \MillionDollarScript\V3\Rest\Api();
mds3_assert_same(
    ['million-dollar-script/v1'],
    $rest_api->rest_namespaces(),
    'Expected only the canonical REST namespace.'
);
$reservation_metadata_method = new ReflectionMethod($rest_api, 'reservation_metadata');
$reservation_metadata_method->setAccessible(true);
$reservation_metadata = $reservation_metadata_method->invoke($rest_api, [
    'subscription_plan_id' => '42',
    'metadata' => [
        'campaign' => 'fixture',
        'subscription_plan_id' => 7,
    ],
]);
mds3_assert_same(
    ['campaign' => 'fixture', 'subscription_plan_id' => 42],
    $reservation_metadata,
    'Expected REST reservations to preserve caller metadata and normalize the explicit subscription plan.'
);

$rest_route_count = count($GLOBALS['mds3_test_rest_routes'] ?? []);
mds3_assert_same(
    true,
    \MillionDollarScript\Extensions\Support::register_rest_route('/fixture', ['methods' => 'GET']),
    'Expected extension REST route registration to succeed.'
);
$extension_rest_routes = array_slice($GLOBALS['mds3_test_rest_routes'] ?? [], $rest_route_count);
mds3_assert_same('million-dollar-script/v1', $extension_rest_routes[0][0] ?? '', 'Expected canonical extension REST namespace.');
mds3_assert_same(1, count($extension_rest_routes), 'Expected one canonical extension REST route registration.');

mds3_assert_same(
    true,
    \MillionDollarScript\V3\Support\BrowserConfig::add(
        'fixture-script',
        'fixtureConfig',
        ['unsafe' => '</script><script>alert(1)</script>']
    ),
    'Expected browser configuration injection to succeed.'
);
$browser_config_script = implode("\n", $GLOBALS['mds3_test_inline_scripts']['fixture-script']['before'] ?? []);
mds3_assert_same(true, str_contains($browser_config_script, 'window.MillionDollarScript'), 'Expected stable browser namespace initialization.');
mds3_assert_same(false, str_contains($browser_config_script, 'MDS3FixtureConfig'), 'Expected no pre-release browser compatibility global.');
mds3_assert_same(false, str_contains($browser_config_script, '</script>'), 'Expected inline browser configuration to hex-escape script terminators.');

$registered_callback_ran = false;
mds3_assert_same(true, \MillionDollarScript\Extensions\Registry::register([
    'id' => 'fixture-extension',
    'init_callback' => static function () use (&$registered_callback_ran) {
        $registered_callback_ran = true;
    },
]), 'Expected stable extension registration.');
mds3_assert_same(true, $registered_callback_ran, 'Expected registered extension initialization callback.');
mds3_assert_same(true, isset(\MillionDollarScript\Extensions\Registry::registered()['fixture-extension']), 'Expected registered extension metadata.');

function mds3_test_png($width, $height) {
    $width = max(1, (int) $width);
    $height = max(1, (int) $height);
    $rows = '';
    for ($row = 0; $row < $height; $row++) {
        $rows .= "\x00" . str_repeat("\x1f\x8b\xc7\xff", $width);
    }

    $chunk = static function ($type, $data) {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };

    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NNC5', $width, $height, 8, 6, 0, 0, 0))
        . $chunk('IDAT', gzcompress($rows, 9))
        . $chunk('IEND', '');
}

function mds3_test_remove_tree($path) {
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}

function mds3_test_has_audit_issue(array $issues, $rule) {
    foreach ($issues as $issue) {
        if (($issue['rule'] ?? '') === $rule || 0 === strpos((string) ($issue['rule'] ?? ''), (string) $rule)) {
            return true;
        }
    }

    return false;
}

$audit_script = dirname(__DIR__, 6) . '/scripts/mds3-release-audit.php';
if (is_file($audit_script)) {
    require_once $audit_script;
    $audit = new \MDS3ReleaseAudit();
    $audit_dir = sys_get_temp_dir() . '/mds3-release-audit-' . uniqid('', true);
    mkdir($audit_dir . '/src', 0777, true);
    mkdir($audit_dir . '/docs', 0777, true);
    file_put_contents($audit_dir . '/src/Stale.php', "<?php\n__('OpenSeadragon', 'million-dollar-script');\n");
    file_put_contents($audit_dir . '/readme.txt', "Use `MILLION_DOLLAR_SCRIPT_VERSION` only when documenting a technical constant.\n");
    $audit_result = $audit->audit([
        'package_dir' => $audit_dir,
        'include_installed' => false,
    ]);
    mds3_assert_same(false, $audit_result['ok'], 'Expected release audit fixture to fail on stale OpenSeadragon wording.');
    mds3_assert_same(true, mds3_test_has_audit_issue($audit_result['issues'], 'stale-openseadragon'), 'Expected release audit fixture to report stale OpenSeadragon wording.');
    mds3_assert_same(false, mds3_test_has_audit_issue($audit_result['issues'], 'public-mds3-wording'), 'Expected release audit fixture to allow technical MDS3 code identifiers inside Markdown code spans.');
    mds3_test_remove_tree($audit_dir);

    $vendor_audit_dir = sys_get_temp_dir() . '/mds3-vendor-audit-' . uniqid('', true);
    mkdir($vendor_audit_dir . '/assets/mds3/vendor/ol', 0777, true);
    file_put_contents($vendor_audit_dir . '/million-dollar-script.php', "<?php\n");
    file_put_contents($vendor_audit_dir . '/assets/mds3/vendor/ol/ol.js', 'modified');
    file_put_contents($vendor_audit_dir . '/assets/mds3/vendor/ol/ol.css', 'modified');
    file_put_contents($vendor_audit_dir . '/assets/mds3/vendor/ol/LICENSE.md', 'modified');
    $vendor_audit_result = $audit->audit([
        'package_dir' => $vendor_audit_dir,
        'include_installed' => false,
    ]);
    mds3_assert_same(false, $vendor_audit_result['ok'], 'Expected release audit fixture to fail when vendored assets change without review.');
    mds3_assert_same(true, mds3_test_has_audit_issue($vendor_audit_result['issues'], 'vendored-asset-integrity'), 'Expected release audit fixture to report a vendored asset checksum mismatch.');
    mds3_test_remove_tree($vendor_audit_dir);

    $contract_audit_dir = sys_get_temp_dir() . '/mds3-contract-audit-' . uniqid('', true);
    mkdir($contract_audit_dir . '/core', 0777, true);
    mkdir($contract_audit_dir . '/extensions/free/mds-example/src', 0777, true);
    mkdir($contract_audit_dir . '/extensions/free/mds-example/.git/objects', 0777, true);
    file_put_contents($contract_audit_dir . '/core/bootstrap.php', "<?php\n");
    file_put_contents(
        $contract_audit_dir . '/extensions/free/mds-example/src/Bad.php',
        "<?php\nuse MDS3\\Internal\\Service;\nuse MillionDollarScript\\V3\\Grid\\GridRepository;\nuse MDS\\Extensions\\Registry;\necho MDS_VERSION;\n"
    );
    file_put_contents(
        $contract_audit_dir . '/extensions/free/mds-example/src/bad.js',
        "window.MDS3GridInstances = {};\n"
    );
    file_put_contents(
        $contract_audit_dir . '/extensions/free/mds-example/.git/objects/ignored.php',
        "<?php\nuse MillionDollarScript\\V3\\Internal;\n"
    );
    $contract_audit_result = $audit->audit([
        'package_dir' => $contract_audit_dir . '/core',
        'extensions_dir' => $contract_audit_dir . '/extensions',
        'include_installed' => false,
        'include_extensions' => true,
        'package_kind' => 'core',
    ]);
    mds3_assert_same(false, $contract_audit_result['ok'], 'Expected release audit fixture to reject private extension contracts.');
    mds3_assert_same(true, mds3_test_has_audit_issue($contract_audit_result['issues'], 'internal-mds3-namespace'), 'Expected release audit fixture to reject the former internal namespace.');
    mds3_assert_same(true, mds3_test_has_audit_issue($contract_audit_result['issues'], 'internal-versioned-namespace'), 'Expected release audit fixture to reject the private versioned namespace.');
    mds3_assert_same(true, mds3_test_has_audit_issue($contract_audit_result['issues'], 'legacy-extension-namespace'), 'Expected release audit fixture to reject the legacy extension namespace.');
    mds3_assert_same(true, mds3_test_has_audit_issue($contract_audit_result['issues'], 'ambiguous-core-global'), 'Expected release audit fixture to reject ambiguous core globals.');
    mds3_assert_same(true, mds3_test_has_audit_issue($contract_audit_result['issues'], 'legacy-mds3-browser-global'), 'Expected release audit fixture to reject the pre-release browser global.');

    unlink($contract_audit_dir . '/extensions/free/mds-example/src/Bad.php');
    unlink($contract_audit_dir . '/extensions/free/mds-example/src/bad.js');
    $ignored_contract_result = $audit->audit([
        'package_dir' => $contract_audit_dir . '/core',
        'extensions_dir' => $contract_audit_dir . '/extensions',
        'include_installed' => false,
        'include_extensions' => true,
        'package_kind' => 'core',
    ]);
    mds3_assert_same(true, $ignored_contract_result['ok'], 'Expected nested repository metadata to be excluded from extension contract scans.');
    mds3_test_remove_tree($contract_audit_dir);
}

$geometry = new \MillionDollarScript\V3\Grid\Geometry(10000, 10000, 10, 10);
mds3_assert_same(1000, $geometry->columns(), 'Expected 1000 columns.');
mds3_assert_same(1000, $geometry->rows(), 'Expected 1000 rows.');
mds3_assert_same(1000000, $geometry->total_blocks(), 'Expected sparse million-block grid.');
mds3_assert_same(['x' => 70, 'y' => 40, 'width' => 10, 'height' => 10], $geometry->rect(4, 7), 'Expected block rectangle.');

$estimate = \MillionDollarScript\V3\Rendering\Estimate::grid(
    ['width' => 10000, 'height' => 10000],
    [['width' => 4000, 'height' => 3000]],
    256,
    14
);
mds3_assert_same(100.0, $estimate['grid_megapixels'], 'Expected grid megapixel estimate.');
mds3_assert_same(12.0, $estimate['source_megapixels'], 'Expected source megapixel estimate.');
mds3_assert_same(10000, $estimate['width'], 'Expected the grid estimate to retain its width.');
mds3_assert_same(10000, $estimate['height'], 'Expected the grid estimate to retain its height.');
mds3_assert_same(15, $estimate['tile_level_count'], 'Expected a complete Deep Zoom level count.');
mds3_assert_same(2147, $estimate['tile_estimate'], 'Expected the service-compatible Deep Zoom tile count.');
mds3_assert_same(400000000, $estimate['uncompressed_canvas_bytes'], 'Expected a four-byte uncompressed canvas estimate.');
mds3_assert_same(533336492, $estimate['uncompressed_pyramid_bytes'], 'Expected the exact uncompressed pyramid estimate.');
mds3_assert_same(562823168, $estimate['storage_estimate_bytes'], 'Expected a conservative padded-tile storage estimate.');
mds3_assert_same(300, $estimate['processing_credits'], 'Expected ImageGrid grid-render credit pricing.');
mds3_assert_same(5, $estimate['estimated_patch_processing_credits'], 'Expected the minimum credit estimate for one asset patch.');
mds3_assert_same(305, $estimate['estimated_workflow_processing_credits'], 'Expected the combined base-render and patch credit estimate.');

$quota = \MillionDollarScript\V3\Rendering\Estimate::quota($estimate, ['grid_megapixels' => 50]);
mds3_assert_same(false, $quota['ok'], 'Expected quota failure.');
mds3_assert_same('grid_megapixels', $quota['failing_key'], 'Expected failing quota key.');
$imagegrid_service = new \MillionDollarScript\V3\Rendering\ImageGridService();
$levels_for_grid = new ReflectionMethod($imagegrid_service, 'levels_for_grid');
$levels_for_grid->setAccessible(true);
mds3_assert_same(14, $levels_for_grid->invoke($imagegrid_service, ['width' => 10000, 'height' => 10000]), 'Expected preflight to use the full-resolution Deep Zoom level.');
$should_use_remote = new ReflectionMethod($imagegrid_service, 'should_use_remote');
$should_use_remote->setAccessible(true);
mds3_assert_same(false, $should_use_remote->invoke($imagegrid_service, ['grid_megapixels' => 1000.0]), 'Expected core ImageGrid service to use local rendering unless an extension enables remote rendering.');
$imagegrid_manifest = $imagegrid_service->manifest([
    'id' => 9,
    'width' => 1000,
    'height' => 1000,
    'block_width' => 10,
    'block_height' => 10,
], []);
mds3_assert_same('grid_render', $imagegrid_manifest['operation'] ?? '', 'Expected ImageGrid manifests to use the current grid_render operation.');
$core_remote_payload = new ReflectionMethod($imagegrid_service, 'core_remote_payload');
$core_remote_payload->setAccessible(true);
$imagegrid_payload = $core_remote_payload->invoke($imagegrid_service, $imagegrid_manifest, ['estimate' => []]);
mds3_assert_same('grid_render', $imagegrid_payload['operation'] ?? '', 'Expected the optional core ImageGrid fallback payload to use the current grid_render operation.');
mds3_assert_same(1000, $imagegrid_payload['options']['width'] ?? 0, 'Expected the optional core ImageGrid fallback payload to include grid width.');
$submit_remote = new ReflectionMethod($imagegrid_service, 'submit_remote');
$submit_remote->setAccessible(true);
$submit_fallback = $submit_remote->invoke($imagegrid_service, 1, ['grid' => ['id' => 1]], ['estimate' => []]);
mds3_assert_same(true, is_wp_error($submit_fallback), 'Expected core ImageGrid remote fallback to be disabled by default.');
mds3_assert_same('mds3_imagegrid_extension_unavailable', $submit_fallback->get_error_code(), 'Expected direct ImageGrid submission to require the extension handler.');

$dims = \MillionDollarScript\V3\Migration\Importer::banner_pixel_dimensions([
    'grid_width' => 100,
    'grid_height' => 50,
    'block_width' => 10,
    'block_height' => 20,
]);
mds3_assert_same(1000, $dims['width'], 'Expected MDS2 block-count grid width to become pixels.');
mds3_assert_same(1000, $dims['height'], 'Expected MDS2 block-count grid height to become pixels.');
mds3_assert_same('paid', \MillionDollarScript\V3\Migration\Importer::order_status('completed'), 'Expected completed legacy orders to become paid.');
mds3_assert_same('unavailable', \MillionDollarScript\V3\Migration\Importer::block_status('nfs'), 'Expected NFS legacy blocks to become unavailable.');
mds3_assert_same('classic', \MillionDollarScript\V3\Grid\GridRepository::normalize_renderer_mode('canvas'), 'Expected legacy canvas renderer alias to become classic.');
mds3_assert_same('openlayers', \MillionDollarScript\V3\Grid\GridRepository::normalize_renderer_mode('OpenLayers'), 'Expected OpenLayers renderer mode to normalize.');
mds3_assert_same('auto', \MillionDollarScript\V3\Grid\GridRepository::normalize_renderer_mode('unexpected'), 'Expected unknown renderer mode to become auto.');
mds3_assert_same('YES', \MillionDollarScript\V3\Settings\SettingsSchema::sanitize('block-selection-mode', 'advanced'), 'Expected legacy advanced selection value to map to YES.');
mds3_assert_same('NO', \MillionDollarScript\V3\Settings\SettingsSchema::sanitize('block-selection-mode', 'simple'), 'Expected legacy simple selection value to map to NO.');
mds3_assert_same('ADJACENT', \MillionDollarScript\V3\Settings\SettingsSchema::sanitize('selection-adjacency-mode', 'strict'), 'Expected legacy strict adjacency to become ADJACENT.');
mds3_assert_same('RECTANGLE', \MillionDollarScript\V3\Settings\SettingsSchema::sanitize('selection-adjacency-mode', 'blocks'), 'Expected blocks selection label to become RECTANGLE.');
mds3_assert_same('NONE', \MillionDollarScript\V3\Settings\SettingsSchema::sanitize('selection-adjacency-mode', 'unrestricted'), 'Expected unrestricted selection label to become NONE.');
mds3_assert_same('no', \MillionDollarScript\V3\Settings\SettingsSchema::sanitize('expire-orders', 'NO'), 'Expected uppercase legacy no values to normalize for yes/no settings.');
mds3_assert_same(-1, \MillionDollarScript\V3\Settings\SettingsSchema::sanitize('minutes-unconfirmed', '-1'), 'Expected MDS2 immediate cleanup timing value to be retained.');
mds3_assert_same('yes', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['accounts-optional'], 'Expected guest orders to be enabled by default.');
mds3_assert_same('light', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['theme_mode'], 'Expected new installs to default to light theme mode.');
mds3_assert_same('https://milliondollarscript.com', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['extension_server_url'], 'Expected new installs to use the production extension server URL.');
mds3_assert_same('https://milliondollarscript.com', \MillionDollarScript\Extensions\Support::extension_server_url(), 'Expected extensions to resolve the production service through the public core facade.');
mds3_assert_same('', \MillionDollarScript\V3\Support\ReleaseProfile::id(), 'Expected source builds to have no private release profile.');
mds3_assert_same('main', \MillionDollarScript\V3\Support\ReleaseProfile::update_channel('main'), 'Expected source builds to preserve the stored update channel.');
mds3_assert_same('#f8fafc', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['primary_color'], 'Expected secondary background color to use the neutral production surface.');
mds3_assert_same('no', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['auto-approve'], 'Expected manual payments to require admin completion by default.');
$original_test_options = $GLOBALS['mds3_test_options'];
$GLOBALS['mds3_test_options'] = [
    'mds3_upgraded_early_alpha_saved_defaults' => MILLION_DOLLAR_SCRIPT_VERSION,
    'mds3_settings' => [
        'currency' => 'CAD',
        'extension_server_url' => 'https://extensions.milliondollarscript.com/',
        'primary_color' => 'red',
        'auto-approve' => 'yes',
    ],
];
$known_alpha_defaults = new ReflectionMethod(\MillionDollarScript\V3\Setup\Installer::class, 'normalize_known_alpha_saved_defaults');
$known_alpha_defaults->setAccessible(true);
$known_alpha_defaults->invoke(null);
$normalized_settings = get_option('mds3_settings', []);
mds3_assert_same('https://milliondollarscript.com', $normalized_settings['extension_server_url'] ?? '', 'Expected known old Extension Server URL defaults to be corrected even when the broad alpha marker exists.');
mds3_assert_same('#f8fafc', $normalized_settings['primary_color'] ?? '', 'Expected known old secondary background defaults to be corrected even when the broad alpha marker exists.');
mds3_assert_same('yes', $normalized_settings['auto-approve'] ?? '', 'Expected the targeted known-default pass not to clobber order settings after the broad alpha migration marker exists.');
mds3_assert_same('CAD', $normalized_settings['currency'] ?? '', 'Expected unrelated settings to be preserved while normalizing exact known defaults.');
mds3_assert_same(MILLION_DOLLAR_SCRIPT_VERSION, get_option('mds3_checked_known_alpha_saved_defaults'), 'Expected the targeted known-default check marker to be recorded.');
$GLOBALS['mds3_test_options'] = [
    'mds3_settings' => [
        'extension_server_url' => 'http://localhost:3030',
        'primary_color' => '#123abc',
    ],
];
$known_alpha_defaults->invoke(null);
$custom_settings = get_option('mds3_settings', []);
mds3_assert_same('http://localhost:3030', $custom_settings['extension_server_url'] ?? '', 'Expected custom Extension Server URLs to be preserved.');
mds3_assert_same('#123abc', $custom_settings['primary_color'] ?? '', 'Expected custom secondary background colors to be preserved.');
$GLOBALS['mds3_test_options'] = $original_test_options;
mds3_assert_same('yes', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['email-user-renewal-reminder'], 'Expected renewal reminder emails to be enabled by default for MDS2 compatibility.');
mds3_assert_same('yes', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['email-user-order-confirmed'], 'Expected confirmed-order email settings to exist for MDS2 compatibility.');
mds3_assert_same('yes', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['email-user-order-completed'], 'Expected completed-order email settings to exist for MDS2 compatibility.');
mds3_assert_same('yes', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['email-user-order-denied'], 'Expected denied-order email settings to exist for MDS2 compatibility.');
mds3_assert_same('no', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['email-admin-publish-notify'], 'Expected placement-published admin emails to stay disabled by default like MDS2.');
mds3_assert_same(7, \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['renewal-reminder-days-1'], 'Expected first renewal reminder to match the MDS2 default.');
$legacy_email_settings = \MillionDollarScript\V3\Settings\SettingsSchema::map_legacy_options([
    'EMAIL_USER_EXPIRE_WARNING' => 'NO',
    'EMAIL_ADMIN_ORDER_EXPIRED' => 'yes',
    'EMAIL_USER_ORDER_CONFIRMED' => 'NO',
    'EMAIL_ADMIN_ORDER_COMPLETED' => 'YES',
    'EMAIL_USER_ORDER_DENIED' => 'NO',
    'EMAIL_ADMIN_PUBLISH_NOTIFY' => 'YES',
    '_milliondollarscript_email-user-order-renewal' => 'NO',
    '_milliondollarscript_email-admin-order-renewal' => 'YES',
    'RENEWAL_REMINDER_DAYS_1' => '5',
    'milliondollarscript_order-expired-subject' => 'Legacy expired subject',
    'milliondollarscript_order-renewal-content' => '<p>Legacy renewal</p>',
]);
mds3_assert_same('no', $legacy_email_settings['email-user-renewal-reminder'], 'Expected legacy expiry-warning email setting to map to MDS3 renewal reminders.');
mds3_assert_same('yes', $legacy_email_settings['email-admin-order-expired'], 'Expected legacy admin-expired email setting to map to MDS3.');
mds3_assert_same('no', $legacy_email_settings['email-user-order-confirmed'], 'Expected legacy confirmed email setting to map to MDS3.');
mds3_assert_same('yes', $legacy_email_settings['email-admin-order-completed'], 'Expected legacy completed email setting to map to MDS3.');
mds3_assert_same('no', $legacy_email_settings['email-user-order-denied'], 'Expected legacy denied email setting to map to MDS3.');
mds3_assert_same('yes', $legacy_email_settings['email-admin-publish-notify'], 'Expected legacy publish notification setting to map to MDS3.');
mds3_assert_same('no', $legacy_email_settings['email-user-order-completed-renewal'], 'Expected legacy renewal user toggle to map to MDS3 renewal paid emails.');
mds3_assert_same('yes', $legacy_email_settings['email-admin-order-completed-renewal'], 'Expected legacy renewal admin toggle to map to MDS3 renewal paid emails.');
mds3_assert_same(5, $legacy_email_settings['renewal-reminder-days-1'], 'Expected legacy renewal reminder day setting to map to MDS3.');
mds3_assert_same('Legacy expired subject', $legacy_email_settings['order-expired-subject'], 'Expected legacy order-expired subject option to map to MDS3.');
mds3_assert_same('<p>Legacy renewal</p>', $legacy_email_settings['order-completed-renewal-content'], 'Expected legacy renewal email content to map to MDS3 renewal paid content.');
$email_fields = \MillionDollarScript\V3\Settings\SettingsSchema::fields();
mds3_assert_same('editor', $email_fields['order-expired-content']['type'] ?? '', 'Expected order expiration emails to use a rich editor field.');
mds3_assert_same('editor', $email_fields['renewal-reminder-content']['type'] ?? '', 'Expected renewal reminder emails to use a rich editor field.');
$legacy_pending_email_settings = \MillionDollarScript\V3\Settings\SettingsSchema::map_legacy_options([
    'EMAIL_USER_ORDER_PENDED' => 'NO',
    'milliondollarscript_order-pending-content' => '<p>Legacy pending</p>',
]);
mds3_assert_same('no', $legacy_pending_email_settings['email-user-order-confirmed'], 'Expected legacy pended email toggle to map to MDS3 payment-requested emails when confirmed-specific data is absent.');
mds3_assert_same('<p>Legacy pending</p>', $legacy_pending_email_settings['order-confirmed-content'], 'Expected legacy pending email content to map to MDS3 payment-requested email content.');
mds3_assert_same('<p>Safe</p>', \MillionDollarScript\V3\Settings\SettingsSchema::sanitize('order-expired-content', '<p onclick="alert(1)">Safe</p>'), 'Expected email template settings to allow safe HTML only.');
mds3_assert_same(false, array_key_exists('imagegrid_api_url', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()), 'Expected ImageGrid API settings to be extension-owned.');
mds3_assert_same(false, array_key_exists('local_render_threshold_megapixels', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()), 'Expected ImageGrid rendering thresholds to be extension-owned.');
mds3_assert_same('#123abc', \MillionDollarScript\V3\Settings\SettingsSchema::sanitize('background_color', '#123abc'), 'Expected color settings to accept hex colors.');
mds3_assert_same('#ffffff', \MillionDollarScript\V3\Settings\SettingsSchema::sanitize('background_color', 'not-a-color'), 'Expected invalid color settings to fall back to defaults.');
mds3_assert_same(42, \MillionDollarScript\V3\Settings\SettingsSchema::sanitize('login-header-image', '42'), 'Expected login header image setting to store attachment IDs.');
$allowed_setting_classifications = [
    \MillionDollarScript\V3\Settings\SettingsSchema::CLASSIFICATION_ACTIVE,
    \MillionDollarScript\V3\Settings\SettingsSchema::CLASSIFICATION_COMPATIBILITY,
    \MillionDollarScript\V3\Settings\SettingsSchema::CLASSIFICATION_DEFERRED,
    \MillionDollarScript\V3\Settings\SettingsSchema::CLASSIFICATION_EXTENSION_OWNED,
];
foreach (array_keys(\MillionDollarScript\V3\Settings\SettingsSchema::fields()) as $settings_field_key) {
    mds3_assert_same(true, in_array(\MillionDollarScript\V3\Settings\SettingsSchema::field_classification($settings_field_key), $allowed_setting_classifications, true), 'Expected every setting to have a recognized runtime classification: ' . $settings_field_key);
}
mds3_assert_same(true, \MillionDollarScript\V3\Settings\SettingsSchema::is_admin_visible('checkout-url'), 'Expected active checkout settings to remain visible in the main Settings UI.');
mds3_assert_same(true, \MillionDollarScript\V3\Settings\SettingsSchema::is_admin_visible('delete_data_on_uninstall'), 'Expected active uninstall cleanup setting to remain visible in the main Settings UI.');
mds3_assert_same(false, \MillionDollarScript\V3\Settings\SettingsSchema::is_admin_visible('resize'), 'Expected deferred image resize setting to be hidden from the main Settings UI.');
mds3_assert_same(false, \MillionDollarScript\V3\Settings\SettingsSchema::is_admin_visible('image-optional'), 'Expected deferred optional-image setting to be hidden from the main Settings UI.');
mds3_assert_same(false, \MillionDollarScript\V3\Settings\SettingsSchema::is_admin_visible('order-locking'), 'Expected deferred order-locking setting to be hidden from the main Settings UI.');
mds3_assert_same(false, \MillionDollarScript\V3\Settings\SettingsSchema::is_admin_visible('endpoint'), 'Expected legacy endpoint slug setting to be hidden from the main Settings UI.');
mds3_assert_same(false, \MillionDollarScript\V3\Settings\SettingsSchema::is_admin_visible('register-page'), 'Expected compatibility-only register page setting to be hidden from the main Settings UI.');
mds3_assert_same(false, \MillionDollarScript\V3\Settings\SettingsSchema::is_admin_visible('forgot-password-page'), 'Expected compatibility-only forgot-password page setting to be hidden from the main Settings UI.');
mds3_assert_same(false, \MillionDollarScript\V3\Settings\SettingsSchema::is_admin_visible('display-pixel-background'), 'Expected compatibility-only display setting to be hidden from the main Settings UI.');
$hidden_admin_settings = \MillionDollarScript\V3\Settings\SettingsSchema::hidden_admin_fields();
mds3_assert_same(true, isset($hidden_admin_settings['resize']), 'Expected hidden admin fields to list deferred resize setting for Upgrade Compatibility.');
mds3_assert_same(true, isset($hidden_admin_settings['image-optional']), 'Expected hidden admin fields to list deferred optional-image setting for Upgrade Compatibility.');
mds3_assert_same(true, isset($hidden_admin_settings['order-locking']), 'Expected hidden admin fields to list deferred order-locking setting for Upgrade Compatibility.');
mds3_assert_same(true, isset($hidden_admin_settings['endpoint']), 'Expected hidden admin fields to list compatibility-only endpoint slug setting for Upgrade Compatibility.');
mds3_assert_same(true, isset($hidden_admin_settings['register-page']), 'Expected hidden admin fields to list compatibility-only register page setting for Upgrade Compatibility.');
mds3_assert_same(true, isset($hidden_admin_settings['forgot-password-page']), 'Expected hidden admin fields to list compatibility-only forgot-password page setting for Upgrade Compatibility.');
mds3_assert_same(true, isset($hidden_admin_settings['display-pixel-background']), 'Expected hidden admin fields to list compatibility-only display setting for Upgrade Compatibility.');
mds3_assert_same(false, isset($hidden_admin_settings['delete_data_on_uninstall']), 'Expected active uninstall cleanup setting not to be listed as hidden compatibility.');
$shortcode = new \MillionDollarScript\V3\Grid\GridShortcode();
$style_vars_method = new ReflectionMethod($shortcode, 'settings_style_vars');
$style_vars_method->setAccessible(true);
$style_vars = $style_vars_method->invoke($shortcode, [
    'background_color' => '#123456',
    'primary_color' => '#234567',
    'text_color' => '#345678',
    'button-color' => '#456789',
    'button_text_color' => '#56789a',
]);
mds3_assert_same('#123456', $style_vars['--mds3-grid-config-bg'] ?? '', 'Expected public grid shell to receive the configured light background color without overriding dark theme tokens.');
mds3_assert_same('#234567', $style_vars['--mds3-grid-config-panel'] ?? '', 'Expected public grid shell to receive the configured light panel color without overriding dark theme tokens.');
mds3_assert_same('#345678', $style_vars['--mds3-grid-config-text'] ?? '', 'Expected public grid shell to receive the configured light text color without overriding dark theme tokens.');
mds3_assert_same('#456789', $style_vars['--mds3-grid-config-accent'] ?? '', 'Expected public grid shell to receive the configured light primary button color without overriding dark theme tokens.');
mds3_assert_same('#56789a', $style_vars['--mds3-grid-config-button-text'] ?? '', 'Expected public grid shell to receive the configured light primary button text color without overriding dark theme tokens.');
mds3_assert_same('#456789', $style_vars['--mds3-grid-config-accent-strong'] ?? '', 'Expected public grid shell light hover accent to follow the configured primary button color.');
mds3_assert_same(false, isset($style_vars['--mds3-grid-bg']), 'Expected configured colors not to override resolved theme tokens inline.');
$original_api_policies = get_option(\MillionDollarScript\V3\Rest\ApiGovernance::POLICIES_OPTION, []);
$api_governance = new \MillionDollarScript\V3\Rest\ApiGovernance();
$api_governance->save_policies([
    'core-api-keys-manage' => 'public_read',
    'core-migration-dry-run' => 'api_key_read',
    'core-migration-execute' => 'api_key_read',
]);
$effective_api_policies = [];
foreach ($api_governance->effective_manifest() as $endpoint) {
    $effective_api_policies[(string) ($endpoint['id'] ?? '')] = $endpoint;
}
mds3_assert_same('wp_capability', $effective_api_policies['core-api-keys-manage']['security_level'] ?? '', 'Expected API key management policy to stay at its administrator minimum.');
mds3_assert_same('wp_capability', $effective_api_policies['core-migration-dry-run']['security_level'] ?? '', 'Expected migration dry-run API policy to stay at its administrator minimum.');
mds3_assert_same('wp_capability', $effective_api_policies['core-migration-execute']['security_level'] ?? '', 'Expected migration execute API policy to stay at its administrator minimum.');
update_option(\MillionDollarScript\V3\Rest\ApiGovernance::POLICIES_OPTION, $original_api_policies, false);
$field_description = \MillionDollarScript\V3\Admin\FieldHelp::description('Use <strong>1000</strong> pixels.', 'mds3-field-test-description', 'extra class!');
mds3_assert_same(true, false !== strpos($field_description, 'id="mds3-field-test-description"'), 'Expected field descriptions to include the requested ID for aria-describedby.');
mds3_assert_same(true, false !== strpos($field_description, 'class="description mds3-field-description extra class"'), 'Expected field descriptions to keep shared classes and sanitized extras.');
mds3_assert_same(true, false !== strpos($field_description, 'Use &lt;strong&gt;1000&lt;/strong&gt; pixels.'), 'Expected field descriptions to escape HTML.');
$field_info = \MillionDollarScript\V3\Admin\FieldHelp::info('Use this for details.');
mds3_assert_same(true, false !== strpos($field_info, 'class="mds3-help"'), 'Expected field info popovers to use the shared help class.');
mds3_assert_same(true, false !== strpos($field_info, 'aria-haspopup="true"'), 'Expected field info popovers to expose accessible popup semantics.');
$docs_link = \MillionDollarScript\V3\Admin\FieldHelp::docs_link('https://example.test/docs?x=1&y=2', 'Read docs');
mds3_assert_same(true, false !== strpos($docs_link, 'class="mds3-docs-link"'), 'Expected docs links to use the shared docs-link class.');
mds3_assert_same(true, false !== strpos($docs_link, 'rel="noopener noreferrer"'), 'Expected docs links to avoid opener access.');
mds3_assert_same('USD', \MillionDollarScript\V3\Commerce\Currency::code('USD'), 'Expected standalone currency helper to preserve configured currency.');
mds3_assert_same('$', \MillionDollarScript\V3\Commerce\Currency::symbol('&#36;'), 'Expected currency symbol helper to decode provider HTML entities.');
mds3_assert_same(false, \MillionDollarScript\V3\Commerce\Currency::provider_locks_currency([]), 'Expected provider currency lock to stay disabled when the setting is missing.');
mds3_assert_same('CAD', \MillionDollarScript\V3\Commerce\Currency::normalize_code('$CAD'), 'Expected currency normalization to strip non-letter characters.');
mds3_assert_same('USDT', \MillionDollarScript\V3\Commerce\Currency::normalize_code('usdt', 'USD', 8), 'Expected currency normalization to preserve longer extension-owned currency-like codes when requested.');
mds3_assert_same(12.35, \MillionDollarScript\V3\Commerce\Currency::amount('12.345'), 'Expected currency amount helper to round positive values.');
mds3_assert_same(0.0, \MillionDollarScript\V3\Commerce\Currency::amount('-9'), 'Expected currency amount helper to clamp negative values.');
update_option('mds3_settings', ['currency' => 'CAD', 'currency-symbol' => 'C$', 'payment_provider' => 'standalone'], false);
mds3_assert_same('CAD', \MillionDollarScript\V3\Commerce\Currency::current_code(), 'Expected current currency helper to use standalone MDS3 settings.');
mds3_assert_same('C$', \MillionDollarScript\V3\Commerce\Currency::current_symbol(), 'Expected current currency symbol helper to use standalone MDS3 settings.');
mds3_assert_same('EUR', \MillionDollarScript\V3\Commerce\Currency::effective_code('eur'), 'Expected effective currency helper to accept unlocked explicit values.');
mds3_assert_same('CAD 12.50', \MillionDollarScript\V3\Commerce\Currency::format(12.5, 'CAD'), 'Expected currency formatter to respect explicit stored currency codes.');
mds3_assert_same('C$12.50 CAD', \MillionDollarScript\V3\Commerce\Currency::format(12.5, '', ['currency' => 'CAD', 'currency-symbol' => 'C$', 'payment_provider' => 'standalone'], true), 'Expected currency formatter to use configured standalone symbol when requested.');
add_filter('million-dollar-script/is/uploaded/file', static function () {
    return true;
});
$upload_validator = new \MillionDollarScript\V3\Media\UploadValidator();
$upload_path = tempnam(sys_get_temp_dir(), 'mds3-upload-');
file_put_contents($upload_path, mds3_test_png(2, 2));
$upload_file = [
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($upload_path),
    'tmp_name' => $upload_path,
    'name' => 'fixture.png',
];
mds3_assert_same(true, $upload_validator->validate($upload_file, 'fixture.png', [
    'max-upload-width' => 2,
    'max-upload-height' => 2,
]), 'Expected upload validator to accept images within configured dimensions.');
$too_wide = $upload_validator->validate($upload_file, 'fixture.png', [
    'max-upload-width' => 1,
    'max-upload-height' => 0,
]);
mds3_assert_same(true, is_wp_error($too_wide), 'Expected upload validator to reject images wider than the configured maximum.');
mds3_assert_same('mds3_upload_width_too_large', $too_wide->get_error_code(), 'Expected a precise upload width error code.');
$too_tall = $upload_validator->validate($upload_file, 'fixture.png', [
    'max-upload-width' => 0,
    'max-upload-height' => 1,
]);
mds3_assert_same(true, is_wp_error($too_tall), 'Expected upload validator to reject images taller than the configured maximum.');
mds3_assert_same('mds3_upload_height_too_large', $too_tall->get_error_code(), 'Expected a precise upload height error code.');
unlink($upload_path);
add_filter('million-dollar-script/payment/providers', static function ($providers) {
    $providers['test-store'] = [
        'id' => 'test-store',
        'label' => 'Test Store',
        'ready' => true,
        'locks_currency' => true,
        'currency_code' => static function () {
            return 'CAD';
        },
        'currency_symbol' => static function () {
            return 'C$';
        },
    ];

    return $providers;
});
mds3_assert_same('CAD', \MillionDollarScript\V3\Commerce\Currency::code('USD', ['payment_provider' => 'test-store']), 'Expected provider currency mode to use the store currency.');
mds3_assert_same('C$', \MillionDollarScript\V3\Commerce\Currency::symbol('$', ['payment_provider' => 'test-store']), 'Expected provider currency mode to use the store currency symbol.');
mds3_assert_same('CAD', \MillionDollarScript\V3\Commerce\Currency::current_code(['currency' => 'USD', 'currency-symbol' => '$', 'payment_provider' => 'test-store']), 'Expected current currency helper to honor provider-locked currencies.');
mds3_assert_same('C$', \MillionDollarScript\V3\Commerce\Currency::current_symbol(['currency' => 'USD', 'currency-symbol' => '$', 'payment_provider' => 'test-store']), 'Expected current currency symbol helper to honor provider-locked currencies.');
mds3_assert_same('CAD', \MillionDollarScript\V3\Commerce\Currency::effective_code('EUR', ['currency' => 'USD', 'currency-symbol' => '$', 'payment_provider' => 'test-store']), 'Expected effective currency helper to use provider currency when locked.');
mds3_assert_same('USD 12.50', \MillionDollarScript\V3\Commerce\Currency::format(12.5, 'USD', ['currency' => 'CAD', 'currency-symbol' => 'C$', 'payment_provider' => 'test-store']), 'Expected currency formatter to preserve explicit historical order currencies.');
add_filter('million-dollar-script/payment/providers', static function ($providers) {
    $providers['callbackless-store'] = [
        'id' => 'callbackless-store',
        'label' => 'Callbackless Store',
        'ready' => true,
        'locks_currency' => true,
    ];

    return $providers;
});
mds3_assert_same('USD', \MillionDollarScript\V3\Commerce\Currency::code('USD', ['payment_provider' => 'callbackless-store']), 'Expected provider currency fallback to avoid recursive currency lookup when callbacks are absent.');
add_filter('million-dollar-script/payment/providers', static function ($providers) {
    $providers['unavailable-store'] = [
        'id' => 'unavailable-store',
        'label' => 'Unavailable Store',
        'ready' => false,
        'create_checkout' => static function () {
            return ['provider' => 'unavailable-store', 'checkout_url' => 'https://example.test/unavailable'];
        },
    ];

    return $providers;
});
$fallback_checkout = \MillionDollarScript\V3\Commerce\Payments::create_checkout([
    'source' => 'mds-grid',
    'source_id' => 10,
    'payment_provider' => 'unavailable-store',
], [
    'default_payload' => [
        'provider' => 'unavailable-store',
        'checkout_url' => '',
        'after_upload_url' => 'https://example.test/manage',
    ],
]);
mds3_assert_same('standalone', $fallback_checkout['provider'] ?? '', 'Expected unavailable payment providers to produce a standalone checkout payload.');
mds3_assert_same('https://example.test/manage', $fallback_checkout['after_upload_url'] ?? '', 'Expected unavailable payment provider fallback to keep the safe default return URL.');

$grid = new \MillionDollarScript\V3\Grid\Grid([
    'id' => 1,
    'width' => 20,
    'height' => 20,
    'block_width' => 10,
    'block_height' => 10,
]);
$shortcode = new \MillionDollarScript\V3\Grid\GridShortcode();
$css_size = new ReflectionMethod($shortcode, 'css_size');
$css_size->setAccessible(true);
$responsive_height = new ReflectionMethod($shortcode, 'is_responsive_height');
$responsive_height->setAccessible(true);
mds3_assert_same('20px', $css_size->invoke($shortcode, '{width}', '100%', true, $grid), 'Expected grid width placeholder to resolve to the selected grid width.');
mds3_assert_same('20px', $css_size->invoke($shortcode, '{height}', '1000px', false, $grid), 'Expected grid height placeholder to resolve to the selected grid height.');
mds3_assert_same('100%', $css_size->invoke($shortcode, 'calc(100vw - 2rem)', '100%', true, $grid), 'Expected unsupported CSS sizes to fall back safely.');
mds3_assert_same(true, $responsive_height->invoke($shortcode, '{height}'), 'Expected grid height placeholder to enable responsive public height.');
mds3_assert_same(true, $responsive_height->invoke($shortcode, 'responsive'), 'Expected responsive height keyword to enable responsive public height.');
mds3_assert_same(false, $responsive_height->invoke($shortcode, '600px'), 'Expected explicit fixed heights to bypass responsive public height.');
mds3_assert_same('[mds3_page type="order" grid_id="9" read_only="false"]', \MillionDollarScript\V3\Pages\PageRepository::shortcode('order', 9), 'Expected generated Order Pixels pages to render an interactive grid.');
mds3_assert_same('[mds3_page type="order" read_only="false"]', \MillionDollarScript\V3\Pages\PageRepository::shortcode('order', 0), 'Expected global Order Pixels pages to defer grid choice for multi-grid installs.');
$reservation = new \MillionDollarScript\V3\Orders\ReservationService();
$normalized_coords = new ReflectionMethod($reservation, 'normalized_coords');
$normalized_coords->setAccessible(true);
mds3_assert_same([
    ['row' => 0, 'col' => 0],
    ['row' => 0, 'col' => 1],
], $normalized_coords->invoke($reservation, $grid, [
    ['row' => 0, 'col' => 0],
    ['row' => '0', 'col' => '0'],
    ['row' => 0, 'col' => 1],
]), 'Expected duplicate reservation coordinates to be normalized before reservation.');

$GLOBALS['wpdb'] = (object) ['prefix' => 'wp_'];
$legacy = new \MillionDollarScript\V3\Migration\LegacySource('wp_mds_;DROP TABLE ');
mds3_assert_same('wp_mds_DROPTABLE', $legacy->source_prefix(), 'Expected legacy source prefixes to be restricted to identifier characters.');

$legacy_detector = new ReflectionMethod(\MillionDollarScript\V3\Setup\LegacyPlugin::class, 'is_legacy_plugin');
$legacy_detector->setAccessible(true);
mds3_assert_same(true, $legacy_detector->invoke(null, 'milliondollarscript-two/milliondollarscript-two.php', [
    'Name' => 'Million Dollar Script Two',
    'TextDomain' => 'milliondollarscript',
    'Version' => '2.6.57',
]), 'Expected the MDS2 core plugin package to be detected.');
mds3_assert_same(false, $legacy_detector->invoke(null, 'mds-woocommerce/mds-woocommerce.php', [
    'Name' => 'Million Dollar Script - WooCommerce Integration',
    'TextDomain' => 'milliondollarscript',
    'Version' => '1.0.0',
]), 'Expected MDS-owned extensions not to be treated as the MDS2 core plugin.');

$resolver = new \MillionDollarScript\V3\Extensions\ExtensionDependencyResolver();
$installed_extensions = [
    [
        'slug' => 'mds-sponsorboard',
        'name' => 'SponsorBoard',
        'plugin_file' => 'mds-sponsorboard/mds-sponsorboard.php',
        'active' => true,
        'provides' => ['inventory.sponsorboard'],
    ],
    [
        'slug' => 'mds-automation',
        'name' => 'Automation',
        'plugin_file' => 'mds-automation/mds-automation.php',
        'active' => true,
        'requires' => ['inventory.sponsorboard'],
    ],
];
mds3_assert_same([], $resolver->missing_requirements([
    'slug' => 'mds-universe',
    'requires' => ['inventory.grid'],
], $installed_extensions), 'Expected transitional core grid capability to satisfy inventory.grid requirements.');
update_option(\MillionDollarScript\V3\Extensions\ExtensionSetup::SELECTED_EXTENSIONS_OPTION, [], false);
$grid_dependency_error = $resolver->activation_error([
    'slug' => 'mds-imagegrid',
    'requires' => ['inventory.grid'],
], $installed_extensions);
mds3_assert_same(true, is_wp_error($grid_dependency_error), 'Expected grid-dependent extensions to be blocked when mds-grid is disabled.');
update_option(\MillionDollarScript\V3\Extensions\ExtensionSetup::SELECTED_EXTENSIONS_OPTION, ['mds-grid'], false);
mds3_assert_same(true, in_array('api.governance', $resolver->core_capabilities(), true), 'Expected MDS3 API governance to be advertised as a core extension capability.');
mds3_assert_same([], $resolver->missing_requirements([
    'slug' => 'mds-revenue-agent',
    'requires' => ['api.governance'],
], $installed_extensions), 'Expected core API governance to satisfy extension requirements.');
mds3_assert_same(['inventory.universe'], $resolver->missing_requirements([
    'slug' => 'mds-agent',
    'requires' => ['inventory.universe'],
], $installed_extensions), 'Expected missing extension capabilities to be reported.');
mds3_assert_same(['SponsorBoard'], $resolver->active_conflicts([
    'slug' => 'mds-universe',
    'provides' => ['inventory.universe'],
    'conflicts' => ['inventory.sponsorboard'],
], $installed_extensions), 'Expected active capability conflicts to be reported.');
$deactivation_error = $resolver->deactivation_error($installed_extensions[0], $installed_extensions);
mds3_assert_same(true, is_wp_error($deactivation_error), 'Expected deactivation to be blocked when an active extension requires the provided capability.');

$dependency_test_globals = [
    'capabilities' => $GLOBALS['mds3_test_capabilities'] ?? null,
    'plugins' => $GLOBALS['mds3_test_plugins'] ?? null,
    'active_plugins' => $GLOBALS['mds3_test_active_plugins'] ?? null,
    'transients' => $GLOBALS['mds3_test_transients'] ?? null,
    'activation_silent' => $GLOBALS['mds3_test_activation_silent'] ?? null,
];
$GLOBALS['mds3_test_capabilities'] = [
    'install_plugins' => true,
    'activate_plugins' => true,
];
$GLOBALS['mds3_test_plugins'] = [
    'woocommerce/woocommerce.php' => ['Name' => 'WooCommerce'],
];
$GLOBALS['mds3_test_active_plugins'] = [];
$GLOBALS['mds3_test_transients'] = [];
$dependency_status = (new \MillionDollarScript\V3\Setup\PluginDependencyInstaller())->install_and_activate('woocommerce');
mds3_assert_same(true, !is_wp_error($dependency_status) && !empty($dependency_status['active']), 'Expected setup dependency installation to activate WooCommerce.');
mds3_assert_same(false, $GLOBALS['mds3_test_activation_silent'], 'Expected dependency activation hooks to run before redirect cleanup.');
mds3_assert_same(false, isset($GLOBALS['mds3_test_transients']['_wc_activation_redirect']), 'Expected MDS-initiated WooCommerce activation to suppress the automatic WooCommerce wizard redirect.');
foreach ($dependency_test_globals as $name => $value) {
    $global_name = 'mds3_test_' . $name;
    if (null === $value) {
        unset($GLOBALS[$global_name]);
    } else {
        $GLOBALS[$global_name] = $value;
    }
}

$active_restore_globals = [
    'plugins' => $GLOBALS['mds3_test_plugins'] ?? null,
    'active_plugins' => $GLOBALS['mds3_test_active_plugins'] ?? null,
];
$GLOBALS['mds3_test_plugins'] = [];
$GLOBALS['mds3_test_active_plugins'] = [];
$restore_method = new ReflectionMethod(\MillionDollarScript\V3\Extensions\ExtensionPackageDelivery::class, 'restore_activation_after_update');
$restore_method->setAccessible(true);
$delivery = new \MillionDollarScript\V3\Extensions\ExtensionPackageDelivery();
$restore_result = $restore_method->invoke($delivery, 'mds-fields/mds-fields.php', [
    'slug' => 'mds-fields',
    'plugin_file' => 'mds-fields/mds-fields.php',
    'requires' => [],
], true);
mds3_assert_same(false, is_wp_error($restore_result), 'Expected a previously active extension to reactivate after its package update.');
mds3_assert_same(true, is_plugin_active('mds-fields/mds-fields.php'), 'Expected extension update reactivation to restore the active plugin state.');
$GLOBALS['mds3_test_active_plugins'] = [];
$restore_method->invoke($delivery, 'mds-fields/mds-fields.php', ['slug' => 'mds-fields'], false);
mds3_assert_same(false, is_plugin_active('mds-fields/mds-fields.php'), 'Expected an inactive extension to remain inactive after its package update.');
foreach ($active_restore_globals as $name => $value) {
    $global_name = 'mds3_test_' . $name;
    if (null === $value) {
        unset($GLOBALS[$global_name]);
    } else {
        $GLOBALS[$global_name] = $value;
    }
}

update_option(\MillionDollarScript\V3\Extensions\ExtensionSetup::SELECTED_EXTENSIONS_OPTION, ['mds-grid'], false);
$distribution = \MillionDollarScript\V3\Support\Distribution::id();
mds3_assert_same('direct', $distribution, 'Expected source runtime tests to exercise the direct distribution.');
mds3_assert_same(true, \MillionDollarScript\V3\Support\Distribution::allows_custom_core_updates(), 'Expected the direct distribution to retain first-party core updates.');
mds3_assert_same(true, \MillionDollarScript\V3\Support\Distribution::allows_external_plugin_delivery(), 'Expected the direct distribution to retain extension package delivery.');
mds3_assert_same(true, \MillionDollarScript\V3\Support\Distribution::allows_remote_catalog(), 'Expected the direct distribution to retain extension catalog discovery.');
$catalog = (new \MillionDollarScript\V3\Extensions\ExtensionCatalog())->catalog();
$catalog_installed = [];
foreach ($catalog['installed'] as $item) {
    $catalog_installed[(string) ($item['slug'] ?? '')] = $item;
}
mds3_assert_same(true, isset($catalog_installed['mds-grid']), 'Expected the bundled Classic Pixel Grid to appear in installed extension discovery.');
mds3_assert_same('core', $catalog_installed['mds-grid']['source'] ?? '', 'Expected the bundled Classic Pixel Grid to be a core catalog item.');
mds3_assert_same('core', $catalog_installed['mds-grid']['setup_source'] ?? '', 'Expected the bundled Classic Pixel Grid setup source to stay core.');
mds3_assert_same(true, !empty($catalog_installed['mds-grid']['bundled']), 'Expected the Classic Pixel Grid catalog item to be marked as bundled.');
mds3_assert_same(true, !empty($catalog_installed['mds-grid']['active']), 'Expected the bundled Classic Pixel Grid to reflect the selected setup state.');
mds3_assert_same(true, in_array('inventory.grid', $catalog_installed['mds-grid']['provides'] ?? [], true), 'Expected bundled Classic Pixel Grid discovery to advertise grid inventory.');
$catalog_filter_backup = $GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/installed'] ?? [];
add_filter('million-dollar-script/extension/catalog/installed', static function ($items) {
    $items[] = [
        'slug' => 'mds-creator-platforms',
        'name' => 'Creator Platforms',
        'plugin_file' => 'mds-creator-platforms/mds-creator-platforms.php',
        'installed' => true,
    ];
    foreach (['mds-license-test', 'mds-premium-test', 'mds-hello-world', 'mds-sample-greeter', 'mds-skeleton', 'mds-test-extension'] as $developer_slug) {
        $items[] = [
            'slug' => $developer_slug,
            'name' => $developer_slug,
            'plugin_file' => $developer_slug . '/' . $developer_slug . '.php',
            'installed' => true,
        ];
    }
    $items[] = [
        'slug' => 'mds-legacy-contact-form',
        'name' => 'Legacy Contact Form',
        'plugin_file' => 'mds-legacy-contact-form/mds-legacy-contact-form.php',
        'installed' => true,
    ];
    $items[] = [
        'slug' => 'mds-launch-wall',
        'name' => 'Launch Wall',
        'plugin_file' => 'mds-launch-wall/mds-launch-wall.php',
        'installed' => true,
        'requires_mds' => '3.0.0',
    ];

    return $items;
});
$filtered_catalog = (new \MillionDollarScript\V3\Extensions\ExtensionCatalog())->catalog();
$filtered_installed_slugs = [];
foreach ($filtered_catalog['installed'] as $item) {
    $filtered_installed_slugs[] = (string) ($item['slug'] ?? '');
}
mds3_assert_same(false, in_array('mds-creator-platforms', $filtered_installed_slugs, true), 'Expected archived Creator Platforms to stay hidden from MDS3 extension discovery.');
foreach (['mds-license-test', 'mds-premium-test', 'mds-hello-world', 'mds-sample-greeter', 'mds-skeleton', 'mds-test-extension'] as $developer_slug) {
    mds3_assert_same(false, in_array($developer_slug, $filtered_installed_slugs, true), 'Expected developer/test extension ' . $developer_slug . ' to stay hidden from production MDS3 extension discovery.');
}
mds3_assert_same(false, in_array('mds-legacy-contact-form', $filtered_installed_slugs, true), 'Expected legacy extensions without MDS 3.0 compatibility metadata to stay hidden from MDS 3.0 extension discovery.');
mds3_assert_same(true, in_array('mds-launch-wall', $filtered_installed_slugs, true), 'Expected MDS 3.0-compatible extensions to remain discoverable.');
mds3_assert_same(null, (new \MillionDollarScript\V3\Extensions\ExtensionCatalog())->installed_item_by_slug('mds-creator-platforms'), 'Expected hidden extensions not to be returned for MDS3 admin activation.');
$remote_supports_method = new ReflectionMethod(\MillionDollarScript\V3\Extensions\ExtensionCatalog::class, 'remote_row_supports_mds3');
$remote_supports_method->setAccessible(true);
$remote_catalog = new \MillionDollarScript\V3\Extensions\ExtensionCatalog();
mds3_assert_same(false, $remote_supports_method->invoke($remote_catalog, [
    'slug' => 'mds-legacy-addon',
    'metadata' => [
        'mds_generation' => '2',
    ],
]), 'Expected remote MDS2-only extension rows to be hidden from the MDS 3.0 catalog.');
mds3_assert_same(true, $remote_supports_method->invoke($remote_catalog, [
    'slug' => 'mds-launch-wall',
    'metadata' => [
        'requires_mds' => '3.0.0',
    ],
]), 'Expected remote MDS 3.0-compatible extension rows to remain visible.');
mds3_assert_same(false, $remote_supports_method->invoke($remote_catalog, [
    'slug' => 'mds-server-row-without-generation',
    'metadata' => [],
]), 'Expected markerless legacy extension-server rows to stay out of the MDS 3.0 catalog.');
if ($catalog_filter_backup) {
    $GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/installed'] = $catalog_filter_backup;
} else {
    unset($GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/installed']);
}
$catalog_filter_backup = $GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/installed'] ?? [];
$developer_filter_backup = $GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/show/developer/extensions'] ?? [];
add_filter('million-dollar-script/extension/catalog/show/developer/extensions', static function () {
    return true;
});
add_filter('million-dollar-script/extension/catalog/installed', static function ($items) {
    foreach (['mds-creator-platforms', 'mds-hello-world', 'mds-sample-greeter', 'mds-skeleton', 'mds-test-extension'] as $developer_slug) {
        $items[] = [
            'slug' => $developer_slug,
            'name' => $developer_slug,
            'plugin_file' => $developer_slug . '/' . $developer_slug . '.php',
            'installed' => true,
        ];
    }

    return $items;
});
$developer_catalog = (new \MillionDollarScript\V3\Extensions\ExtensionCatalog())->catalog();
$developer_installed_slugs = [];
foreach ($developer_catalog['installed'] as $item) {
    $developer_installed_slugs[] = (string) ($item['slug'] ?? '');
}
mds3_assert_same(false, in_array('mds-creator-platforms', $developer_installed_slugs, true), 'Expected Creator Platforms to stay hidden even when developer extensions are shown.');
foreach (['mds-hello-world', 'mds-sample-greeter', 'mds-skeleton', 'mds-test-extension'] as $developer_slug) {
    mds3_assert_same(true, in_array($developer_slug, $developer_installed_slugs, true), 'Expected explicit developer mode to reveal ' . $developer_slug . '.');
}
if ($catalog_filter_backup) {
    $GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/installed'] = $catalog_filter_backup;
} else {
    unset($GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/installed']);
}
if ($developer_filter_backup) {
    $GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/show/developer/extensions'] = $developer_filter_backup;
} else {
    unset($GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/show/developer/extensions']);
}
update_option(\MillionDollarScript\V3\Extensions\ExtensionSetup::SELECTED_EXTENSIONS_OPTION, [], false);
$gridless_catalog = (new \MillionDollarScript\V3\Extensions\ExtensionCatalog())->catalog();
$gridless_installed = [];
foreach ($gridless_catalog['installed'] as $item) {
    $gridless_installed[(string) ($item['slug'] ?? '')] = $item;
}
mds3_assert_same(false, !empty($gridless_installed['mds-grid']['active']), 'Expected bundled Classic Pixel Grid discovery to support disabled setup state.');
update_option(\MillionDollarScript\V3\Extensions\ExtensionSetup::SELECTED_EXTENSIONS_OPTION, ['mds-grid'], false);

$setup = new \MillionDollarScript\V3\Extensions\ExtensionSetup();
$setup_filter_backup = $GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/installed'] ?? [];
add_filter('million-dollar-script/extension/catalog/installed', static function ($items) {
    $items[] = [
        'slug' => 'mds-legacy-helper',
        'name' => 'Legacy Helper',
        'plugin_file' => 'mds-legacy-helper/mds-legacy-helper.php',
        'installed' => true,
    ];
    $items[] = [
        'slug' => 'mds-imagegrid',
        'name' => 'ImageGrid',
        'plugin_file' => 'mds-imagegrid/mds-imagegrid.php',
        'installed' => true,
        'provides' => ['rendering.imagegrid'],
        'requires' => ['inventory.grid'],
    ];

    return $items;
});
$setup_catalog_slugs = [];
foreach ($setup->choices() as $item) {
    $setup_catalog_slugs[] = (string) ($item['slug'] ?? '');
}
mds3_assert_same(false, in_array('mds-legacy-helper', $setup_catalog_slugs, true), 'Expected setup choices to skip installed legacy extensions without MDS3 capability metadata.');
mds3_assert_same(true, in_array('mds-imagegrid', $setup_catalog_slugs, true), 'Expected setup choices to keep installed extensions with MDS3 capability metadata.');
if ($setup_filter_backup) {
    $GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/installed'] = $setup_filter_backup;
} else {
    unset($GLOBALS['mds3_test_filters']['million-dollar-script/extension/catalog/installed']);
}
$setup_choices = [
    [
        'slug' => 'mds-grid',
        'name' => 'Classic Pixel Grid',
        'provides' => ['inventory.grid'],
        'installed' => true,
        'active' => true,
        'locked' => true,
        'setup_source' => 'core',
    ],
    [
        'slug' => 'mds-imagegrid',
        'name' => 'ImageGrid',
        'provides' => ['rendering.imagegrid'],
        'requires' => ['inventory.grid'],
        'installed' => true,
        'active' => false,
        'setup_source' => 'installed',
    ],
    [
        'slug' => 'mds-revenue-agent',
        'name' => 'Revenue Agent',
        'requires' => ['rendering.imagegrid'],
        'installed' => false,
        'active' => false,
        'setup_source' => 'available',
    ],
];
$setup_plan = $setup->selection_plan(['mds-revenue-agent'], $setup_choices);
mds3_assert_same(true, in_array('mds-grid', $setup_plan['selected'], true), 'Expected setup plan to keep the bundled grid selected.');
mds3_assert_same(true, in_array('mds-imagegrid', $setup_plan['selected'], true), 'Expected setup plan to auto-select required providers.');
mds3_assert_same(true, isset($setup_plan['locked']['mds-imagegrid']), 'Expected auto-selected dependencies to be locked.');
mds3_assert_same(1, count($setup_plan['skipped']), 'Expected unavailable selected extensions to be reported as install follow-ups.');

update_option(\MillionDollarScript\V3\Extensions\ExtensionSetup::SELECTED_EXTENSIONS_OPTION, ['mds-grid', 'mds-imagegrid'], false);
$activation_settings_backup = get_option('mds3_settings', []);
update_option('mds3_settings', ['payment_provider' => 'standalone'], false);
$selected_after_reconciliation = $setup->ensure_selected('mds-woocommerce');
mds3_assert_same(true, in_array('mds-woocommerce', $selected_after_reconciliation, true), 'Expected an already-active WooCommerce adapter to reconcile into the selected site capabilities.');
mds3_assert_same('standalone', get_option('mds3_settings', [])['payment_provider'] ?? '', 'Expected capability reconciliation to preserve an intentional standalone payment-routing choice.');
update_option(\MillionDollarScript\V3\Extensions\ExtensionSetup::SELECTED_EXTENSIONS_OPTION, ['mds-grid', 'mds-imagegrid'], false);
$selected_after_activation = $setup->apply_activation_defaults('mds-woocommerce');
mds3_assert_same(true, in_array('mds-woocommerce', $selected_after_activation, true), 'Expected an activated setup extension to become a selected site capability.');
mds3_assert_same(true, in_array('mds-imagegrid', $selected_after_activation, true), 'Expected setup extension activation to preserve existing site capabilities.');
mds3_assert_same('woocommerce', get_option('mds3_settings', [])['payment_provider'] ?? '', 'Expected WooCommerce Checkout activation to enable WooCommerce payment routing by default.');
update_option('mds3_settings', $activation_settings_backup, false);
update_option(\MillionDollarScript\V3\Extensions\ExtensionSetup::SELECTED_EXTENSIONS_OPTION, ['mds-grid'], false);

$setup_conflict_plan = $setup->selection_plan(['mds-universe', 'mds-sponsorboard'], [
    [
        'slug' => 'mds-grid',
        'name' => 'Classic Pixel Grid',
        'provides' => ['inventory.grid'],
        'installed' => true,
        'active' => true,
        'locked' => true,
        'setup_source' => 'core',
    ],
    [
        'slug' => 'mds-universe',
        'name' => 'Universe',
        'provides' => ['inventory.universe'],
        'installed' => true,
        'active' => false,
        'setup_source' => 'installed',
    ],
    [
        'slug' => 'mds-sponsorboard',
        'name' => 'SponsorBoard',
        'provides' => ['inventory.sponsorboard'],
        'conflicts' => ['inventory.universe'],
        'installed' => true,
        'active' => false,
        'setup_source' => 'installed',
    ],
]);
mds3_assert_same(true, count($setup_conflict_plan['errors']) > 0, 'Expected setup plan to report selected capability conflicts.');

$optional_grid_plan = $setup->selection_plan([], [
    [
        'slug' => 'mds-grid',
        'name' => 'Classic Pixel Grid',
        'provides' => ['inventory.grid'],
        'installed' => true,
        'active' => false,
        'bundled' => true,
        'setup_source' => 'core',
    ],
]);
mds3_assert_same(false, in_array('mds-grid', $optional_grid_plan['selected'], true), 'Expected the bundled grid to be a default choice, not a hard-forced selection.');
$dependent_grid_plan = $setup->selection_plan(['mds-grid', 'mds-imagegrid'], [
    [
        'slug' => 'mds-grid',
        'name' => 'Classic Pixel Grid',
        'provides' => ['inventory.grid'],
        'installed' => true,
        'active' => false,
        'bundled' => true,
        'setup_source' => 'core',
    ],
    [
        'slug' => 'mds-imagegrid',
        'name' => 'ImageGrid',
        'requires' => ['inventory.grid'],
        'installed' => true,
        'active' => true,
        'setup_source' => 'installed',
    ],
]);
mds3_assert_same(true, isset($dependent_grid_plan['locked']['mds-grid']), 'Expected selected active dependencies to lock the bundled grid on the setup page.');

$api_governance = new \MillionDollarScript\V3\Rest\ApiGovernance();
$normalized_manifest = $api_governance->normalize_endpoint_manifest([
    [
        'id' => 'fixture-read',
        'route' => '/million-dollar-script/v1/fixture',
        'methods' => ['get', 'TRACE'],
        'scope' => 'fixture.read',
        'minimum_security_level' => 'api_key_read',
        'description' => 'Read fixture data.',
    ],
    [
        'extension' => 'legacy-package-shape',
        'endpoints' => [['method' => 'GET', 'path' => '/fixture']],
    ],
    [
        'id' => 'fixture-read',
        'route' => '/million-dollar-script/v1/duplicate',
        'methods' => ['GET'],
        'scope' => 'fixture.read',
        'minimum_security_level' => 'public_read',
        'description' => 'Duplicate fixture endpoint.',
    ],
    [
        'id' => 'invalid-security',
        'route' => '/million-dollar-script/v1/invalid-security',
        'methods' => ['GET'],
        'scope' => 'fixture.read',
        'minimum_security_level' => 'manage_options',
        'description' => 'Invalid security vocabulary.',
    ],
]);
mds3_assert_same(1, count($normalized_manifest), 'Expected API manifest normalization to reject nested, duplicate, and invalid-security entries.');
mds3_assert_same(['GET'], $normalized_manifest[0]['methods'] ?? [], 'Expected API manifest normalization to retain only supported HTTP methods.');
mds3_assert_same('/million-dollar-script/v1/fixture', $normalized_manifest[0]['route'] ?? '', 'Expected API manifest normalization to preserve canonical Million Dollar Script routes.');
$openapi = $api_governance->openapi();
mds3_assert_same('3.1.0', $openapi['openapi'] ?? '', 'Expected API governance to expose an OpenAPI document.');
mds3_assert_same('X-Million-Dollar-Script-API-Key', $openapi['components']['securitySchemes']['ApiKeyAuth']['name'] ?? '', 'Expected OpenAPI to advertise the product-named API key header.');
mds3_assert_same(false, isset($openapi['components']['securitySchemes']['ApiKeyAuthLegacy']), 'Expected OpenAPI to avoid advertising MDS shorthand API key headers.');
mds3_assert_same(true, isset($openapi['paths']['/grids/{id}']['get']), 'Expected grid read endpoint in OpenAPI paths.');
mds3_assert_same('core.grid.read', $openapi['paths']['/grids/{id}']['get']['x-mds-scope'] ?? '', 'Expected OpenAPI operations to expose Million Dollar Script API scopes.');
mds3_assert_same([], $openapi['paths']['/grids/{id}']['get']['security'] ?? null, 'Expected public grid reads to remain unauthenticated in OpenAPI.');
mds3_assert_same('#/components/schemas/Grid', $openapi['paths']['/grids/{id}']['get']['responses']['200']['content']['application/json']['schema']['$ref'] ?? '', 'Expected grid reads to reference the static Grid schema.');
mds3_assert_same('#/components/schemas/ReservationRequest', $openapi['paths']['/grids/{id}/reservations']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? '', 'Expected reservation writes to publish their request contract.');
mds3_assert_same('#/components/schemas/BlockCoordinate', $openapi['components']['schemas']['ReservationRequest']['properties']['blocks']['items']['$ref'] ?? '', 'Expected reservation blocks to use row and column coordinates.');
mds3_assert_same(['row', 'col'], $openapi['components']['schemas']['BlockCoordinate']['required'] ?? [], 'Expected reservation coordinates to require row and col.');
mds3_assert_same('#/components/schemas/OrderStatusUpdate', $openapi['paths']['/orders/{id}']['patch']['requestBody']['content']['application/json']['schema']['$ref'] ?? '', 'Expected order updates to publish the supported status contract.');
mds3_assert_same('#/components/schemas/Error', $openapi['paths']['/orders/{id}']['get']['responses']['404']['content']['application/json']['schema']['$ref'] ?? '', 'Expected REST errors to use the shared error schema.');
mds3_assert_same(true, isset($openapi['paths']['/extensions/openapi']['get']), 'Expected extension OpenAPI endpoint to be discoverable.');
mds3_assert_same('core.extension.read', $openapi['paths']['/extensions/openapi']['get']['x-mds-scope'] ?? '', 'Expected OpenAPI discovery to require the extension read scope.');
mds3_assert_same([
    ['ApiKeyAuth' => []],
    ['BearerAuth' => []],
], $openapi['paths']['/extensions/openapi']['get']['security'] ?? null, 'Expected API-key endpoints to accept product-named and bearer credentials.');
mds3_assert_same(true, isset($openapi['paths']['/extensions/discovery']['get']), 'Expected extension-scoped API discovery to be discoverable.');
mds3_assert_same(true, isset($openapi['paths']['/api/keys/{id}/rotate']['post']), 'Expected API key rotation endpoint in OpenAPI paths.');
mds3_assert_same(false, isset($openapi['paths']['/api/keys/{id}/rotate']['post']['requestBody']), 'Expected API key rotation to omit a request body it does not consume.');
mds3_assert_same(false, isset($openapi['paths']['/grids/{id}/render']['post']['requestBody']), 'Expected render submission to omit a request body it does not consume.');
mds3_assert_same(true, isset($openapi['paths']['/migration/dry-run']['post']), 'Expected the migration dry-run endpoint in OpenAPI paths.');
mds3_assert_same(true, isset($openapi['paths']['/migration/execute']['post']), 'Expected the migration execute endpoint in OpenAPI paths.');
mds3_assert_same(false, isset($openapi['paths']['/migration/{path}']), 'Expected OpenAPI to omit wildcard policy fallbacks.');
mds3_assert_same(false, isset($openapi['paths']['/api/{path}']), 'Expected OpenAPI to omit generic administrator API wildcard fallbacks.');
mds3_assert_same(false, isset($openapi['paths']['/extensions/{path}']), 'Expected OpenAPI to omit generic extension wildcard fallbacks.');
mds3_assert_same(false, isset($openapi['paths']['/imagegrid/account']), 'Expected ImageGrid account endpoints to be extension-owned and hidden when the ImageGrid extension is inactive.');
$core_openapi = $api_governance->openapi(false);
mds3_assert_same('Static contract for the Million Dollar Script core API.', $core_openapi['info']['description'] ?? '', 'Expected core-only OpenAPI generation to identify the static core contract.');
mds3_assert_same([], $core_openapi['x-mds']['extension_manifests'] ?? null, 'Expected core-only OpenAPI generation to omit environment-specific extension manifests.');
$discovery = $api_governance->discovery();
mds3_assert_same('https://example.test/wp-json/million-dollar-script/v1/extensions/openapi', $discovery['openapi_url'] ?? '', 'Expected API discovery to link to the OpenAPI document.');
update_option(\MillionDollarScript\V3\Rest\ApiGovernance::POLICIES_OPTION, ['core-grids-read' => 'public_write_nonce'], false);
$nonce_test_request = new class('GET', '/million-dollar-script/v1/grids', []) {
    private string $method;
    private string $route;
    private array $headers;

    public function __construct($method, $route, array $headers) {
        $this->method = $method;
        $this->route = $route;
        $this->headers = $headers;
    }

    public function get_method() {
        return $this->method;
    }

    public function get_route() {
        return $this->route;
    }

    public function get_header($name) {
        return $this->headers[strtolower((string) $name)] ?? '';
    }
};
$nonce_denied = $api_governance->authorize($nonce_test_request, 'core.grid.read', 'public_read');
mds3_assert_same(true, is_wp_error($nonce_denied), 'Expected nonce-tightened public endpoints to reject missing REST nonces.');
$nonce_allowed_request = new class('GET', '/million-dollar-script/v1/grids', ['x_wp_nonce' => 'valid-rest-nonce']) {
    private string $method;
    private string $route;
    private array $headers;

    public function __construct($method, $route, array $headers) {
        $this->method = $method;
        $this->route = $route;
        $this->headers = $headers;
    }

    public function get_method() {
        return $this->method;
    }

    public function get_route() {
        return $this->route;
    }

    public function get_header($name) {
        return $this->headers[strtolower((string) $name)] ?? '';
    }
};
mds3_assert_same(true, $api_governance->authorize($nonce_allowed_request, 'core.grid.read', 'public_read'), 'Expected nonce-tightened public endpoints to accept valid REST nonces.');
update_option(\MillionDollarScript\V3\Rest\ApiGovernance::POLICIES_OPTION, [], false);
$key_from_request = new ReflectionMethod($api_governance, 'key_from_request');
$key_from_request->setAccessible(true);
$request_with_product_header = new class(['x_million_dollar_script_api_key' => 'preferred-key']) {
    private array $headers;

    public function __construct(array $headers) {
        $this->headers = $headers;
    }

    public function get_header($name) {
        return $this->headers[strtolower((string) $name)] ?? '';
    }
};
mds3_assert_same('preferred-key', $key_from_request->invoke($api_governance, $request_with_product_header), 'Expected the product-named API key header to be accepted.');
$request_with_shorthand_header = new class(['x_mds3_api_key' => 'legacy-key']) {
    private array $headers;

    public function __construct(array $headers) {
        $this->headers = $headers;
    }

    public function get_header($name) {
        return $this->headers[strtolower((string) $name)] ?? '';
    }
};
mds3_assert_same('', $key_from_request->invoke($api_governance, $request_with_shorthand_header), 'Expected the shorthand API key header to be rejected.');
$request_with_bearer = new class(['authorization' => 'Bearer bearer-key']) {
    private array $headers;

    public function __construct(array $headers) {
        $this->headers = $headers;
    }

    public function get_header($name) {
        return $this->headers[strtolower((string) $name)] ?? '';
    }
};
mds3_assert_same('bearer-key', $key_from_request->invoke($api_governance, $request_with_bearer), 'Expected bearer API keys to remain accepted.');
$api_key_repo = new \MillionDollarScript\V3\Rest\ApiKeyRepository();
$generate_api_key_method = new ReflectionMethod($api_key_repo, 'generate_key');
$generate_api_key_method->setAccessible(true);
mds3_assert_same(0, strpos($generate_api_key_method->invoke($api_key_repo), 'milliondollarscript_'), 'Expected generated API keys to use the product name prefix.');
$default_api_scopes = new ReflectionMethod($api_key_repo, 'default_scopes');
$default_api_scopes->setAccessible(true);
mds3_assert_same(['core.grid.read', 'core.extension.read'], $default_api_scopes->invoke($api_key_repo), 'Expected blank API key scopes to fall back to useful least-privilege read scopes.');

update_option(\MillionDollarScript\V3\Extensions\ExtensionSetup::SELECTED_EXTENSIONS_OPTION, [], false);
mds3_assert_same(false, (new \MillionDollarScript\V3\Extensions\ExtensionRuntime())->is_enabled('mds-grid'), 'Expected runtime to allow Classic Pixel Grid to be disabled.');
$gridless_openapi = (new \MillionDollarScript\V3\Rest\ApiGovernance())->openapi();
mds3_assert_same(false, isset($gridless_openapi['paths']['/grids']), 'Expected grid REST paths to disappear when Classic Pixel Grid is disabled.');
mds3_assert_same(false, in_array('inventory.grid', (new \MillionDollarScript\V3\Extensions\ExtensionDependencyResolver())->core_capabilities(), true), 'Expected inventory.grid to belong to the bundled grid runtime, not permanent core.');
mds3_assert_same(['core.extension.read'], $default_api_scopes->invoke($api_key_repo), 'Expected gridless API keys to default only to extension discovery scope.');
$admin = new \MillionDollarScript\V3\Admin\Admin();
$extension_visual_defaults_method = new ReflectionMethod($admin, 'extension_visual_defaults');
$extension_visual_defaults_method->setAccessible(true);
$subscriptions_visual = $extension_visual_defaults_method->invoke($admin, 'mds-subscriptions');
mds3_assert_same('dashicons-update', $subscriptions_visual['icon'] ?? '', 'Expected Subscriptions to use its recurring-payment icon instead of the generic extension fallback.');
$settings_groups_method = new ReflectionMethod($admin, 'settings_groups');
$settings_groups_method->setAccessible(true);
$gridless_groups = $settings_groups_method->invoke($admin);
mds3_assert_same(false, array_key_exists('Orders & Uploads', $gridless_groups), 'Expected grid-only order settings to be hidden when Classic Pixel Grid is disabled.');
mds3_assert_same(false, array_key_exists('Rendering', $gridless_groups), 'Expected grid-only rendering settings to be hidden when Classic Pixel Grid is disabled.');
mds3_assert_same(true, array_key_exists('General', $gridless_groups), 'Expected shared settings to remain visible when Classic Pixel Grid is disabled.');
$settings_fields_method = new ReflectionMethod($admin, 'settings_fields_for_save');
$settings_fields_method->setAccessible(true);
$gridless_fields = $settings_fields_method->invoke($admin);
mds3_assert_same(false, array_key_exists('expire-orders', $gridless_fields), 'Expected hidden grid settings not to be reset on gridless settings saves.');
mds3_assert_same(true, array_key_exists('theme_mode', $gridless_fields), 'Expected shared settings to remain saveable in gridless mode.');
$api_route_label_method = new ReflectionMethod($admin, 'api_route_label');
$api_route_label_method->setAccessible(true);
mds3_assert_same('/million-dollar-script/v1/grids/{id}', $api_route_label_method->invoke($admin, '/million-dollar-script/v1/grids/(?P<id>\d+)'), 'Expected admin API route labels to hide WordPress regex syntax.');
mds3_assert_same('/million-dollar-script/v1/migration/{path}', $api_route_label_method->invoke($admin, '/million-dollar-script/v1/migration/*'), 'Expected wildcard API route labels to be readable.');
mds3_assert_same('/million-dollar-script/v1/extensions/{path}', $api_route_label_method->invoke($admin, '/million-dollar-script/v1/extensions*'), 'Expected suffix wildcard API route labels to remain readable.');
$memory_status = new \MillionDollarScript\V3\Support\MemoryStatus();
mds3_assert_same(268435456, \MillionDollarScript\V3\Support\MemoryStatus::bytes('256M'), 'Expected memory shorthand values to convert to bytes.');
mds3_assert_same(-1, \MillionDollarScript\V3\Support\MemoryStatus::bytes('-1'), 'Expected unlimited PHP memory to remain distinguishable.');
$site_health_tests = $memory_status->site_health_tests([]);
mds3_assert_same(true, isset($site_health_tests['direct']['million-dollar-script-php-memory']), 'Expected Million Dollar Script to register a direct Site Health memory test.');
$grid_capacity_status = new \MillionDollarScript\V3\Support\GridCapacityStatus();
$capacity_health_tests = $grid_capacity_status->site_health_tests([]);
mds3_assert_same(true, isset($capacity_health_tests['direct']['million-dollar-script-grid-capacity']), 'Expected Million Dollar Script to register a direct Site Health grid-capacity test.');
mds3_assert_same(1000000, \MillionDollarScript\V3\Support\GridCapacityStatus::virtual_blocks(10000, 10000, 10, 10), 'Expected empty cells in a 10,000-pixel grid to remain a virtual one-million-block geometry.');
mds3_assert_same('recommended', \MillionDollarScript\V3\Support\GridCapacityStatus::placement_range(4999), 'Expected grids below 5,000 active placements to remain in the recommended range.');
mds3_assert_same('conditional', \MillionDollarScript\V3\Support\GridCapacityStatus::placement_range(5000), 'Expected 5,000 active placements to enter the conditional range.');
mds3_assert_same('review', \MillionDollarScript\V3\Support\GridCapacityStatus::placement_range(10000), 'Expected 10,000 active placements to require capacity review.');
$original_memory_limit = ini_get('memory_limit');
ini_set('memory_limit', '128M');
$low_memory_status = \MillionDollarScript\V3\Support\MemoryStatus::status();
mds3_assert_same(false, $low_memory_status['meets_minimum'], 'Expected a 128 MB PHP memory limit to be below the supported minimum.');
$low_memory_health = $memory_status->site_health_result();
mds3_assert_same('critical', $low_memory_health['status'] ?? '', 'Expected Site Health to flag memory below the supported minimum.');
mds3_assert_same(true, false !== strpos((string) ($low_memory_health['actions'] ?? ''), 'million-dollar-script%3Atroubleshooting'), 'Expected low-memory Site Health guidance to link to bundled troubleshooting.');
ini_set('memory_limit', (string) $original_memory_limit);
(new \MillionDollarScript\V3\Extensions\ExtensionSetup())->ensure_selected('mds-grid');
mds3_assert_same(true, (new \MillionDollarScript\V3\Extensions\ExtensionRuntime())->is_enabled('mds-grid'), 'Expected migration/setup helpers to be able to re-enable the bundled grid.');
$grid_groups = $settings_groups_method->invoke($admin);
mds3_assert_same(true, array_key_exists('Orders & Uploads', $grid_groups), 'Expected grid settings to be visible when Classic Pixel Grid is enabled.');
$settings_active_tab_method = new ReflectionMethod($admin, 'settings_active_tab');
$settings_active_tab_method->setAccessible(true);
$_GET['tab'] = 'rendering';
mds3_assert_same('settings-rendering', $settings_active_tab_method->invoke($admin, $grid_groups, ['upgrade' => 'Upgrade Compatibility']), 'Expected direct Rendering tab links to select the rendering panel.');
$_GET['tab'] = 'upgrade';
mds3_assert_same('upgrade', $settings_active_tab_method->invoke($admin, $grid_groups, ['upgrade' => 'Upgrade Compatibility']), 'Expected direct Upgrade tab links to select the upgrade panel.');
unset($_GET['tab']);
update_option(\MillionDollarScript\V3\Extensions\ExtensionSetup::SELECTED_EXTENSIONS_OPTION, ['mds-grid'], false);

$ajax = new \MillionDollarScript\V3\Grid\GridAjax();
$interaction_payload = new ReflectionMethod($ajax, 'interaction_payload');
$interaction_payload->setAccessible(true);
$interaction = $interaction_payload->invoke($ajax, [
    'link-target' => '_self',
    'enable-cloaking' => 'NO',
    'enable-mouseover' => 'NO',
    'tooltip-trigger' => 'click',
    'max-popup-size' => 420,
    'max-image-size' => 512,
]);
mds3_assert_same('no', $interaction['enable_mouseover'], 'Expected grid interaction payload to expose the popup enable setting.');
mds3_assert_same('click', $interaction['tooltip_trigger'], 'Expected grid interaction payload to expose the tooltip trigger setting.');
mds3_assert_same(420, $interaction['max_popup_size'], 'Expected grid interaction payload to expose the popup max width setting.');
mds3_assert_same(512, $interaction['max_image_size'], 'Expected grid interaction payload to expose the popup max image size.');
$upload_payload = new ReflectionMethod($ajax, 'upload_payload');
$upload_payload->setAccessible(true);
$upload = $upload_payload->invoke($ajax, [
    'url-optional' => 'no',
    'text-optional' => 'no',
    'max-upload-width' => 640,
    'max-upload-height' => 480,
]);
mds3_assert_same(true, $upload['url_required'], 'Expected upload payload to expose URL requirement state.');
mds3_assert_same(true, $upload['text_required'], 'Expected upload payload to expose popup text requirement state.');
mds3_assert_same(true, $upload['url_visible'], 'Expected required advertiser URL fields to remain visible.');
mds3_assert_same(true, $upload['text_visible'], 'Expected required popup text fields to remain visible.');
mds3_assert_same(640, $upload['max_width'], 'Expected upload payload to expose configured max upload width.');
mds3_assert_same(480, $upload['max_height'], 'Expected upload payload to expose configured max upload height.');
$hidden_upload = $upload_payload->invoke($ajax, [
    'url-optional' => 'hidden',
    'text-optional' => 'hidden',
]);
mds3_assert_same(false, $hidden_upload['url_required'], 'Expected hidden advertiser URL fields not to remain required.');
mds3_assert_same(false, $hidden_upload['text_required'], 'Expected hidden popup text fields not to remain required.');
mds3_assert_same(false, $hidden_upload['url_visible'], 'Expected hidden advertiser URL fields to be absent from customer forms.');
mds3_assert_same(false, $hidden_upload['text_visible'], 'Expected hidden popup text fields to be absent from customer forms.');

$required_fields = \MillionDollarScript\V3\Media\PlacementFieldContract::validate([], [
    'url-optional' => 'no',
    'text-optional' => 'no',
]);
mds3_assert_same(true, is_wp_error($required_fields), 'Expected the shared placement contract to reject missing required fields.');
mds3_assert_same('million_dollar_script_url_required', $required_fields->get_error_code(), 'Expected URL validation to run before popup text validation.');
$optional_fields = \MillionDollarScript\V3\Media\PlacementFieldContract::validate([
    'fit_mode' => 'contain',
    'link_url' => 'example.com/ad',
    'alt_text' => '<b>Fixture</b>',
    'popup_text' => '<p>Safe <strong>copy</strong><script>bad()</script></p>',
], [
    'url-optional' => 'yes',
    'text-optional' => 'yes',
    'popup-rich-text' => 'yes',
]);
mds3_assert_same(false, is_wp_error($optional_fields), 'Expected valid optional placement fields to pass the shared contract.');
mds3_assert_same('https://example.com/ad', $optional_fields['link_url'], 'Expected the shared placement contract to normalize advertiser URLs.');
mds3_assert_same('Fixture', $optional_fields['alt_text'], 'Expected the shared placement contract to sanitize alternative text.');
mds3_assert_same(false, false !== strpos($optional_fields['popup_text'], '<script'), 'Expected the shared placement contract to remove unsafe popup markup.');
$preserved_fields = \MillionDollarScript\V3\Media\PlacementFieldContract::validate([
    'link_url' => 'https://replacement.example/',
    'popup_text' => 'Replacement popup',
], [
    'url-optional' => 'hidden',
    'text-optional' => 'hidden',
], [
    'fit_mode' => 'cover',
    'link_url' => 'https://saved.example/',
    'alt_text' => 'Saved title',
    'popup_text' => '<strong>Saved popup</strong>',
]);
mds3_assert_same('https://saved.example/', $preserved_fields['link_url'], 'Expected hidden URL fields to preserve existing placement data.');
mds3_assert_same('<strong>Saved popup</strong>', $preserved_fields['popup_text'], 'Expected hidden popup fields to preserve existing placement data.');
$placement_payload = new ReflectionMethod($ajax, 'placement_payload');
$placement_payload->setAccessible(true);
$popup_payload = $placement_payload->invoke($ajax, [
    'id' => 91,
    'grid_id' => 7,
    'order_id' => 0,
    'attachment_id' => 0,
    'x' => 10,
    'y' => 20,
    'width' => 30,
    'height' => 40,
    'fit_mode' => 'cover',
    'link_url' => 'example.com/ad',
    'alt_text' => 'Popup fixture',
    'popup_text' => '<p onclick="alert(1)">Visit <strong>us</strong> <a href="https://bad.example">today</a><br><em>now</em></p><script>alert(1)</script>',
    'status' => 'active',
], [
    'enable-cloaking' => 'YES',
    'popup-rich-text' => 'yes',
    'popup-template' => '<article>%image%<div>%text%</div><span>%url%</span><b>%alt_text%</b><script>alert(1)</script></article>',
]);
mds3_assert_same('Visit us todaynow', $popup_payload['popup_text'], 'Expected popup payload to expose plain text for scripts and REST clients.');
mds3_assert_same(true, false !== strpos($popup_payload['popup_text_html'], '<strong>us</strong>'), 'Expected rich popup text to retain safe formatting.');
mds3_assert_same(true, false !== strpos($popup_payload['popup_text_html'], '<em>now</em>'), 'Expected rich popup text to retain italic formatting.');
mds3_assert_same(false, false !== strpos($popup_payload['popup_text_html'], '<a '), 'Expected rich popup text to reject advertiser-provided links.');
mds3_assert_same(false, false !== strpos($popup_payload['popup_text_html'], 'onclick'), 'Expected rich popup text to remove attributes.');
mds3_assert_same(true, false !== strpos($popup_payload['popover_html'], '%image%'), 'Expected custom popover HTML to preserve the image placeholder for the browser.');
mds3_assert_same(true, false !== strpos($popup_payload['popover_html'], '<strong>us</strong>'), 'Expected custom popover HTML to include rich popup text.');
mds3_assert_same(true, false !== strpos($popup_payload['popover_html'], 'https://example.com/ad'), 'Expected custom popover HTML to include normalized advertiser URLs.');
mds3_assert_same(false, false !== strpos($popup_payload['popover_html'], '<script'), 'Expected custom popover HTML to remove scripts.');
mds3_assert_same('https://example.com/ad', \MillionDollarScript\V3\Media\PlacementFieldContract::advertiser_url('example.com/ad'), 'Expected advertiser URLs without a protocol to default to https.');
mds3_assert_same('http://example.com/ad', \MillionDollarScript\V3\Media\PlacementFieldContract::advertiser_url('http://example.com/ad'), 'Expected explicit http advertiser URLs to be preserved.');
mds3_assert_same('https://example.com/ad', \MillionDollarScript\V3\Media\PlacementFieldContract::advertiser_url('https://https://example.com/ad'), 'Expected duplicate https protocols to collapse.');
mds3_assert_same('https://example.com/ad', \MillionDollarScript\V3\Media\PlacementFieldContract::advertiser_url('//example.com/ad'), 'Expected protocol-relative advertiser URLs to default to https.');
mds3_assert_same('', \MillionDollarScript\V3\Media\PlacementFieldContract::advertiser_url('not a url'), 'Expected invalid advertiser URLs to be rejected.');
$empty_custom_popup = $placement_payload->invoke($ajax, [
    'id' => 92,
    'grid_id' => 7,
    'attachment_id' => 0,
    'alt_text' => '',
    'link_url' => '',
    'popup_text' => '',
    'status' => 'active',
], [
    'popup-template' => '<div>%text%</div>',
]);
mds3_assert_same('', $empty_custom_popup['popover_html'], 'Expected empty custom layouts to fall back to the built-in accessible popup.');

$advertiser_page_settings = [
    'mds-pixel-template' => 'yes',
    'mds-pixel-base' => 'Featured Advertisers',
    'mds-pixel-slug-structure' => '%username%-%display_name%-%title%-%placement_id%',
];
mds3_assert_same(true, \MillionDollarScript\V3\Media\AdvertiserPageUrls::enabled($advertiser_page_settings), 'Expected the active advertiser-page setting to enable public pages.');
mds3_assert_same('featured-advertisers', \MillionDollarScript\V3\Media\AdvertiserPageUrls::base($advertiser_page_settings), 'Expected advertiser page bases to be URL-safe.');
mds3_assert_same(
    'fixture-advertiser-91',
    \MillionDollarScript\V3\Media\AdvertiserPageUrls::build_slug([
        'id' => 91,
        'alt_text' => 'Fixture Advertiser',
        'order_id' => 42,
    ], ['slug' => 'main-grid'], $advertiser_page_settings),
    'Expected MDS2 account-name tokens to resolve blank while public placement fields remain available.'
);
mds3_assert_same(
    \MillionDollarScript\V3\Settings\SettingsSchema::CLASSIFICATION_ACTIVE,
    \MillionDollarScript\V3\Settings\SettingsSchema::field_classification('mds-pixel-template'),
    'Expected individual advertiser pages to be an active runtime setting instead of dormant compatibility data.'
);
mds3_assert_same('%placement_id%', \MillionDollarScript\V3\Settings\SettingsSchema::defaults()['mds-pixel-slug-structure'], 'Expected new advertiser pages to use a privacy-safe default slug.');
$concise_legacy_title = \MillionDollarScript\V3\Media\AdvertiserPageTitle::resolve([
    'id' => 91,
    'alt_text' => 'Unused fallback',
    'popup_text' => 'Longer description',
], (object) [
    'post_type' => 'mds-pixel',
    'post_title' => 'Acme &amp; Partners',
]);
mds3_assert_same('Acme & Partners', $concise_legacy_title['title'], 'Expected concise legacy advertiser titles to remain authoritative after safe entity normalization.');
mds3_assert_same(false, $concise_legacy_title['normalized'], 'Expected concise legacy advertiser titles not to be flagged as repaired.');
$oversized_legacy_title = \MillionDollarScript\V3\Media\AdvertiserPageTitle::resolve([
    'id' => 92,
    'alt_text' => 'Concise Sponsor Name',
    'popup_text' => '<p>A complete public sponsor description remains available separately.</p>',
], (object) [
    'post_type' => 'mds-pixel',
    'post_title' => "This legacy title contains an entire advertiser description that was entered into the old single-line title field and should not become the heading.\nIt also contains a second line.",
]);
mds3_assert_same('Concise Sponsor Name', $oversized_legacy_title['title'], 'Expected oversized legacy titles to prefer concise placement alt text.');
mds3_assert_same(true, $oversized_legacy_title['normalized'], 'Expected oversized legacy titles to be flagged for migration reconciliation.');
$unicode_legacy_title = \MillionDollarScript\V3\Media\AdvertiserPageTitle::resolve([
    'id' => 93,
    'alt_text' => '',
    'popup_text' => 'Équipe créative internationale avec une description publique détaillée pour les visiteurs du site et leurs partenaires commerciaux.',
], (object) [
    'post_type' => 'mds-pixel',
    'post_title' => str_repeat('Présentation détaillée — ', 10),
]);
mds3_assert_same(true, $unicode_legacy_title['normalized'], 'Expected long Unicode legacy titles to normalize deterministically.');
mds3_assert_same(true, mb_strlen($unicode_legacy_title['title'], 'UTF-8') <= 100, 'Expected derived advertiser titles to stay within the documented display bound.');
$native_title = \MillionDollarScript\V3\Media\AdvertiserPageTitle::resolve([
    'id' => 94,
    'alt_text' => 'Native placement title remains the title',
    'popup_text' => 'Native placement description',
]);
mds3_assert_same('Native placement title remains the title', $native_title['title'], 'Expected native placements to retain alt text as their advertiser title.');
$advertiser_page_template = file_get_contents(dirname(__DIR__, 2) . '/templates/frontend/advertiser-page.php');
mds3_assert_same(false, false !== strpos((string) $advertiser_page_template, 'order_id'), 'Expected the public advertiser template to omit private order identifiers.');
mds3_assert_same(false, false !== strpos((string) $advertiser_page_template, 'user_id'), 'Expected the public advertiser template to omit account identifiers.');
mds3_assert_same(true, false !== strpos((string) $advertiser_page_template, 'million-dollar-script/advertiser/page/before-content'), 'Expected the default advertiser template to expose privacy-safe region hooks.');
$advertiser_pages_source = file_get_contents(dirname(__DIR__, 2) . '/src/Media/AdvertiserPages.php');
mds3_assert_same(true, false !== strpos((string) $advertiser_pages_source, "locate_template('million-dollar-script/single-advertiser.php')"), 'Expected a documented classic/child-theme advertiser page override.');
mds3_assert_same(1, substr_count((string) $advertiser_pages_source, "locate_template('mds-pixel/single-mds-pixel.php')"), 'Expected the old MDS2 theme path to be detected only for the admin conversion notice.');
mds3_assert_same(true, false !== strpos((string) $advertiser_pages_source, 'return $override ?: $template;'), 'Expected runtime template resolution to return only the current override or the active theme template.');
mds3_assert_same(true, false !== strpos((string) $advertiser_pages_source, "'show_in_rest' => true"), 'Expected the advertiser post type to participate in block-theme template editing.');

$grid_shell_source = file_get_contents(dirname(__DIR__, 2) . '/templates/frontend/grid/shell.php');
$grid_footer_position = strpos((string) $grid_shell_source, 'class="mds3-grid-footer"');
$grid_hint_position = strpos((string) $grid_shell_source, 'class="mds3-grid-interaction-hint"');
mds3_assert_same(true, false !== $grid_footer_position, 'Expected the grid viewer to render an external footer.');
mds3_assert_same(true, false !== $grid_hint_position && $grid_hint_position > $grid_footer_position, 'Expected wheel-zoom guidance to render in the footer instead of over the advertising canvas.');

require __DIR__ . '/service-signature-fixture.php';
require __DIR__ . '/extension-bundle-fixture.php';

echo "MDS3 runtime tests passed.\n";
