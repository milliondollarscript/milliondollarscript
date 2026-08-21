<?php
/**
 * Admin sidebar and toolbar navigation helpers.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Extensions\ExtensionCatalog;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersAdminNavigation {

    public function admin_bar_menu($admin_bar) {
        if (!is_admin_bar_showing() || !current_user_can('manage_options') || !is_object($admin_bar) || !method_exists($admin_bar, 'add_node')) {
            return;
        }

        $root_id = 'mds3-admin-bar';
        $admin_bar->add_node([
            'id' => $root_id,
            'title' => __('MDS', 'million-dollar-script'),
            'href' => admin_url('admin.php?page=mds3'),
            'meta' => [
                'title' => __('Million Dollar Script', 'million-dollar-script'),
            ],
        ]);

        foreach ($this->admin_bar_core_items() as $item) {
            $this->admin_bar_add_item($admin_bar, $root_id, $item);
        }

        $extensions_id = $root_id . '-extensions';
        $admin_bar->add_node([
            'id' => $extensions_id,
            'parent' => $root_id,
            'title' => __('Extensions', 'million-dollar-script'),
            'href' => admin_url('admin.php?page=mds3-extensions'),
        ]);
        $admin_bar->add_node([
            'id' => $extensions_id . '-catalog',
            'parent' => $extensions_id,
            'title' => __('Open extensions', 'million-dollar-script'),
            'href' => admin_url('admin.php?page=mds3-extensions'),
        ]);

        foreach ($this->admin_bar_extension_items() as $item) {
            $this->admin_bar_add_item($admin_bar, $extensions_id, $item);
        }
    }

    private function admin_bar_core_items() {
        $items = [
            [
                'id' => 'dashboard',
                'title' => __('Dashboard', 'million-dollar-script'),
                'href' => admin_url('admin.php?page=mds3'),
            ],
        ];

        if ($this->grid_enabled()) {
            $items[] = [
                'id' => 'grids',
                'title' => __('Grids', 'million-dollar-script'),
                'href' => admin_url('admin.php?page=mds3-grids'),
            ];
            $items[] = [
                'id' => 'orders',
                'title' => __('Orders', 'million-dollar-script'),
                'href' => admin_url('admin.php?page=mds3-orders'),
            ];
            $items[] = [
                'id' => 'new-grid',
                'parent' => 'grids',
                'title' => __('New grid', 'million-dollar-script'),
                'href' => admin_url('admin.php?page=mds3-grids'),
            ];
        }

        $items[] = [
            'id' => 'settings',
            'title' => __('Settings', 'million-dollar-script'),
            'href' => admin_url('admin.php?page=mds3-settings'),
        ];
        $items[] = [
            'id' => 'api',
            'title' => __('API Access', 'million-dollar-script'),
            'href' => admin_url('admin.php?page=mds3-api'),
        ];
        $items[] = [
            'id' => 'docs',
            'title' => __('Documentation', 'million-dollar-script'),
            'href' => admin_url('admin.php?page=mds3-docs'),
        ];
        $items[] = [
            'id' => 'setup',
            'title' => __('Setup', 'million-dollar-script'),
            'href' => admin_url('admin.php?page=mds3-setup'),
        ];
        $items[] = [
            'id' => 'status',
            'title' => __('System Status', 'million-dollar-script'),
            'href' => admin_url('admin.php?page=mds3-system-status'),
        ];

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/bar/core/items', $items);
    }

    private function admin_bar_extension_items() {
        $catalog = [
            'installed' => (new ExtensionCatalog())->installed(),
            'available' => [],
        ];
        $cards = [];
        foreach ((array) ($catalog['installed'] ?? []) as $item) {
            if (!is_array($item) || !empty($item['bundled']) || 'core' === (string) ($item['source'] ?? '')) {
                continue;
            }

            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            if (!$slug || empty($item['active'])) {
                continue;
            }

            $cards[$slug] = [
                'title' => $this->admin_navigation_extension_name((string) ($item['name'] ?? ''), $slug),
                'href' => add_query_arg([
                    'page' => 'mds3-extensions',
                    'extension' => $slug,
                ], admin_url('admin.php')),
            ];
        }

        foreach ($this->admin_navigation_onboarding_items(array_keys($cards), $catalog) as $item) {
            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            if (!$slug || empty($cards[$slug])) {
                continue;
            }

            if (!empty($item['name'])) {
                $cards[$slug]['title'] = $this->admin_navigation_extension_name((string) $item['name'], $slug);
            }

            foreach ((array) ($item['actions'] ?? []) as $action) {
                if (!is_array($action) || empty($action['url'])) {
                    continue;
                }

                $cards[$slug]['href'] = (string) $action['url'];
                break;
            }
        }

        /**
         * Filters extension links shown below the MDS admin-bar Extensions menu.
         *
         * @param array $cards   Associative array keyed by extension slug.
         * @param array $catalog Extension catalog data.
         */
        $cards = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/bar/extension/items', $cards, $catalog);
        if (!is_array($cards)) {
            return [];
        }

        uasort($cards, static function ($a, $b) {
            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return $cards;
    }

    private function admin_navigation_onboarding_items(array $selected_slugs, array $catalog) {
        $selected_slugs = array_values(array_filter(array_map('sanitize_key', $selected_slugs)));
        if (!$selected_slugs) {
            return [];
        }

        $items = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/extension/onboarding/items', [], [
            'selected_slugs' => $selected_slugs,
            'catalog' => $catalog,
        ]);
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = sanitize_key((string) ($item['slug'] ?? (is_string($key) ? $key : '')));
            if (!$slug || !in_array($slug, $selected_slugs, true)) {
                continue;
            }

            $actions = [];
            foreach ((array) ($item['actions'] ?? []) as $action) {
                if (!is_array($action) || empty($action['url'])) {
                    continue;
                }

                $actions[] = [
                    'label' => sanitize_text_field((string) ($action['label'] ?? '')),
                    'url' => esc_url_raw((string) $action['url']),
                    'primary' => !empty($action['primary']),
                ];
            }

            $normalized[] = [
                'slug' => $slug,
                'name' => sanitize_text_field((string) ($item['name'] ?? $slug)),
                'actions' => $actions,
                'priority' => absint($item['priority'] ?? 50),
            ];
        }

        usort($normalized, static function ($a, $b) {
            $priority = (int) ($a['priority'] ?? 50) <=> (int) ($b['priority'] ?? 50);
            if (0 !== $priority) {
                return $priority;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $normalized;
    }

    private function admin_bar_add_item($admin_bar, $parent_id, array $item) {
        $href = esc_url_raw((string) ($item['href'] ?? ''));
        $title = sanitize_text_field((string) ($item['title'] ?? ''));
        if (!$href || !$title) {
            return;
        }

        $id = 'mds3-admin-bar-' . sanitize_key((string) ($item['id'] ?? md5($href)));
        $item_parent = sanitize_key((string) ($item['parent'] ?? ''));
        $node_parent = $item_parent ? 'mds3-admin-bar-' . $item_parent : $parent_id;

        $admin_bar->add_node([
            'id' => $id,
            'parent' => $node_parent,
            'title' => $title,
            'href' => $href,
        ]);
    }

    private function admin_navigation_extension_name($name, $slug) {
        $name = trim((string) preg_replace('/^Million Dollar Script(?:\s*-\s*|\s+)/i', '', (string) $name));
        if ('' !== $name) {
            return $name;
        }

        $slug = preg_replace('/^mds-/', '', sanitize_key((string) $slug));

        return ucwords(str_replace('-', ' ', (string) $slug));
    }

    private function sidebar_core_pages() {
        $pages = [
            'mds3',
            'mds3-grids',
            'mds3-orders',
            'mds3-extensions',
            'mds3-docs',
            'mds3-api',
            'mds3-settings',
        ];

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/sidebar/core/pages', $pages);
    }

    private function is_mds_admin_screen($hook = '') {
        $hook = (string) $hook;
        if (false !== strpos($hook, 'mds3') || false !== strpos($hook, 'million-dollar-script_page_mds')) {
            return true;
        }

        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        if (!$page) {
            return false;
        }
        if (0 === strpos($page, 'mds3') || in_array($page, $this->sidebar_core_pages(), true)) {
            return true;
        }

        global $submenu;
        foreach ((array) ($submenu['mds3'] ?? []) as $item) {
            if ($page === sanitize_key((string) ($item[2] ?? ''))) {
                return true;
            }
        }

        return false;
    }
}
