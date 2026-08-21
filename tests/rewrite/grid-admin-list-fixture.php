<?php
/**
 * WP-CLI admin grid list fixture for Million Dollar Script.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/grid-admin-list-fixture.php
 */

use MillionDollarScript\V3\Grid\GridRepository;

if (!defined('ABSPATH')) {
    exit;
}

$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admin) {
    throw new RuntimeException('Grid admin list fixture requires an administrator user.');
}
wp_set_current_user(absint($admin[0]));

$repo = new GridRepository();
$prefix = 'Grid Admin List Fixture ' . wp_generate_uuid4();
$created = [];
$extra_column_key = 'fixture_note';

$create_grid = static function (array $data) use ($repo, &$created) {
    $grid = $repo->create($data);
    if (is_wp_error($grid)) {
        throw new RuntimeException('Could not create grid admin list fixture row: ' . $grid->get_error_message());
    }
    $created[] = $grid->id();

    return $grid;
};

$assert_same = static function ($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
};

$assert_contains = static function ($needle, $haystack, $message) {
    if (false === strpos((string) $haystack, (string) $needle)) {
        throw new RuntimeException($message . ' Missing: ' . $needle);
    }
};

$assert_not_contains = static function ($needle, $haystack, $message) {
    if (false !== strpos((string) $haystack, (string) $needle)) {
        throw new RuntimeException($message . ' Unexpected: ' . $needle);
    }
};

add_filter('million-dollar-script/admin/grid/list/extra/columns', static function ($columns, $context) use ($prefix, $extra_column_key) {
    if (($context['search'] ?? '') !== $prefix) {
        return $columns;
    }

    $columns[$extra_column_key] = ['label' => 'Fixture <b>Note</b>'];
    $columns['id'] = 'Reserved Core Column';
    $columns['bad key'] = '';

    return $columns;
}, 10, 2);

add_filter('million-dollar-script/admin/grid/list/column/html', static function ($html, $column_key, $grid, $context) use ($prefix, $extra_column_key) {
    if (($context['search'] ?? '') !== $prefix || $column_key !== $extra_column_key) {
        return $html;
    }

    return '<strong>Fixture Cell</strong><script>alert(1)</script>';
}, 10, 4);

add_filter('million-dollar-script/admin/grid/list/row/actions', static function ($actions, $grid, $context) use ($prefix) {
    if (($context['search'] ?? '') !== $prefix) {
        return $actions;
    }

    $actions['unsafe'] = [
        'label' => 'Unsafe Action',
        'url' => 'javascript:alert(1)',
    ];
    $actions['safe'] = [
        'label' => 'Fixture Action',
        'url' => admin_url('admin.php?page=mds3-grids&grid_id=' . $grid->id()),
        'class' => 'mds-fixture-action',
    ];

    return $actions;
}, 10, 3);

