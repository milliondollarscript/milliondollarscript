<?php
/**
 * Dashboard metrics, navigation, service, and status panels.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Docs\DocsRegistry;
use MillionDollarScript\V3\Extensions\ExtensionLicenseManager;
use MillionDollarScript\V3\Extensions\ExtensionOnboarding;
use MillionDollarScript\V3\Extensions\ExtensionServer;
use MillionDollarScript\V3\Support\DB;
use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait RendersDashboardPanels {

    private function dashboard_hero_placements() {
        return [
            ['x' => 5, 'y' => 12, 'w' => 17, 'h' => 12, 'delay' => -0.4, 'state' => 'sold'],
            ['x' => 27, 'y' => 12, 'w' => 10, 'h' => 12, 'delay' => -1.8, 'state' => 'reserved'],
            ['x' => 42, 'y' => 12, 'w' => 18, 'h' => 12, 'delay' => -3.2, 'state' => 'available'],
            ['x' => 65, 'y' => 12, 'w' => 12, 'h' => 12, 'delay' => -2.6, 'state' => 'sold'],
            ['x' => 82, 'y' => 12, 'w' => 13, 'h' => 12, 'delay' => -1.1, 'state' => 'available'],
            ['x' => 10, 'y' => 34, 'w' => 18, 'h' => 14, 'delay' => -2.2, 'state' => 'available'],
            ['x' => 34, 'y' => 32, 'w' => 11, 'h' => 18, 'delay' => -0.7, 'state' => 'sold'],
            ['x' => 50, 'y' => 36, 'w' => 14, 'h' => 11, 'delay' => -3.6, 'state' => 'reserved'],
            ['x' => 69, 'y' => 34, 'w' => 20, 'h' => 14, 'delay' => -1.5, 'state' => 'sold'],
            ['x' => 5, 'y' => 61, 'w' => 12, 'h' => 17, 'delay' => -3.1, 'state' => 'reserved'],
            ['x' => 23, 'y' => 61, 'w' => 17, 'h' => 11, 'delay' => -1.2, 'state' => 'sold'],
            ['x' => 47, 'y' => 60, 'w' => 22, 'h' => 16, 'delay' => -2.8, 'state' => 'available'],
            ['x' => 75, 'y' => 62, 'w' => 12, 'h' => 14, 'delay' => -0.2, 'state' => 'sold'],
            ['x' => 91, 'y' => 61, 'w' => 5, 'h' => 16, 'delay' => -2.0, 'state' => 'available'],
        ];
    }

    private function dashboard_menu() {
        $server_url = rtrim((string) (ExtensionServer::public_url() ?: ExtensionServer::base_url() ?: 'https://milliondollarscript.com'), '/');
        $manage_items = [
            ['label' => __('Settings', 'million-dollar-script'), 'url' => admin_url('admin.php?page=mds3-settings'), 'icon' => 'dashicons-admin-generic'],
            ['label' => __('System Status', 'million-dollar-script'), 'url' => admin_url('admin.php?page=mds3-system-status'), 'icon' => 'dashicons-heart'],
        ];
        if ($this->grid_enabled()) {
            array_unshift(
                $manage_items,
                ['label' => __('Orders', 'million-dollar-script'), 'url' => admin_url('admin.php?page=mds3-orders'), 'icon' => 'dashicons-cart']
            );
            array_unshift(
                $manage_items,
                ['label' => __('Grids', 'million-dollar-script'), 'url' => admin_url('admin.php?page=mds3-grids'), 'icon' => 'dashicons-grid-view']
            );
        }

        $launch_items = [
            ['label' => __('Setup', 'million-dollar-script'), 'url' => admin_url('admin.php?page=mds3-setup'), 'icon' => 'dashicons-admin-tools'],
            ['label' => __('View site', 'million-dollar-script'), 'url' => home_url('/'), 'icon' => 'dashicons-external', 'target' => '_blank'],
        ];
        if ($this->grid_enabled()) {
            array_splice($launch_items, 1, 0, [
                ['label' => __('Migration', 'million-dollar-script'), 'url' => admin_url('admin.php?page=mds3-migration'), 'icon' => 'dashicons-migrate'],
            ]);
        }

        $groups = [
            'manage' => [
                'label' => __('Manage', 'million-dollar-script'),
                'items' => $manage_items,
            ],
            'launch' => [
                'label' => __('Launch', 'million-dollar-script'),
                'items' => $launch_items,
            ],
            'extend' => [
                'label' => __('Extend', 'million-dollar-script'),
                'items' => [
                    ['label' => __('Extensions', 'million-dollar-script'), 'url' => admin_url('admin.php?page=mds3-extensions'), 'icon' => 'dashicons-admin-plugins'],
                    ['label' => __('API Access', 'million-dollar-script'), 'url' => admin_url('admin.php?page=mds3-api'), 'icon' => 'dashicons-rest-api'],
                    ['label' => __('Documentation', 'million-dollar-script'), 'url' => admin_url('admin.php?page=mds3-docs'), 'icon' => 'dashicons-book'],
                    ['label' => __('Changelog', 'million-dollar-script'), 'url' => $server_url . '/changelog', 'icon' => 'dashicons-list-view', 'target' => '_blank'],
                ],
            ],
        ];

        $groups = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/dashboard/menu/items', $groups);
        if (!is_array($groups) || !$groups) {
            return;
        }

        Template::display('admin/partials/dashboard-menu.php', [
            'groups' => $groups,
        ], $this);
    }

    private function dashboard_metric($label, $value, $description, $url = '') {
        Template::display('admin/partials/dashboard-metric.php', [
            'description' => $description,
            'label' => $label,
            'url' => $url,
            'value' => $value,
        ], $this);
    }

    private function dashboard_recent_orders(array $orders) {
        Template::display('admin/partials/dashboard-recent-orders.php', [
            'orders' => $orders,
        ], $this);
    }

    private function dashboard_service_cards() {
        $cards = [
            [
                'title' => __('Premium Installation Service', 'million-dollar-script'),
                'description' => __('Get help with complete plugin setup, page creation, checkout configuration, and launch checks.', 'million-dollar-script'),
                'url' => 'https://milliondollarscript.com/install-service/',
                'label' => __('Learn more', 'million-dollar-script'),
                'external' => true,
            ],
            [
                'title' => __('Custom Development Services', 'million-dollar-script'),
                'description' => __('Commission custom features, integrations, data migrations, or extension work for a specific site.', 'million-dollar-script'),
                'url' => 'https://milliondollarscript.com/order-custom-development-services/',
                'label' => __('Request custom work', 'million-dollar-script'),
                'external' => true,
            ],
            [
                'title' => __('Reliable Hosting', 'million-dollar-script'),
                'description' => __('Hostinger offers fast, affordable hosting suitable for getting a Million Dollar Script grid online.', 'million-dollar-script'),
                'url' => 'https://hostinger.com?REFERRALCODE=MILLIONDOLLARS',
                'label' => __('Check hosting deals', 'million-dollar-script'),
                'external' => true,
            ],
        ];

        $cards = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/dashboard/service/cards', $cards);
        Template::display('admin/partials/dashboard-service-cards.php', [
            'cards' => is_array($cards) ? $cards : [],
        ], $this);
    }

    private function dashboard_extension_cards(array $catalog) {
        $cards = [];
        $docs_registry = new DocsRegistry();
        $docs_packages = $docs_registry->packages();
        $claim_context = $this->dashboard_extension_claim_context();
        $license_manager = new ExtensionLicenseManager();

        foreach ((array) ($catalog['installed'] ?? []) as $item) {
            if (!is_array($item) || !empty($item['bundled']) || 'core' === (string) ($item['source'] ?? '')) {
                continue;
            }

            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            if (!$slug) {
                continue;
            }

            $name = $this->dashboard_extension_display_name((string) ($item['name'] ?? ''), $slug);
            $actions = [];
            if (empty($item['active'])) {
                $actions[] = [
                    'label' => __('Activate in Extensions', 'million-dollar-script'),
                    'url' => add_query_arg([
                        'page' => 'mds3-extensions',
                        'extension' => $slug,
                    ], admin_url('admin.php')),
                    'primary' => true,
                    'icon' => 'dashicons-yes-alt',
                ];
            }

            if (
                $claim_context
                && $slug === (string) ($claim_context['slug'] ?? '')
                && !empty($item['license_required'])
                && !$license_manager->is_active($slug)
            ) {
                array_unshift($actions, [
                    'label' => __('Claim license', 'million-dollar-script'),
                    'post_action' => 'mds3_claim_extension_license',
                    'nonce_action' => 'mds3_claim_extension_license_' . $slug,
                    'data' => [
                        'slug' => $slug,
                        'claim_token' => (string) ($claim_context['claim_token'] ?? ''),
                    ],
                    'primary' => true,
                    'icon' => 'dashicons-awards',
                ]);
            }

            $cards[$slug] = [
                'slug' => $slug,
                'name' => $name,
                'description' => (string) ($item['description'] ?? ''),
                'version' => (string) ($item['version'] ?? ''),
                'active' => !empty($item['active']),
                'installed' => !empty($item['installed']),
                'license_required' => !empty($item['license_required']),
                'update_available' => !empty($item['update_available']),
                'actions' => $actions,
                'visual' => $this->extension_visual_metadata($item, $name, $slug),
            ];
        }

        $cards = $this->dashboard_apply_extension_onboarding_actions($cards);

        /**
         * Filters extension quick-access cards on the dashboard.
         *
         * Extensions may update their own card, add valid admin actions, or add
         * a card when they are not represented by the extension catalog.
         *
         * @param array $cards   Associative array of cards keyed by extension slug.
         * @param array $catalog Extension catalog data.
         * @param mixed $admin   Current admin controller.
         */
        $cards = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/dashboard/extension/cards', $cards, $catalog, $this);
        $cards = $this->dashboard_add_extension_docs_actions(
            is_array($cards) ? $cards : [],
            $docs_registry,
            $docs_packages
        );
        $cards = $this->dashboard_normalize_extension_cards(is_array($cards) ? $cards : []);
        $active_count = count(array_filter($cards, static function ($card) {
            return !empty($card['active']);
        }));

        Template::display('admin/partials/dashboard-extension-cards.php', [
            'active_count' => $active_count,
            'available_count' => count((array) ($catalog['available'] ?? [])),
            'cards' => $cards,
            'installed_count' => count($cards),
        ], $this);
    }

    private function dashboard_extension_display_name($name, $slug) {
        $name = trim((string) $name);
        $name = trim((string) preg_replace('/^Million Dollar Script(?:\s*-\s*|\s+)/i', '', $name));
        if ('' !== $name) {
            return $name;
        }

        $slug = preg_replace('/^mds-/', '', sanitize_key((string) $slug));

        return ucwords(str_replace('-', ' ', (string) $slug));
    }

    private function dashboard_extension_docs_url($slug, DocsRegistry $registry, array $packages) {
        $slug = sanitize_key((string) $slug);
        if (!$slug || empty($packages[$slug]) || empty($packages[$slug]['docs']) || !is_array($packages[$slug]['docs'])) {
            return '';
        }

        return $registry->package_url($slug);
    }

    private function dashboard_add_extension_docs_actions(array $cards, DocsRegistry $registry, array $packages) {
        foreach ($cards as $key => $card) {
            if (!is_array($card)) {
                continue;
            }

            $slug = sanitize_key((string) ($card['slug'] ?? $key));
            $docs_url = $this->dashboard_extension_docs_url($slug, $registry, $packages);
            if (!$docs_url) {
                continue;
            }

            $actions = array_values(array_filter((array) ($card['actions'] ?? []), static function ($action) {
                if (!is_array($action)) {
                    return false;
                }

                $label = strtolower(trim(wp_strip_all_tags((string) ($action['label'] ?? ''))));
                $url = (string) ($action['url'] ?? '');

                return !in_array($label, ['docs', 'documentation'], true)
                    && !str_contains($url, 'page=mds3-docs');
            }));
            $actions[] = [
                'label' => __('Docs', 'million-dollar-script'),
                'url' => $docs_url,
                'icon' => 'dashicons-book',
            ];
            $cards[$key]['actions'] = $actions;
        }

        return $cards;
    }

    private function dashboard_extension_claim_context() {
        $claim_token = sanitize_text_field(wp_unslash($_GET['claimToken'] ?? $_GET['claim'] ?? ''));
        $slug = sanitize_key(wp_unslash($_GET['extension'] ?? $_GET['slug'] ?? ''));
        if (!$claim_token || !$slug) {
            return [];
        }

        return [
            'claim_token' => $claim_token,
            'slug' => $slug,
        ];
    }

    private function dashboard_apply_extension_onboarding_actions(array $cards) {
        if (!$cards) {
            return $cards;
        }

        $items = (new ExtensionOnboarding())->items(array_keys($cards));
        foreach ($items as $item) {
            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            if (!$slug || empty($cards[$slug]) || !is_array($cards[$slug])) {
                continue;
            }

            if (!empty($item['summary'])) {
                $cards[$slug]['description'] = (string) $item['summary'];
            }
            if (!empty($item['name'])) {
                $cards[$slug]['name'] = (string) $item['name'];
            }

            $actions = [];
            foreach ((array) ($item['actions'] ?? []) as $action) {
                if (!is_array($action) || empty($action['url']) || empty($action['label'])) {
                    continue;
                }

                $actions[] = [
                    'label' => (string) $action['label'],
                    'url' => (string) $action['url'],
                    'primary' => !empty($action['primary']),
                    'icon' => !empty($action['primary']) ? 'dashicons-admin-generic' : 'dashicons-arrow-right-alt2',
                ];
            }

            if ($actions) {
                $cards[$slug]['actions'] = array_merge($actions, (array) ($cards[$slug]['actions'] ?? []));
            }
        }

        return $cards;
    }

    private function dashboard_normalize_extension_cards(array $cards) {
        $normalized = [];
        foreach ($cards as $key => $card) {
            if (!is_array($card)) {
                continue;
            }

            $slug = sanitize_key((string) ($card['slug'] ?? $key));
            $name = $this->dashboard_extension_display_name((string) ($card['name'] ?? ''), $slug);
            if (!$slug || !$name) {
                continue;
            }

            $actions = [];
            $seen_actions = [];
            foreach ((array) ($card['actions'] ?? []) as $action) {
                if (!is_array($action) || (empty($action['url']) && empty($action['post_action']))) {
                    continue;
                }

                $action_key = strtolower((string) ($action['label'] ?? '')) . '|' . (string) ($action['post_action'] ?? '') . '|' . (string) ($action['url'] ?? '');
                if (isset($seen_actions[$action_key])) {
                    continue;
                }
                $seen_actions[$action_key] = true;

                $actions[] = [
                    'label' => (string) ($action['label'] ?? __('Open', 'million-dollar-script')),
                    'url' => (string) ($action['url'] ?? ''),
                    'post_action' => sanitize_key((string) ($action['post_action'] ?? '')),
                    'nonce_action' => (string) ($action['nonce_action'] ?? ''),
                    'data' => is_array($action['data'] ?? null) ? $action['data'] : [],
                    'primary' => !empty($action['primary']),
                    'external' => !empty($action['external']),
                    'target' => (string) ($action['target'] ?? ''),
                    'icon' => sanitize_html_class((string) ($action['icon'] ?? '')),
                ];
            }

            $normalized[$slug] = [
                'slug' => $slug,
                'name' => $name,
                'description' => (string) ($card['description'] ?? ''),
                'version' => (string) ($card['version'] ?? ''),
                'active' => !empty($card['active']),
                'installed' => !empty($card['installed']),
                'license_required' => !empty($card['license_required']),
                'update_available' => !empty($card['update_available']),
                'actions' => $actions,
                'visual' => $this->extension_visual_metadata($card, $name, $slug),
            ];
        }

        uasort($normalized, static function ($a, $b) {
            if (!empty($a['active']) !== !empty($b['active'])) {
                return !empty($a['active']) ? -1 : 1;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $normalized;
    }

    private function extension_visual_metadata(array $item, $name = '', $slug = '') {
        $slug = sanitize_key((string) ($slug ?: ($item['slug'] ?? '')));
        $name = (string) ($name ?: ($item['name'] ?? $slug));
        $defaults = $this->extension_visual_defaults($slug);
        $icon = $this->extension_visual_icon($item['icon'] ?? $item['admin_icon'] ?? '');
        if (!$icon) {
            $icon = $this->extension_visual_icon($defaults['icon'] ?? '');
        }

        $color = $this->extension_visual_hex($item['accent_color'] ?? $item['color'] ?? '');
        $background = $this->extension_visual_hex($item['icon_background'] ?? '');
        $border = $this->extension_visual_hex($item['icon_border'] ?? '');

        $color = $color ?: $this->extension_visual_hex($defaults['color'] ?? '');
        $background = $background ?: $this->extension_visual_hex($defaults['background'] ?? '');
        $border = $border ?: $this->extension_visual_hex($defaults['border'] ?? '');

        $initial_name = trim(preg_replace('/^Million Dollar Script(?:\s*-\s*|\s+)/i', '', $name));
        $initial = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $initial_name ?: $name), 0, 1)) ?: 'M';
        $visual = [
            'icon' => $icon,
            'initial' => $initial,
            'color' => $color,
            'background' => $background,
            'border' => $border,
        ];

        /**
         * Filters visual metadata used for extension cards and quick-access UI.
         *
         * @param array $visual Normalized icon, initial, color, background, and border values.
         * @param array $item   Extension catalog/card item.
         * @param string $slug  Extension slug.
         */
        $visual = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/visual/metadata', $visual, $item, $slug);

        if (!is_array($visual)) {
            return [
                'icon' => '',
                'initial' => $initial,
                'color' => '',
                'background' => '',
                'border' => '',
            ];
        }

        $visual = [
            'icon' => $this->extension_visual_icon($visual['icon'] ?? ''),
            'initial' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($visual['initial'] ?? $initial)), 0, 2)) ?: $initial,
            'color' => $this->extension_visual_hex($visual['color'] ?? ''),
            'background' => $this->extension_visual_hex($visual['background'] ?? ''),
            'border' => $this->extension_visual_hex($visual['border'] ?? ''),
        ];

        $visual['color'] = $this->extension_visual_readable_color($visual['color'], $visual['background']);

        return $visual;
    }

    private function extension_visual_icon($icon) {
        $icon = sanitize_html_class((string) $icon);
        if ($icon && 0 !== strpos($icon, 'dashicons-')) {
            $icon = 'dashicons-' . $icon;
        }

        return $icon;
    }

    private function extension_visual_style(array $visual) {
        $declarations = [];
        $map = [
            'color' => '--mds3-extension-icon-color',
            'background' => '--mds3-extension-icon-bg',
            'border' => '--mds3-extension-icon-border',
        ];

        foreach ($map as $key => $variable) {
            $color = $this->extension_visual_hex($visual[$key] ?? '');
            if ($color) {
                $declarations[] = $variable . ':' . $color;
            }
        }

        return implode(';', $declarations);
    }

    private function extension_visual_hex($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        if (function_exists('sanitize_hex_color')) {
            return sanitize_hex_color($value) ?: '';
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '';
    }

    private function extension_visual_readable_color($color, $background) {
        $color = $this->extension_visual_hex($color);
        $background = $this->extension_visual_hex($background);
        if (!$color || !$background || $this->extension_visual_contrast_ratio($color, $background) >= 4.5) {
            return $color;
        }

        $dark = '#111827';
        $light = '#ffffff';

        return $this->extension_visual_contrast_ratio($dark, $background) >= $this->extension_visual_contrast_ratio($light, $background)
            ? $dark
            : $light;
    }

    private function extension_visual_contrast_ratio($first, $second) {
        $first_luminance = $this->extension_visual_relative_luminance($first);
        $second_luminance = $this->extension_visual_relative_luminance($second);
        $lighter = max($first_luminance, $second_luminance);
        $darker = min($first_luminance, $second_luminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function extension_visual_relative_luminance($color) {
        $color = ltrim((string) $color, '#');
        $channels = [
            hexdec(substr($color, 0, 2)) / 255,
            hexdec(substr($color, 2, 2)) / 255,
            hexdec(substr($color, 4, 2)) / 255,
        ];

        foreach ($channels as &$channel) {
            $channel = $channel <= 0.04045
                ? $channel / 12.92
                : pow(($channel + 0.055) / 1.055, 2.4);
        }
        unset($channel);

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    private function extension_visual_defaults($slug) {
        $defaults = [
            'mds-grid' => ['icon' => 'dashicons-grid-view', 'color' => '#2563eb', 'background' => '#eff6ff', 'border' => '#bfdbfe'],
            'mds-imagegrid' => ['icon' => 'dashicons-format-gallery', 'color' => '#4f46e5', 'background' => '#eef2ff', 'border' => '#c7d2fe'],
            'mds-sponsorboard' => ['icon' => 'dashicons-megaphone', 'color' => '#b45309', 'background' => '#fff7ed', 'border' => '#fed7aa'],
            'mds-woocommerce' => ['icon' => 'dashicons-cart', 'color' => '#7e22ce', 'background' => '#faf5ff', 'border' => '#e9d5ff'],
            'mds-fields' => ['icon' => 'dashicons-feedback', 'color' => '#9333ea', 'background' => '#faf5ff', 'border' => '#e9d5ff'],
            'mds-contact-form' => ['icon' => 'dashicons-email-alt2', 'color' => '#0369a1', 'background' => '#eff6ff', 'border' => '#bfdbfe'],
            'mds-google-analytics' => ['icon' => 'dashicons-chart-bar', 'color' => '#a16207', 'background' => '#fefce8', 'border' => '#fef08a'],
            'mds-seo-basic' => ['icon' => 'dashicons-search', 'color' => '#15803d', 'background' => '#f0fdf4', 'border' => '#bbf7d0'],
            'mds-launch-wall' => ['icon' => 'dashicons-layout', 'color' => '#be123c', 'background' => '#fff1f2', 'border' => '#fecdd3'],
            'mds-missions' => ['icon' => 'dashicons-flag', 'color' => '#c2410c', 'background' => '#fff7ed', 'border' => '#fed7aa'],
            'mds-time-capsule' => ['icon' => 'dashicons-clock', 'color' => '#4338ca', 'background' => '#eef2ff', 'border' => '#c7d2fe'],
            'mds-bounty-engine' => ['icon' => 'dashicons-awards', 'color' => '#b91c1c', 'background' => '#fef2f2', 'border' => '#fecaca'],
            'mds-local-money-map' => ['icon' => 'dashicons-location-alt', 'color' => '#166534', 'background' => '#f0fdf4', 'border' => '#bbf7d0'],
            'mds-revenue-agent' => ['icon' => 'dashicons-money-alt', 'color' => '#15803d', 'background' => '#f0fdf4', 'border' => '#bbf7d0'],
            'mds-revenue-reports' => ['icon' => 'dashicons-chart-line', 'color' => '#2563eb', 'background' => '#eff6ff', 'border' => '#bfdbfe'],
            'mds-sponsorable-ai-tools' => ['icon' => 'dashicons-superhero', 'color' => '#6d28d9', 'background' => '#f5f3ff', 'border' => '#ddd6fe'],
            'mds-attention-cooperative' => ['icon' => 'dashicons-groups', 'color' => '#1d4ed8', 'background' => '#eff6ff', 'border' => '#bfdbfe'],
            'mds-translation' => ['icon' => 'dashicons-translation', 'color' => '#be185d', 'background' => '#fdf2f8', 'border' => '#fbcfe8'],
            'mds-advertiser-workspace' => ['icon' => 'dashicons-businessperson', 'color' => '#3655b3', 'background' => '#eff6ff', 'border' => '#bfdbfe'],
            'mds-campaign-scheduler' => ['icon' => 'dashicons-calendar-alt', 'color' => '#7c3aed', 'background' => '#f5f3ff', 'border' => '#ddd6fe'],
            'mds-search-visibility' => ['icon' => 'dashicons-visibility', 'color' => '#047857', 'background' => '#ecfdf5', 'border' => '#a7f3d0'],
            'mds-subscriptions' => ['icon' => 'dashicons-update', 'color' => '#0f766e', 'background' => '#f0fdfa', 'border' => '#99f6e4'],
            'mds-support-passport' => ['icon' => 'dashicons-id-alt', 'color' => '#9f1239', 'background' => '#fff1f2', 'border' => '#fecdd3'],
        ];

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/visual/defaults', $defaults[$slug] ?? [
            'icon' => 'dashicons-admin-plugins',
            'color' => '#475569',
            'background' => '#f8fafc',
            'border' => '#cbd5e1',
        ], $slug, $defaults);
    }

    private function dashboard_system_status(array $settings) {
        $memory_status = \MillionDollarScript\V3\Support\MemoryStatus::status();
        $server_url = ExtensionServer::base_url($settings);
        $server_mode = ExtensionServer::mode();
        $grid_enabled = $this->grid_enabled();
        $commerce_status = Payments::active_provider_label($settings);
        if (!$grid_enabled) {
            $commerce_status = sprintf(
                /* translators: %s: payment provider name */
                __('%s available', 'million-dollar-script'),
                Payments::active_provider_label($settings)
            );
        }
        $rows = [
            __('Plugin version', 'million-dollar-script') => defined('MILLION_DOLLAR_SCRIPT_VERSION') ? MILLION_DOLLAR_SCRIPT_VERSION : '',
            __('Database version', 'million-dollar-script') => (string) get_option('mds3_db_version', __('Not installed', 'million-dollar-script')),
            __('WordPress', 'million-dollar-script') => get_bloginfo('version'),
            __('PHP', 'million-dollar-script') => PHP_VERSION,
            __('PHP memory limit', 'million-dollar-script') => (string) $memory_status['effective_label'],
            __('Commerce', 'million-dollar-script') => $commerce_status,
            __('Theme mode', 'million-dollar-script') => ucfirst((string) ($settings['theme_mode'] ?? 'system')),
            __('Extension server', 'million-dollar-script') => $server_url ? $server_mode . ' - ' . $server_url : __('Not configured', 'million-dollar-script'),
            __('Logging', 'million-dollar-script') => 'yes' === (string) ($settings['log-enable'] ?? 'no') ? __('Enabled', 'million-dollar-script') : __('Disabled', 'million-dollar-script'),
        ];

        $rows = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/dashboard/system/status', $rows, $settings);
        Template::display('admin/partials/dashboard-system-status.php', [
            'memory_status' => $memory_status,
            'rows' => is_array($rows) ? $rows : [],
        ], $this);
    }

    private function dashboard_order_counts() {
        global $wpdb;

        $counts = ['paid_total' => 0.0];
        if (!DB::table_exists(DB::table('orders'))) {
            return $counts;
        }

        $rows = $wpdb->get_results('SELECT status, COUNT(*) total, SUM(total) revenue FROM ' . DB::ident(DB::table('orders')) . ' GROUP BY status', ARRAY_A);
        foreach (is_array($rows) ? $rows : [] as $row) {
            $status = sanitize_key((string) ($row['status'] ?? ''));
            if (!$status) {
                continue;
            }
            $counts[$status] = absint($row['total'] ?? 0);
            if (in_array($status, ['paid', 'completed'], true)) {
                $counts['paid_total'] += (float) ($row['revenue'] ?? 0);
            }
        }

        return $counts;
    }

    private function dashboard_money($amount, array $settings) {
        return Currency::format($amount, Currency::current_code($settings), $settings, true);
    }

    private function dashboard_status_label($status) {
        $status = sanitize_key((string) $status);

        return $status ? ucwords(str_replace('_', ' ', $status)) : __('Unknown', 'million-dollar-script');
    }
}
