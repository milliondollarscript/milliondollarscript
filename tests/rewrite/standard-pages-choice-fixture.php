<?php
/**
 * WP-CLI fixture: a new site gets the pages it needs, not every page that exists.
 *
 * The standard-pages step created every page role it knew about, so a site that
 * shows payment in a dialog, never asks anyone to confirm an order and has no
 * advertiser list still ended up with a Payment page, a Confirm Order page and an
 * Advertiser List page — three pages to wonder about (llm-task fca7aae6, from the
 * 2026-09-06 direction that the extra pages be offered rather than imposed).
 *
 * What must not change: the pages a store works with are always there, and a page
 * that already exists — assigned here, or detected in an old Million Dollar Script 2
 * site — is used whatever this form says. Only creating a page is the choice.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/standard-pages-choice-fixture.php
 */

use MillionDollarScript\V3\Admin\Admin;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Setup\PageOptionAudit;
use MillionDollarScript\V3\Support\DB;

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

$all_types = array_keys(PageRepository::standard_labels());
$baseline_options = [];
foreach ($all_types as $type) {
    $baseline_options[$type] = get_option(PageOptionAudit::option_name($type));
}
$baseline_pages = array_map('intval', (array) $wpdb->get_col(
    'SELECT ID FROM ' . DB::ident($wpdb->posts) . " WHERE post_type = 'page'"
));

// A role left unconfigured on purpose, and a role a store cannot work without.
$declined = array('thank-you', 'no-orders');
$needed = 'upload';

$grid_id = 0;
$fixture_grid = 0;
$created_pages = [];

$page_for_role = static function ($type) {
    return absint(get_option(PageOptionAudit::option_name($type), 0));
};

