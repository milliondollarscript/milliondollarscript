<?php
/**
 * WP-CLI fixture for the opt-in starter pages and navigation workflow.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/starter-site-fixture.php
 */

use MillionDollarScript\V3\Setup\StarterNavigation;
use MillionDollarScript\V3\Setup\StarterSite;

if (!defined('ABSPATH')) {
    exit;
}

$administrator_ids = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$administrator_ids) {
    throw new RuntimeException('Starter-site fixture requires an administrator user.');
}

$original_user_id = get_current_user_id();
$original_state = get_option(StarterSite::OPTION, false);
$original_show_on_front = get_option('show_on_front');
$original_page_on_front = get_option('page_on_front');
$original_page_for_posts = get_option('page_for_posts');
$original_locations = get_theme_mod('nav_menu_locations', null);
$standard_option_names = ['mds3_page_grid_id', 'mds3_page_order_id', 'mds3_page_manage_id'];
$original_standard_options = [];
$fixture_page_ids = [];
$starter_created_page_ids = [];
$starter_navigation = [];
$starter_navigation_created = false;
$existing_navigation_content = [];
$original_contact_shortcode = $GLOBALS['shortcode_tags']['mds_contact_form'] ?? null;
$original_stylesheet = get_option('stylesheet');
$registered_locations_before = null;
$fixture_menu_ids = [];
$fixture_navigation_ids = [];

foreach (get_posts([
    'post_status' => 'publish',
    'post_type' => 'wp_navigation',
    'posts_per_page' => -1,
]) as $navigation_post) {
    $existing_navigation_content[absint($navigation_post->ID)] = (string) $navigation_post->post_content;
}

foreach ($standard_option_names as $option_name) {
    $original_standard_options[$option_name] = get_option($option_name, false);
}

wp_set_current_user(absint($administrator_ids[0]));

