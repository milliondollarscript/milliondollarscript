<?php
/**
 * MDS3 plugin kernel.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\V3;

use MillionDollarScript\V3\Admin\Admin;
use MillionDollarScript\V3\Blocks\EditorBlocks;
use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Docs\RemoteDocsClient;
use MillionDollarScript\V3\Extensions\ExtensionAdmin;
use MillionDollarScript\V3\Extensions\ExtensionPackageDelivery;
use MillionDollarScript\V3\Extensions\ExtensionRuntime;
use MillionDollarScript\V3\Grid\GridAjax;
use MillionDollarScript\V3\Grid\GridPostType;
use MillionDollarScript\V3\Grid\GridShortcode;
use MillionDollarScript\V3\Media\AdvertiserPages;
use MillionDollarScript\V3\Orders\OrderCleanup;
use MillionDollarScript\V3\Orders\OrderNotifications;
use MillionDollarScript\V3\Pages\PageShortcodes;
use MillionDollarScript\V3\Rendering\TileController;
use MillionDollarScript\V3\Rest\Api;
use MillionDollarScript\V3\Setup\Installer;
use MillionDollarScript\V3\Setup\OrderExpirationBackfill;
use MillionDollarScript\V3\Setup\StarterSite;
use MillionDollarScript\V3\Support\Component;
use MillionDollarScript\V3\Support\Distribution;
use MillionDollarScript\V3\Support\GridCapacityStatus;
use MillionDollarScript\V3\Support\MemoryStatus;
use MillionDollarScript\V3\Support\NetworkDiagnostics;
use MillionDollarScript\V3\Support\ReleaseProfile;
use MillionDollarScript\V3\Updates\CorePluginUpdater;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin {

    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static $instance;

    /**
     * Whether boot() has run.
     *
     * @var bool
     */
    private $booted = false;

    /**
     * Components.
     *
     * @var Component[]
     */
    private $components = [];

    /**
     * Return singleton.
     *
     * @return Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Compatibility alias for older extension checks.
     *
     * @return Plugin
     */
    public static function get_instance() {
        return self::instance();
    }

    /**
     * Whether the runtime has completed its one-time boot registration.
     *
     * @return bool
     */
    public static function is_booted() {
        return null !== self::$instance && self::$instance->booted;
    }

    /**
     * Boot the plugin runtime.
     *
     * @return void
     */
    public function boot() {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        add_action('init', [$this, 'load_textdomain'], 0);
        add_action('init', [Installer::class, 'ensure'], 1);
        add_action(OrderExpirationBackfill::HOOK, [OrderExpirationBackfill::class, 'run_scheduled']);
        add_action('plugins_loaded', [$this, 'register_extensions'], 30);

        $this->components = [
            new Admin(),
            new Api(),
            new ExtensionAdmin(),
            new RemoteDocsClient(),
            new OrderCleanup(),
            new OrderNotifications(),
            new Payments(),
            new StarterSite(),
            new AdvertiserPages(),
            new MemoryStatus(),
            new GridCapacityStatus(),
            new NetworkDiagnostics(),
            new ReleaseProfile(),
        ];

        if (Distribution::allows_external_plugin_delivery()) {
            $this->components[] = new ExtensionPackageDelivery();
        }
        if (Distribution::allows_custom_core_updates()) {
            $this->components[] = new CorePluginUpdater();
        }

        if ((new ExtensionRuntime())->is_enabled('mds-grid')) {
            $this->components = array_merge($this->components, [
                new EditorBlocks(),
                new GridPostType(),
                new GridShortcode(),
                new PageShortcodes(),
                new GridAjax(),
                new TileController(),
            ]);
        }

        foreach ($this->components as $component) {
            $component->register();
        }

        \MillionDollarScript\Core\Hooks::do('million-dollar-script/loaded', $this);
    }

    /**
     * Give MDS-owned extensions a stable registration event after all active
     * plugin files have loaded.
     *
     * @return void
     */
    public function register_extensions() {
        \MillionDollarScript\Core\Hooks::do('million-dollar-script/register/extensions');
    }

    /**
     * Load translations.
     *
     * @return void
     */
    public function load_textdomain() {
        load_plugin_textdomain('million-dollar-script', false, dirname(MILLION_DOLLAR_SCRIPT_BASENAME) . '/languages');
    }

    /**
     * Plugin URL.
     *
     * @return string
     */
    public function get_plugin_url() {
        return MILLION_DOLLAR_SCRIPT_URL;
    }

    /**
     * Plugin version.
     *
     * @return string
     */
    public function get_version() {
        return MILLION_DOLLAR_SCRIPT_VERSION;
    }
}
