<?php
/**
 * Top-level MDS 3.0 admin page render callbacks.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Docs\DocsRegistry;
use MillionDollarScript\V3\Extensions\ExtensionCatalog;
use MillionDollarScript\V3\Extensions\ExtensionOnboarding;
use MillionDollarScript\V3\Extensions\ExtensionServer;
use MillionDollarScript\V3\Extensions\ExtensionSetup;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Migration\DryRun;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Rest\ApiGovernance;
use MillionDollarScript\V3\Rest\ApiKeyRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Settings\SettingsTransfer;
use MillionDollarScript\V3\Setup\LegacyPlugin;
use MillionDollarScript\V3\Setup\PluginDependencyInstaller;
use MillionDollarScript\V3\Setup\StarterSite;
use MillionDollarScript\V3\Support\Template;
use MillionDollarScript\V3\Support\Distribution;
use MillionDollarScript\V3\Support\GridCapacityStatus;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersAdminPages {

    public function dashboard() {
        $grid_enabled = $this->grid_enabled();
        $grid_repo = $grid_enabled ? new GridRepository() : null;
        $grid_count = $grid_repo ? $grid_repo->admin_count() : 0;
        $orders = $grid_enabled ? (new OrderRepository())->recent(5) : [];
        $order_counts = $grid_enabled ? $this->dashboard_order_counts() : ['paid_total' => 0.0];
        $catalog = (new ExtensionCatalog())->catalog();
        $missing_pages = $grid_enabled ? $this->missing_standard_pages() : [];
        $settings = wp_parse_args(is_array(get_option('mds3_settings', [])) ? get_option('mds3_settings', []) : [], SettingsSchema::defaults());
        $active_extensions = count(array_filter($catalog['installed'] ?? [], static function ($item) {
            return !empty($item['active']);
        }));
        $available_extensions = count($catalog['available'] ?? []);
        $endpoint_count = count((new ApiGovernance())->effective_manifest());

        Template::display('admin/pages/dashboard.php', [
            'active_extensions' => $active_extensions,
            'available_extensions' => $available_extensions,
            'catalog' => $catalog,
            'endpoint_count' => $endpoint_count,
            'grid_enabled' => $grid_enabled,
            'grid_count' => $grid_count,
            'missing_pages' => $missing_pages,
            'order_counts' => $order_counts,
            'orders' => $orders,
            'settings' => $settings,
        ], $this);
    }

    public function setup() {
        $grid_enabled = $this->grid_enabled();
        $grid = $grid_enabled ? (new GridRepository())->first_active() : null;
        $missing_pages = $grid_enabled ? $this->missing_standard_pages() : [];
        $settings = wp_parse_args(is_array(get_option('mds3_settings', [])) ? get_option('mds3_settings', []) : [], SettingsSchema::defaults());
        $payment_options = SettingsSchema::payment_provider_options();
        if (!isset($payment_options['woocommerce'])) {
            $payment_options['woocommerce'] = __('WooCommerce checkout', 'million-dollar-script');
        }
        $active_payment_provider = Payments::active_provider_id($settings);
        $payment_dependencies = PluginDependencyInstaller::all_statuses();
        $setup_complete = 'yes' === get_option('mds3_setup_complete', 'no');
        $legacy_plugins = LegacyPlugin::detected_plugins();
        $legacy_source = LegacyPlugin::source_status_for_setup($settings['legacy_mds2_source_prefix'] ?? '');
        $extension_setup = new ExtensionSetup();
        $extension_choices = $extension_setup->choices();
        $selected_extensions = $extension_setup->selected_slugs($extension_choices);
        $woocommerce_adapter = $this->woocommerce_adapter_status($extension_choices);
        if (!empty($woocommerce_adapter['active']) && !in_array('mds-woocommerce', $selected_extensions, true)) {
            $extension_setup->ensure_selected('mds-woocommerce');
            $selected_extensions = $extension_setup->selected_slugs($extension_choices);
        }
        $requested_payment_provider = sanitize_key((string) ($settings['payment_provider'] ?? 'standalone'));
        $payment_provider_readiness = \MillionDollarScript\Core\Hooks::apply(
            'million-dollar-script/setup/payment/provider/readiness',
            [
                'actions' => [],
                'items' => [],
                'ready' => true,
                'review_url' => '',
            ],
            $requested_payment_provider,
            $settings
        );
        $payment_provider_readiness = is_array($payment_provider_readiness) ? $payment_provider_readiness : [];
        $provider_available = 'standalone' === $requested_payment_provider || Payments::provider_ready($requested_payment_provider);
        $provider_configuration_ready = !array_key_exists('ready', $payment_provider_readiness) || !empty($payment_provider_readiness['ready']);
        $setup_ready = $provider_available && $provider_configuration_ready;
        $extension_onboarding = new ExtensionOnboarding();
        $extension_page_result_key = 'mds3_extension_setup_pages_result_' . get_current_user_id();
        $extension_page_result = get_transient($extension_page_result_key);
        if (is_array($extension_page_result)) {
            delete_transient($extension_page_result_key);
        } else {
            $extension_page_result = [];
        }
        $extension_legal_result_key = 'mds3_extension_legal_pages_result_' . get_current_user_id();
        $extension_legal_result = get_transient($extension_legal_result_key);
        if (is_array($extension_legal_result)) {
            delete_transient($extension_legal_result_key);
        } else {
            $extension_legal_result = [];
        }
        $starter_site = new StarterSite();
        $starter_site_result_key = 'mds3_starter_site_result_' . get_current_user_id();
        $starter_site_result = get_transient($starter_site_result_key);
        if (is_array($starter_site_result)) {
            delete_transient($starter_site_result_key);
        } else {
            $starter_site_result = [];
        }

        Template::display('admin/pages/setup.php', [
            'active_payment_provider' => $active_payment_provider,
            'extension_choices' => $extension_choices,
            'extension_legal_result' => $extension_legal_result,
            'extension_onboarding_items' => $extension_onboarding->items($selected_extensions),
            'extension_page_result' => $extension_page_result,
            'extension_setup' => $extension_setup,
            'grid' => $grid,
            'grid_enabled' => $grid_enabled,
            'legacy_plugins' => $legacy_plugins,
            'legacy_source' => $legacy_source,
            'missing_pages' => $missing_pages,
            'payment_dependencies' => $payment_dependencies,
            'payment_options' => $payment_options,
            'payment_provider_readiness' => $payment_provider_readiness,
            'selected_extensions' => $selected_extensions,
            'settings' => $settings,
            'setup_complete' => $setup_complete,
            'setup_ready' => $setup_ready,
            'starter_site_result' => $starter_site_result,
            'starter_site_status' => $starter_site->status(),
            'woocommerce_adapter' => $woocommerce_adapter,
        ], $this);
    }

    private function woocommerce_adapter_status(array $extension_choices) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = 'mds-woocommerce/mds-woocommerce.php';
        $plugins = get_plugins();
        $available = false;
        foreach ($extension_choices as $choice) {
            if ('mds-woocommerce' === sanitize_key((string) ($choice['slug'] ?? ''))) {
                $available = true;
                break;
            }
        }

        return [
            'plugin_file' => $plugin_file,
            'installed' => isset($plugins[$plugin_file]) || file_exists(WP_PLUGIN_DIR . '/' . $plugin_file),
            'active' => is_plugin_active($plugin_file),
            'available' => $available,
            'can_install' => $available && Distribution::allows_external_plugin_delivery() && current_user_can('install_plugins'),
            'can_activate' => current_user_can('activate_plugins'),
            'extensions_url' => admin_url('admin.php?page=mds3-extensions'),
        ];
    }

    public function grids() {
        $repo = new GridRepository();
        $request = wp_unslash($_GET);
        $grid_status = sanitize_key($request['grid_status'] ?? 'all');
        if ('all' !== $grid_status && !in_array($grid_status, GridRepository::admin_statuses(), true)) {
            $grid_status = 'all';
        }
        $search = sanitize_text_field((string) ($request['s'] ?? ''));
        $orderby = sanitize_key($request['orderby'] ?? 'id');
        if (!in_array($orderby, GridRepository::admin_orderby_keys(), true)) {
            $orderby = 'id';
        }
        $order = 'asc' === strtolower((string) ($request['order'] ?? '')) ? 'asc' : 'desc';
        $paged = max(1, absint($request['paged'] ?? 1));
        $per_page = 20;
        $total_grids = $repo->admin_count($grid_status, $search);
        $total_pages = max(1, (int) ceil($total_grids / $per_page));
        $paged = min($paged, $total_pages);
        $grids = $repo->admin_page([
            'status' => $grid_status,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $paged,
            'per_page' => $per_page,
        ]);
        $editing = !empty($request['grid_id']) ? $repo->find(absint($request['grid_id'])) : null;
        $grid_currency = '';
        $currency_locked = false;
        $grid_tabs = [];
        $active_tab = 'grid-details';
        $grid_import_result = get_transient('mds3_grid_import_result_' . get_current_user_id());
        if (is_array($grid_import_result)) {
            delete_transient('mds3_grid_import_result_' . get_current_user_id());
        } else {
            $grid_import_result = [];
        }
        if ($editing) {
            $grid_currency = Currency::code($editing->get('currency', 'USD'));
            $currency_locked = Currency::provider_locks_currency();
            $grid_tabs = [
                'grid-details' => __('Details', 'million-dollar-script'),
                'grid-public' => __('Public Page', 'million-dollar-script'),
                'grid-packages' => __('Packages', 'million-dollar-script'),
                'grid-price-zones' => __('Price Zones', 'million-dollar-script'),
                'grid-availability' => __('Availability', 'million-dollar-script'),
            ];
            $grid_tabs = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/grid/tabs', $grid_tabs, $editing);
            $active_tab = 'grid-details';
            if (!empty($_GET['packages'])) {
                $active_tab = 'grid-packages';
            } elseif (!empty($_GET['price_rules'])) {
                $active_tab = 'grid-price-zones';
            } elseif (!empty($_GET['availability'])) {
                $active_tab = 'grid-availability';
            } elseif (!empty($_GET['public_page'])) {
                $active_tab = 'grid-public';
            } elseif (!empty($_GET['tab'])) {
                $active_tab = sanitize_key(wp_unslash($_GET['tab']));
            }
            if (!isset($grid_tabs[$active_tab])) {
                $active_tab = 'grid-details';
            }
        }

        Template::display('admin/pages/grids.php', [
            'active_tab' => $active_tab,
            'currency_locked' => $currency_locked,
            'editing' => $editing,
            'grid_currency' => $grid_currency,
            'grid_import_result' => $grid_import_result,
            'grid_capacity' => $editing ? GridCapacityStatus::for_grid($editing) : [],
            'grid_list' => [
                'status' => $grid_status,
                'search' => $search,
                'orderby' => $orderby,
                'order' => $order,
                'paged' => $paged,
                'per_page' => $per_page,
                'total' => $total_grids,
                'total_pages' => $total_pages,
                'counts' => $repo->admin_status_counts($search),
            ],
            'grid_tabs' => $grid_tabs,
            'grids' => $grids,
            'renderer_modes' => GridRepository::renderer_modes(),
        ], $this);
    }

    public function orders() {
        $repo = new OrderRepository();
        $request = wp_unslash($_GET);
        $selected_status = sanitize_key($request['order_status'] ?? '');
        if ($selected_status && !in_array($selected_status, OrderRepository::statuses(), true)) {
            $selected_status = '';
        }
        $providers = $repo->providers();
        $selected_provider = sanitize_key($request['provider'] ?? '');
        if ($selected_provider && !in_array($selected_provider, $providers, true)) {
            $selected_provider = '';
        }
        $orderby = sanitize_key($request['orderby'] ?? 'id');
        if (!in_array($orderby, ['id', 'status', 'customer', 'total', 'provider', 'date', 'grid', 'placement', 'term'], true)) {
            $orderby = 'id';
        }
        $order = 'asc' === strtolower((string) ($request['order'] ?? '')) ? 'asc' : 'desc';
        $paged = max(1, absint($request['paged'] ?? 1));
        $order_cursor = absint($request['order_cursor'] ?? 0);
        $per_page = 50;
        $payment_state = sanitize_key($request['payment_state'] ?? '');
        if (!in_array($payment_state, ['', 'paid', 'unpaid', 'failed', 'refunded'], true)) {
            $payment_state = '';
        }
        $upload_state = sanitize_key($request['upload_state'] ?? '');
        if (!in_array($upload_state, ['', 'uploaded', 'missing'], true)) {
            $upload_state = '';
        }
        $expiration_state = sanitize_key($request['expiration_state'] ?? '');
        if (!in_array($expiration_state, ['', 'active_term', 'expired_term', 'renewable', 'renewal_started', 'has_term', 'no_term'], true)) {
            $expiration_state = '';
        }
        $placement_state = sanitize_key($request['placement_state'] ?? '');
        if (!in_array($placement_state, ['', 'active', 'pending', 'cancelled', 'archived', 'none', 'not_active'], true)) {
            $placement_state = '';
        }
        $filters = [
            'status' => $selected_status,
            'grid_id' => absint($request['grid_id'] ?? 0),
            'provider' => $selected_provider,
            'payment_state' => $payment_state,
            'upload_state' => $upload_state,
            'expiration_state' => $expiration_state,
            'placement_state' => $placement_state,
            'search' => sanitize_text_field((string) ($request['s'] ?? '')),
            'date_from' => sanitize_text_field((string) ($request['date_from'] ?? '')),
            'date_to' => sanitize_text_field((string) ($request['date_to'] ?? '')),
            'orderby' => $orderby,
            'order' => $order,
            'paged' => $paged,
            'order_cursor' => $order_cursor,
        ];
        $total_orders = $repo->count($filters);

        $next_cursor = 0;
        if ('id' === $orderby && (1 === $paged || $order_cursor)) {
            $cursor_page = $repo->cursor_page(array_merge($filters, [
                'limit' => $per_page,
                'cursor_id' => $order_cursor,
            ]));
            $orders = $cursor_page['items'];
            $next_cursor = $cursor_page['has_more'] ? absint($cursor_page['next_id']) : 0;
        } else {
            // Preserve intentionally bookmarked numbered/sorted pages while
            // normal default-sort navigation uses the bounded keyset path.
            $orders = $repo->query(array_merge($filters, [
                'limit' => $per_page,
                'offset' => ($paged - 1) * $per_page,
            ]));
        }
        $selected_order_id = absint($request['order_id'] ?? 0);
        $selected_order = $selected_order_id ? $repo->find($selected_order_id) : null;

        Template::display('admin/pages/orders.php', [
            'bulk_result' => [
                'updated' => absint($_GET['bulk_updated'] ?? 0),
                'skipped' => absint($_GET['bulk_skipped'] ?? 0),
                'status' => sanitize_key(wp_unslash($_GET['bulk_status'] ?? '')),
            ],
            'bulk_status_labels' => $this->order_bulk_status_labels(),
            'filters' => $filters,
            'grid_options' => (new GridRepository())->all(),
            'order_counts' => $repo->counts_by_status(),
            'orders' => $orders,
            'pagination' => [
                'current' => $paged,
                'per_page' => $per_page,
                'total' => $total_orders,
                'total_pages' => max(1, (int) ceil($total_orders / $per_page)),
                'next_cursor' => $next_cursor,
            ],
            'provider_options' => $providers,
            'selected_order' => $selected_order,
            'selected_status' => $selected_status,
            'status_labels' => $this->order_status_labels(),
        ], $this);
    }

    public function extensions() {
        $extension_catalog = new ExtensionCatalog();
        $catalog = $extension_catalog->catalog();
        $server_url = ExtensionServer::base_url();
        $server_public_url = ExtensionServer::public_url(null, $server_url);
        $server_mode = ExtensionServer::mode();
        $installed = is_array($catalog['installed'] ?? null) ? $catalog['installed'] : [];
        $available = is_array($catalog['available'] ?? null) ? $catalog['available'] : [];
        $visible_items = array_merge($installed, $available);
        $stats = [
            'installed' => count($installed),
            'active' => count(array_filter($installed, static function ($item) {
                return !empty($item['active']);
            })),
            'updates' => count(array_filter($installed, static function ($item) {
                return !empty($item['update_available']);
            })),
            'premium' => count(array_filter($visible_items, static function ($item) {
                return !empty($item['license_required']);
            })),
            'free' => count(array_filter($visible_items, static function ($item) {
                return empty($item['license_required']);
            })),
        ];

        Template::display('admin/pages/extensions.php', [
            'catalog' => $catalog,
            'external_plugin_delivery' => Distribution::allows_external_plugin_delivery(),
            'external_catalog_url' => Distribution::extension_catalog_url(),
            'remote_catalog_enabled' => Distribution::allows_remote_catalog(),
            'server_mode' => $server_mode,
            'bundle_notice' => $extension_catalog->bundle_notice(),
            'server_notice' => $extension_catalog->server_notice(),
            'server_public_url' => $server_public_url,
            'server_url' => $server_url,
            'stats' => $stats,
        ], $this);
    }

    public function api_access() {
        $repo = new ApiKeyRepository();
        $governance = new ApiGovernance();
        $created_key = get_transient('mds3_api_key_created_' . get_current_user_id());
        if ($created_key) {
            delete_transient('mds3_api_key_created_' . get_current_user_id());
        }
        $endpoints = $governance->effective_manifest();

        Template::display('admin/pages/api-access.php', [
            'active_keys' => $repo->active(),
            'audit_logs' => $repo->recent_audit_logs(),
            'created_key' => $created_key,
            'endpoints' => $endpoints,
            'levels' => $governance->security_levels(),
            'scope_options' => $this->api_key_scope_options($endpoints),
        ], $this);
    }

    public function docs() {
        $registry = new DocsRegistry();
        $package_slug = sanitize_key(wp_unslash($_GET['package'] ?? ''));
        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $doc_key = sanitize_text_field(wp_unslash($_GET['doc'] ?? ''));
        $refresh_status = sanitize_key(wp_unslash($_GET['docs_refresh'] ?? ''));
        $refresh_retry_after = absint(wp_unslash($_GET['retry_after'] ?? 0));
        $selected_doc = $registry->find($doc_key, $package_slug, $search);
        $markdown = $selected_doc ? $registry->read($selected_doc) : '';
        $rendered_doc = $markdown ? $registry->render_markdown($markdown) : '';
        if ($selected_doc && $rendered_doc) {
            $rendered_doc = $registry->strip_leading_title_heading($rendered_doc, (string) ($selected_doc['title'] ?? ''));
            $rendered_doc = $registry->highlight_search_terms($rendered_doc, $search);
        }

        $documents = $registry->documents($package_slug, $search);
        $packages = $registry->packages();
        Template::display('admin/pages/docs.php', [
            'documents' => $documents,
            'docs_registry' => $registry,
            'packages' => $packages,
            'package_slug' => $package_slug,
            'refresh_retry_after' => $refresh_retry_after,
            'refresh_status' => $refresh_status,
            'rendered_doc' => $rendered_doc,
            'search' => $search,
            'selected_doc' => $selected_doc,
            'last_manual_refresh_at' => \MillionDollarScript\V3\Docs\RemoteDocsClient::last_manual_refresh_at(),
            'warnings' => $registry->warnings(),
        ], $this);
    }

    public function imagegrid() {
        Template::display('admin/pages/imagegrid.php', [
            'docs_url' => add_query_arg([
                'page' => 'mds3-docs',
                'package' => 'mds-imagegrid',
                'doc' => 'usage',
            ], admin_url('admin.php')),
            'rendering_settings_url' => admin_url('admin.php?page=mds3-settings&tab=rendering'),
        ], $this);
    }

    public function migration() {
        $grid_enabled = $this->grid_enabled();
        $source_prefix = $this->sanitize_source_prefix(wp_unslash($_GET['source_prefix'] ?? ''));
        $report = (new DryRun())->report($source_prefix);
        $latest = $this->latest_migration_run($report['source_prefix'] ?? '');

        Template::display('admin/pages/migration.php', [
            'grid_enabled' => $grid_enabled,
            'latest' => $latest,
            'legacy_plugins' => LegacyPlugin::detected_plugins(),
            'report' => $report,
            'source_prefix' => $source_prefix,
        ], $this);
    }

    public function settings() {
        $settings = wp_parse_args(is_array(get_option('mds3_settings', [])) ? get_option('mds3_settings', []) : [], SettingsSchema::defaults());
        $settings = Currency::settings_with_effective_values($settings);
        $provider_currency_locked = Currency::provider_locks_currency($settings);
        $groups = $this->settings_groups();
        $tabs = array_keys($groups);
        $extra_tabs = [
            'upgrade' => __('Upgrade Compatibility', 'million-dollar-script'),
            'settings-transfer' => __('Import / Export', 'million-dollar-script'),
        ];
        $extra_tabs = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/settings/tabs', $extra_tabs, $settings);
        $active_tab = $this->settings_active_tab($groups, $extra_tabs);

        Template::display('admin/pages/settings.php', [
            'active_tab' => $active_tab,
            'extra_tabs' => $extra_tabs,
            'cleanup_extensions' => \MillionDollarScript\Extensions\CleanupPolicy::registered(),
            'grid_enabled' => $this->grid_enabled(),
            'groups' => $groups,
            'hidden_compatibility_fields' => SettingsSchema::hidden_admin_fields(),
            'provider_currency_locked' => $provider_currency_locked,
            'settings_import_preview' => (new SettingsTransfer())->preview_for_user(),
            'settings' => $settings,
            'tabs' => $tabs,
        ], $this);
    }

    public function system_status() {
        $settings = wp_parse_args(is_array(get_option('mds3_settings', [])) ? get_option('mds3_settings', []) : [], SettingsSchema::defaults());

        Template::display('admin/pages/system-status.php', [
            'grid_capacity_status' => GridCapacityStatus::status(),
            'network_diagnostics' => \MillionDollarScript\V3\Support\NetworkDiagnostics::latest(),
            'settings' => $settings,
        ], $this);
    }

    private function settings_active_tab(array $groups, array $extra_tabs) {
        $available = [];
        foreach (array_keys($groups) as $group) {
            $slug = sanitize_key(sanitize_title((string) $group));
            $tab_id = 'settings-' . $slug;
            $available[$tab_id] = $tab_id;
            $available[$slug] = $tab_id;
        }

        foreach (array_keys($extra_tabs) as $tab_id) {
            $tab_id = sanitize_key((string) $tab_id);
            if ($tab_id) {
                $available[$tab_id] = $tab_id;
            }
        }

        $requested = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        if ($requested && isset($available[$requested])) {
            return $available[$requested];
        }

        $first = reset($available);

        return is_string($first) ? $first : '';
    }
}