try {
    // The two sets the whole screen is built from: what is always created, and
    // what is offered. They must cover the page set exactly once, or the form
    // either nags about a page a store needs or hides one it cannot do without.
    $core = PageRepository::core_types();
    $optional = array_keys(PageRepository::optional_labels());
    $check(array_values(array_intersect($core, $optional)) === [], 'a page role is both required and optional');
    $union = $core;
    array_push($union, ...$optional);
    sort($union);
    $expected = $all_types;
    sort($expected);
    $check($union === $expected, 'the required and optional page sets do not cover the page set: ' . var_export(array_diff($expected, $union), true));
    $check(in_array($needed, $core, true), 'the Upload page is not treated as a page a store needs');

    $grid = (new GridRepository())->first_active();
    if (!$grid) {
        $grid = (new GridRepository())->create([
            'title' => 'Page Choice Fixture Grid',
            'width' => 100,
            'height' => 100,
            'block_width' => 10,
            'block_height' => 10,
            'price_per_block' => 2,
            'currency' => 'USD',
            'status' => 'active',
        ]);
        if (is_wp_error($grid) || !$grid) {
            throw new RuntimeException('fixture grid could not be created: ' . (is_wp_error($grid) ? $grid->get_error_message() : 'unknown'));
        }
        $fixture_grid = (int) $grid->id();
    }
    $grid_id = (int) $grid->id();

    // Take the roles back to the state a new site is in: no page assigned, and no
    // page of their own to adopt, because a page this plugin made is never offered
    // as a migrated page.
    foreach (array_merge($declined, array($needed)) as $type) {
        delete_option(PageOptionAudit::option_name($type));
    }

    // Which roles a store needs have no page assigned. The step has to fill every
    // one of them whatever it was told to create, and an unassigned role is filled
    // by making a page - a page the site already has is not silently re-pointed at.
    $needed_unfilled = array_values(array_filter(
        PageRepository::core_types(),
        static function ($type) use ($page_for_role) {
            return !$page_for_role($type);
        }
    ));
    $check($needed_unfilled, 'this site has no unfilled page role a store needs, so the fixture proves nothing');

    // 1. Nothing chosen: the pages a store needs appear, the others do not.
    $run = Admin::ensure_standard_pages_core($grid_id, [], array());
    foreach (PageRepository::core_types() as $type) {
        $check($page_for_role($type) > 0, 'the ' . PageRepository::labels()[$type] . ' page was not created when nothing was chosen');
    }
    foreach ($declined as $type) {
        $check(0 === $page_for_role($type), 'the ' . $type . ' page was created without being asked for');
    }
    $skipped = is_array($run['skipped'] ?? null) ? $run['skipped'] : [];
    foreach ($declined as $type) {
        $check(in_array($type, $skipped, true), 'the run did not report that it left the ' . $type . ' page unset');
    }
    $check(!in_array($needed, $skipped, true), 'the run reported the Upload page as left unset');

    $created_pages = array_values(array_diff(
        array_map('intval', (array) $wpdb->get_col('SELECT ID FROM ' . DB::ident($wpdb->posts) . " WHERE post_type = 'page'")),
        $baseline_pages
    ));
    $check(
        count($created_pages) === count($needed_unfilled),
        'the step made ' . count($created_pages) . ' pages where ' . count($needed_unfilled) . ' were missing: ' . var_export($created_pages, true)
    );

    // 2. Asked for one: it appears, the rest stay out of the way, and the page
    // created a moment ago is not created twice.
    $upload_page = $page_for_role($needed);
    $run = Admin::ensure_standard_pages_core($grid_id, [], array('thank-you'));
    $check($page_for_role('thank-you') > 0, 'the Thank You page was not created when it was asked for');
    $check(0 === $page_for_role('no-orders'), 'the No Orders page was created without being asked for');
    $check($page_for_role($needed) === $upload_page, 'a second Upload page was created beside the first');

    // 3. No choice offered at all (an older caller): every role is filled, as before.
    Admin::ensure_standard_pages_core($grid_id, [], null);
    $check($page_for_role('no-orders') > 0, 'the No Orders page was not created when no choice was offered');

    // 4. A role that already has a page keeps it whatever the form says — a site
    // owner who assigned a page should not lose it because a box was left empty.
    $assigned_thanks = $page_for_role('thank-you');
    $check($assigned_thanks > 0, 'no Thank You page to assign');
    update_option(PageOptionAudit::option_name('thank-you'), $assigned_thanks, false);
    $run = Admin::ensure_standard_pages_core($grid_id, [], array());
    $check($page_for_role('thank-you') === $assigned_thanks, 'an assigned Thank You page was dropped by leaving the box empty');

    // 5. A role whose page has been sent to the trash is a missing role, not a filled
    // one. The screen counts a trashed page as no page; if this call accepted it, the
    // click creates nothing, says nothing, and the step stays pending - which is what a
    // site owner notices (Ryan, 2026-10-16: "I clicked create missing pages even though
    // it seemed like none were missing").
    $trashed_page = $page_for_role($needed);
    $check($trashed_page > 0, 'no Upload page available to trash for the dead-page case');
    wp_trash_post($trashed_page);
    Admin::ensure_standard_pages_core($grid_id, [], array());
    $replacement = $page_for_role($needed);
    $check($replacement > 0 && $replacement !== $trashed_page, 'the repair left the role pointing at a trashed page');
    $check('publish' === get_post_field('post_status', $replacement), 'the replacement Upload page is not published');
    $check($needed === (string) get_post_meta($replacement, '_mds3_page_type', true), 'the replacement page does not say what job it has');
    // Making a page is something the screen can report, so the run has to leave that
    // evidence behind rather than a silent redirect.
    $check(false !== get_transient('mds3_pages_created_notice'), 'a run that created a page left the screen nothing to say');
    delete_transient('mds3_pages_created_notice');

    // 6. With nothing to create, the run must still leave an answer behind. The button
    // that adds no pages is the one that reads as broken, so the screen decides what to
    // say from whether this run gave any role a page - not from the absence of a message.
    $is_changed = static function (array $before) {
        $method = new ReflectionMethod(Admin::class, 'standard_pages_changed');
        $method->setAccessible(true);

        return (bool) $method->invoke(null, $before);
    };
    $before_steady = static function () {
        $ids = [];
        foreach (array_keys(PageRepository::standard_labels()) as $type) {
            $ids[$type] = absint(get_option(PageOptionAudit::option_name($type), 0));
        }

        return $ids;
    };
    $steady = $before_steady();
    Admin::ensure_standard_pages_core($grid_id, [], array());
    $check(!$is_changed($steady), 'a run that changed no page role was counted as a change, so the screen would stay silent');
    $check(false === get_transient('mds3_pages_created_notice'), 'a run that created pages reported nothing, or the no-op notice is wrong');
    $repaired = $before_steady();
    $trashed_again = $page_for_role($needed);
    wp_trash_post($trashed_again);
    Admin::ensure_standard_pages_core($grid_id, [], array());
    $check($is_changed($repaired), 'a run that replaced a trashed page was not counted as a change');
    delete_transient('mds3_pages_created_notice');

    // The chosen pages are real pages with the right job, not placeholders.
    foreach (array('thank-you', $needed) as $type) {
        $post_id = $page_for_role($type);
        $check($post_id > 0 && 'publish' === get_post_field('post_status', $post_id), $type . ' page is not a published page');
        $check($type === (string) get_post_meta($post_id, '_mds3_page_type', true), $type . ' page does not say what job it has');
        $check(false !== strpos((string) get_post_field('post_content', $post_id), '[mds3_page type="' . $type . '"'), $type . ' page has no page to show');
    }
} finally {
    foreach ($all_types as $type) {
        if (false === $baseline_options[$type] || null === $baseline_options[$type]) {
            delete_option(PageOptionAudit::option_name($type));
        } else {
            update_option(PageOptionAudit::option_name($type), $baseline_options[$type], false);
        }
    }

    // Remove the pages this run made — the page, its role, and its entry in the
    // page registry — and leave every page the site already had.
    $now_pages = array_map('intval', (array) $wpdb->get_col('SELECT ID FROM ' . DB::ident($wpdb->posts) . " WHERE post_type = 'page'"));
    foreach (array_values(array_diff($now_pages, $baseline_pages)) as $page_id) {
        if (DB::table_exists(DB::table('pages'))) {
            $wpdb->delete(DB::table('pages'), ['post_id' => $page_id]);
        }
        wp_delete_post($page_id, true);
    }

    if ($fixture_grid) {
        (new GridRepository())->delete($fixture_grid);
    }
    delete_transient('mds3_pages_created_notice');
    delete_transient('mds3_pages_failed_notice');
}

if ($fails) {
    fwrite(STDERR, implode(PHP_EOL, $fails) . PHP_EOL);
    exit(1);
}

echo 'MDS3 standard pages choice fixture passed.' . PHP_EOL;
