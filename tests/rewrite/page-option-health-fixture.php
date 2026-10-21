<?php
/**
 * WP-CLI fixture: a page option that points at a page which is gone.
 *
 * Why this exists: a standard-page option is a post id, and posts get deleted. The reader
 * falls back to another page, so nothing ever reported it - on a real site "Manage upload"
 * and the order email led to a plain grid page because mds3_page_upload_id named a post
 * that did not exist (Vikunja #29, llm-task 048b5b44). The option is now cleared and the
 * settings screen says why the row is empty, instead of reading as never configured.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/page-option-health-fixture.php
 */

use MillionDollarScript\V3\Admin\Admin;
use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Setup\PageOptionAudit;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$fails = [];
$check = function ($cond, $msg) use (&$fails) {
    if (!$cond) {
        $fails[] = $msg;
    }
};

$call = static function ($method, array $args = []) {
    $method = new ReflectionMethod(Admin::class, $method);
    $method->setAccessible(true);

    return $method->invokeArgs(new Admin(), $args);
};

$capture = static function ($method, array $args = []) use ($call) {
    ob_start();
    $call($method, $args);

    return (string) ob_get_clean();
};

$role = 'upload';
$option = PageOptionAudit::option_name($role);

// Remember what the site had, so this fixture leaves it exactly as it found it.
$original_option = get_option($option, false);
$original_cleared = get_option(PageOptionAudit::CLEARED_OPTION, false);

// Any id that no longer resolves to a post. The exact number does not matter; that it is
// gone is the case under test.
$missing_id = 999999999;
$live_page_id = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'MDS3 fixture live page ' . current_time('U'),
    'post_content' => '',
], true);
$trash_page_id = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'MDS3 fixture trashed page ' . current_time('U'),
    'post_content' => '',
], true);
wp_trash_post($trash_page_id);
$attachment_id = wp_insert_post([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'post_title' => 'MDS3 fixture attachment ' . current_time('U'),
], true);

// 1. A dead post id is cleared and remembered.
update_option($option, (int) $missing_id, false);
update_option(PageOptionAudit::CLEARED_OPTION, [], false);
$cleared = PageOptionAudit::run();
$check(in_array($role, $cleared, true), 'the audit did not report the broken ' . $role . ' page option');
$check(absint(get_option($option, 0)) === 0, 'the broken page option still holds a dead post id');
$check(PageOptionAudit::was_cleared($role), 'the audit did not remember clearing the option, so the screen cannot explain the empty row');

// 2. A trashed page is as broken as a missing one, and so is a post that is not a page.
update_option(PageOptionAudit::option_name('manage'), (int) $trash_page_id, false);
update_option(PageOptionAudit::option_name('thank-you'), (int) $attachment_id, false);
$second = PageOptionAudit::run();
$check(in_array('manage', $second, true), 'a trashed page was accepted as usable');
$check(in_array('thank-you', $second, true), 'an option pointing at a non-page post was accepted as usable');

// 3. A real page is left alone, and pointing a previously broken role at one clears the
//    explanation again.
update_option($option, (int) $live_page_id, false);
$third = PageOptionAudit::run();
$check(!in_array($role, $third, true), 'the audit touched a page option that points at a live page');
$check(absint(get_option($option, 0)) === (int) $live_page_id, 'a working page option was changed');
$check(!PageOptionAudit::was_cleared($role), 'the explanation was still shown after the option was fixed');

// 4. The settings screen explains a cleared role instead of showing a blank row.
update_option($option, 0, false);
update_option(PageOptionAudit::CLEARED_OPTION, [$role => time()], false);
$map = $capture('settings_page_map', []);
$check(
    strpos($map, 'the saved page no longer exists') !== false,
    'the page map does not explain a cleared page option'
);

// Nothing else on the site is reported as missing by this fixture's own doing; the
// detection used by the Setup screen must agree with the audit about a trashed page.
$missing_roles = $call('missing_standard_pages');
$check(
    isset($missing_roles['manage']),
    'the Setup screen still counts a trashed page as a page, while the audit clears it'
);

// Put the site back.
foreach ([$live_page_id, $trash_page_id, $attachment_id] as $post_id) {
    if ($post_id && !is_wp_error($post_id)) {
        wp_delete_post((int) $post_id, true);
    }
}
foreach (['upload' => $original_option, 'manage' => false, 'thank-you' => false] as $type => $value) {
    $name = PageOptionAudit::option_name((string) $type);
    if ($value === false) {
        delete_option($name);
    } else {
        update_option($name, $value, false);
    }
}
if ($original_cleared === false) {
    delete_option(PageOptionAudit::CLEARED_OPTION);
} else {
    update_option(PageOptionAudit::CLEARED_OPTION, $original_cleared, false);
}

// An unmapped page role stores 0, and get_post(0) hands back whichever post the running
// screen happens to have loaded. Asked about 0 directly, the audit reported that post as a
// mapped page: a Setup screen told the site every role was mapped while its Thank You page
// did not exist, and the option to create it stayed hidden.
$loaded_by_screen = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Page Option Health Fixture Loaded Post',
]);
$previous_global = $GLOBALS['post'] ?? null;
$GLOBALS['post'] = get_post($loaded_by_screen);
$check(false === PageOptionAudit::is_usable_page(0), 'is_usable_page(0) reported the post the screen loaded as a mapped page.');
$check(false === PageOptionAudit::is_usable_page(''), 'is_usable_page("") reported the post the screen loaded as a mapped page.');
$check(true === PageOptionAudit::is_usable_page($loaded_by_screen), 'is_usable_page() stopped accepting a published page given by id.');
$GLOBALS['post'] = $previous_global;
wp_delete_post($loaded_by_screen, true);

if ($fails) {
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

echo 'MDS3 page option health fixture passed.' . PHP_EOL;
exit(0);
