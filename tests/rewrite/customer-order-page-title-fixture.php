<?php
/**
 * WP-CLI fixture: the page a customer manages an order on is named for what it holds.
 *
 * The page has carried the name of one control on it while holding the order state,
 * the payment step, and the ad's image, destination link and popup text. Renaming it
 * is a change to a durable contract, so it is tested on three site states: one still
 * on the old default, one whose owner renamed the page, and one that has no such page.
 * The address must not move in any of them - menus, links and shortcodes point at the
 * slug, the page type and the saved option, not at the title.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/customer-order-page-title-fixture.php
 */

use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Setup\Installer;

if (!defined('ABSPATH')) {
    exit;
}

$guard_option = 'mds3_upgraded_customer_order_page_title';
$page_option = 'mds3_page_upload_id';
$original_page_id = get_option($page_option, false);
$original_guard = get_option($guard_option, false);
$created_ids = array();

$run_step = static function () {
    $method = new ReflectionMethod(Installer::class, 'upgrade_customer_order_page_title');
    $method->setAccessible(true);
    $method->invoke(null);
};

$make_page = static function ($title) use (&$created_ids) {
    $post_id = wp_insert_post(array(
        'post_type' => 'page',
        'post_title' => $title,
        'post_name' => 'upload',
        'post_status' => 'publish',
        'post_content' => '[mds3_page type="upload"]',
    ));
    if (is_wp_error($post_id) || !$post_id) {
        throw new RuntimeException('The fixture could not create the page it needs to rename.');
    }
    $created_ids[] = (int) $post_id;

    return (int) $post_id;
};

try {
    // 1. A site still carrying the default name is retitled, and keeps its address.
    $page_id = $make_page(__('Upload', 'million-dollar-script'));
    update_option($page_option, $page_id, false);
    delete_option($guard_option);
    $run_step();

    $retitled = get_post($page_id);
    $label = (string) (PageRepository::labels()['upload'] ?? '');
    if ('' === $label) {
        throw new RuntimeException('The fixture could not read the page label it is checking against.');
    }
    if ((string) $retitled->post_title !== $label) {
        throw new RuntimeException('The order page is still named for one control on it: ' . (string) $retitled->post_title . '.');
    }
    if ('upload' !== (string) $retitled->post_name) {
        throw new RuntimeException('Renaming the order page moved its address to ' . (string) $retitled->post_name . '.');
    }
    if (!get_option($guard_option)) {
        throw new RuntimeException('The retitle step did not record that it ran, so it would run on every admin load.');
    }

    // 2. A title written by the site owner is left alone.
    wp_update_post(array('ID' => $page_id, 'post_title' => 'Our ad page'));
    delete_option($guard_option);
    $run_step();
    if ('Our ad page' !== (string) get_post($page_id)->post_title) {
        throw new RuntimeException('The upgrade overwrote a page title the site owner had written.');
    }

    // 3. A site whose role points at a page that is gone is left alone, without error,
    // and the step does not invent an assignment.
    update_option($page_option, 999999, false);
    delete_option($guard_option);
    $run_step();
    if (999999 !== absint(get_option($page_option, 0))) {
        throw new RuntimeException('The upgrade changed a page assignment it had no page for, to ' . absint(get_option($page_option, 0)) . '.');
    }

    echo "customer-order-page-title fixture passed\n";
} finally {
    foreach ($created_ids as $created_id) {
        wp_delete_post($created_id, true);
    }
    if (false === $original_page_id) {
        delete_option($page_option);
    } else {
        update_option($page_option, $original_page_id, false);
    }
    if (false === $original_guard) {
        delete_option($guard_option);
    } else {
        update_option($guard_option, $original_guard, false);
    }
}
