<?php
/**
 * Plugin Name: Million Dollar Script
 * Plugin URI: https://milliondollarscript.com
 * Description: WordPress-first pixel grid advertising plugin.
 * Version: 3.0.0
 * Author: Million Dollar Script
 * Author URI: https://milliondollarscript.com
 * Text Domain: million-dollar-script
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 8.1
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package MillionDollarScript
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MILLION_DOLLAR_SCRIPT_VERSION', '3.0.0');
define('MILLION_DOLLAR_SCRIPT_PRODUCT_FAMILY', 'modern');
define('MILLION_DOLLAR_SCRIPT_CORE_API_VERSION', 1);
define('MILLION_DOLLAR_SCRIPT_DISTRIBUTION', 'direct');
define('MILLION_DOLLAR_SCRIPT_FILE', __FILE__);
define('MILLION_DOLLAR_SCRIPT_BASENAME', plugin_basename(__FILE__));
define('MILLION_DOLLAR_SCRIPT_PATH', plugin_dir_path(__FILE__));
define('MILLION_DOLLAR_SCRIPT_URL', plugin_dir_url(__FILE__));
define('MILLION_DOLLAR_SCRIPT_SRC_PATH', MILLION_DOLLAR_SCRIPT_PATH . 'src/');
define('MILLION_DOLLAR_SCRIPT_ASSETS_URL', MILLION_DOLLAR_SCRIPT_URL . 'assets/mds3/');

require_once MILLION_DOLLAR_SCRIPT_SRC_PATH . 'Autoload.php';

\MillionDollarScript\V3\Autoload::register(MILLION_DOLLAR_SCRIPT_SRC_PATH);
require_once MILLION_DOLLAR_SCRIPT_SRC_PATH . 'Compatibility.php';

if (!class_exists('MDS\Core\Plugin', false)) {
    class_alias(\MillionDollarScript\V3\Plugin::class, 'MDS\Core\Plugin');
}

if (!function_exists('mds_initialize')) {
    /**
     * Initialize the Million Dollar Script runtime.
     *
     * @return void
     */
    function mds_initialize() {
        \MillionDollarScript\V3\Plugin::instance()->boot();
    }
}

register_activation_hook(MILLION_DOLLAR_SCRIPT_FILE, [\MillionDollarScript\V3\Setup\Installer::class, 'activate']);
register_deactivation_hook(MILLION_DOLLAR_SCRIPT_FILE, [\MillionDollarScript\V3\Setup\Installer::class, 'deactivate']);
register_uninstall_hook(MILLION_DOLLAR_SCRIPT_FILE, [\MillionDollarScript\V3\Setup\Installer::class, 'uninstall']);

add_action('plugins_loaded', 'mds_initialize', 5);