try {
    $alpha = $create_grid([
        'title' => $prefix . ' Alpha',
        'width' => 1000,
        'height' => 1000,
        'block_width' => 10,
        'block_height' => 10,
        'renderer_mode' => 'classic',
        'status' => 'active',
    ]);
    $bravo = $create_grid([
        'title' => $prefix . ' Bravo',
        'width' => 2000,
        'height' => 1000,
        'block_width' => 10,
        'block_height' => 10,
        'renderer_mode' => 'openlayers',
        'status' => 'paused',
    ]);
    $charlie = $create_grid([
        'title' => $prefix . ' Charlie',
        'width' => 500,
        'height' => 500,
        'block_width' => 10,
        'block_height' => 10,
        'renderer_mode' => 'auto',
        'status' => 'archived',
    ]);
    $delta = $create_grid([
        'title' => $prefix . ' Delta',
        'width' => 1200,
        'height' => 1200,
        'block_width' => 10,
        'block_height' => 10,
        'renderer_mode' => 'classic',
        'status' => 'active',
    ]);
    $echo = $create_grid([
        'title' => $prefix . ' Echo',
        'width' => 3000,
        'height' => 1000,
        'block_width' => 10,
        'block_height' => 10,
        'renderer_mode' => 'openlayers',
        'status' => 'archived',
    ]);

    $assert_same(5, $repo->admin_count('all', $prefix), 'Search-scoped all-grid admin count was incorrect.');
    $assert_same(2, $repo->admin_count('active', $prefix), 'Search-scoped active admin count was incorrect.');
    $assert_same(1, $repo->admin_count('paused', $prefix), 'Search-scoped paused admin count was incorrect.');
    $assert_same(2, $repo->admin_count('archived', $prefix), 'Search-scoped archived admin count was incorrect.');
    $assert_same(5, $repo->admin_count('invalid-status', $prefix), 'Invalid admin status should fall back to all matching rows.');

    $counts = $repo->admin_status_counts($prefix);
    $assert_same(5, absint($counts['all'] ?? 0), 'Admin status counts all total was incorrect.');
    $assert_same(2, absint($counts['active'] ?? 0), 'Admin status counts active total was incorrect.');
    $assert_same(1, absint($counts['paused'] ?? 0), 'Admin status counts paused total was incorrect.');
    $assert_same(2, absint($counts['archived'] ?? 0), 'Admin status counts archived total was incorrect.');

    $page_one = $repo->admin_page([
        'search' => $prefix,
        'orderby' => 'title',
        'order' => 'asc',
        'page' => 1,
        'per_page' => 2,
    ]);
    $assert_same(2, count($page_one), 'Admin grid list page one size was incorrect.');
    $assert_same($alpha->id(), $page_one[0]->id(), 'Admin grid title sorting did not place Alpha first.');
    $assert_same($bravo->id(), $page_one[1]->id(), 'Admin grid title sorting did not place Bravo second.');

    $page_three = $repo->admin_page([
        'search' => $prefix,
        'orderby' => 'title',
        'order' => 'asc',
        'page' => 3,
        'per_page' => 2,
    ]);
    $assert_same(1, count($page_three), 'Admin grid list page three size was incorrect.');
    $assert_same($echo->id(), $page_three[0]->id(), 'Admin grid pagination did not return the expected final row.');

    $largest = $repo->admin_page([
        'search' => $prefix,
        'orderby' => 'dimensions',
        'order' => 'desc',
        'page' => 1,
        'per_page' => 1,
    ]);
    $assert_same(1, count($largest), 'Admin grid dimensions sort did not return a row.');
    $assert_same($echo->id(), $largest[0]->id(), 'Admin grid dimensions sort did not place the largest grid first.');

    $renderer_sorted = $repo->admin_page([
        'search' => $prefix,
        'orderby' => 'renderer',
        'order' => 'asc',
        'page' => 1,
        'per_page' => 5,
    ]);
    $assert_same(5, count($renderer_sorted), 'Admin grid renderer sorting did not return the expected rows.');
    $assert_same($charlie->id(), $renderer_sorted[0]->id(), 'Admin grid renderer sorting did not place auto before classic/openlayers.');

    $archived = $repo->admin_page([
        'search' => $prefix,
        'status' => 'archived',
        'orderby' => 'id',
        'order' => 'asc',
        'page' => 1,
        'per_page' => 10,
    ]);
    $assert_same([$charlie->id(), $echo->id()], array_map(static fn($grid) => $grid->id(), $archived), 'Admin grid status filter returned the wrong archived rows.');

    $grid_list = [
        'status' => 'all',
        'search' => $prefix,
        'orderby' => 'id',
        'order' => 'desc',
        'paged' => 1,
        'per_page' => 20,
        'total' => 5,
        'total_pages' => 1,
        'counts' => $counts,
    ];
    $html = \MillionDollarScript\V3\Support\Template::render('admin/pages/grids.php', [
        'active_tab' => 'grid-details',
        'currency_locked' => false,
        'editing' => null,
        'grid_currency' => '',
        'grid_list' => $grid_list,
        'grid_tabs' => [],
        'grids' => [$alpha],
        'renderer_modes' => GridRepository::renderer_modes(),
    ], new \MillionDollarScript\V3\Admin\Admin());

    $assert_contains('Fixture Note', $html, 'Grid list extra column label was not rendered.');
    $assert_contains('<strong>Fixture Cell</strong>', $html, 'Grid list extra column HTML was not rendered.');
    $assert_contains('Fixture Action', $html, 'Grid list extra row action was not rendered.');
    $assert_not_contains('Reserved Core Column', $html, 'Grid list extra columns should not override core columns.');
    $assert_not_contains('<script>', $html, 'Grid list extra column HTML should be sanitized.');
    $assert_not_contains('javascript:alert', $html, 'Grid list extra row action URLs should reject unsafe protocols.');
} finally {
    foreach (array_reverse($created) as $grid_id) {
        $repo->delete($grid_id);
    }
}

WP_CLI::success('Grid admin list fixture passed.');
