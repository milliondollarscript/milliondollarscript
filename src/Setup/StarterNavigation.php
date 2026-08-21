<?php
/**
 * Starter navigation creation that preserves existing theme choices.
 *
 * @package MillionDollarScript\V3\Setup
 */

namespace MillionDollarScript\V3\Setup;

if (!defined('ABSPATH')) {
    exit;
}

final class StarterNavigation {

    /**
     * @return array<string,mixed>
     */
    public function ensure(array $pages, array $state, array &$result) {
        $navigation = is_array($state['navigation'] ?? null) ? $state['navigation'] : [];
        if (function_exists('wp_is_block_theme') && wp_is_block_theme() && post_type_exists('wp_navigation')) {
            return $this->ensure_block_navigation($pages, $navigation, $result);
        }

        return $this->ensure_classic_navigation($pages, $navigation, $result);
    }

    public function exists(array $navigation) {
        $id = absint($navigation['id'] ?? 0);
        if (!$id) {
            return false;
        }
        if ('block' === ($navigation['type'] ?? '')) {
            return 'wp_navigation' === get_post_type($id) && 'trash' !== get_post_status($id);
        }
        if ('classic' === ($navigation['type'] ?? '')) {
            return (bool) wp_get_nav_menu_object($id);
        }
        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private function ensure_block_navigation(array $pages, array $navigation, array &$result) {
        $post_id = 'block' === ($navigation['type'] ?? '') ? absint($navigation['id'] ?? 0) : 0;
        if ($post_id && 'wp_navigation' === get_post_type($post_id) && 'trash' !== get_post_status($post_id)) {
            $result['reused']['navigation'] = $post_id;
            return $navigation;
        }

        // A navigation block without an explicit ref uses the most recently
        // published wp_navigation post. Creating another one could silently
        // change an existing block-theme header, so preserve that navigation.
        $existing_navigation = get_posts([
            'fields' => 'ids',
            'no_found_rows' => true,
            'order' => 'DESC',
            'orderby' => 'date ID',
            'post_status' => 'publish',
            'post_type' => 'wp_navigation',
            'posts_per_page' => 1,
        ]);
        if ($existing_navigation) {
            $post_id = absint($existing_navigation[0]);
            $result['reused']['navigation'] = $post_id;
            return ['id' => $post_id, 'preserved' => true, 'type' => 'block'];
        }

        $content = '';
        foreach ($this->navigation_items($pages) as $item) {
            $content .= serialize_block([
                'attrs' => [
                    'id' => $item['post_id'],
                    'kind' => 'post-type',
                    'label' => $item['label'],
                    'type' => 'page',
                    'url' => get_permalink($item['post_id']),
                ],
                'blockName' => 'core/navigation-link',
                'innerBlocks' => [],
                'innerContent' => [],
                'innerHTML' => '',
            ]);
        }

        $post_id = wp_insert_post([
            'post_content' => $content,
            'post_status' => 'publish',
            'post_title' => __('Million Dollar Script Starter', 'million-dollar-script'),
            'post_type' => 'wp_navigation',
        ], true);
        if (is_wp_error($post_id)) {
            $result['errors'][] = $post_id->get_error_message();
            return [];
        }

        $post_id = absint($post_id);
        update_post_meta($post_id, '_million_dollar_script_starter_navigation', 'yes');
        $result['created']['navigation'] = $post_id;

        return ['id' => $post_id, 'type' => 'block'];
    }

    /**
     * @return array<string,mixed>
     */
    private function ensure_classic_navigation(array $pages, array $navigation, array &$result) {
        if (!function_exists('wp_create_nav_menu')) {
            require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
        }

        $menu = 'classic' === ($navigation['type'] ?? '') ? wp_get_nav_menu_object(absint($navigation['id'] ?? 0)) : false;
        if (!$menu) {
            $name = __('Million Dollar Script Starter', 'million-dollar-script');
            $suffix = 1;
            while (wp_get_nav_menu_object($name)) {
                $suffix++;
                $name = sprintf(
                    /* translators: %d: navigation suffix */
                    __('Million Dollar Script Starter %d', 'million-dollar-script'),
                    $suffix
                );
            }

            $menu_id = wp_create_nav_menu($name);
            if (is_wp_error($menu_id)) {
                $result['errors'][] = $menu_id->get_error_message();
                return [];
            }
            $menu = wp_get_nav_menu_object(absint($menu_id));
            update_term_meta(absint($menu_id), '_million_dollar_script_starter_navigation', 'yes');
            $result['created']['navigation'] = absint($menu_id);
        } else {
            $result['reused']['navigation'] = absint($menu->term_id);
        }

        $menu_id = absint($menu->term_id);
        $existing_keys = [];
        foreach ((array) wp_get_nav_menu_items($menu_id, ['post_status' => 'any']) as $menu_item) {
            $key = sanitize_key((string) get_post_meta($menu_item->ID, '_million_dollar_script_starter_item', true));
            if ($key) {
                $existing_keys[$key] = absint($menu_item->ID);
            }
        }

        foreach ($this->navigation_items($pages) as $position => $item) {
            if (!empty($existing_keys[$item['key']])) {
                continue;
            }
            $item_id = wp_update_nav_menu_item($menu_id, 0, [
                'menu-item-object' => 'page',
                'menu-item-object-id' => $item['post_id'],
                'menu-item-position' => $position + 1,
                'menu-item-status' => 'publish',
                'menu-item-title' => $item['label'],
                'menu-item-type' => 'post_type',
            ]);
            if (is_wp_error($item_id)) {
                $result['errors'][] = $item_id->get_error_message();
                continue;
            }
            update_post_meta(absint($item_id), '_million_dollar_script_starter_item', $item['key']);
        }

        $location = sanitize_key((string) ($navigation['location'] ?? ''));
        if (!$location) {
            $location = $this->assign_available_menu_location($menu_id);
        }

        return array_filter(['id' => $menu_id, 'location' => $location, 'type' => 'classic']);
    }

    private function assign_available_menu_location($menu_id) {
        $registered = get_registered_nav_menus();
        $locations = get_theme_mod('nav_menu_locations', []);
        $locations = is_array($locations) ? $locations : [];
        $preferred = ['primary', 'menu-1', 'header', 'main'];

        foreach (array_unique(array_merge($preferred, array_keys($registered))) as $location) {
            if (!isset($registered[$location]) || !empty($locations[$location])) {
                continue;
            }
            $locations[$location] = absint($menu_id);
            set_theme_mod('nav_menu_locations', $locations);
            return sanitize_key($location);
        }

        return '';
    }

    /**
     * @return array<int,array{key:string,label:string,post_id:int}>
     */
    private function navigation_items(array $pages) {
        $labels = [
            'home' => __('Home', 'million-dollar-script'),
            'blog' => __('Blog', 'million-dollar-script'),
            'order' => __('Order Pixels', 'million-dollar-script'),
            'manage' => __('Manage Pixels', 'million-dollar-script'),
            'contact' => __('Contact', 'million-dollar-script'),
            'about' => __('About', 'million-dollar-script'),
        ];
        $items = [];
        foreach ($labels as $key => $label) {
            $post_id = absint($pages[$key] ?? 0);
            if ($this->valid_page($post_id)) {
                $items[] = ['key' => $key, 'label' => $label, 'post_id' => $post_id];
            }
        }
        return $items;
    }

    private function valid_page($post_id) {
        return $post_id > 0 && 'page' === get_post_type($post_id) && false !== get_post_status($post_id) && 'trash' !== get_post_status($post_id);
    }
}
