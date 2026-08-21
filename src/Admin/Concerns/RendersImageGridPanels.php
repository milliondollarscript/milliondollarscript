<?php
/**
 * ImageGrid extension detection and settings prompt panel.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Extensions\ExtensionCatalog;
use MillionDollarScript\V3\Rendering\ImageGridService;
use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersImageGridPanels {

    private function imagegrid_extension_active() {
        if (
            defined('MDS_IMAGEGRID_VERSION')
            || function_exists('\\MillionDollarScript\\Extensions\\ImageGrid\\mds_imagegrid_app_url')
            || function_exists('\\MDS\\Extensions\\ImageGrid\\mds_imagegrid_app_url')
        ) {
            return true;
        }

        $active_plugins = (array) get_option('active_plugins', []);
        $network_plugins = is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', [])) : [];
        foreach (array_merge($active_plugins, $network_plugins) as $plugin_file) {
            if ('mds-imagegrid' === sanitize_key(dirname((string) $plugin_file))) {
                return true;
            }
        }

        return false;
    }

    private function imagegrid_settings_prompt() {
        $catalog = new ExtensionCatalog();
        $installed = $catalog->installed_item_by_slug('mds-imagegrid');
        $available = $catalog->available_item('mds-imagegrid');
        $installed_inactive = $installed && empty($installed['active']);
        $purchase_url = (string) ($available['purchase_url'] ?? '');

        Template::display('admin/partials/imagegrid-settings-prompt.php', [
            'installed_inactive' => $installed_inactive,
            'service_url' => $purchase_url ?: ImageGridService::ACCOUNT_URL,
        ], $this);
    }
}
