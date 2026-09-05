<?php
/**
 * MDS 3.0 admin surface.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin;

use MillionDollarScript\V3\Extensions\ExtensionRuntime;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\BrowserConfig;
use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class Admin implements Component {
    use Concerns\HandlesAdminActions;
    use Concerns\HandlesCacheAdminActions;
    use Concerns\RendersAdminPages;
    use Concerns\RendersAdminNavigation;
    use Concerns\RendersDashboardPanels;
    use Concerns\RendersGridPanels;
    use Concerns\RendersFormFields;
    use Concerns\RendersImageGridPanels;
    use Concerns\RendersApiPanels;
    use Concerns\RendersExtensionPanels;
    use Concerns\RendersMigrationPanels;

    public function register() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_bar_menu', [$this, 'admin_bar_menu'], 80);
        add_action('admin_head', [$this, 'hide_internal_submenus']);
        add_action('admin_init', [$this, 'maybe_redirect_to_setup']);
        add_action('admin_enqueue_scripts', [$this, 'assets'], 1);
        add_filter('admin_body_class', [$this, 'admin_body_class']);
        add_filter('language_attributes', [$this, 'admin_language_attributes'], 10, 2);
        add_action('admin_post_mds3_save_settings', [$this, 'save_settings']);
        add_action('admin_post_mds3_export_settings', [$this, 'export_settings']);
        add_action('admin_post_mds3_preview_settings_import', [$this, 'preview_settings_import']);
        add_action('admin_post_mds3_apply_settings_import', [$this, 'apply_settings_import']);
        add_action('admin_post_mds3_clear_settings_import_preview', [$this, 'clear_settings_import_preview']);
        add_action('admin_post_mds3_save_setup', [$this, 'save_setup']);
        add_action('admin_post_mds3_install_plugin_dependency', [$this, 'install_plugin_dependency']);
        add_action('wp_ajax_mds3_install_plugin_dependency', [$this, 'ajax_install_plugin_dependency']);
        add_action('admin_post_million_dollar_script_refresh_docs', [$this, 'refresh_docs']);
        add_action('admin_post_mds3_create_api_key', [$this, 'create_api_key']);
        add_action('admin_post_mds3_rotate_api_key', [$this, 'rotate_api_key']);
        add_action('admin_post_mds3_revoke_api_key', [$this, 'revoke_api_key']);
        add_action('admin_post_mds3_save_api_policies', [$this, 'save_api_policies']);
        add_action('admin_post_mds3_mds2_keep_active', [$this, 'keep_mds2_active']);
        add_action('admin_post_mds3_mds2_deactivate', [$this, 'deactivate_mds2']);
        add_action('admin_post_mds3_ensure_standard_pages', [$this, 'ensure_standard_pages']);
        add_action('admin_post_mds3_create_extension_setup_pages', [$this, 'create_extension_setup_pages']);
        add_action('admin_post_mds3_create_extension_legal_pages', [$this, 'create_extension_legal_pages']);
        add_action('admin_post_mds3_create_extension_onboarding_pages', [$this, 'create_extension_onboarding_pages']);
        add_action('admin_post_mds3_run_migration_import', [$this, 'run_migration_import']);
        add_action('admin_post_mds3_pause_migration_import', [$this, 'pause_migration_import']);
        add_action('admin_post_mds3_clear_cache', [$this, 'clear_cache']);
        add_action('wp_ajax_mds3_migration_step', [$this, 'ajax_migration_step']);

        if ($this->grid_enabled()) {
            add_action('admin_post_mds3_create_grid', [$this, 'create_grid']);
            add_action('admin_post_mds3_update_grid', [$this, 'update_grid']);
            add_action('admin_post_mds3_archive_grid', [$this, 'archive_grid']);
            add_action('admin_post_mds3_export_grids', [$this, 'export_grids']);
            add_action('admin_post_mds3_import_grids', [$this, 'import_grids']);
            add_action('admin_post_mds3_create_grid_page', [$this, 'create_grid_page']);
            add_action('admin_post_mds3_set_grid_page_mode', [$this, 'set_grid_page_mode']);
            add_action('admin_post_mds3_save_package', [$this, 'save_package']);
            add_action('admin_post_mds3_archive_package', [$this, 'archive_package']);
            add_action('admin_post_mds3_save_price_rule', [$this, 'save_price_rule']);
            add_action('admin_post_mds3_archive_price_rule', [$this, 'archive_price_rule']);
            add_action('admin_post_mds3_set_region_status', [$this, 'set_region_status']);
            add_action('admin_post_mds3_update_order_status', [$this, 'update_order_status']);
            add_action('admin_post_mds3_bulk_order_status', [$this, 'bulk_order_status']);
            add_action('admin_post_mds3_start_order_renewal', [$this, 'start_order_renewal']);
            add_action('admin_post_mds3_move_order_placement', [$this, 'move_order_placement']);
            add_action('wp_ajax_mds3_order_detail', [$this, 'ajax_order_detail']);
            add_action('wp_ajax_mds3_preview_order_move', [$this, 'ajax_preview_order_move']);
        }
    }

    public function admin_body_class($classes) {
        if (!$this->is_mds_admin_screen()) {
            return $classes;
        }

        return trim((string) $classes . ' mds3-admin-theme-' . $this->admin_theme_mode());
    }

    /**
     * Expose the MDS admin theme on the opening HTML element.
     *
     * WordPress prints language attributes with the opening tag, before the
     * document head or body exists. This lets the render-blocking admin
     * stylesheet establish the correct canvas on the first paint without an
     * inline script, inline style, or a late body-class dependency.
     *
     * @param string $output  Existing language attributes.
     * @param string $doctype Current document type.
     * @return string
     */
    public function admin_language_attributes($output, $doctype = 'html') {
        if (!is_admin() || 'html' !== (string) $doctype || !$this->is_mds_admin_screen()) {
            return $output;
        }

        return trim((string) $output . ' data-mds3-admin-theme="' . esc_attr($this->admin_theme_mode()) . '"');
    }

    public function assets($hook) {
        if (current_user_can('manage_options')) {
            wp_enqueue_style('mds3-admin-navigation', MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'css/admin-navigation.css', [], $this->asset_version('assets/mds3/css/admin-navigation.css'));
            wp_enqueue_script('mds3-admin-navigation', MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'js/admin-navigation.js', [], $this->asset_version('assets/mds3/js/admin-navigation.js'), true);
            BrowserConfig::add('mds3-admin-navigation', 'adminNavigation', [
                'corePages' => $this->sidebar_core_pages(),
                'extensionsStateKey' => 'mds3_sidebar_extensions_open',
                'hideExtensionLinksLabel' => __('Hide extension links', 'million-dollar-script'),
                'showExtensionLinksLabel' => __('Show extension links', 'million-dollar-script'),
            ]);
        }

        if (!$this->is_mds_admin_screen($hook)) {
            return;
        }

        wp_enqueue_style('mds3-admin', MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'css/admin.css', [], $this->asset_version('assets/mds3/css/admin.css'));
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }
        wp_enqueue_script('mds3-admin', MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'js/admin.js', ['wp-color-picker'], $this->asset_version('assets/mds3/js/admin.js'), true);
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }
        BrowserConfig::add('mds3-admin', 'admin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'adminPostUrl' => admin_url('admin-post.php'),
            'dependencyNonce' => wp_create_nonce('mds3_install_plugin_dependency'),
            'migrationNonce' => wp_create_nonce('mds3_migration_step'),
            'orderDetailNonce' => wp_create_nonce('mds3_order_detail'),
            'i18n' => [
                'loading' => __('Loading...', 'million-dollar-script'),
                'error' => __('Unable to load order details.', 'million-dollar-script'),
                'migrationError' => __('Migration progress could not be updated.', 'million-dollar-script'),
                'installing' => __('Installing...', 'million-dollar-script'),
                'dependencyReady' => __('Plugin installed and activated. You can continue setup.', 'million-dollar-script'),
                'dependencyFailed' => __('The plugin could not be installed automatically.', 'million-dollar-script'),
                'dismissNotice' => __('Dismiss notification', 'million-dollar-script'),
                'extensionActivating' => __('Activating extension...', 'million-dollar-script'),
                /* translators: 1: first visible extension number, 2: last visible extension number, 3: total extensions, 4: current page, 5: total pages. */
                'extensionPageStatus' => __('Showing %1$d-%2$d of %3$d · Page %4$d of %5$d', 'million-dollar-script'),
                'shortcodeCopied' => __('Copied', 'million-dollar-script'),
                'shortcodeCopyFailed' => __('Copy failed', 'million-dollar-script'),
                'docsCodeCopied' => __('Copied', 'million-dollar-script'),
                'docsCodeCopyFailed' => __('Copy failed', 'million-dollar-script'),
                'selectImage' => __('Select image', 'million-dollar-script'),
                'useImage' => __('Use image', 'million-dollar-script'),
                'moveChooseTarget' => __('Choose a target row and column first.', 'million-dollar-script'),
                'movePreviewFailed' => __('The placement could not be previewed.', 'million-dollar-script'),
                'movePreviewing' => __('Checking placement...', 'million-dollar-script'),
                /* translators: %s: dependent extension name */
                'requiredBy' => __('Required by %s', 'million-dollar-script'),
                /* translators: 1: extension name, 2: conflicting extension name. */
                'conflictsWith' => __('%1$s conflicts with %2$s.', 'million-dollar-script'),
            ],
        ]);
    }

    private function asset_version($relative_path) {
        $path = MILLION_DOLLAR_SCRIPT_PATH . ltrim((string) $relative_path, '/');
        $mtime = file_exists($path) ? filemtime($path) : false;

        return $mtime ? MILLION_DOLLAR_SCRIPT_VERSION . '.' . $mtime : MILLION_DOLLAR_SCRIPT_VERSION;
    }

    public function menu() {
        add_menu_page(
            __('Million Dollar Script', 'million-dollar-script'),
            __('Million Dollar Script', 'million-dollar-script'),
            'manage_options',
            'mds3',
            [$this, 'dashboard'],
            'dashicons-grid-view',
            25
        );

        add_submenu_page('mds3', __('Dashboard', 'million-dollar-script'), __('Dashboard', 'million-dollar-script'), 'manage_options', 'mds3', [$this, 'dashboard']);
        if ($this->grid_enabled()) {
            add_submenu_page('mds3', __('Grids', 'million-dollar-script'), __('Grids', 'million-dollar-script'), 'manage_options', 'mds3-grids', [$this, 'grids']);
            add_submenu_page('mds3', __('Orders', 'million-dollar-script'), __('Orders', 'million-dollar-script'), 'manage_options', 'mds3-orders', [$this, 'orders']);
        }
        add_submenu_page('mds3', __('Extensions', 'million-dollar-script'), __('Extensions', 'million-dollar-script'), 'manage_options', 'mds3-extensions', [$this, 'extensions']);
        add_submenu_page('mds3', __('Documentation', 'million-dollar-script'), __('Documentation', 'million-dollar-script'), 'manage_options', 'mds3-docs', [$this, 'docs']);
        add_submenu_page('mds3', __('API Access', 'million-dollar-script'), __('API Access', 'million-dollar-script'), 'manage_options', 'mds3-api', [$this, 'api_access']);
        add_submenu_page('mds3', __('Settings', 'million-dollar-script'), __('Settings', 'million-dollar-script'), 'manage_options', 'mds3-settings', [$this, 'settings']);
        add_submenu_page('mds3', __('Setup', 'million-dollar-script'), __('Setup', 'million-dollar-script'), 'manage_options', 'mds3-setup', [$this, 'setup']);
        if ($this->grid_enabled()) {
            add_submenu_page('mds3', __('Migration', 'million-dollar-script'), __('Migration', 'million-dollar-script'), 'manage_options', 'mds3-migration', [$this, 'migration']);
        }
        add_submenu_page('mds3', __('System Status', 'million-dollar-script'), __('System Status', 'million-dollar-script'), 'manage_options', 'mds3-system-status', [$this, 'system_status']);

        \MillionDollarScript\Core\Hooks::do('million-dollar-script/admin/menu');
    }

    public function hide_internal_submenus() {
        foreach (['mds3-setup', 'mds3-migration', 'mds3-system-status'] as $slug) {
            remove_submenu_page('mds3', $slug);
        }
    }

    private function admin_theme_mode() {
        $settings = get_option('mds3_settings', []);
        $mode = is_array($settings) ? ($settings['theme_mode'] ?? 'light') : 'light';
        $mode = SettingsSchema::sanitize('theme_mode', $mode);

        return in_array($mode, ['light', 'dark', 'system'], true) ? $mode : 'light';
    }

    private function grid_enabled() {
        return (new ExtensionRuntime())->is_enabled('mds-grid');
    }

    private function settings_groups() {
        $groups = SettingsSchema::groups();
        if (!$this->grid_enabled()) {
            foreach (array_keys($groups) as $group) {
                if (in_array($this->settings_group_slug($group), ['urls-redirects', 'display-interaction', 'orders-uploads', 'order-emails', 'rendering'], true)) {
                    unset($groups[$group]);
                }
            }
        }

        foreach ($groups as $group => $fields) {
            $visible = [];
            foreach ($fields as $field) {
                $key = sanitize_key((string) ($field['key'] ?? ''));
                if (!$key || SettingsSchema::is_admin_visible($key)) {
                    $visible[] = $field;
                }
            }

            if (!$visible && 'rendering' !== $this->settings_group_slug($group)) {
                unset($groups[$group]);
                continue;
            }

            $groups[$group] = $visible;
        }

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/settings/groups', $groups, $this->grid_enabled());
    }

    private function settings_fields_for_save() {
        $fields = [];
        foreach ($this->settings_groups() as $group) {
            foreach ($group as $field) {
                if (!empty($field['key'])) {
                    $fields[$field['key']] = $field;
                }
            }
        }

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/settings/fields/for/save', $fields, $this->grid_enabled());
    }

    private function settings_group_slug($group) {
        return sanitize_key(sanitize_title((string) $group));
    }

    public function maybe_redirect_to_setup() {
        if (!current_user_can('manage_options') || wp_doing_ajax() || 'yes' === get_option('mds3_setup_complete', 'no')) {
            return;
        }

        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        $allowed_pages = \MillionDollarScript\Core\Hooks::apply(
            'million-dollar-script/setup/allowed-admin-pages',
            // mds3-migration is reachable pre-setup on purpose: the MDS2 step in the
            // setup wizard links to it for a dry-run review, and the import itself
            // is an explicit admin-post action that stays gated by its own checks.
            ['mds3-setup', 'mds3-extensions', 'mds3-migration']
        );
        $allowed_pages = array_values(array_unique(array_filter(array_map('sanitize_key', is_array($allowed_pages) ? $allowed_pages : []))));
        if ('yes' === get_transient('mds3_setup_redirect') && !isset($_GET['activate-multi']) && !in_array($page, $allowed_pages, true)) {
            delete_transient('mds3_setup_redirect');
            wp_safe_redirect(admin_url('admin.php?page=mds3-setup'));
            exit;
        }

        if (!$page || 0 !== strpos($page, 'mds3') || in_array($page, $allowed_pages, true)) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=mds3-setup'));
        exit;
    }
}