try {
    delete_option(StarterSite::OPTION);

    foreach ([
        'grid' => 'Fixture Grid Page',
        'order' => 'Fixture Order Pixels',
        'manage' => 'Fixture Manage Pixels',
    ] as $type => $title) {
        $page_id = wp_insert_post([
            'post_content' => '<p>' . esc_html($title) . '</p>',
            'post_name' => 'mds-starter-fixture-' . $type,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_type' => 'page',
        ], true);
        if (is_wp_error($page_id)) {
            throw new RuntimeException($page_id->get_error_message());
        }
        $fixture_page_ids[] = absint($page_id);
        update_option('mds3_page_' . $type . '_id', absint($page_id), false);
    }

    $existing_front_id = wp_insert_post([
        'post_content' => '<p>Existing front-page content must remain unchanged.</p>',
        'post_name' => 'mds-starter-fixture-existing-home',
        'post_status' => 'publish',
        'post_title' => 'Existing Fixture Home',
        'post_type' => 'page',
    ], true);
    if (is_wp_error($existing_front_id)) {
        throw new RuntimeException($existing_front_id->get_error_message());
    }
    $existing_front_id = absint($existing_front_id);
    $fixture_page_ids[] = $existing_front_id;
    update_option('show_on_front', 'page');
    update_option('page_on_front', $existing_front_id);
    update_option('page_for_posts', 0);

    $service = new StarterSite();
    $first = $service->create();
    if (!empty($first['errors'])) {
        throw new RuntimeException('Starter-site creation returned errors: ' . implode('; ', array_map('strval', $first['errors'])));
    }

    $status = $service->status();
    if (empty($status['configured'])) {
        throw new RuntimeException('Starter-site status did not report configured after creation.');
    }
    if ($existing_front_id !== absint($status['pages']['home'] ?? 0) || $existing_front_id !== absint(get_option('page_on_front', 0))) {
        throw new RuntimeException('Starter-site creation replaced the existing static front page.');
    }
    if ('Existing front-page content must remain unchanged.' !== wp_strip_all_tags((string) get_post_field('post_content', $existing_front_id))) {
        throw new RuntimeException('Starter-site creation changed existing front-page content.');
    }

    foreach (['blog', 'contact', 'about'] as $key) {
        $post_id = absint($status['pages'][$key] ?? 0);
        if (!$post_id || 'page' !== get_post_type($post_id)) {
            throw new RuntimeException('Starter-site creation did not resolve the ' . $key . ' page.');
        }
    }
    $contact_id = absint($status['pages']['contact']);
    if (false === strpos((string) get_post_field('post_content', $contact_id), '[' . StarterSite::CONTACT_SHORTCODE)) {
        throw new RuntimeException('Starter Contact page does not use the safe core wrapper shortcode.');
    }

    $starter_created_page_ids = array_values(array_filter(array_map('absint', array_diff_key($first['created'] ?? [], ['navigation' => true]))));
    $starter_navigation = is_array($status['navigation'] ?? null) ? $status['navigation'] : [];
    $navigation_id = absint($starter_navigation['id'] ?? 0);
    $starter_navigation_created = !empty($first['created']['navigation']);
    if (!$navigation_id) {
        throw new RuntimeException('Starter-site creation did not resolve navigation.');
    }
    if (!empty($starter_navigation['preserved'])) {
        if (empty($status['navigation_needs_review'])) {
            throw new RuntimeException('Preserved navigation did not request an administrator review.');
        }
        if (!isset($existing_navigation_content[$navigation_id])) {
            throw new RuntimeException('Starter-site creation reported preserving an unknown navigation.');
        }
        if ($existing_navigation_content[$navigation_id] !== (string) get_post_field('post_content', $navigation_id)) {
            throw new RuntimeException('Starter-site creation changed existing block navigation.');
        }
    } elseif ('block' === ($starter_navigation['type'] ?? '')) {
        $navigation_post = get_post($navigation_id);
        if (!$navigation_post || 'wp_navigation' !== $navigation_post->post_type || 6 !== count(parse_blocks((string) $navigation_post->post_content))) {
            throw new RuntimeException('Starter block navigation does not contain the six recommended page links.');
        }
    } else {
        if ('classic' !== ($starter_navigation['type'] ?? '') || 6 !== count((array) wp_get_nav_menu_items($navigation_id))) {
            throw new RuntimeException('Starter classic navigation does not contain the six recommended page links.');
        }
    }

    remove_shortcode('mds_contact_form');
    $fallback = $service->render_contact();
    if (false === strpos($fallback, 'has not enabled its contact form') || false !== strpos($fallback, '[mds_contact_form')) {
        throw new RuntimeException('Inactive Contact Form fallback was missing or exposed a raw extension shortcode.');
    }
    add_shortcode('mds_contact_form', static function () {
        return '<form data-starter-contact-fixture="active"></form>';
    });
    if (false === strpos($service->render_contact(), 'data-starter-contact-fixture="active"')) {
        throw new RuntimeException('Starter Contact page did not switch automatically to the active extension shortcode.');
    }

    $about_id = absint($status['pages']['about']);
    $about_was_created = in_array($about_id, $starter_created_page_ids, true);
    if ($about_was_created) {
        wp_update_post(['ID' => $about_id, 'post_content' => '<p>Administrator-edited About content.</p>']);
    }

    $before_second_state = get_option(StarterSite::OPTION, []);
    $before_second_page_count = (int) wp_count_posts('page')->publish;
    $second = $service->create();
    if (!empty($second['errors'])) {
        throw new RuntimeException('Idempotent starter-site repair returned errors.');
    }
    $after_second_state = get_option(StarterSite::OPTION, []);
    if ($before_second_state['pages'] !== $after_second_state['pages'] || $before_second_state['navigation'] !== $after_second_state['navigation']) {
        throw new RuntimeException('Idempotent starter-site repair changed page or navigation identities.');
    }
    if ($before_second_page_count !== (int) wp_count_posts('page')->publish) {
        throw new RuntimeException('Idempotent starter-site repair created duplicate pages.');
    }
    if ($about_was_created && '<p>Administrator-edited About content.</p>' !== (string) get_post_field('post_content', $about_id)) {
        throw new RuntimeException('Idempotent starter-site repair overwrote edited page content.');
    }

    // A classic theme usually already shows a menu of its own. The starter
    // links have to join that menu instead of a parallel menu parked in a
    // spare location the theme renders in a drawer or footer.
    $classic_theme = null;
    foreach (['kadence', 'Divi'] as $candidate) {
        if (wp_get_theme($candidate)->exists()) {
            $classic_theme = $candidate;
            break;
        }
    }

    if (null === $classic_theme) {
        echo "Skipped classic-theme menu check: no classic theme installed.\n";
    } else {
        switch_theme($classic_theme);
        $registered_locations_before = $GLOBALS['_wp_registered_nav_menus'] ?? [];
        $GLOBALS['_wp_registered_nav_menus'] = array_merge(
            ['primary' => 'Primary location', 'secondary' => 'Secondary location'],
            (array) $registered_locations_before
        );
        // The location assignments belong to the theme, so a fixture that moves them has to
        // put them back: a leftover pointing at a fixture menu that is deleted afterwards is
        // exactly the broken header this next case is about.
        $fixture_nav_locations = get_theme_mod('nav_menu_locations', []);

        $site_menu_id = wp_create_nav_menu('Fixture Existing Site Menu');
        if (is_wp_error($site_menu_id)) {
            throw new RuntimeException('Could not create the fixture site menu.');
        }
        $site_menu_id = absint($site_menu_id);
        $fixture_menu_ids[] = $site_menu_id;
        $site_item_id = wp_update_nav_menu_item($site_menu_id, 0, [
            'menu-item-object' => 'page',
            'menu-item-object-id' => $existing_front_id,
            'menu-item-status' => 'publish',
            'menu-item-type' => 'post_type',
        ]);
        if (is_wp_error($site_item_id)) {
            throw new RuntimeException('Could not add an item to the fixture site menu.');
        }

        $locations = (array) get_theme_mod('nav_menu_locations', []);
        $locations['primary'] = $site_menu_id;
        set_theme_mod('nav_menu_locations', $locations);

        $nav_result = [];
        $untouched = (new StarterNavigation())->ensure($status['pages'], ['navigation' => []], $nav_result);
        if ('classic' !== ($untouched['type'] ?? '')) {
            throw new RuntimeException('Classic theme did not take the classic navigation path.');
        }
        if (absint($untouched['id'] ?? 0) !== $site_menu_id) {
            throw new RuntimeException('With the option off the step pointed at a menu other than the one the theme shows.');
        }
        // The rule Ryan set on 2026-10-18: unless the box is ticked, this step writes no menu,
        // adds nothing to one, and rewrites nothing it wrote before. The fixture menu holds one
        // item of the site's own, so the menu must still hold exactly that one.
        if (empty($nav_result['navigation_untouched'])) {
            throw new RuntimeException('The step did not report that it wrote no navigation.');
        }
        if (isset($nav_result['navigation_note'])) {
            throw new RuntimeException('The step announced a menu change it was never asked to make.');
        }
        $item_count = count((array) wp_get_nav_menu_items($site_menu_id));
        if (1 !== $item_count) {
            throw new RuntimeException('The step changed a menu it was not asked to touch: ' . $item_count . ' items instead of 1.');
        }

        $repeat_result = [];
        (new StarterNavigation())->ensure($status['pages'], ['navigation' => $untouched], $repeat_result);
        if (count((array) wp_get_nav_menu_items($site_menu_id)) !== $item_count) {
            throw new RuntimeException('A second run with the option off changed the menu.');
        }

        // Ticked, the site's own links are replaced by the starter pages. The page itself stays;
        // only the link goes.
        $replace_result = [];
        $replaced = (new StarterNavigation())->ensure(
            $status['pages'],
            ['navigation' => $untouched, 'navigation_mode' => 'replace'],
            $replace_result
        );
        if (empty($replace_result['navigation_replaced'])) {
            throw new RuntimeException('Ticking the option did not report a replaced menu.');
        }
        $after_replace = (array) wp_get_nav_menu_items($site_menu_id);
        $kept_ids = array_map('intval', array_map(static function ($item) {
            return absint($item->ID ?? $item->_ID ?? 0);
        }, $after_replace));
        // The starter pages may legitimately point at the same page again (a Home link does) -
        // what must not survive is the site's own link, which was the item just created.
        if (in_array(absint($site_item_id), $kept_ids, true)) {
            throw new RuntimeException('The site’s own link survived a replacement it agreed to.');
        }
        if (count($after_replace) < 5) {
            throw new RuntimeException('The replaced menu holds fewer starter pages than it should: ' . count($after_replace));
        }

        // The recorded location is only a promise until the theme actually holds it. A theme
        // switch or a menu edit can clear that assignment; this block checks what the step does
        // about it - which, with the option off, is nothing at all.
        $cleared_locations = (array) get_theme_mod('nav_menu_locations', []);
        $cleared_locations['primary'] = 0;
        set_theme_mod('nav_menu_locations', $cleared_locations);
        // With the option off, nothing about navigation moves - not its content, and not which
        // header slot it sits in (Ryan, 2026-10-18). A cleared assignment is reported, not
        // silently repaired behind the site owner; ticking the option is what puts it back.
        $reclaim_result = [];
        $untouched_after_clear = (new StarterNavigation())->ensure(
            $status['pages'],
            ['navigation' => ['id' => $site_menu_id, 'location' => 'primary', 'type' => 'classic']],
            $reclaim_result
        );
        if (absint($untouched_after_clear['id'] ?? 0) !== $site_menu_id) {
            throw new RuntimeException('With the option off the step pointed at a menu other than the one already recorded.');
        }
        if (empty($reclaim_result['navigation_untouched'])) {
            throw new RuntimeException('A run that wrote nothing did not say so.');
        }
        $held_locations = (array) get_theme_mod('nav_menu_locations', []);
        if (0 !== absint($held_locations['primary'] ?? 0)) {
            throw new RuntimeException('The step claimed a header location it was not asked to touch.');
        }

        $reclaim_result = [];
        $reclaimed = (new StarterNavigation())->ensure(
            $status['pages'],
            ['navigation' => ['id' => $site_menu_id, 'location' => 'primary', 'type' => 'classic'], 'navigation_mode' => 'replace'],
            $reclaim_result
        );
        if (absint($reclaimed['id'] ?? 0) !== $site_menu_id) {
            throw new RuntimeException('A second run made a new menu instead of putting the existing one back where it belonged.');
        }
        if ('primary' !== ($reclaimed['location'] ?? '')) {
            throw new RuntimeException('A menu whose location had been cleared was not put back there.');
        }
        $held_locations = (array) get_theme_mod('nav_menu_locations', []);
        if (absint($held_locations['primary'] ?? 0) !== $site_menu_id) {
            throw new RuntimeException('The header location was left empty after a run that claimed to fill it.');
        }

        // A header location left pointing at a menu that has since been deleted: the theme
        // shows its fallback there and the owner reads that as the menu having gone. With the
        // option off the step reports that and writes nothing; with it ticked it claims the
        // empty header spot, and does not extend a menu parked in a utility area - which is
        // what the header was doing under Kadence on 2026-10-16.
        $utility_menu_id = absint(wp_create_nav_menu('Fixture Utility Site Menu'));
        $fixture_menu_ids[] = $utility_menu_id;
        $dangling_menu_id = absint(wp_create_nav_menu('Fixture Deleted Site Menu'));
        $moved_locations = (array) get_theme_mod('nav_menu_locations', []);
        $moved_locations['secondary'] = $utility_menu_id;
        $moved_locations['primary'] = $dangling_menu_id;
        set_theme_mod('nav_menu_locations', $moved_locations);
        wp_delete_nav_menu($dangling_menu_id);

        $nothing_written = [];
        $no_menu = (new StarterNavigation())->ensure($status['pages'], [], $nothing_written);
        if (!empty($no_menu['id'])) {
            throw new RuntimeException('The step wrote a menu it was never asked to write.');
        }
        if (empty($nothing_written['navigation_untouched'])) {
            throw new RuntimeException('A run that wrote no menu did not say so.');
        }
        if (absint(((array) get_theme_mod('nav_menu_locations', []))['secondary'] ?? 0) !== $utility_menu_id) {
            throw new RuntimeException('The site’s own menu moved out of its location during a run that touched nothing.');
        }

        $repaired_result = [];
        $repaired_menu = (new StarterNavigation())->ensure($status['pages'], ['navigation_mode' => 'replace'], $repaired_result);
        $repaired_id = absint($repaired_menu['id'] ?? 0);
        if ('classic' !== ($repaired_menu['type'] ?? '') || !$repaired_id) {
            throw new RuntimeException('A classic theme with a broken header menu did not make one.');
        }
        $fixture_menu_ids[] = $repaired_id;
        if ('primary' !== ($repaired_menu['location'] ?? '')) {
            throw new RuntimeException('The new menu did not take the header location that had no menu in it.');
        }
        $written_locations = (array) get_theme_mod('nav_menu_locations', []);
        if (absint($written_locations['primary'] ?? 0) !== $repaired_id) {
            throw new RuntimeException('The header location was still left pointing at a menu that does not exist.');
        }
        if (absint($written_locations['secondary'] ?? 0) !== $utility_menu_id) {
            throw new RuntimeException('The site’s own menu was moved out of the location it was in.');
        }
        set_theme_mod('nav_menu_locations', $fixture_nav_locations);
        if (get_option('stylesheet') !== $original_stylesheet) {
            switch_theme($original_stylesheet);
        }
    }

    // A block theme with a navigation the site built for itself. The starter links join it and
    // the links the site wrote stay in it; replacing the content used to delete a menu its
    // owner had made, which is worse than not making one (task 7b70c157, from Ryan's
    // 2026-10-16 report about the starter site and menus).
    if (wp_is_block_theme() && post_type_exists('wp_navigation')) {
        $owner_link = [
            'attrs' => ['kind' => 'custom', 'label' => 'Fixture Owner Link', 'url' => home_url('/fixture-owner-page/')],
            'blockName' => 'core/navigation-link',
            'innerBlocks' => [],
            'innerContent' => [],
            'innerHTML' => ['', ''],
        ];
        $owner_navigation_id = absint(wp_insert_post([
            'post_content' => serialize_block([
                'attrs' => [],
                'blockName' => 'core/navigation',
                'innerHTML' => ['<!-- wp:navigation -->', '<!-- /wp:navigation -->'],
                'innerBlocks' => [$owner_link],
                'innerContent' => ['', null, ''],
            ]),
            'post_status' => 'publish',
            'post_title' => 'Fixture Owner Navigation',
            'post_type' => 'wp_navigation',
        ]));
        $fixture_navigation_ids[] = $owner_navigation_id;

        delete_option(StarterSite::OPTION);
        $owner_result = [];
        $owner_left_alone = (new StarterNavigation())->ensure($status['pages'], [], $owner_result);
        if (absint($owner_left_alone['id'] ?? 0) !== $owner_navigation_id) {
            throw new RuntimeException('The step pointed at a navigation other than the one the site made.');
        }
        if (!empty($owner_result['errors'])) {
            throw new RuntimeException('Reading the site navigation reported errors: ' . implode('; ', array_map('strval', $owner_result['errors'])));
        }
        if (empty($owner_result['navigation_untouched'])) {
            throw new RuntimeException('A run that changed no navigation did not say so.');
        }
        $owner_content = (string) get_post_field('post_content', $owner_navigation_id);
        if (false === strpos($owner_content, 'Fixture Owner Link')) {
            throw new RuntimeException('The site’s own navigation link did not survive a run that was not asked to touch it.');
        }
        // The rule as Ryan set it on 2026-10-18: with the option off, nothing is written into
        // a navigation the site owns - not even the pages the customer needs.
        foreach (['Order Pixels', 'Manage Pixels'] as $starter_label) {
            if (false !== strpos($owner_content, $starter_label)) {
                throw new RuntimeException('The ' . $starter_label . ' link was written into a navigation the option left alone.');
            }
        }
        if (isset($owner_result['navigation_note'])) {
            throw new RuntimeException('The step announced a navigation change it did not make.');
        }

        // Ticked, the same navigation is written with the starter pages and the site's own link
        // goes with it - the choice the option exists to offer.
        $replace_owner_result = [];
        (new StarterNavigation())->ensure(
            $status['pages'],
            ['navigation' => $owner_left_alone, 'navigation_mode' => 'replace'],
            $replace_owner_result
        );
        $replaced_owner = (string) get_post_field('post_content', $owner_navigation_id);
        if (false !== strpos($replaced_owner, 'Fixture Owner Link')) {
            throw new RuntimeException('Ticking the option left the site’s own link in place.');
        }
        foreach (['Order Pixels', 'Manage Pixels'] as $starter_label) {
            if (false === strpos($replaced_owner, $starter_label)) {
                throw new RuntimeException('The ' . $starter_label . ' link was not written when the option was ticked.');
            }
        }
        if (empty($replace_owner_result['navigation_replaced'])) {
            throw new RuntimeException('Writing the site navigation did not report what it replaced.');
        }

        $again_result = [];
        (new StarterNavigation())->ensure($status['pages'], ['navigation' => $owner_left_alone], $again_result);
        if (1 !== substr_count((string) get_post_field('post_content', $owner_navigation_id), 'Order Pixels')) {
            throw new RuntimeException('A run with the option off changed the navigation content.');
        }
        if (isset($again_result['navigation_note'])) {
            throw new RuntimeException('A second starter-site run re-announced a navigation change it did not make.');
        }

        // A navigation that lists pages by itself already shows these pages, so it is left
        // exactly as the site built it and the step asks for a look instead.
        $page_list_navigation_id = absint(wp_insert_post([
            'post_content' => serialize_block([
                'attrs' => [],
                'blockName' => 'core/navigation',
                'innerHTML' => ['<!-- wp:navigation -->', '<!-- /wp:navigation -->'],
                'innerBlocks' => [
                    ['attrs' => [], 'blockName' => 'core/page-list', 'innerHTML' => '', 'innerBlocks' => [], 'innerContent' => []],
                ],
                'innerContent' => ['', null, ''],
            ]),
            'post_status' => 'publish',
            'post_title' => 'Fixture Page List Navigation',
            'post_type' => 'wp_navigation',
        ]));
        $fixture_navigation_ids[] = $page_list_navigation_id;
        $page_list_before = (string) get_post_field('post_content', $page_list_navigation_id);
        delete_option(StarterSite::OPTION);
        $kept_result = [];
        $kept = (new StarterNavigation())->ensure($status['pages'], [], $kept_result);
        if (absint($kept['id'] ?? 0) !== $page_list_navigation_id || empty($kept['preserved'])) {
            throw new RuntimeException('A navigation that lists pages automatically was not left alone.');
        }
        if ($page_list_before !== (string) get_post_field('post_content', $page_list_navigation_id)) {
            throw new RuntimeException('A navigation that lists pages automatically was edited.');
        }
    }

    // A site owner who already wrote a navigation asked for a choice: links added behind
    // their back is one answer, overwriting it is another, and neither should happen by
    // accident (llm-task efdcfa60). Both answers are checked against one fixture navigation.
    delete_option(StarterSite::OPTION);
    $foreign_nav_id = absint(wp_insert_post([
        'post_content' => '<nav><ul class="wp-block-navigation__navigation-menu-container"><li><a href="https://example.test/mine">The owner wrote this</a></li></ul></nav>',
        'post_status' => 'publish',
        'post_title' => 'Fixture Navigation Under Test',
        'post_type' => 'wp_navigation',
    ]));
    $fixture_navigation_ids[] = $foreign_nav_id;

    $kept_by_default = $service->create();
    if (absint($kept_by_default['status']['navigation']['id'] ?? 0) !== $foreign_nav_id) {
        throw new RuntimeException('Left alone, the starter site did not use the site\'s own navigation.');
    }
    if (strpos((string) get_post_field('post_content', $foreign_nav_id), 'The owner wrote this') === false) {
        throw new RuntimeException('The default answer removed a link the site owner had written.');
    }
    if (get_post_meta($foreign_nav_id, '_million_dollar_script_starter_navigation', true)) {
        throw new RuntimeException('The default answer claimed a navigation it had only linked into.');
    }

    $replaced = $service->create(['navigation_mode' => 'replace']);
    if (empty($replaced['navigation_replaced'])) {
        throw new RuntimeException('Replacing the navigation was not reported to the caller.');
    }
    $replaced_content = (string) get_post_field('post_content', $foreign_nav_id);
    if (strpos($replaced_content, 'The owner wrote this') !== false) {
        throw new RuntimeException('Replacing the navigation left the previous links in place.');
    }
    $replaced_pages = is_array($service->status()['pages'] ?? null) ? $service->status()['pages'] : [];
    // The six pages are what the screen promises. The grid page is the Home entry, which is
    // why there is no separate grid link to look for.
    foreach (['home', 'blog', 'order', 'manage', 'contact', 'about'] as $expected_key) {
        $expected_link = (string) get_permalink(absint($replaced_pages[$expected_key] ?? 0));
        if ($expected_link === '' || strpos($replaced_content, $expected_link) === false) {
            throw new RuntimeException('Replacing the navigation did not list the ' . $expected_key . ' page.');
        }
    }
    if ('yes' !== get_post_meta($foreign_nav_id, '_million_dollar_script_starter_navigation', true)) {
        throw new RuntimeException('A replaced navigation was not marked as the plugin\'s, so a later Repair would not rewrite it.');
    }
    if ('replace' !== (string) ($service->status()['navigation']['mode'] ?? '')) {
        throw new RuntimeException('The chosen answer was not remembered, so a later run would silently change it.');
    }
    echo "PASS navigation mode: the owner's link survives by default and is replaced only on request\n";
} finally {
    foreach ($fixture_navigation_ids as $navigation_id) {
        wp_delete_post(absint($navigation_id), true);
    }
    if (null !== $registered_locations_before) {
        $GLOBALS['_wp_registered_nav_menus'] = $registered_locations_before;
    }
    foreach ($fixture_menu_ids as $menu_id) {
        wp_delete_nav_menu(absint($menu_id));
    }
    if (get_option('stylesheet') !== $original_stylesheet) {
        switch_theme($original_stylesheet);
    }
    if (null === $original_contact_shortcode) {
        remove_shortcode('mds_contact_form');
    } else {
        $GLOBALS['shortcode_tags']['mds_contact_form'] = $original_contact_shortcode;
    }

    if ($starter_navigation_created && 'block' === ($starter_navigation['type'] ?? '') && !empty($starter_navigation['id'])) {
        wp_delete_post(absint($starter_navigation['id']), true);
    } elseif ($starter_navigation_created && 'classic' === ($starter_navigation['type'] ?? '') && !empty($starter_navigation['id'])) {
        wp_delete_nav_menu(absint($starter_navigation['id']));
    }

    foreach (array_unique(array_merge($starter_created_page_ids, $fixture_page_ids)) as $page_id) {
        wp_delete_post(absint($page_id), true);
    }

    if (false === $original_state) {
        delete_option(StarterSite::OPTION);
    } else {
        update_option(StarterSite::OPTION, $original_state, false);
    }
    update_option('show_on_front', $original_show_on_front);
    update_option('page_on_front', $original_page_on_front);
    update_option('page_for_posts', $original_page_for_posts);
    if (null === $original_locations) {
        remove_theme_mod('nav_menu_locations');
    } else {
        set_theme_mod('nav_menu_locations', $original_locations);
    }
    foreach ($original_standard_options as $option_name => $value) {
        if (false === $value) {
            delete_option($option_name);
        } else {
            update_option($option_name, $value, false);
        }
    }
    wp_set_current_user($original_user_id);
}

echo "Starter-site fixture passed.\n";
