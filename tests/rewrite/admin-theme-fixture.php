<?php
/**
 * WordPress fixture for the MDS admin first-paint theme contract.
 *
 * Run with:
 * ./scripts/wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/admin-theme-fixture.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$original_settings = get_option('mds3_settings', []);
$original_screen = $GLOBALS['current_screen'] ?? null;
$original_page = $_GET['page'] ?? null;
$failures = [];
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

try {
    if (!function_exists('set_current_screen')) {
        require_once ABSPATH . 'wp-admin/includes/screen.php';
    }

    set_current_screen('toplevel_page_mds3');
    $_GET['page'] = 'mds3';
    foreach (['dark', 'system', 'light'] as $mode) {
        $settings = is_array($original_settings) ? $original_settings : [];
        $settings['theme_mode'] = $mode;
        update_option('mds3_settings', $settings, false);

        $attributes = apply_filters('language_attributes', 'lang="en-US"', 'html');
        $body_classes = apply_filters('admin_body_class', 'wp-admin');
        $assert(
            false !== strpos((string) $attributes, 'data-mds3-admin-theme="' . $mode . '"'),
            'The opening HTML element should expose the selected ' . $mode . ' MDS admin theme.'
        );
        $assert(
            false !== strpos((string) $body_classes, 'mds3-admin-theme-' . $mode),
            'The body should retain its ' . $mode . ' MDS admin theme class.'
        );
    }

    set_current_screen('dashboard');
    unset($_GET['page']);
    $attributes = apply_filters('language_attributes', 'lang="en-US"', 'html');
    $assert(
        false === strpos((string) $attributes, 'data-mds3-admin-theme='),
        'Non-MDS WordPress admin screens must not receive the MDS theme attribute.'
    );

    set_current_screen('toplevel_page_mds3');
    $_GET['page'] = 'mds3';
    $xml_attributes = apply_filters('language_attributes', 'lang="en-US"', 'xhtml');
    $assert(
        false === strpos((string) $xml_attributes, 'data-mds3-admin-theme='),
        'Non-HTML document attributes must remain unchanged.'
    );

    $css = file_get_contents(MILLION_DOLLAR_SCRIPT_PATH . 'assets/mds3/css/admin.css');
    foreach (['light', 'dark', 'system'] as $mode) {
        $assert(
            false !== strpos((string) $css, 'html[data-mds3-admin-theme="' . $mode . '"]'),
            'Admin CSS should establish the ' . $mode . ' root canvas from the opening HTML attribute.'
        );
    }
} finally {
    update_option('mds3_settings', is_array($original_settings) ? $original_settings : [], false);
    $GLOBALS['current_screen'] = $original_screen;
    if (null === $original_page) {
        unset($_GET['page']);
    } else {
        $_GET['page'] = $original_page;
    }
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "MDS admin theme fixture passed.\n";
