<?php
/**
 * Native WordPress editor blocks.
 *
 * @package MillionDollarScript\V3\Blocks
 */

namespace MillionDollarScript\V3\Blocks;

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Pages\PageShortcodes;
use MillionDollarScript\V3\Setup\LegacyPlugin;
use MillionDollarScript\V3\Support\BrowserConfig;
use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class EditorBlocks implements Component {

    private const BLOCK_CATEGORY = 'million-dollar-script';

    public function register() {
        add_action('init', [$this, 'register_blocks']);
        add_action('enqueue_block_editor_assets', [$this, 'editor_assets']);
        add_action('enqueue_block_assets', [$this, 'block_canvas_assets']);
        add_filter('block_categories_all', [$this, 'block_categories'], 10, 2);
    }

    public function register_blocks() {
        $api_version = $this->block_api_version();

        register_block_type('mds/grid', [
            'api_version' => $api_version,
            'title' => __('Grid Embed', 'million-dollar-script'),
            'category' => self::BLOCK_CATEGORY,
            'icon' => 'grid-view',
            'description' => __('Embed a specific Million Dollar Script grid directly in this page.', 'million-dollar-script'),
            'keywords' => $this->block_keywords([__('grid', 'million-dollar-script'), __('pixels', 'million-dollar-script'), __('embed', 'million-dollar-script')]),
            'attributes' => [
                'id' => [
                    'type' => 'number',
                    'default' => 0,
                ],
                'readOnly' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'width' => [
                    'type' => 'string',
                    'default' => '100%',
                ],
                'height' => [
                    'type' => 'string',
                    'default' => '{height}',
                ],
                'renderer' => [
                    'type' => 'string',
                    'default' => 'auto',
                ],
                'showStats' => [
                    'type' => 'string',
                    'default' => 'inherit',
                ],
            ],
            'render_callback' => [$this, 'render_grid'],
        ]);

        register_block_type('mds/stats', [
            'api_version' => $api_version,
            'title' => __('Stats Widget', 'million-dollar-script'),
            'category' => self::BLOCK_CATEGORY,
            'icon' => 'chart-bar',
            'description' => __('Display a compact sold and available inventory widget.', 'million-dollar-script'),
            'keywords' => $this->block_keywords([__('stats', 'million-dollar-script'), __('inventory', 'million-dollar-script')]),
            'attributes' => [
                'id' => [
                    'type' => 'number',
                    'default' => 0,
                ],
                'unit' => [
                    'type' => 'string',
                    'default' => 'settings',
                ],
                'width' => [
                    'type' => 'string',
                    'default' => '240px',
                ],
                'numberColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'labelColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'backgroundColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'borderColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
            'render_callback' => [$this, 'render_stats'],
        ]);

        register_block_type('mds/page', [
            'api_version' => $api_version,
            'title' => __('Page Flow', 'million-dollar-script'),
            'category' => self::BLOCK_CATEGORY,
            'icon' => 'screenoptions',
            'description' => __('Display a Million Dollar Script page panel or flow.', 'million-dollar-script'),
            'keywords' => $this->block_keywords([__('order pixels', 'million-dollar-script'), __('customer flow', 'million-dollar-script')]),
            'attributes' => [
                'type' => [
                    'type' => 'string',
                    'default' => 'grid',
                ],
                'id' => [
                    'type' => 'number',
                    'default' => 0,
                ],
                'readOnly' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'width' => [
                    'type' => 'string',
                    'default' => '100%',
                ],
                'height' => [
                    'type' => 'string',
                    'default' => '{height}',
                ],
                'renderer' => [
                    'type' => 'string',
                    'default' => 'auto',
                ],
                'showStats' => [
                    'type' => 'string',
                    'default' => 'inherit',
                ],
                'unit' => [
                    'type' => 'string',
                    'default' => 'settings',
                ],
                'listLayout' => [
                    'type' => 'string',
                    'default' => 'list',
                ],
                'listColumns' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'listSearch' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'numberColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'labelColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'backgroundColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'borderColor' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
            'render_callback' => [$this, 'render_page'],
        ]);

        $this->register_extension_blocks($api_version);

        if (!LegacyPlugin::should_register_legacy_embeds()) {
            return;
        }

        foreach ($this->legacy_block_names() as $block_name) {
            if (\WP_Block_Type_Registry::get_instance()->is_registered($block_name)) {
                continue;
            }

            register_block_type($block_name, [
                'api_version' => $api_version,
                'title' => __('Legacy Million Dollar Script Embed', 'million-dollar-script'),
                'category' => self::BLOCK_CATEGORY,
                'icon' => 'grid-view',
                'description' => __('Render a Million Dollar Script 2 block through Million Dollar Script compatibility.', 'million-dollar-script'),
                'keywords' => $this->block_keywords([__('legacy', 'million-dollar-script'), __('MDS2', 'million-dollar-script')]),
                'attributes' => [
                    'data' => [
                        'type' => 'object',
                        'default' => [],
                    ],
                ],
                'render_callback' => [$this, 'render_legacy_block'],
            ]);
        }
    }

    public function editor_assets() {
        $script_path = MILLION_DOLLAR_SCRIPT_PATH . 'assets/mds3/js/blocks.js';

        wp_enqueue_script(
            'mds3-blocks',
            MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'js/blocks.js',
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n'],
            $this->asset_version($script_path),
            true
        );
        $this->enqueue_editor_styles();

        BrowserConfig::add('mds3-blocks', 'blocks', [
            'grids' => $this->grid_options(),
            'pageTypes' => $this->page_type_options(),
            'extensionBlocks' => $this->extension_block_editor_config(),
            'blockApiVersion' => $this->block_api_version(),
            'gridsAdminUrl' => admin_url('admin.php?page=mds3-grids'),
            'blockCategory' => self::BLOCK_CATEGORY,
        ]);
    }

    public function block_canvas_assets() {
        if (!is_admin()) {
            return;
        }

        $this->enqueue_editor_styles();
    }

    private function enqueue_editor_styles() {
        $style_path = MILLION_DOLLAR_SCRIPT_PATH . 'assets/mds3/css/blocks.css';

        wp_enqueue_style('mds3-blocks-editor', MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'css/blocks.css', [], $this->asset_version($style_path));
    }

    private function asset_version($path) {
        $mtime = file_exists((string) $path) ? filemtime((string) $path) : false;

        return $mtime ? MILLION_DOLLAR_SCRIPT_VERSION . '.' . $mtime : MILLION_DOLLAR_SCRIPT_VERSION;
    }

    public function block_categories(array $categories, $editor_context = null) {
        unset($editor_context);

        foreach ($categories as $category) {
            if (self::BLOCK_CATEGORY === (string) ($category['slug'] ?? '')) {
                return $categories;
            }
        }

        array_unshift($categories, [
            'slug' => self::BLOCK_CATEGORY,
            'title' => __('Million Dollar Script', 'million-dollar-script'),
            'icon' => 'grid-view',
        ]);

        return $categories;
    }

    public function render_grid(array $attributes) {
        $id = absint($attributes['id'] ?? 0);
        $read_only = !empty($attributes['readOnly']) ? 'true' : 'false';
        $width = sanitize_text_field($attributes['width'] ?? '100%');
        $height = sanitize_text_field($attributes['height'] ?? '{height}');
        $renderer = GridRepository::normalize_renderer_mode($attributes['renderer'] ?? 'auto');
        $show_stats = $this->stats_visibility($attributes['showStats'] ?? 'inherit');

        return do_shortcode(sprintf(
            '[mds_grid id="%d" read_only="%s" width="%s" height="%s" renderer="%s" show_stats="%s"]',
            $id,
            $read_only,
            esc_attr($width),
            esc_attr($height),
            esc_attr($renderer),
            esc_attr($show_stats)
        ));
    }

    public function render_stats(array $attributes) {
        $id = absint($attributes['id'] ?? 0);
        $unit = sanitize_key((string) ($attributes['unit'] ?? 'settings')) ?: 'settings';
        $width = sanitize_text_field((string) ($attributes['width'] ?? '240px'));

        return (new PageShortcodes())->render([
            'type' => 'stats',
            'grid_id' => $id,
            'unit' => $unit,
            'width' => $width,
            'number_color' => $this->hex_color($attributes['numberColor'] ?? ''),
            'label_color' => $this->hex_color($attributes['labelColor'] ?? ''),
            'background_color' => $this->hex_color($attributes['backgroundColor'] ?? ''),
            'border_color' => $this->hex_color($attributes['borderColor'] ?? ''),
        ]);
    }

    public function render_page(array $attributes) {
        $type = sanitize_key((string) ($attributes['type'] ?? 'grid'));
        if (!PageRepository::is_valid_type($type)) {
            $type = 'grid';
        }

        $args = [
            'type' => $type,
            'grid_id' => absint($attributes['id'] ?? 0),
            'width' => sanitize_text_field((string) ($attributes['width'] ?? '100%')),
            'height' => sanitize_text_field((string) ($attributes['height'] ?? '{height}')),
            'read_only' => !empty($attributes['readOnly']) ? 'true' : 'false',
            'renderer' => GridRepository::normalize_renderer_mode($attributes['renderer'] ?? 'auto'),
            'show_stats' => $this->stats_visibility($attributes['showStats'] ?? 'inherit'),
            'unit' => sanitize_key((string) ($attributes['unit'] ?? 'settings')) ?: 'settings',
            'list_layout' => sanitize_key((string) ($attributes['listLayout'] ?? 'list')),
            'list_columns' => sanitize_text_field((string) ($attributes['listColumns'] ?? '')),
            'list_search' => !empty($attributes['listSearch']) ? 'yes' : 'no',
            'number_color' => $this->hex_color($attributes['numberColor'] ?? ''),
            'label_color' => $this->hex_color($attributes['labelColor'] ?? ''),
            'background_color' => $this->hex_color($attributes['backgroundColor'] ?? ''),
            'border_color' => $this->hex_color($attributes['borderColor'] ?? ''),
        ];

        if ('order' === $type) {
            $args['read_only'] = 'false';
        }

        return (new PageShortcodes())->render($args);
    }

    public function render_legacy_block(array $attributes) {
        $data = is_array($attributes['data'] ?? null) ? $attributes['data'] : [];
        $source = array_merge($data, $attributes);

        $type = sanitize_key((string) ($source['milliondollarscript_type'] ?? $source['mds_type'] ?? $source['type'] ?? 'grid'));
        $id = absint($source['milliondollarscript_id'] ?? $source['mds_id'] ?? $source['grid_id'] ?? $source['id'] ?? 0);
        $width = sanitize_text_field((string) ($source['milliondollarscript_width'] ?? $source['mds_width'] ?? $source['width'] ?? '100%'));
        $height = sanitize_text_field((string) ($source['milliondollarscript_height'] ?? $source['mds_height'] ?? $source['height'] ?? '{height}'));
        $renderer = GridRepository::normalize_renderer_mode($source['renderer'] ?? 'auto');

        return (new PageShortcodes())->legacy([
            'type' => $type,
            'grid_id' => $id,
            'width' => $width,
            'height' => $height,
            'renderer' => $renderer,
        ]);
    }

    private function grid_options() {
        $options = [];
        foreach ((new GridRepository())->all() as $grid) {
            $options[] = [
                'id' => $grid->id(),
                'title' => (string) $grid->get('title', __('Untitled grid', 'million-dollar-script')),
                'width' => absint($grid->get('width', 1000)),
                'height' => absint($grid->get('height', 1000)),
                'blockWidth' => absint($grid->get('block_width', 10)),
                'blockHeight' => absint($grid->get('block_height', 10)),
                'renderer' => GridRepository::normalize_renderer_mode($grid->settings()['renderer_mode'] ?? 'auto'),
            ];
        }

        return $options;
    }

    private function page_type_options() {
        $labels = PageRepository::labels();
        $descriptions = [
            'grid' => __('Show the public pixel grid.', 'million-dollar-script'),
            'order' => __('Show an interactive ordering grid for visitors to reserve pixels.', 'million-dollar-script'),
            'write-ad' => __('Continue the customer flow for writing ad content.', 'million-dollar-script'),
            'confirm-order' => __('Show order confirmation details when an order is in progress.', 'million-dollar-script'),
            'payment' => __('Send customers to the correct checkout or payment flow.', 'million-dollar-script'),
            'manage' => __('Let customers find and manage their pixel orders.', 'million-dollar-script'),
            'thank-you' => __('Show the order thank-you panel after checkout.', 'million-dollar-script'),
            'list' => __('Display published advertisers for a selected grid.', 'million-dollar-script'),
            'upload' => __('Let customers upload or update creative for an order.', 'million-dollar-script'),
            'no-orders' => __('Show the no-orders account panel.', 'million-dollar-script'),
            'stats' => __('Show sold and available inventory.', 'million-dollar-script'),
        ];

        $options = [];
        foreach (PageRepository::TYPES as $type) {
            $options[] = [
                'type' => $type,
                'label' => $labels[$type] ?? ucfirst(str_replace('-', ' ', $type)),
                'description' => $descriptions[$type] ?? '',
            ];
        }

        return $options;
    }

    private function register_extension_blocks($api_version) {
        foreach ($this->extension_block_definitions() as $definition) {
            if (empty($definition['render_callback']) || !is_callable($definition['render_callback'])) {
                continue;
            }

            if (\WP_Block_Type_Registry::get_instance()->is_registered($definition['name'])) {
                continue;
            }

            register_block_type($definition['name'], [
                'api_version' => $api_version,
                'title' => $definition['title'],
                'category' => $definition['category'],
                'icon' => $definition['icon'],
                'description' => $definition['description'],
                'keywords' => $definition['keywords'],
                'attributes' => $definition['attributes'],
                'render_callback' => $definition['render_callback'],
            ]);
        }
    }

    private function extension_block_editor_config() {
        $config = [];
        foreach ($this->extension_block_definitions() as $definition) {
            if (empty($definition['render_callback']) || !is_callable($definition['render_callback'])) {
                continue;
            }

            $config[] = [
                'name' => $definition['name'],
                'title' => $definition['title'],
                'description' => $definition['description'],
                'category' => $definition['category'],
                'icon' => $definition['icon'],
                'keywords' => $definition['keywords'],
                'attributes' => $definition['attributes'],
                'controls' => $definition['controls'],
                'preview' => $definition['preview'],
                'shortcode' => $definition['shortcode'],
            ];
        }

        return $config;
    }

    /**
     * Return sanitized extension block definitions.
     *
     * Extension definitions should include:
     * - name: block name such as mds-example/card.
     * - title, description, icon, category.
     * - attributes: WordPress block attribute schema.
     * - controls: optional generic editor controls.
     * - preview: optional editor preview title/description/rows.
     * - render_callback: extension-owned callable.
     *
     * @return array<int,array<string,mixed>>
     */
    private function extension_block_definitions() {
        /**
         * Register extension-owned editor blocks for Million Dollar Script.
         *
         * Blocks registered here are rendered by extension-owned PHP callbacks
         * and edited through the shared Million Dollar Script editor UI.
         *
         * @param array<int,array<string,mixed>> $definitions Extension block definitions.
         */
        $definitions = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/editor/extension/blocks', []);
        if (!is_array($definitions)) {
            return [];
        }

        $sanitized = [];
        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $name = $this->extension_block_name($definition['name'] ?? '');
            if (!$name) {
                continue;
            }

            $sanitized[] = [
                'name' => $name,
                'title' => sanitize_text_field((string) ($definition['title'] ?? $name)),
                'description' => sanitize_text_field((string) ($definition['description'] ?? '')),
                'category' => self::BLOCK_CATEGORY,
                'icon' => sanitize_key((string) ($definition['icon'] ?? 'screenoptions')) ?: 'screenoptions',
                'keywords' => $this->extension_block_keywords($definition['keywords'] ?? []),
                'attributes' => $this->extension_block_attributes($definition['attributes'] ?? []),
                'controls' => $this->extension_block_controls($definition['controls'] ?? []),
                'preview' => $this->extension_block_preview($definition['preview'] ?? []),
                'shortcode' => $this->extension_block_shortcode($definition['shortcode'] ?? ''),
                'render_callback' => $definition['render_callback'] ?? null,
            ];
        }

        return $sanitized;
    }

    private function block_keywords(array $keywords = []) {
        return $this->unique_keywords(array_merge([
            __('MDS', 'million-dollar-script'),
            __('Million Dollar Script', 'million-dollar-script'),
            __('million dollar script', 'million-dollar-script'),
        ], $keywords));
    }

    private function extension_block_keywords($keywords) {
        $keywords = is_array($keywords) ? $keywords : [];

        return $this->block_keywords(array_filter(array_map(static function ($keyword) {
            return is_scalar($keyword) ? (string) $keyword : '';
        }, $keywords)));
    }

    private function unique_keywords(array $keywords) {
        $unique = [];
        foreach ($keywords as $keyword) {
            $keyword = sanitize_text_field((string) $keyword);
            if ('' === $keyword) {
                continue;
            }

            $key = strtolower($keyword);
            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = $keyword;
        }

        return array_values($unique);
    }

    private function extension_block_name($name) {
        $name = strtolower(trim((string) $name));
        if (!preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $name)) {
            return '';
        }

        return $name;
    }

    private function extension_block_shortcode($tag) {
        $tag = sanitize_key((string) $tag);
        if (!preg_match('/^[a-z0-9_]+$/', $tag)) {
            return '';
        }

        return $tag;
    }

    private function extension_block_attributes($attributes) {
        if (!is_array($attributes)) {
            return [];
        }

        $allowed_types = ['string', 'number', 'integer', 'boolean', 'array', 'object'];
        $sanitized = [];
        foreach ($attributes as $key => $schema) {
            $key = sanitize_key((string) $key);
            if (!$key || !is_array($schema)) {
                continue;
            }

            $type = sanitize_key((string) ($schema['type'] ?? 'string'));
            if (!in_array($type, $allowed_types, true)) {
                $type = 'string';
            }

            $item = ['type' => $type];
            if (array_key_exists('default', $schema)) {
                $item['default'] = $this->extension_block_default($schema['default']);
            }
            if (!empty($schema['enum']) && is_array($schema['enum'])) {
                $item['enum'] = array_values(array_filter(array_map(static function ($value) {
                    return is_scalar($value) ? sanitize_text_field((string) $value) : null;
                }, $schema['enum'])));
            }

            $sanitized[$key] = $item;
        }

        return $sanitized;
    }

    private function extension_block_default($value) {
        if (is_bool($value) || is_numeric($value)) {
            return $value;
        }
        if (is_array($value)) {
            return array_map([$this, 'extension_block_default'], $value);
        }

        return sanitize_text_field((string) $value);
    }

    private function extension_block_controls($controls) {
        if (!is_array($controls)) {
            return [];
        }

        $allowed_types = ['text', 'textarea', 'toggle', 'select', 'number', 'entity'];
        $sanitized = [];
        foreach ($controls as $control) {
            if (!is_array($control)) {
                continue;
            }

            $attribute = sanitize_key((string) ($control['attribute'] ?? ''));
            if (!$attribute) {
                continue;
            }

            $type = sanitize_key((string) ($control['type'] ?? 'text'));
            if (!in_array($type, $allowed_types, true)) {
                $type = 'text';
            }

            $item = [
                'attribute' => $attribute,
                'type' => $type,
                'label' => sanitize_text_field((string) ($control['label'] ?? ucfirst(str_replace('_', ' ', $attribute)))),
                'help' => sanitize_text_field((string) ($control['help'] ?? '')),
            ];

            if (in_array($type, ['select', 'entity'], true) && !empty($control['options']) && is_array($control['options'])) {
                $item['options'] = [];
                foreach ($control['options'] as $option) {
                    if (!is_array($option)) {
                        continue;
                    }
                    $item['options'][] = [
                        'label' => sanitize_text_field((string) ($option['label'] ?? '')),
                        'value' => sanitize_text_field((string) ($option['value'] ?? '')),
                    ];
                }
            }
            if ('entity' === $type) {
                $item['emptyLabel'] = sanitize_text_field((string) ($control['empty_label'] ?? $control['emptyLabel'] ?? __('First available item', 'million-dollar-script')));
                $item['customLabel'] = sanitize_text_field((string) ($control['custom_label'] ?? $control['customLabel'] ?? __('Enter a custom ID', 'million-dollar-script')));
                $item['customHelp'] = sanitize_text_field((string) ($control['custom_help'] ?? $control['customHelp'] ?? __('Use this when the item is not listed. The same ID is saved for this block.', 'million-dollar-script')));
            }

            $sanitized[] = $item;
        }

        return $sanitized;
    }

    private function extension_block_preview($preview) {
        if (!is_array($preview)) {
            return [];
        }

        $rows = [];
        foreach ((array) ($preview['rows'] ?? []) as $row) {
            if (!is_scalar($row)) {
                continue;
            }
            $rows[] = sanitize_text_field((string) $row);
        }

        return [
            'title' => sanitize_text_field((string) ($preview['title'] ?? '')),
            'description' => sanitize_text_field((string) ($preview['description'] ?? '')),
            'rows' => $rows,
        ];
    }

    private function hex_color($value) {
        $color = sanitize_hex_color((string) $value);

        return $color ?: '';
    }

    private function stats_visibility($value) {
        $value = sanitize_key((string) $value);

        return in_array($value, ['inherit', 'show', 'hide'], true) ? $value : 'inherit';
    }

    private function block_api_version() {
        $wp_version = function_exists('get_bloginfo') ? (string) get_bloginfo('version') : '';

        return version_compare($wp_version, '6.3', '>=') ? 3 : 2;
    }

    private function legacy_block_names() {
        return [
            'carbon-fields/million-dollar-script',
            'milliondollarscript/grid-block',
            'milliondollarscript/mds-block',
            'milliondollarscript/display',
            'milliondollarscript/grid',
            'milliondollarscript/order',
            'milliondollarscript/stats',
            'mds/grid-block',
            'mds/order-block',
            'mds/stats-block',
            'mds/display-block',
        ];
    }
}
