<?php
/**
 * WP-CLI fixture for shared extension block registration.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/million-dollar-script/tests/rewrite/extension-blocks-fixture.php
 */

use MillionDollarScript\V3\Blocks\EditorBlocks;

if (!defined('ABSPATH')) {
    exit;
}

function mds3_extension_blocks_fixture_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

add_filter('million-dollar-script/editor/extension/blocks', static function ($blocks) {
    $blocks[] = [
        'name' => 'mds-fixture/demo',
        'title' => 'Fixture Extension Block',
        'description' => 'Fixture block rendered by an extension callback.',
        'icon' => 'admin-plugins',
        'category' => 'widgets',
        'shortcode' => 'mds_fixture_demo',
        'keywords' => ['fixture', '<b>bad</b>'],
        'attributes' => [
            'heading' => [
                'type' => 'string',
                'default' => 'Fixture heading',
            ],
            'enabled' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'item_id' => [
                'type' => 'number',
                'default' => 0,
            ],
        ],
        'controls' => [
            [
                'attribute' => 'item_id',
                'type' => 'entity',
                'label' => 'Fixture item',
                'help' => 'Choose an item or enter a custom ID.',
                'empty_label' => 'First fixture item',
                'custom_label' => 'Custom fixture item ID',
                'custom_help' => 'Custom fixture ID help.',
                'options' => [
                    [
                        'label' => '<b>Fixture option</b>',
                        'value' => '42',
                    ],
                ],
            ],
            [
                'attribute' => 'heading',
                'type' => 'text',
                'label' => 'Heading',
                'help' => 'Fixture heading help.',
            ],
            [
                'attribute' => 'enabled',
                'type' => 'toggle',
                'label' => 'Enabled',
            ],
        ],
        'preview' => [
            'title' => 'Fixture Preview',
            'description' => 'Fixture preview description.',
            'rows' => ['First row', 'Second row'],
        ],
        'render_callback' => static function (array $attributes) {
            return '<div class="mds-fixture-demo-block">' . esc_html($attributes['heading'] ?? '') . '</div>';
        },
    ];

    $blocks[] = [
        'name' => 'not valid',
        'title' => '<script>Bad</script>',
        'render_callback' => static function () {
            return 'bad';
        },
    ];

    $blocks[] = [
        'name' => 'mds-fixture/no-callback',
        'title' => 'No callback',
    ];

    return $blocks;
});

$editor_blocks = new EditorBlocks();
$register = new ReflectionMethod($editor_blocks, 'register_extension_blocks');
$register->setAccessible(true);
$register->invoke($editor_blocks, 3);

$registry = \WP_Block_Type_Registry::get_instance();
mds3_extension_blocks_fixture_assert($registry->is_registered('mds-fixture/demo'), 'Valid extension block was not registered.');
mds3_extension_blocks_fixture_assert(!$registry->is_registered('mds-fixture/no-callback'), 'Extension block without callback was registered.');
$registered = $registry->get_registered('mds-fixture/demo');
mds3_extension_blocks_fixture_assert('million-dollar-script' === $registered->category, 'Extension block did not use the Million Dollar Script category.');
mds3_extension_blocks_fixture_assert(in_array('MDS', $registered->keywords, true), 'Extension block was missing the MDS search keyword.');
mds3_extension_blocks_fixture_assert(in_array('Million Dollar Script', $registered->keywords, true), 'Extension block was missing the Million Dollar Script search keyword.');

$html = render_block([
    'blockName' => 'mds-fixture/demo',
    'attrs' => [
        'heading' => '<strong>Fixture output</strong>',
        'enabled' => true,
    ],
    'innerBlocks' => [],
    'innerHTML' => '',
    'innerContent' => [],
]);
mds3_extension_blocks_fixture_assert(false !== strpos($html, 'Fixture output'), 'Extension block did not render through its callback.');
mds3_extension_blocks_fixture_assert(false === strpos($html, '<strong>'), 'Extension block callback output was not escaped.');

$config_method = new ReflectionMethod($editor_blocks, 'extension_block_editor_config');
$config_method->setAccessible(true);
$config = $config_method->invoke($editor_blocks);
$match = null;
foreach ($config as $item) {
    if (($item['name'] ?? '') === 'mds-fixture/demo') {
        $match = $item;
        break;
    }
}

mds3_extension_blocks_fixture_assert(is_array($match), 'Extension block editor config was missing.');
mds3_extension_blocks_fixture_assert(empty($match['render_callback']), 'Editor config leaked render_callback.');
mds3_extension_blocks_fixture_assert('Fixture Extension Block' === ($match['title'] ?? ''), 'Extension block title was not sanitized as expected.');
mds3_extension_blocks_fixture_assert('mds_fixture_demo' === ($match['shortcode'] ?? ''), 'Extension block shortcode was not exposed.');
mds3_extension_blocks_fixture_assert(3 === count($match['controls'] ?? []), 'Extension block controls were not exposed.');
mds3_extension_blocks_fixture_assert('entity' === ($match['controls'][0]['type'] ?? ''), 'Extension block entity control type was not exposed.');
mds3_extension_blocks_fixture_assert('Fixture option' === ($match['controls'][0]['options'][0]['label'] ?? ''), 'Extension block entity option label was not sanitized.');
mds3_extension_blocks_fixture_assert('42' === ($match['controls'][0]['options'][0]['value'] ?? ''), 'Extension block entity option value was not exposed.');
mds3_extension_blocks_fixture_assert('First fixture item' === ($match['controls'][0]['emptyLabel'] ?? ''), 'Extension block entity empty label was not exposed.');
mds3_extension_blocks_fixture_assert('Custom fixture item ID' === ($match['controls'][0]['customLabel'] ?? ''), 'Extension block entity custom label was not exposed.');
mds3_extension_blocks_fixture_assert('million-dollar-script' === ($match['category'] ?? ''), 'Editor config did not use the Million Dollar Script category.');
mds3_extension_blocks_fixture_assert(in_array('fixture', $match['keywords'] ?? [], true), 'Editor config did not expose extension keywords.');
mds3_extension_blocks_fixture_assert(in_array('bad', $match['keywords'] ?? [], true), 'Editor config did not sanitize extension keywords.');

echo "Extension blocks fixture passed.\n";
