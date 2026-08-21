(function (wp) {
    'use strict';

    if (!wp || !wp.blocks || !wp.element || !wp.components || !wp.blockEditor) {
        return;
    }

    var createElement = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var RawHTML = wp.element.RawHTML;
    var wpShortcode = wp.shortcode || null;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelColorSettings = wp.blockEditor.PanelColorSettings;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var blockEditorDispatch = wp.data && wp.data.dispatch
        ? wp.data.dispatch(wp.blockEditor.store || 'core/block-editor')
        : null;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var TextareaControl = wp.components.TextareaControl;
    var TextControl = wp.components.TextControl;
    var ToggleControl = wp.components.ToggleControl;
    var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (text) { return text; };
    var config = (window.MillionDollarScript && window.MillionDollarScript.blocks) || {};
    var grids = config.grids || [];
    var pageTypes = config.pageTypes || [];
    var extensionBlocks = config.extensionBlocks || [];
    var blockApiVersion = Number(config.blockApiVersion || 2);
    var gridsAdminUrl = config.gridsAdminUrl || '';
    var blockCategory = config.blockCategory || 'million-dollar-script';
    var defaultHeight = '{height}';
    var defaultPreviewHeight = '1000px';
    var defaultWidth = '100%';
    var defaultStatsWidth = '240px';

    function findGrid(id) {
        return grids.find(function (grid) {
            return Number(grid.id) === Number(id || 0);
        }) || null;
    }

    function autoHeightForGrid(grid) {
        if (!grid || !Number(grid.width) || !Number(grid.height)) {
            return defaultHeight;
        }

        return '{height}';
    }

    function gridMeta(grid) {
        if (!grid) {
            return __('Uses the first available grid when no grid is selected', 'million-dollar-script');
        }

        return grid.width + 'x' + grid.height + ' px, blocks ' + grid.blockWidth + 'x' + grid.blockHeight + ' px';
    }

    function rendererLabel(renderer) {
        renderer = renderer || 'auto';
        if (renderer === 'openlayers') {
            return __('OpenLayers', 'million-dollar-script');
        }
        if (renderer === 'classic') {
            return __('Classic', 'million-dollar-script');
        }
        return __('Automatic', 'million-dollar-script');
    }

    function gridModeLabel(readOnly) {
        return readOnly ? __('View-only grid', 'million-dollar-script') : __('Ordering enabled', 'million-dollar-script');
    }

    function gridModeHelp(readOnly) {
        return readOnly
            ? __('Visitors can view the grid here. Use an Order Pixels page or turn this off if visitors should order directly from this block.', 'million-dollar-script')
            : __('Visitors can click available blocks in this grid and start an order from this block.', 'million-dollar-script');
    }

    function statsVisibility(value) {
        value = value || 'inherit';
        return ['inherit', 'show', 'hide'].indexOf(value) !== -1 ? value : 'inherit';
    }

    function statsVisibilityLabel(value) {
        value = statsVisibility(value);
        if (value === 'show') {
            return __('Show stats', 'million-dollar-script');
        }
        if (value === 'hide') {
            return __('Hide stats', 'million-dollar-script');
        }
        return __('Use grid setting', 'million-dollar-script');
    }

    function statsVisibilityOptions() {
        return [
            { label: __('Use grid setting', 'million-dollar-script'), value: 'inherit' },
            { label: __('Show stats', 'million-dollar-script'), value: 'show' },
            { label: __('Hide stats', 'million-dollar-script'), value: 'hide' }
        ];
    }

    function helpText(text) {
        return createElement('p', { className: 'mds3-editor-control-help' }, text);
    }

    function previewSize(value, fallback, grid) {
        var raw = String(value || '').trim().toLowerCase();
        if (raw === '{width}' || raw === '{grid_width}') {
            return grid && grid.width ? grid.width + 'px' : fallback;
        }
        if (raw === '{height}' || raw === '{grid_height}') {
            return grid && grid.height ? grid.height + 'px' : defaultPreviewHeight;
        }
        return value || fallback;
    }

    function gridOptions() {
        var options = [{ label: __('First available grid', 'million-dollar-script'), value: '0' }];
        grids.forEach(function (grid) {
            options.push({
                label: '#' + grid.id + ' ' + grid.title,
                value: String(grid.id)
            });
        });
        return options;
    }

    function pageTypeOptions() {
        return pageTypes.map(function (pageType) {
            return {
                label: pageType.label,
                value: pageType.type
            };
        });
    }

    function findPageType(type) {
        return pageTypes.find(function (pageType) {
            return pageType.type === type;
        }) || pageTypes[0] || { type: 'grid', label: __('Pixel Grid', 'million-dollar-script'), description: '' };
    }

    function pageTypeNeedsGrid(type) {
        return ['grid', 'order', 'write-ad', 'confirm-order', 'payment', 'thank-you', 'list', 'upload', 'stats'].indexOf(type) !== -1;
    }

    function pageTypeUsesGridDisplay(type) {
        return type === 'grid' || type === 'order';
    }

    function pageVariationIcon(type) {
        var icons = {
            grid: 'grid-view',
            order: 'cart',
            'write-ad': 'edit-page',
            'confirm-order': 'yes-alt',
            payment: 'money-alt',
            manage: 'admin-users',
            'thank-you': 'heart',
            list: 'list-view',
            upload: 'upload',
            'no-orders': 'info',
            stats: 'chart-bar'
        };

        return icons[type] || 'screenoptions';
    }

    function pageDefaultAttributes(type) {
        var attrs = { type: type || 'grid' };
        if (type === 'order') {
            attrs.readOnly = false;
        }
        if (type === 'stats') {
            attrs.width = defaultStatsWidth;
        }
        return attrs;
    }

    function extensionDefaultAttributes(definition) {
        var attrs = {};
        Object.keys(definition.attributes || {}).forEach(function (key) {
            if (Object.prototype.hasOwnProperty.call(definition.attributes[key], 'default')) {
                attrs[key] = definition.attributes[key].default;
            }
        });
        return attrs;
    }

    function uniqueStrings(items) {
        var seen = {};
        var output = [];
        (items || []).forEach(function (item) {
            item = String(item || '').trim();
            if (!item || seen[item.toLowerCase()]) {
                return;
            }
            seen[item.toLowerCase()] = true;
            output.push(item);
        });

        return output;
    }

    function mdsKeywords(extra) {
        return uniqueStrings([
            __('MDS', 'million-dollar-script'),
            __('Million Dollar Script', 'million-dollar-script'),
            __('million dollar script', 'million-dollar-script')
        ].concat(extra || []));
    }

    function blockProps(props) {
        return useBlockProps ? useBlockProps(props) : props;
    }

    function shortcodeValue(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function shortcode(tag, attributes) {
        var pairs = [];
        Object.keys(attributes || {}).forEach(function (key) {
            var value = attributes[key];
            if (value === undefined || value === null || value === '') {
                return;
            }
            pairs.push(key + '="' + shortcodeValue(value) + '"');
        });

        return '[' + tag + (pairs.length ? ' ' + pairs.join(' ') : '') + ']';
    }

    function shortcodeMarkup(tag, attributes) {
        var value = shortcode(tag, attributes);

        return RawHTML ? createElement(RawHTML, null, value) : value;
    }

    function shortcodeNamed(attrs, key, fallback) {
        var named = attrs && attrs.named ? attrs.named : {};
        var value = named[key];

        return value === undefined || value === null || value === '' ? fallback : value;
    }

    function shortcodeNumber(attrs, key, fallback) {
        var value = parseInt(shortcodeNamed(attrs, key, fallback), 10);

        return Number.isNaN(value) ? fallback : value;
    }

    function shortcodeBoolean(attrs, key, fallback) {
        var value = String(shortcodeNamed(attrs, key, fallback ? 'true' : 'false')).toLowerCase();

        return ['1', 'true', 'yes', 'y', 'on'].indexOf(value) !== -1;
    }

    function gridShortcodeAttributes(attributes) {
        return {
            id: Number(attributes.id || 0),
            read_only: attributes.readOnly ? 'true' : 'false',
            width: attributes.width || defaultWidth,
            height: attributes.height || defaultHeight,
            renderer: attributes.renderer || 'auto',
            show_stats: statsVisibility(attributes.showStats)
        };
    }

    function pageShortcodeAttributes(attributes) {
        var type = attributes.type || 'grid';
        var output = {
            type: type,
            grid_id: Number(attributes.id || 0)
        };

        if (pageTypeUsesGridDisplay(type)) {
            output.width = attributes.width || defaultWidth;
            output.height = attributes.height || defaultHeight;
            output.read_only = type === 'order' ? 'false' : (attributes.readOnly ? 'true' : 'false');
            output.renderer = attributes.renderer || 'auto';
            output.show_stats = statsVisibility(attributes.showStats);
        } else if (type === 'stats') {
            output.unit = attributes.unit || 'settings';
            output.width = attributes.width || defaultStatsWidth;
            output.number_color = attributes.numberColor || '';
            output.label_color = attributes.labelColor || '';
            output.background_color = attributes.backgroundColor || '';
            output.border_color = attributes.borderColor || '';
        } else if (type === 'list') {
            output.list_layout = attributes.listLayout || 'list';
            output.list_columns = attributes.listColumns || '';
            output.list_search = attributes.listSearch === false ? 'no' : 'yes';
        }

        return output;
    }

    function statsShortcodeAttributes(attributes) {
        return {
            type: 'stats',
            grid_id: Number(attributes.id || 0),
            unit: attributes.unit || 'settings',
            width: attributes.width || defaultStatsWidth,
            number_color: attributes.numberColor || '',
            label_color: attributes.labelColor || '',
            background_color: attributes.backgroundColor || '',
            border_color: attributes.borderColor || ''
        };
    }

    function gridAttributesFromShortcode(attrs) {
        return {
            id: shortcodeNumber(attrs, 'id', 0),
            readOnly: shortcodeBoolean(attrs, 'read_only', true),
            width: shortcodeNamed(attrs, 'width', defaultWidth),
            height: shortcodeNamed(attrs, 'height', defaultHeight),
            renderer: shortcodeNamed(attrs, 'renderer', 'auto'),
            showStats: statsVisibility(shortcodeNamed(attrs, 'show_stats', 'inherit'))
        };
    }

    function pageAttributesFromShortcode(attrs) {
        var type = shortcodeNamed(attrs, 'type', 'grid');

        return {
            type: type,
            id: shortcodeNumber(attrs, 'grid_id', shortcodeNumber(attrs, 'id', 0)),
            readOnly: shortcodeBoolean(attrs, 'read_only', true),
            width: shortcodeNamed(attrs, 'width', type === 'stats' ? defaultStatsWidth : defaultWidth),
            height: shortcodeNamed(attrs, 'height', defaultHeight),
            renderer: shortcodeNamed(attrs, 'renderer', 'auto'),
            showStats: statsVisibility(shortcodeNamed(attrs, 'show_stats', 'inherit')),
            unit: shortcodeNamed(attrs, 'unit', 'settings'),
            listLayout: shortcodeNamed(attrs, 'list_layout', 'list'),
            listColumns: shortcodeNamed(attrs, 'list_columns', ''),
            listSearch: shortcodeBoolean(attrs, 'list_search', true),
            numberColor: shortcodeNamed(attrs, 'number_color', ''),
            labelColor: shortcodeNamed(attrs, 'label_color', ''),
            backgroundColor: shortcodeNamed(attrs, 'background_color', ''),
            borderColor: shortcodeNamed(attrs, 'border_color', '')
        };
    }

    function statsAttributesFromShortcode(attrs) {
        return {
            id: shortcodeNumber(attrs, 'grid_id', shortcodeNumber(attrs, 'id', 0)),
            unit: shortcodeNamed(attrs, 'unit', 'settings'),
            width: shortcodeNamed(attrs, 'width', defaultStatsWidth),
            numberColor: shortcodeNamed(attrs, 'number_color', ''),
            labelColor: shortcodeNamed(attrs, 'label_color', ''),
            backgroundColor: shortcodeNamed(attrs, 'background_color', ''),
            borderColor: shortcodeNamed(attrs, 'border_color', '')
        };
    }

    function emptyDynamicBlockDeprecation(attributes) {
        return [
            {
                attributes: attributes,
                save: function () {
                    return null;
                }
            }
        ];
    }

    function rawShortcode(node, tags) {
        var text = node && node.textContent ? String(node.textContent).trim() : '';
        var match = null;

        if (!text || !wpShortcode || typeof wpShortcode.next !== 'function') {
            return null;
        }

        tags = Array.isArray(tags) ? tags : [tags];
        tags.some(function (tag) {
            var next = wpShortcode.next(tag, text);
            if (!next || !next.content || String(next.content).trim() !== text) {
                return false;
            }

            match = {
                tag: tag,
                attrs: (next.shortcode && next.shortcode.attrs) || { named: {} }
            };

            return true;
        });

        return match;
    }

    function rawShortcodeTransform(blockName, tags, attributesFromShortcode, isMatch) {
        return {
            type: 'raw',
            selector: 'p',
            priority: 5,
            isMatch: function (node) {
                var parsed = rawShortcode(node, tags);

                if (!parsed) {
                    return false;
                }

                return typeof isMatch === 'function' ? !!isMatch(parsed.attrs, parsed.tag) : true;
            },
            transform: function (node) {
                var parsed = rawShortcode(node, tags);

                if (!parsed || (typeof isMatch === 'function' && !isMatch(parsed.attrs, parsed.tag))) {
                    return null;
                }

                return wp.blocks.createBlock(blockName, attributesFromShortcode(parsed.attrs, parsed.tag));
            }
        };
    }

    function shortcodeTransform(tag, attributes) {
        return {
            type: 'shortcode',
            tag: tag,
            attributes: attributes
        };
    }

    function gridShortcodeTransform(tag) {
        return shortcodeTransform(tag, {
            id: { type: 'number', shortcode: function (attrs) { return gridAttributesFromShortcode(attrs).id; } },
            readOnly: { type: 'boolean', shortcode: function (attrs) { return gridAttributesFromShortcode(attrs).readOnly; } },
            width: { type: 'string', shortcode: function (attrs) { return gridAttributesFromShortcode(attrs).width; } },
            height: { type: 'string', shortcode: function (attrs) { return gridAttributesFromShortcode(attrs).height; } },
            renderer: { type: 'string', shortcode: function (attrs) { return gridAttributesFromShortcode(attrs).renderer; } },
            showStats: { type: 'string', shortcode: function (attrs) { return gridAttributesFromShortcode(attrs).showStats; } }
        });
    }

    function pageShortcodeTransform() {
        return Object.assign(shortcodeTransform('mds3_page', {
            type: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).type; } },
            id: { type: 'number', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).id; } },
            readOnly: { type: 'boolean', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).readOnly; } },
            width: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).width; } },
            height: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).height; } },
            renderer: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).renderer; } },
            showStats: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).showStats; } },
            unit: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).unit; } },
            listLayout: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).listLayout; } },
            listColumns: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).listColumns; } },
            listSearch: { type: 'boolean', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).listSearch; } },
            numberColor: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).numberColor; } },
            labelColor: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).labelColor; } },
            backgroundColor: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).backgroundColor; } },
            borderColor: { type: 'string', shortcode: function (attrs) { return pageAttributesFromShortcode(attrs).borderColor; } }
        }), {
            isMatch: function (attrs) {
                return shortcodeNamed(attrs, 'type', 'grid') !== 'stats';
            }
        });
    }

    function statsShortcodeTransform() {
        return Object.assign(shortcodeTransform('mds3_page', {
            id: { type: 'number', shortcode: function (attrs) { return statsAttributesFromShortcode(attrs).id; } },
            unit: { type: 'string', shortcode: function (attrs) { return statsAttributesFromShortcode(attrs).unit; } },
            width: { type: 'string', shortcode: function (attrs) { return statsAttributesFromShortcode(attrs).width; } },
            numberColor: { type: 'string', shortcode: function (attrs) { return statsAttributesFromShortcode(attrs).numberColor; } },
            labelColor: { type: 'string', shortcode: function (attrs) { return statsAttributesFromShortcode(attrs).labelColor; } },
            backgroundColor: { type: 'string', shortcode: function (attrs) { return statsAttributesFromShortcode(attrs).backgroundColor; } },
            borderColor: { type: 'string', shortcode: function (attrs) { return statsAttributesFromShortcode(attrs).borderColor; } }
        }), {
            isMatch: function (attrs) {
                return shortcodeNamed(attrs, 'type', '') === 'stats';
            }
        });
    }

    function shouldIgnoreSelectEvent(event) {
        return event.target && event.target.closest && event.target.closest('input, select, textarea, button, a');
    }

    function selectBlock(clientId) {
        if (blockEditorDispatch && blockEditorDispatch.selectBlock && clientId) {
            blockEditorDispatch.selectBlock(clientId);
        }
    }

    function selectBlockOnPointer(clientId) {
        return function (event) {
            if (shouldIgnoreSelectEvent(event)) {
                return;
            }
            selectBlock(clientId);
        };
    }

    function selectBlockOnKey(clientId) {
        return function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            selectBlock(clientId);
        };
    }

    function colorSettingsPanel(attributes, setAttributes) {
        if (!PanelColorSettings) {
            return createElement(PanelBody, { title: __('Stats colors', 'million-dollar-script'), initialOpen: false },
                createElement(TextControl, {
                    label: __('Number color', 'million-dollar-script'),
                    __next40pxDefaultSize: true,
                    __nextHasNoMarginBottom: true,
                    value: attributes.numberColor || '',
                    onChange: function (value) {
                        setAttributes({ numberColor: value || '' });
                    }
                }),
                createElement(TextControl, {
                    label: __('Label color', 'million-dollar-script'),
                    __next40pxDefaultSize: true,
                    __nextHasNoMarginBottom: true,
                    value: attributes.labelColor || '',
                    onChange: function (value) {
                        setAttributes({ labelColor: value || '' });
                    }
                })
            );
        }

        return createElement(PanelColorSettings, {
            title: __('Stats colors', 'million-dollar-script'),
            initialOpen: false,
            colorSettings: [
                {
                    label: __('Number color', 'million-dollar-script'),
                    value: attributes.numberColor || '',
                    onChange: function (value) {
                        setAttributes({ numberColor: value || '' });
                    }
                },
                {
                    label: __('Label color', 'million-dollar-script'),
                    value: attributes.labelColor || '',
                    onChange: function (value) {
                        setAttributes({ labelColor: value || '' });
                    }
                },
                {
                    label: __('Background color', 'million-dollar-script'),
                    value: attributes.backgroundColor || '',
                    onChange: function (value) {
                        setAttributes({ backgroundColor: value || '' });
                    }
                },
                {
                    label: __('Border color', 'million-dollar-script'),
                    value: attributes.borderColor || '',
                    onChange: function (value) {
                        setAttributes({ borderColor: value || '' });
                    }
                }
            ]
        });
    }

    function statsStyle(attributes, width) {
        var style = {
            '--mds3-editor-stats-width': width
        };

        if (attributes.numberColor) {
            style['--mds3-stats-number-color'] = attributes.numberColor;
        }
        if (attributes.labelColor) {
            style['--mds3-stats-label-color'] = attributes.labelColor;
        }
        if (attributes.backgroundColor) {
            style['--mds3-stats-background-color'] = attributes.backgroundColor;
        }
        if (attributes.borderColor) {
            style['--mds3-stats-border-color'] = attributes.borderColor;
        }

        return style;
    }

    function unitLabel(unit) {
        if (unit === 'blocks') {
            return __('Blocks', 'million-dollar-script');
        }
        if (unit === 'pixels') {
            return __('Pixels', 'million-dollar-script');
        }
        return __('Site setting', 'million-dollar-script');
    }

    function previewCells(readOnly) {
        var cells = [];
        var sold = [4, 5, 16, 17, 42, 43, 44, 58, 70, 71];
        var reserved = [26, 27, 38, 39];
        var selected = [62, 63, 74, 75];
        var unavailable = [88, 89, 90, 91];

        for (var i = 0; i < 96; i += 1) {
            var className = 'mds3-editor-grid-cell';
            if (sold.indexOf(i) !== -1) {
                className += ' is-sold';
            } else if (reserved.indexOf(i) !== -1) {
                className += ' is-reserved';
            } else if (unavailable.indexOf(i) !== -1) {
                className += ' is-unavailable';
            } else if (!readOnly && selected.indexOf(i) !== -1) {
                className += ' is-selected';
            }
            cells.push(createElement('span', { key: i, className: className }));
        }

        return cells;
    }

    function previewHeader(icon, title, eyebrow) {
        return createElement('div', { className: 'mds3-editor-preview-header' },
            createElement('span', { className: 'dashicons dashicons-' + icon, 'aria-hidden': 'true' }),
            createElement('div', null,
                eyebrow ? createElement('span', { className: 'mds3-editor-preview-eyebrow' }, eyebrow) : null,
                createElement('strong', null, title)
            )
        );
    }

    function summaryGrid(items) {
        items = (items || []).filter(function (item) {
            return item && item.value !== undefined && item.value !== null && String(item.value) !== '';
        });

        if (!items.length) {
            return null;
        }

        return createElement('dl', { className: 'mds3-editor-summary-grid' },
            items.map(function (item) {
                return createElement('div', { key: item.label },
                    createElement('dt', null, item.label),
                    createElement('dd', null, item.value)
                );
            })
        );
    }

    function gridSummaryItems(selectedGrid, attributes, modeOverride) {
        return [
            {
                label: __('Grid', 'million-dollar-script'),
                value: selectedGrid ? selectedGrid.title : __('First available', 'million-dollar-script')
            },
            {
                label: __('Size', 'million-dollar-script'),
                value: selectedGrid ? selectedGrid.width + 'x' + selectedGrid.height + ' px' : __('Automatic', 'million-dollar-script')
            },
            {
                label: __('Blocks', 'million-dollar-script'),
                value: selectedGrid ? selectedGrid.blockWidth + 'x' + selectedGrid.blockHeight + ' px' : __('Automatic', 'million-dollar-script')
            },
            {
                label: __('Mode', 'million-dollar-script'),
                value: modeOverride || gridModeLabel(!!attributes.readOnly)
            }
        ];
    }

    function gridPreview(selectedGrid, attributes) {
        var readOnly = !!attributes.readOnly;
        var renderer = attributes.renderer || 'auto';

        return createElement('div', { className: 'mds3-editor-grid-preview' },
            createElement('div', { className: 'mds3-editor-grid-preview-toolbar' },
                createElement('span', { className: readOnly ? 'mds3-editor-mode-pill' : 'mds3-editor-mode-pill is-ordering' }, gridModeLabel(readOnly)),
                createElement('span', { className: 'mds3-editor-renderer-pill' }, rendererLabel(renderer))
            ),
            createElement('div', { className: 'mds3-editor-grid-preview-grid', 'aria-hidden': 'true' },
                previewCells(readOnly)
            ),
            createElement('div', { className: 'mds3-editor-grid-preview-footer' },
                createElement('span', null, __('Example placements', 'million-dollar-script')),
                createElement('span', null, readOnly ? __('View-only display', 'million-dollar-script') : __('Ordering display', 'million-dollar-script'))
            ),
            !selectedGrid && gridsAdminUrl ? createElement('a', {
                className: 'mds3-editor-grid-admin-link',
                href: gridsAdminUrl
            }, __('Manage grids', 'million-dollar-script')) : null
        );
    }

    function pagePanelPreview(pageType, selectedGrid, attributes) {
        var type = pageType.type || 'grid';
        var title = pageType.label || __('Page Flow', 'million-dollar-script');
        var description = pageType.description || '';

        if (pageTypeUsesGridDisplay(type)) {
            return gridPreview(selectedGrid, Object.assign({}, attributes, { readOnly: type === 'order' ? false : attributes.readOnly }));
        }

        if (type === 'stats') {
            return createElement('div', { className: 'mds3-editor-page-preview mds3-editor-page-preview-stats', style: statsStyle(attributes, attributes.width || defaultStatsWidth) },
                createElement('div', { className: 'mds3-editor-stats-preview' },
                    createElement('div', null,
                        createElement('strong', null, '12,500'),
                        createElement('span', null, __('Sold', 'million-dollar-script'))
                    ),
                    createElement('div', null,
                        createElement('strong', null, '987,500'),
                        createElement('span', null, __('Available', 'million-dollar-script'))
                    )
                )
            );
        }

        if (type === 'list') {
            return createElement('div', { className: 'mds3-editor-page-preview mds3-editor-page-preview-list' },
                attributes.listSearch === false ? null : createElement('div', { className: 'mds3-editor-page-search' }, __('Search advertisers', 'million-dollar-script')),
                createElement('div', { className: 'mds3-editor-page-list-row' },
                    createElement('span', { className: 'mds3-editor-page-list-thumb' }),
                    createElement('span', null,
                        createElement('strong', null, __('Sample advertiser', 'million-dollar-script')),
                        createElement('em', null, attributes.listLayout === 'accordion' ? __('Accordion details', 'million-dollar-script') : __('Website and popup text', 'million-dollar-script'))
                    )
                ),
                createElement('div', { className: 'mds3-editor-page-list-row' },
                    createElement('span', { className: 'mds3-editor-page-list-thumb is-secondary' }),
                    createElement('span', null,
                        createElement('strong', null, __('Another advertiser', 'million-dollar-script')),
                        createElement('em', null, attributes.listLayout === 'cards' ? __('Card layout', 'million-dollar-script') : __('Published placement', 'million-dollar-script'))
                    )
                )
            );
        }

        return createElement('div', { className: 'mds3-editor-page-preview' },
            createElement('div', { className: 'mds3-editor-page-preview-heading' },
                createElement('span', { className: 'dashicons dashicons-' + pageVariationIcon(type), 'aria-hidden': 'true' }),
                createElement('strong', null, title)
            ),
            createElement('p', null, description),
            createElement('div', { className: 'mds3-editor-page-preview-action' },
                type === 'manage' ? __('Manage order', 'million-dollar-script') :
                    type === 'upload' ? __('Upload creative', 'million-dollar-script') :
                        type === 'payment' ? __('Continue checkout', 'million-dollar-script') :
                            type === 'thank-you' ? __('Order received', 'million-dollar-script') :
                                __('Open grid', 'million-dollar-script')
            )
        );
    }

    function extensionControl(control, attributes, setAttributes) {
        var attribute = control.attribute;
        var value = attributes[attribute];
        var next = function (nextValue) {
            var update = {};
            update[attribute] = nextValue;
            setAttributes(update);
        };

        if (control.type === 'entity') {
            return entityControl(control, value, next);
        }

        if (control.type === 'toggle') {
            return createElement(ToggleControl, {
                key: attribute,
                label: control.label,
                help: control.help || '',
                __nextHasNoMarginBottom: true,
                checked: !!value,
                onChange: function (checked) {
                    next(!!checked);
                }
            });
        }

        if (control.type === 'select') {
            return createElement(SelectControl, {
                key: attribute,
                label: control.label,
                help: control.help || '',
                __next40pxDefaultSize: true,
                __nextHasNoMarginBottom: true,
                value: value === undefined || value === null ? '' : String(value),
                options: control.options || [],
                onChange: next
            });
        }

        if (control.type === 'number') {
            return createElement(TextControl, {
                key: attribute,
                label: control.label,
                help: control.help || '',
                type: 'number',
                __next40pxDefaultSize: true,
                __nextHasNoMarginBottom: true,
                value: value === undefined || value === null ? '' : String(value),
                onChange: function (nextValue) {
                    next(nextValue === '' ? '' : Number(nextValue));
                }
            });
        }

        if (control.type === 'textarea' && TextareaControl) {
            return createElement(TextareaControl, {
                key: attribute,
                label: control.label,
                help: control.help || '',
                __nextHasNoMarginBottom: true,
                value: value === undefined || value === null ? '' : String(value),
                onChange: next
            });
        }

        return createElement(TextControl, {
            key: attribute,
            label: control.label,
            help: control.help || '',
            __next40pxDefaultSize: true,
            __nextHasNoMarginBottom: true,
            value: value === undefined || value === null ? '' : String(value),
            onChange: next
        });
    }

    function entityControl(control, value, onChange) {
        var options = Array.isArray(control.options) ? control.options : [];
        var valueString = value === undefined || value === null ? '' : String(value);
        var hasSelectedOption = options.some(function (option) {
            return String(option.value) === valueString;
        });
        var selectedMode = valueString && valueString !== '0' && !hasSelectedOption ? '__custom' : valueString;
        var selectOptions = [
            { label: control.emptyLabel || __('First available item', 'million-dollar-script'), value: '0' }
        ].concat(options).concat([
            { label: control.customLabel || __('Enter a custom ID', 'million-dollar-script'), value: '__custom' }
        ]);

        return createElement('div', { key: control.attribute, className: 'mds3-editor-entity-control' },
            createElement(SelectControl, {
                label: control.label,
                help: control.help || '',
                __next40pxDefaultSize: true,
                __nextHasNoMarginBottom: true,
                value: selectedMode || '0',
                options: selectOptions,
                onChange: function (nextValue) {
                    if (nextValue === '__custom') {
                        onChange(valueString && valueString !== '0' ? Number(valueString) : '');
                        return;
                    }
                    onChange(parseInt(nextValue, 10) || 0);
                }
            }),
            selectedMode === '__custom' ? createElement(TextControl, {
                label: control.customLabel || __('Custom ID', 'million-dollar-script'),
                help: control.customHelp || __('Use this when the item is not listed. The same ID is saved for this block.', 'million-dollar-script'),
                type: 'number',
                __next40pxDefaultSize: true,
                __nextHasNoMarginBottom: true,
                value: valueString === '0' ? '' : valueString,
                onChange: function (nextValue) {
                    onChange(nextValue === '' ? '' : Number(nextValue));
                }
            }) : null
        );
    }

    function extensionSettingRows(definition, attributes) {
        var controls = Array.isArray(definition.controls) ? definition.controls : [];
        return controls.map(function (control) {
            var value = attributes[control.attribute];
            var label = control.label || control.attribute;
            var display = '';

            if (control.type === 'toggle') {
                display = value ? __('Yes', 'million-dollar-script') : __('No', 'million-dollar-script');
            } else if (control.type === 'entity') {
                var options = Array.isArray(control.options) ? control.options : [];
                var match = options.find(function (option) {
                    return String(option.value) === String(value || 0);
                });
                if (match) {
                    display = match.label;
                } else if (!value || String(value) === '0') {
                    display = control.emptyLabel || __('First available item', 'million-dollar-script');
                } else {
                    display = String(value);
                }
            } else if (control.type === 'select') {
                var choices = Array.isArray(control.options) ? control.options : [];
                var selected = choices.find(function (option) {
                    return String(option.value) === String(value);
                });
                display = selected ? selected.label : String(value || '');
            } else {
                display = String(value === undefined || value === null || value === '' ? __('Not set', 'million-dollar-script') : value);
            }

            return {
                label: label,
                value: display
            };
        }).filter(function (row) {
            return row.value !== '';
        }).slice(0, 6);
    }

    function extensionShortcodeAttributes(definition, attributes) {
        var output = {};
        Object.keys(definition.attributes || {}).forEach(function (key) {
            var schema = definition.attributes[key] || {};
            var value = attributes[key];
            if (value === undefined && Object.prototype.hasOwnProperty.call(schema, 'default')) {
                value = schema.default;
            }
            if (value === undefined || value === null || value === '') {
                return;
            }
            output[key] = typeof value === 'boolean' ? (value ? 'true' : 'false') : value;
        });

        return output;
    }

    function extensionAttributeFromShortcode(schema, attrs, key) {
        var fallback = schema && Object.prototype.hasOwnProperty.call(schema, 'default') ? schema.default : '';
        var type = schema && schema.type ? schema.type : 'string';
        if (type === 'boolean') {
            return shortcodeBoolean(attrs, key, !!fallback);
        }
        if (type === 'number' || type === 'integer') {
            return shortcodeNumber(attrs, key, Number(fallback || 0));
        }

        return shortcodeNamed(attrs, key, fallback);
    }

    function extensionTransforms(definition) {
        if (!definition.shortcode) {
            return {};
        }

        var attributes = {};
        Object.keys(definition.attributes || {}).forEach(function (key) {
            attributes[key] = {
                type: (definition.attributes[key] && definition.attributes[key].type) || 'string',
                shortcode: function (attrs) {
                    return extensionAttributeFromShortcode(definition.attributes[key] || {}, attrs, key);
                }
            };
        });

        return {
            from: [
                {
                    type: 'shortcode',
                    tag: definition.shortcode,
                    attributes: attributes
                },
                rawShortcodeTransform(definition.name, definition.shortcode, function (attrs) {
                    var output = {};
                    Object.keys(definition.attributes || {}).forEach(function (key) {
                        output[key] = extensionAttributeFromShortcode(definition.attributes[key] || {}, attrs, key);
                    });

                    return output;
                })
            ]
        };
    }

    function extensionSave(definition, attributes) {
        if (!definition.shortcode) {
            return null;
        }

        return shortcodeMarkup(definition.shortcode, extensionShortcodeAttributes(definition, attributes));
    }

    function extensionBlockPreview(definition, attributes) {
        var preview = definition.preview || {};
        var rows = Array.isArray(preview.rows) ? preview.rows : [];
        var settings = extensionSettingRows(definition, attributes);

        return createElement('div', { className: 'mds3-editor-extension-preview' },
            previewHeader(definition.icon || 'screenoptions', preview.title || definition.title, __('Extension block', 'million-dollar-script')),
            preview.description || definition.description ? createElement('p', null, preview.description || definition.description) : null,
            rows.length ? createElement('ul', null, rows.map(function (row, index) {
                return createElement('li', { key: index }, row);
            })) : null,
            settings.length ? createElement('dl', { className: 'mds3-editor-extension-settings' },
                settings.map(function (row) {
                    return createElement('div', { key: row.label },
                        createElement('dt', null, row.label),
                        createElement('dd', null, row.value)
                    );
                })
            ) : null
        );
    }

    wp.blocks.registerBlockType('mds/grid', {
        apiVersion: blockApiVersion,
        title: __('Grid Embed', 'million-dollar-script'),
        description: __('Embed a specific Million Dollar Script grid directly in this page.', 'million-dollar-script'),
        icon: 'grid-view',
        category: blockCategory,
        keywords: mdsKeywords([__('grid', 'million-dollar-script'), __('pixels', 'million-dollar-script'), __('embed', 'million-dollar-script')]),
        attributes: {
            id: { type: 'number', default: 0 },
            readOnly: { type: 'boolean', default: true },
            width: { type: 'string', default: defaultWidth },
            height: { type: 'string', default: '{height}' },
            renderer: { type: 'string', default: 'auto' },
            showStats: { type: 'string', default: 'inherit' }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var selectedGrid = findGrid(attributes.id);
            var title = selectedGrid ? selectedGrid.title : __('First available grid', 'million-dollar-script');
            var height = attributes.height || defaultHeight;
            var width = attributes.width || defaultWidth;
            var previewWidth = previewSize(width, defaultWidth, selectedGrid);
            var previewHeight = previewSize(height, defaultHeight, selectedGrid);
            var aspect = selectedGrid && selectedGrid.width && selectedGrid.height ? selectedGrid.width + ' / ' + selectedGrid.height : '16 / 9';

            return createElement(Fragment, null,
                createElement(InspectorControls, null,
                    createElement(PanelBody, { title: __('Grid settings', 'million-dollar-script'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Grid', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            value: String(attributes.id || 0),
                            options: gridOptions(),
                            onChange: function (value) {
                                var nextGrid = findGrid(value);
                                var next = { id: parseInt(value, 10) || 0 };
                                if (!attributes.height || attributes.height === defaultHeight) {
                                    next.height = autoHeightForGrid(nextGrid);
                                }
                                if (nextGrid && (!attributes.renderer || attributes.renderer === 'auto')) {
                                    next.renderer = nextGrid.renderer || 'auto';
                                }
                                setAttributes(next);
                            }
                        }),
                        createElement(ToggleControl, {
                            label: __('Allow ordering from this grid', 'million-dollar-script'),
                            __nextHasNoMarginBottom: true,
                            help: gridModeHelp(!!attributes.readOnly),
                            checked: !attributes.readOnly,
                            onChange: function (value) {
                                setAttributes({ readOnly: !value });
                            }
                        }),
                        helpText(__('Leave ordering off for the main public grid when you want visitors to use a separate Order Pixels page.', 'million-dollar-script')),
                        createElement(TextControl, {
                            label: __('Width', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('CSS size such as 100%, 960px, or 80vw. Use {width} to match the selected grid.', 'million-dollar-script'),
                            value: width,
                            onChange: function (value) {
                                setAttributes({ width: value || defaultWidth });
                            }
                        }),
                        createElement(TextControl, {
                            label: __('Height', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('CSS size such as 1000px or 80vh. Use {height} to match the selected grid.', 'million-dollar-script'),
                            value: height,
                            onChange: function (value) {
                                setAttributes({ height: value || defaultHeight });
                            }
                        }),
                        createElement(SelectControl, {
                            label: __('Renderer', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('Automatic chooses the best renderer for the grid. OpenLayers is better for very large tiled grids; Classic is simpler for small grids.', 'million-dollar-script'),
                            value: attributes.renderer || 'auto',
                            options: [
                                { label: __('Automatic', 'million-dollar-script'), value: 'auto' },
                                { label: __('OpenLayers', 'million-dollar-script'), value: 'openlayers' },
                                { label: __('Classic', 'million-dollar-script'), value: 'classic' }
                            ],
                            onChange: function (value) {
                                setAttributes({ renderer: value || 'auto' });
                            }
                        }),
                        createElement(SelectControl, {
                            label: __('Stats display', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('Choose whether this block follows the grid setting, always shows the stats box above the grid, or hides it on this page.', 'million-dollar-script'),
                            value: statsVisibility(attributes.showStats),
                            options: statsVisibilityOptions(),
                            onChange: function (value) {
                                setAttributes({ showStats: statsVisibility(value) });
                            }
                        })
                    )
                ),
                createElement('div', blockProps({
                    className: 'mds3-editor-grid-block',
                    tabIndex: 0,
                    role: 'button',
                    'aria-label': __('Select Million Dollar Script grid block', 'million-dollar-script'),
                    onMouseDown: selectBlockOnPointer(props.clientId),
                    onClick: selectBlockOnPointer(props.clientId),
                    onKeyDown: selectBlockOnKey(props.clientId),
                    style: {
                        '--mds3-editor-grid-width': previewWidth,
                        '--mds3-editor-grid-height': previewHeight,
                        '--mds3-editor-grid-aspect': aspect
                    }
                }),
                    previewHeader('grid-view', title, __('Grid embed', 'million-dollar-script')),
                    gridPreview(selectedGrid, attributes),
                    summaryGrid(gridSummaryItems(selectedGrid, attributes).concat([
                        {
                            label: __('Renderer', 'million-dollar-script'),
                            value: rendererLabel(attributes.renderer || 'auto')
                        },
                        {
                            label: __('Stats', 'million-dollar-script'),
                            value: statsVisibilityLabel(attributes.showStats)
                        }
                    ]))
                )
            );
        },
        save: function (props) {
            return shortcodeMarkup('mds_grid', gridShortcodeAttributes(props.attributes || {}));
        },
        deprecated: emptyDynamicBlockDeprecation({
            id: { type: 'number', default: 0 },
            readOnly: { type: 'boolean', default: true },
            width: { type: 'string', default: defaultWidth },
            height: { type: 'string', default: '{height}' },
            renderer: { type: 'string', default: 'auto' },
            showStats: { type: 'string', default: 'inherit' }
        }),
        transforms: {
            from: [
                gridShortcodeTransform('mds_grid'),
                gridShortcodeTransform('pixel_grid'),
                rawShortcodeTransform('mds/grid', ['mds_grid', 'pixel_grid'], gridAttributesFromShortcode)
            ]
        }
    });

    wp.blocks.registerBlockType('mds/stats', {
        apiVersion: blockApiVersion,
        title: __('Stats Widget', 'million-dollar-script'),
        description: __('Display a compact sold and available inventory widget.', 'million-dollar-script'),
        icon: 'chart-bar',
        category: blockCategory,
        keywords: mdsKeywords([__('stats', 'million-dollar-script'), __('inventory', 'million-dollar-script')]),
        attributes: {
            id: { type: 'number', default: 0 },
            unit: { type: 'string', default: 'settings' },
            width: { type: 'string', default: defaultStatsWidth },
            numberColor: { type: 'string', default: '' },
            labelColor: { type: 'string', default: '' },
            backgroundColor: { type: 'string', default: '' },
            borderColor: { type: 'string', default: '' }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var selectedGrid = findGrid(attributes.id);
            var title = selectedGrid ? selectedGrid.title : __('First available grid', 'million-dollar-script');
            var width = attributes.width || defaultStatsWidth;
            var unit = attributes.unit || 'settings';

            return createElement(Fragment, null,
                createElement(InspectorControls, null,
                    createElement(PanelBody, { title: __('Stats settings', 'million-dollar-script'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Grid', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            value: String(attributes.id || 0),
                            options: gridOptions(),
                            onChange: function (value) {
                                setAttributes({ id: parseInt(value, 10) || 0 });
                            }
                        }),
                        createElement(SelectControl, {
                            label: __('Display unit', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('Choose whether the block follows the site-wide stats setting or always displays blocks or pixels.', 'million-dollar-script'),
                            value: unit,
                            options: [
                                { label: __('Use site setting', 'million-dollar-script'), value: 'settings' },
                                { label: __('Blocks', 'million-dollar-script'), value: 'blocks' },
                                { label: __('Pixels', 'million-dollar-script'), value: 'pixels' }
                            ],
                            onChange: function (value) {
                                setAttributes({ unit: value || 'settings' });
                            }
                        }),
                        createElement(TextControl, {
                            label: __('Width', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('CSS size for the stats box, such as 240px, 100%, or 18rem.', 'million-dollar-script'),
                            value: width,
                            onChange: function (value) {
                                setAttributes({ width: value || defaultStatsWidth });
                            }
                        })
                    ),
                    colorSettingsPanel(attributes, setAttributes)
                ),
                createElement('div', blockProps({
                    className: 'mds3-editor-stats-block',
                    tabIndex: 0,
                    role: 'button',
                    'aria-label': __('Select Million Dollar Script stats block', 'million-dollar-script'),
                    onMouseDown: selectBlockOnPointer(props.clientId),
                    onClick: selectBlockOnPointer(props.clientId),
                    onKeyDown: selectBlockOnKey(props.clientId),
                    style: statsStyle(attributes, width)
                }),
                    previewHeader('chart-bar', __('Stats', 'million-dollar-script'), __('Inventory summary', 'million-dollar-script')),
                    createElement('div', { className: 'mds3-editor-stats-preview' },
                        createElement('div', null,
                            createElement('strong', null, '12,500'),
                            createElement('span', null, __('Sold', 'million-dollar-script'))
                        ),
                        createElement('div', null,
                            createElement('strong', null, '987,500'),
                            createElement('span', null, __('Available', 'million-dollar-script'))
                        )
                    ),
                    summaryGrid([
                        { label: __('Grid', 'million-dollar-script'), value: title },
                        { label: __('Unit', 'million-dollar-script'), value: unitLabel(unit) }
                    ])
                )
            );
        },
        save: function (props) {
            return shortcodeMarkup('mds3_page', statsShortcodeAttributes(props.attributes || {}));
        },
        deprecated: emptyDynamicBlockDeprecation({
            id: { type: 'number', default: 0 },
            unit: { type: 'string', default: 'settings' },
            width: { type: 'string', default: defaultStatsWidth },
            numberColor: { type: 'string', default: '' },
            labelColor: { type: 'string', default: '' },
            backgroundColor: { type: 'string', default: '' },
            borderColor: { type: 'string', default: '' }
        }),
        transforms: {
            from: [
                statsShortcodeTransform(),
                rawShortcodeTransform('mds/stats', 'mds3_page', statsAttributesFromShortcode, function (attrs) {
                    return shortcodeNamed(attrs, 'type', '') === 'stats';
                })
            ]
        }
    });

    wp.blocks.registerBlockType('mds/page', {
        apiVersion: blockApiVersion,
        title: __('Page Flow', 'million-dollar-script'),
        description: __('Display a Million Dollar Script page panel or customer flow.', 'million-dollar-script'),
        icon: 'screenoptions',
        category: blockCategory,
        keywords: mdsKeywords([__('order pixels', 'million-dollar-script'), __('customer flow', 'million-dollar-script')]),
        attributes: {
            type: { type: 'string', default: 'grid' },
            id: { type: 'number', default: 0 },
            readOnly: { type: 'boolean', default: true },
            width: { type: 'string', default: defaultWidth },
            height: { type: 'string', default: defaultHeight },
            renderer: { type: 'string', default: 'auto' },
            showStats: { type: 'string', default: 'inherit' },
            unit: { type: 'string', default: 'settings' },
            listLayout: { type: 'string', default: 'list' },
            listColumns: { type: 'string', default: '' },
            listSearch: { type: 'boolean', default: true },
            numberColor: { type: 'string', default: '' },
            labelColor: { type: 'string', default: '' },
            backgroundColor: { type: 'string', default: '' },
            borderColor: { type: 'string', default: '' }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var type = attributes.type || 'grid';
            var pageType = findPageType(type);
            var selectedGrid = findGrid(attributes.id);
            var width = attributes.width || (type === 'stats' ? defaultStatsWidth : defaultWidth);
            var height = attributes.height || defaultHeight;
            var unit = attributes.unit || 'settings';

            return createElement(Fragment, null,
                createElement(InspectorControls, null,
                    createElement(PanelBody, { title: __('Page settings', 'million-dollar-script'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Page type', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            value: type,
                            options: pageTypeOptions(),
                            onChange: function (value) {
                                var next = pageDefaultAttributes(value);
                                next.id = attributes.id || 0;
                                setAttributes(next);
                            }
                        }),
                        pageType.description ? helpText(pageType.description) : null,
                        pageTypeNeedsGrid(type) ? createElement(SelectControl, {
                            label: __('Grid', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            value: String(attributes.id || 0),
                            options: gridOptions(),
                            onChange: function (value) {
                                var nextGrid = findGrid(value);
                                var next = { id: parseInt(value, 10) || 0 };
                                if (pageTypeUsesGridDisplay(type) && (!attributes.height || attributes.height === defaultHeight)) {
                                    next.height = autoHeightForGrid(nextGrid);
                                }
                                if (nextGrid && pageTypeUsesGridDisplay(type) && (!attributes.renderer || attributes.renderer === 'auto')) {
                                    next.renderer = nextGrid.renderer || 'auto';
                                }
                                setAttributes(next);
                            }
                        }) : null,
                        type === 'grid' ? createElement(ToggleControl, {
                            label: __('Allow ordering from this grid', 'million-dollar-script'),
                            __nextHasNoMarginBottom: true,
                            checked: !attributes.readOnly,
                            help: gridModeHelp(!!attributes.readOnly),
                            onChange: function (value) {
                                setAttributes({ readOnly: !value });
                            }
                        }) : null,
                        type === 'order' ? helpText(__('Order Pixels pages are always interactive so visitors can select available blocks.', 'million-dollar-script')) : null,
                        pageTypeUsesGridDisplay(type) ? createElement(TextControl, {
                            label: __('Width', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('CSS size such as 100%, 960px, or 80vw. Use {width} to match the selected grid.', 'million-dollar-script'),
                            value: width,
                            onChange: function (value) {
                                setAttributes({ width: value || defaultWidth });
                            }
                        }) : null,
                        pageTypeUsesGridDisplay(type) ? createElement(TextControl, {
                            label: __('Height', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('CSS size such as 1000px or 80vh. Use {height} to match the selected grid.', 'million-dollar-script'),
                            value: height,
                            onChange: function (value) {
                                setAttributes({ height: value || defaultHeight });
                            }
                        }) : null,
                        pageTypeUsesGridDisplay(type) ? createElement(SelectControl, {
                            label: __('Renderer', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('Automatic chooses the best renderer for the grid.', 'million-dollar-script'),
                            value: attributes.renderer || 'auto',
                            options: [
                                { label: __('Automatic', 'million-dollar-script'), value: 'auto' },
                                { label: __('OpenLayers', 'million-dollar-script'), value: 'openlayers' },
                                { label: __('Classic', 'million-dollar-script'), value: 'classic' }
                            ],
                            onChange: function (value) {
                                setAttributes({ renderer: value || 'auto' });
                            }
                        }) : null,
                        pageTypeUsesGridDisplay(type) ? createElement(SelectControl, {
                            label: __('Stats display', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('Choose whether this page follows the grid setting, always shows the stats box above the grid, or hides it here.', 'million-dollar-script'),
                            value: statsVisibility(attributes.showStats),
                            options: statsVisibilityOptions(),
                            onChange: function (value) {
                                setAttributes({ showStats: statsVisibility(value) });
                            }
                        }) : null,
                        type === 'stats' ? createElement(SelectControl, {
                            label: __('Display unit', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('Choose whether the block follows the site-wide stats setting or always displays blocks or pixels.', 'million-dollar-script'),
                            value: unit,
                            options: [
                                { label: __('Use site setting', 'million-dollar-script'), value: 'settings' },
                                { label: __('Blocks', 'million-dollar-script'), value: 'blocks' },
                                { label: __('Pixels', 'million-dollar-script'), value: 'pixels' }
                            ],
                            onChange: function (value) {
                                setAttributes({ unit: value || 'settings' });
                            }
                        }) : null,
                        type === 'stats' ? createElement(TextControl, {
                            label: __('Width', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('CSS size for the stats box, such as 240px, 100%, or 18rem.', 'million-dollar-script'),
                            value: width,
                            onChange: function (value) {
                                setAttributes({ width: value || defaultStatsWidth });
                            }
                        }) : null,
                        type === 'list' ? createElement(SelectControl, {
                            label: __('List layout', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            value: attributes.listLayout || 'list',
                            options: [
                                { label: __('List', 'million-dollar-script'), value: 'list' },
                                { label: __('Cards', 'million-dollar-script'), value: 'cards' },
                                { label: __('Accordion', 'million-dollar-script'), value: 'accordion' }
                            ],
                            onChange: function (value) {
                                setAttributes({ listLayout: value || 'list' });
                            }
                        }) : null,
                        type === 'list' ? createElement(TextControl, {
                            label: __('Visible list fields', 'million-dollar-script'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('Comma-separated fields such as image,title,url,popup,alt. Leave empty for the default fields.', 'million-dollar-script'),
                            value: attributes.listColumns || '',
                            onChange: function (value) {
                                setAttributes({ listColumns: value || '' });
                            }
                        }) : null,
                        type === 'list' ? createElement(ToggleControl, {
                            label: __('Show advertiser search', 'million-dollar-script'),
                            __nextHasNoMarginBottom: true,
                            checked: attributes.listSearch !== false,
                            onChange: function (value) {
                                setAttributes({ listSearch: !!value });
                            }
                        }) : null
                    ),
                    type === 'stats' ? colorSettingsPanel(attributes, setAttributes) : null
                ),
                createElement('div', blockProps({
                    className: 'mds3-editor-page-block mds3-editor-page-block-' + type,
                    tabIndex: 0,
                    role: 'button',
                    'aria-label': __('Select Million Dollar Script page block', 'million-dollar-script'),
                    onMouseDown: selectBlockOnPointer(props.clientId),
                    onClick: selectBlockOnPointer(props.clientId),
                    onKeyDown: selectBlockOnKey(props.clientId)
                }),
                    previewHeader(pageVariationIcon(type), pageType.label, __('Customer flow', 'million-dollar-script')),
                    pagePanelPreview(pageType, selectedGrid, attributes),
                    summaryGrid(pageTypeUsesGridDisplay(type)
                        ? gridSummaryItems(selectedGrid, attributes, type === 'order' ? gridModeLabel(false) : gridModeLabel(!!attributes.readOnly)).concat([
                            { label: __('Renderer', 'million-dollar-script'), value: rendererLabel(attributes.renderer || 'auto') },
                            { label: __('Stats', 'million-dollar-script'), value: statsVisibilityLabel(attributes.showStats) }
                        ])
                        : [
                            { label: __('Page type', 'million-dollar-script'), value: pageType.label },
                            { label: __('Grid', 'million-dollar-script'), value: selectedGrid ? selectedGrid.title : __('First available', 'million-dollar-script') }
                        ])
                )
            );
        },
        save: function (props) {
            return shortcodeMarkup('mds3_page', pageShortcodeAttributes(props.attributes || {}));
        },
        deprecated: emptyDynamicBlockDeprecation({
            type: { type: 'string', default: 'grid' },
            id: { type: 'number', default: 0 },
            readOnly: { type: 'boolean', default: true },
            width: { type: 'string', default: defaultWidth },
            height: { type: 'string', default: defaultHeight },
            renderer: { type: 'string', default: 'auto' },
            showStats: { type: 'string', default: 'inherit' },
            unit: { type: 'string', default: 'settings' },
            listLayout: { type: 'string', default: 'list' },
            listColumns: { type: 'string', default: '' },
            listSearch: { type: 'boolean', default: true },
            numberColor: { type: 'string', default: '' },
            labelColor: { type: 'string', default: '' },
            backgroundColor: { type: 'string', default: '' },
            borderColor: { type: 'string', default: '' }
        }),
        transforms: {
            from: [
                pageShortcodeTransform(),
                rawShortcodeTransform('mds/page', 'mds3_page', pageAttributesFromShortcode, function (attrs) {
                    return shortcodeNamed(attrs, 'type', 'grid') !== 'stats';
                })
            ]
        }
    });

    if (wp.blocks.registerBlockVariation && pageTypes.length) {
        pageTypes.forEach(function (pageType) {
            wp.blocks.registerBlockVariation('mds/page', {
                name: pageType.type,
                title: pageType.label,
                description: pageType.description,
                icon: pageVariationIcon(pageType.type),
                keywords: mdsKeywords([pageType.label, pageType.type]),
                attributes: pageDefaultAttributes(pageType.type),
                scope: ['inserter'],
                isActive: function (attributes) {
                    return attributes.type === pageType.type;
                }
            });
        });
    }

    extensionBlocks.forEach(function (definition) {
        if (!definition || !definition.name || wp.blocks.getBlockType(definition.name)) {
            return;
        }

        wp.blocks.registerBlockType(definition.name, {
            apiVersion: blockApiVersion,
            title: definition.title || definition.name,
            description: definition.description || '',
            icon: definition.icon || 'screenoptions',
            category: definition.category || blockCategory,
            keywords: mdsKeywords((definition.keywords || []).concat([definition.title || '', definition.name || ''])),
            attributes: definition.attributes || {},
            transforms: extensionTransforms(definition),
            edit: function (props) {
                var attributes = props.attributes;
                var controls = Array.isArray(definition.controls) ? definition.controls : [];

                return createElement(Fragment, null,
                    createElement(InspectorControls, null,
                        controls.length ? createElement(PanelBody, { title: __('Block settings', 'million-dollar-script'), initialOpen: true },
                            controls.map(function (control) {
                                return extensionControl(control, attributes, props.setAttributes);
                            })
                        ) : null
                    ),
                    createElement('div', blockProps({
                        className: 'mds3-editor-extension-block',
                        tabIndex: 0,
                        role: 'button',
                        'aria-label': definition.title || __('Select Million Dollar Script extension block', 'million-dollar-script'),
                        onMouseDown: selectBlockOnPointer(props.clientId),
                        onClick: selectBlockOnPointer(props.clientId),
                        onKeyDown: selectBlockOnKey(props.clientId)
                    }),
                        extensionBlockPreview(definition, attributes)
                    )
                );
            },
            save: function (props) {
                return extensionSave(definition, props.attributes || {});
            },
            deprecated: emptyDynamicBlockDeprecation(definition.attributes || {})
        });
    });
}(window.wp));
