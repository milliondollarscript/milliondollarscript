<?php
/**
 * A site chooses what a window change does to a visitor's view, and the choice
 * has to reach the page: the grid shell carries it as data-fit-on-resize, which
 * grid.js reads before it re-fits anything. This checks all three states - not
 * set at all (a site that never touched the setting, which must get the shipped
 * default of fitting), set to yes, and set to no.
 *
 * Run with:
 * scripts/wp-fixture-suite.sh grid-fit-on-resize-fixture
 */

if (!defined('ABSPATH')) {
    exit;
}

use MillionDollarScript\V3\Grid\GridRepository;

$failures = [];
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$original_settings = get_option('mds3_settings', []);
$grid_id = 0;

$render_shell = static function () use ($grid_id) {
    return do_shortcode('[mds_grid id=' . (int) $grid_id . ']');
};

$attribute_in = static function ($html) {
    if (preg_match('/data-fit-on-resize="([^"]*)"/', $html, $match)) {
        return $match[1];
    }
    return null;
};

try {
    $grid = (new GridRepository())->create([
        'title' => 'Grid Fit On Resize Fixture',
        'width' => 10,
        'height' => 10,
        'block_width' => 10,
        'block_height' => 10,
        'price_per_block' => 25,
        'currency' => 'USD',
        'status' => 'active',
    ]);
    if (is_wp_error($grid) || !$grid) {
        throw new RuntimeException('Could not create the fixture grid: ' . (is_wp_error($grid) ? $grid->get_error_message() : 'no grid'));
    }
    $grid_id = (int) $grid->id();

    // A site that has the option but never chose it gets the default the plugin
    // ships with, which is to fit.
    $settings = is_array($original_settings) ? $original_settings : [];
    unset($settings['grid-fit-on-resize']);
    update_option('mds3_settings', $settings);

    $html = $render_shell();
    $assert(false !== strpos($html, 'mds3-grid-shell'), 'The shortcode did not render a grid shell at all.');
    $assert('true' === $attribute_in($html), 'A site that never set the option should render data-fit-on-resize="true"; got ' . var_export($attribute_in($html), true));

    foreach (['no' => 'false', 'yes' => 'true'] as $choice => $expected) {
        $settings = is_array(get_option('mds3_settings', [])) ? (array) get_option('mds3_settings', []) : [];
        $settings['grid-fit-on-resize'] = $choice;
        update_option('mds3_settings', $settings);

        $value = $attribute_in($render_shell());
        $assert($expected === $value, 'grid-fit-on-resize=' . $choice . ' should reach the page as data-fit-on-resize="' . $expected . '", got ' . var_export($value, true));
    }
} catch (Throwable $e) {
    $failures[] = 'Fixture error: ' . $e->getMessage();
} finally {
    update_option('mds3_settings', $original_settings);
    if ($grid_id) {
        $repository = new GridRepository();
        if (method_exists($repository, 'delete')) {
            $repository->delete($grid_id);
        }
    }
}

echo $failures ? "FAIL\n  - " . implode("\n  - ", $failures) . "\n" : "PASS grid-fit-on-resize-fixture\n";
