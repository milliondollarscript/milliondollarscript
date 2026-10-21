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
        $replace = 'replace' === sanitize_key((string) ($state['navigation_mode'] ?? 'keep'));

        if (function_exists('wp_is_block_theme') && wp_is_block_theme() && post_type_exists('wp_navigation')) {
            return $this->ensure_block_navigation($pages, $navigation, $result, $replace);
        }

        return $this->ensure_classic_navigation($pages, $navigation, $result, $replace);
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
     * Where this site's owner edits navigation, in words their theme actually has.
     *
     * A block theme has no Appearance > Menus screen, so pointing a site owner at it sends
     * them looking for something that is not in their admin (llm-task efdcfa60).
     *
     * @return array{label:string,url:string,block:bool}
     */
    public static function editor_hint() {
        if (function_exists('wp_is_block_theme') && wp_is_block_theme() && post_type_exists('wp_navigation')) {
            return array(
                'label' => __('the Site Editor (Appearance > Editor, then Navigation)', 'million-dollar-script'),
                'url' => admin_url('site-editor.php'),
                'block' => true,
            );
        }

        return array(
            'label' => __('Appearance > Menus', 'million-dollar-script'),
            'url' => admin_url('nav-menus.php'),
            'block' => false,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function ensure_block_navigation(array $pages, array $navigation, array &$result, $replace = false) {
        // A navigation block without an explicit ref shows the most recently published
        // wp_navigation post, so that is the one a visitor actually sees. Choosing it is how
        // the wizard reaches the menu the theme renders - creating a second one would build a
        // menu nobody displays.
        $post_id = $this->stored_block_navigation($navigation) ?: $this->latest_published_navigation();

        // Asked to leave navigation alone means exactly that (Ryan, 2026-10-18): no menu is
        // created, no links are added to a menu the site owns, and a menu this step wrote on
        // an earlier run is not rewritten either. The step says what is still missing instead.
        if (!$replace) {
            $result['navigation_untouched'] = true;
            if (!$post_id) {
                return ['untouched' => true];
            }

            return [
                'id' => $post_id,
                'type' => 'block',
                'preserved' => true,
                'untouched' => true,
            ];
        }

        if (!$post_id) {
            $created = $this->create_block_navigation($pages, $result);

            return $created ? ['id' => $created, 'type' => 'block'] : [];
        }

        // Asked to replace, and this navigation belongs to the site: write the starter
        // links over it and take ownership, so a later Repair rewrites the same
        // navigation instead of adding links somewhere else (llm-task efdcfa60).
        if ($replace && 'yes' !== get_post_meta($post_id, '_million_dollar_script_starter_navigation', true)) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $this->build_navigation_content($pages),
            ]);
            update_post_meta($post_id, '_million_dollar_script_starter_navigation', 'yes');
            $result['navigation_replaced'] = true;
            $result['reused']['navigation'] = $post_id;

            return [
                'id' => $post_id,
                'type' => 'block',
            ];
        }

        if ('yes' === get_post_meta($post_id, '_million_dollar_script_starter_navigation', true)) {
            // This navigation is ours. Rewriting its links is what "Repair missing starter
            // items" is for: the pages it points at may have been replaced since, and a menu
            // that still names a deleted page is a dead link on every page of the site.
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $this->build_navigation_content($pages),
            ]);
            $result['reused']['navigation'] = $post_id;

            return ['id' => $post_id, 'type' => 'block'];
        }

        if ($this->lists_pages_automatically($post_id)) {
            // A Page List navigation already shows these pages. Adding links beside it would
            // print each page twice, so it is left as the site built it and the step reports
            // the navigation as needing a look.
            $result['reused']['navigation'] = $post_id;

            return ['id' => $post_id, 'preserved' => true, 'type' => 'block'];
        }

        // The site's own navigation, made of explicit links. Add the missing ones and keep
        // everything already there: replacing this content used to delete a menu the owner
        // had built themselves, with nothing left to point at afterwards.
        // Deliberately not marked as ours. The links this workflow adds are recognised on the
        // next run by the page they point at, so a repeat run adds nothing; marking the whole
        // navigation as ours would let a later run rewrite it and drop the site's own links.
        $added = $this->append_block_links($post_id, $pages, $result);
        if ($added > 0) {
            $result['navigation_note'] = [
                'items' => $added,
                'menu' => (string) get_the_title($post_id),
            ];
        }
        $result['reused']['navigation'] = $post_id;

        return ['id' => $post_id, 'type' => 'block'];
    }

    /**
     * The navigation this workflow created earlier, when it is still there.
     */
    private function stored_block_navigation(array $navigation) {
        $post_id = 'block' === ($navigation['type'] ?? '') ? absint($navigation['id'] ?? 0) : 0;
        if (!$post_id || 'wp_navigation' !== get_post_type($post_id) || 'trash' === get_post_status($post_id)) {
            return 0;
        }

        return $post_id;
    }

    private function latest_published_navigation() {
        $found = get_posts([
            'fields' => 'ids',
            'no_found_rows' => true,
            'order' => 'DESC',
            'orderby' => 'date ID',
            'post_status' => 'publish',
            'post_type' => 'wp_navigation',
            'posts_per_page' => 1,
        ]);

        return $found ? absint($found[0]) : 0;
    }

    /**
     * @param array<string,int> $pages
     */
    private function create_block_navigation(array $pages, array &$result) {
        $post_id = wp_insert_post([
            'post_content' => $this->build_navigation_content($pages),
            'post_status' => 'publish',
            'post_title' => __('Million Dollar Script Starter', 'million-dollar-script'),
            'post_type' => 'wp_navigation',
        ], true);
        if (is_wp_error($post_id)) {
            $result['errors'][] = $post_id->get_error_message();

            return 0;
        }

        $post_id = absint($post_id);
        update_post_meta($post_id, '_million_dollar_script_starter_navigation', 'yes');
        $result['created']['navigation'] = $post_id;

        return $post_id;
    }

    /**
     * True when a navigation renders its links from the site's pages by itself.
     */
    private function lists_pages_automatically($post_id) {
        foreach (parse_blocks((string) get_post_field('post_content', $post_id)) as $block) {
            if ($this->block_named($block, 'core/page-list')) {
                return true;
            }
        }

        return false;
    }

    private function block_named(array $block, $name) {
        if ((string) ($block['blockName'] ?? '') === $name) {
            return true;
        }
        foreach ((array) ($block['innerBlocks'] ?? []) as $inner) {
            if (is_array($inner) && $this->block_named($inner, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add the starter links this navigation is missing, in place among the links the site
     * wrote. Returns the number added.
     *
     * @param array<string,int> $pages
     */
    private function append_block_links($post_id, array $pages, array &$result) {
        $blocks = parse_blocks((string) get_post_field('post_content', $post_id));
        $linked = $this->linked_page_ids($blocks);
        $missing = [];
        foreach ($this->navigation_items($pages) as $item) {
            if (!isset($linked[$item['post_id']])) {
                $missing[] = $this->navigation_link_block($item);
            }
        }
        if (!$missing) {
            return 0;
        }

        $updated = wp_update_post([
            'ID' => $post_id,
            'post_content' => serialize_blocks($this->place_links($blocks, $missing)),
        ], true);
        if (is_wp_error($updated)) {
            $result['errors'][] = $updated->get_error_message();

            return 0;
        }

        return count($missing);
    }

    /**
     * Page IDs a navigation already links to, so a repeat run does not add a second copy.
     */
    private function linked_page_ids(array $blocks) {
        $ids = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            if ('core/navigation-link' === (string) ($block['blockName'] ?? '')) {
                $linked_id = absint($block['attrs']['id'] ?? 0);
                if ($linked_id) {
                    $ids[$linked_id] = true;
                }
            }
            foreach (array_keys($this->linked_page_ids((array) ($block['innerBlocks'] ?? []))) as $nested) {
                $ids[$nested] = true;
            }
        }

        return $ids;
    }

    /**
     * Put the new links inside the site's navigation block, so they appear in its menu;
     * a post without one gets them after its existing content.
     *
     * innerContent is the map the serializer follows between a container's children, with one
     * null per child block. Adding children without extending it is how the added links go
     * missing between building them and saving the post.
     */
    private function place_links(array $blocks, array $links) {
        foreach ($blocks as $index => $block) {
            if (!is_array($block)) {
                continue;
            }
            if ('core/navigation' === (string) ($block['blockName'] ?? '')) {
                $children = array_merge((array) ($block['innerBlocks'] ?? []), $links);
                $blocks[$index]['innerBlocks'] = $children;
                $blocks[$index]['innerContent'] = array_merge(
                    [''],
                    array_fill(0, count($children), null),
                    ['']
                );

                return $blocks;
            }
            $inner = (array) ($block['innerBlocks'] ?? []);
            if ($inner) {
                $placed = $this->place_links($inner, $links);
                if ($placed !== $inner) {
                    $blocks[$index]['innerBlocks'] = $placed;
                    $blocks[$index]['innerContent'] = array_merge(
                        [''],
                        array_fill(0, count($placed), null),
                        ['']
                    );

                    return $blocks;
                }
            }
        }

        return array_merge($blocks, $links);
    }

    /**
     * @return array<string,mixed>
     */
    private function ensure_classic_navigation(array $pages, array $navigation, array &$result, $replace = false) {
        if (!function_exists('wp_create_nav_menu')) {
            require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
        }

        $menu = 'classic' === ($navigation['type'] ?? '') ? wp_get_nav_menu_object(absint($navigation['id'] ?? 0)) : false;
        $location = sanitize_key((string) ($navigation['location'] ?? ''));
        if (!$menu) {
            // Most classic themes already show a menu of their own. Add the
            // starter links to that menu instead of creating a parallel one
            // and hunting for a spare location the theme may render in a
            // drawer or footer where visitors never see it.
            $visible = $this->menu_at_visible_location();
            if ($visible) {
                $menu = $visible['menu'];
                $location = $visible['location'];
            }
        }

        // Same rule as the block path: with the option off this step does not write a
        // menu, add links to one, or rewrite one it made before (Ryan, 2026-10-18).
        if (!$replace) {
            $result['navigation_untouched'] = true;
            if (!$menu) {
                return ['untouched' => true];
            }

            return [
                'id' => absint($menu->term_id),
                'type' => 'classic',
                'location' => $location,
                'preserved' => true,
                'untouched' => true,
            ];
        }

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
        if ($replace && !get_term_meta($menu_id, '_million_dollar_script_starter_navigation', true)) {
            // Same promise as the block path: the owner asked for the starter navigation, so
            // the menu is emptied before the starter links are written. This is how core
            // empties a menu - wp_delete_nav_menu_item() does not exist (checked on 7.1.3),
            // and a function_exists() guard around it silently turned the feature off.
            foreach ((array) get_objects_in_term($menu_id, 'nav_menu') as $item_id) {
                wp_delete_post(absint($item_id), true);
            }
            update_term_meta($menu_id, '_million_dollar_script_starter_navigation', 'yes');
            $result['navigation_replaced'] = true;
        }
        // A menu we did not create belongs to the site. Its owner should be told
        // that starter links were added to it instead of finding new items in
        // Appearance → Menus later (Vikunja #31 item 6).
        $site_owned_menu = !get_term_meta($menu_id, '_million_dollar_script_starter_navigation', true);
        $added_items = 0;
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
            $added_items++;
        }

        if ($site_owned_menu && $added_items > 0) {
            $result['navigation_note'] = [
                'items' => $added_items,
                'menu' => (string) $menu->name,
            ];
        }

        if ('' === $location) {
            $location = $this->assign_available_menu_location($menu_id);
        } else {
            // The location this workflow recorded is only a promise until something holds it.
            // A theme switch, a menu deleted in the meantime, or an edit in the theme settings
            // can leave that spot empty - and the site is then looking at a fallback list of
            // pages where its menu used to be.
            $location = $this->claim_menu_location($menu_id, $location);
        }

        return array_filter(['id' => $menu_id, 'location' => $location, 'type' => 'classic']);
    }

    /**
     * Make the location this workflow recorded hold the menu again.
     *
     * Returns the location the menu ends up at: the recorded one while the theme still keeps
     * it there, that same spot if it is empty or left pointing at a menu that no longer
     * exists, and otherwise the first header spot the theme offers.
     */
    private function claim_menu_location($menu_id, $location) {
        $registered = get_registered_nav_menus();
        $locations = get_theme_mod('nav_menu_locations', []);
        $locations = is_array($locations) ? $locations : [];
        $menu_id = absint($menu_id);

        if ('' !== $location && isset($registered[$location]) && absint($locations[$location] ?? 0) === $menu_id) {
            return sanitize_key($location);
        }
        if ('' !== $location && isset($registered[$location])) {
            $assigned = absint($locations[$location] ?? 0);
            if (!$assigned || !wp_get_nav_menu_object($assigned)) {
                $locations[$location] = $menu_id;
                set_theme_mod('nav_menu_locations', $locations);

                return sanitize_key($location);
            }
        }

        return $this->assign_available_menu_location($menu_id);
    }

    /**
     * The menu a visitor can already see: the site's header location, when the theme has one
     * assigned to a menu that exists. A menu parked in a footer, social or utility location
     * does not count - adding the starter links there leaves the header showing whatever the
     * theme falls back to, and that is what a site owner sees (Kadence, checked 2026-10-16).
     *
     * @return array{menu:WP_Term,location:string}|null
     */
    private function menu_at_visible_location() {
        $locations = get_theme_mod('nav_menu_locations', []);
        if (!is_array($locations)) {
            return null;
        }

        foreach ($this->header_menu_locations() as $location) {
            if (empty($locations[$location])) {
                continue;
            }
            $menu = wp_get_nav_menu_object(absint($locations[$location]));
            if ($menu) {
                return ['menu' => $menu, 'location' => $location];
            }
        }

        return null;
    }

    /**
     * The locations a visitor reads as the site's menu, in the order to use them.
     *
     * Themes name these Primary, Header, Main or Menu. A footer, social, mobile or secondary
     * location is somewhere else on the page, and a menu put there is not a menu the site
     * header offers - so it is not the one to extend, and not the one to claim.
     *
     * @return array<int,string>
     */
    private function header_menu_locations() {
        $registered = array_keys(get_registered_nav_menus());
        $named = [];
        foreach ($registered as $location) {
            if (preg_match('/^(primary|header|main|menu(-\d+)?|navigation)$/', (string) $location)) {
                $named[] = (string) $location;
            }
        }
        $named = array_merge(['primary', 'header', 'main', 'menu-1'], $named);
        $named = array_values(array_unique(array_filter($named, static function ($location) use ($registered) {
            return in_array($location, $registered, true);
        })));

        return $named;
    }

    private function assign_available_menu_location($menu_id) {
        $registered = get_registered_nav_menus();
        $locations = get_theme_mod('nav_menu_locations', []);
        $locations = is_array($locations) ? $locations : [];

        foreach (array_merge($this->header_menu_locations(), array_keys($registered)) as $location) {
            if (!isset($registered[$location])) {
                continue;
            }
            $assigned = absint($locations[$location] ?? 0);
            // A location still naming a menu that has since been deleted is not taken: the
            // theme is showing its fallback there, which reads as the menu having disappeared.
            // Claiming that spot is how the site ends up with a menu again.
            if ($assigned && wp_get_nav_menu_object($assigned)) {
                continue;
            }
            $locations[$location] = absint($menu_id);
            set_theme_mod('nav_menu_locations', $locations);
            return sanitize_key($location);
        }

        return '';
    }

    /**
     * Serialize the starter items as navigation-link blocks.
     *
     * @param array<string,int> $pages
     */
    private function build_navigation_content(array $pages) {
        $content = '';
        foreach ($this->navigation_items($pages) as $item) {
            $content .= serialize_block($this->navigation_link_block($item));
        }
        return $content;
    }

    /**
     * One starter link as a block, in the shape the site editor writes.
     *
     * @param array{key:string,label:string,post_id:int} $item
     */
    private function navigation_link_block(array $item) {
        return [
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
            'innerHTML' => ['', ''],
        ];
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
