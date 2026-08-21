<?php
/**
 * WP-CLI fixture for the opt-in starter pages and navigation workflow.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/starter-site-fixture.php
 */

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
} finally {
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
