<?php
/**
 * Grid, order, package, pricing, availability, and standard-page panels.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Grid\GridPostType;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Grid\PackageRepository;
use MillionDollarScript\V3\Grid\PriceRuleRepository;
use MillionDollarScript\V3\Media\AdvertiserPageManager;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderPlacementMovePlanner;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\OrderRenewal;
use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersGridPanels {

    private function grid_public_page_panel($grid, $wrap = true) {
        Template::display('admin/partials/grid-public-page-panel.php', [
            'grid' => $grid,
            'wrap' => $wrap,
        ], $this);
    }

    private function grid_public_page_actions($grid, $allow_mode_toggle = true) {
        $page_id = GridPostType::page_id($grid->id());
        $page_url = $page_id ? get_permalink($page_id) : '';
        $edit_url = $page_id ? get_edit_post_link($page_id, '') : '';

        Template::display('admin/partials/grid-public-page-actions.php', [
            'allow_mode_toggle' => (bool) $allow_mode_toggle,
            'edit_url' => $edit_url,
            'grid' => $grid,
            'page_mode' => $page_id ? GridPostType::page_mode($grid->id()) : '',
            'page_id' => $page_id,
            'page_url' => $page_url,
        ], $this);
    }

    private function grid_list_extra_columns(array $context) {
        /**
         * Filters extra columns for the Grids admin list.
         *
         * Return an associative array of column_key => label, or
         * column_key => ['label' => 'Column label']. Core sanitizes keys and
         * labels and ignores reserved core column keys.
         *
         * @param array $columns Extra column definitions.
         * @param array $context Current grid list filters, sorting, and pagination state.
         */
        $raw_columns = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/grid/list/extra/columns', [], $context);
        if (!is_array($raw_columns)) {
            return [];
        }

        $reserved = ['id', 'title', 'status', 'renderer', 'dimensions', 'updated', 'embed', 'public_page', 'actions'];
        $columns = [];

        foreach ($raw_columns as $key => $definition) {
            $key = sanitize_key((string) $key);
            if (!$key || in_array($key, $reserved, true)) {
                continue;
            }

            $label = is_array($definition) ? ($definition['label'] ?? '') : $definition;
            if (!is_scalar($label)) {
                continue;
            }

            $label = sanitize_text_field((string) $label);
            if ('' === $label) {
                continue;
            }

            $columns[$key] = $label;
        }

        return $columns;
    }

    private function grid_list_extra_column_html($column_key, $grid, array $context) {
        $column_key = sanitize_key((string) $column_key);
        if (!$column_key) {
            return '';
        }

        /**
         * Filters HTML for an extra Grids admin list column cell.
         *
         * Core runs the returned value through wp_kses_post() before output.
         *
         * @param string        $html       Cell HTML.
         * @param string        $column_key Sanitized extra column key.
         * @param \MillionDollarScript\V3\Grid\Grid $grid     Current grid row.
         * @param array         $context    Current grid list filters, sorting, and pagination state.
         */
        $html = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/grid/list/column/html', '', $column_key, $grid, $context);
        if (!is_scalar($html)) {
            return '';
        }

        return wp_kses_post((string) $html);
    }

    private function grid_list_extra_row_actions($grid, array $context) {
        /**
         * Filters safe link-style row actions for the Grids admin list.
         *
         * Each action should be an array with label and url. Optional keys:
         * class, target, rel, capability. Core escapes labels, classes, URLs,
         * target, and rel; unsafe protocols or unauthorized actions are skipped.
         *
         * @param array           $actions Extra row actions.
         * @param \MillionDollarScript\V3\Grid\Grid $grid    Current grid row.
         * @param array           $context Current grid list filters, sorting, and pagination state.
         */
        $raw_actions = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/admin/grid/list/row/actions', [], $grid, $context);
        if (!is_array($raw_actions)) {
            return;
        }

        foreach ($raw_actions as $key => $action) {
            if (!is_array($action)) {
                continue;
            }

            $capability = is_scalar($action['capability'] ?? null) ? (string) $action['capability'] : '';
            if ($capability && !current_user_can($capability)) {
                continue;
            }

            $label = is_scalar($action['label'] ?? null) ? sanitize_text_field((string) $action['label']) : '';
            $url = is_scalar($action['url'] ?? null) ? esc_url((string) $action['url']) : '';
            if ('' === $label || '' === $url) {
                continue;
            }

            $class = is_scalar($action['class'] ?? null) ? sanitize_html_class((string) $action['class']) : '';
            $target = is_scalar($action['target'] ?? null) ? (string) $action['target'] : '';
            $target = in_array($target, ['_blank', '_self'], true) ? $target : '';
            $rel = is_scalar($action['rel'] ?? null) ? sanitize_text_field((string) $action['rel']) : '';
            if ('_blank' === $target && false === strpos($rel, 'noopener')) {
                $rel = trim($rel . ' noopener noreferrer');
            }

            $classes = trim('button button-small ' . $class);
            ?>
            <a class="<?php echo esc_attr($classes); ?>" href="<?php echo esc_url($url); ?>"<?php echo $target ? ' target="' . esc_attr($target) . '"' : ''; ?><?php echo $rel ? ' rel="' . esc_attr($rel) . '"' : ''; ?>>
                <?php echo esc_html($label); ?>
            </a>
            <?php
        }
    }

    private function standard_pages_panel($standalone = true) {
        Template::display('admin/partials/standard-pages-panel.php', [
            'grid_enabled' => $this->grid_enabled(),
            'standalone' => $standalone,
        ], $this);
    }

    private function missing_standard_pages() {
        $missing = [];
        foreach (PageRepository::standard_labels() as $type => $label) {
            $post_id = absint(get_option('mds3_page_' . $type . '_id', 0));
            if (!$post_id || !get_post($post_id)) {
                $missing[$type] = $label;
            }
        }

        return $missing;
    }

    private function settings_page_map() {
        $types = PageRepository::labels();
        $rows = [];
        foreach ($types as $type => $label) {
            $post_id = absint(get_option('mds3_page_' . $type . '_id', 0));
            $title = $post_id ? get_the_title($post_id) : '';

            $rows[] = [
                'edit_url' => $post_id && $title ? get_edit_post_link($post_id) : '',
                'label' => $label,
                'permalink' => $post_id && $title ? get_permalink($post_id) : '',
                'post_id' => $post_id,
                'shortcode' => PageRepository::shortcode($type, 0),
                'title' => $title,
            ];
        }

        Template::display('admin/partials/settings-page-map.php', [
            'rows' => $rows,
        ], $this);
    }

    private function order_status_button(array $order, $status, $label) {
        if ($status === ($order['status'] ?? '')) {
            return;
        }

        $order_id = absint($order['id'] ?? 0);
        $confirm = '';
        if ('cancelled' === $status) {
            /* translators: %d: order ID. */
            $confirm = sprintf(__('Cancel order #%d? Select OK to cancel it, or Cancel to keep it unchanged.', 'million-dollar-script'), $order_id);
        }

        $this->inline_post_button(
            'mds3_update_order_status',
            'mds3_update_order_status_' . $order_id . '_' . sanitize_key($status),
            [
                'order_id' => $order_id,
                'status' => sanitize_key($status),
            ],
            $label,
            'button-small',
            $confirm
        );
    }

    private function order_status_labels() {
        return [
            'reserved' => __('Reserved', 'million-dollar-script'),
            'pending_payment' => __('Awaiting payment', 'million-dollar-script'),
            'paid' => __('Paid', 'million-dollar-script'),
            'cancelled' => __('Cancelled', 'million-dollar-script'),
            'failed' => __('Failed', 'million-dollar-script'),
            'refunded' => __('Refunded', 'million-dollar-script'),
            'expired' => __('Expired', 'million-dollar-script'),
            'denied' => __('Denied', 'million-dollar-script'),
            'deleted' => __('Deleted', 'million-dollar-script'),
        ];
    }

    private function order_bulk_statuses() {
        return ['paid', 'cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'];
    }

    private function order_bulk_status_labels() {
        return array_intersect_key($this->order_status_labels(), array_flip($this->order_bulk_statuses()));
    }

    private function order_detail_panel(array $order, $inline = false) {
        $repo = new OrderRepository();
        $items = $repo->items($order['id']);
        $rect = $repo->item_rect($order['id']);
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $renewals = new OrderRenewal();
        $status_labels = $this->order_status_labels();

        $manage_url = $this->order_manage_url($order);
        $order_placements = (new PlacementRepository())->for_order(absint($order['id'] ?? 0));
        $first_placement_id = $order_placements ? absint($order_placements[0]['id'] ?? 0) : 0;
        $page_manager = new AdvertiserPageManager();
        $advertiser_page_url = $first_placement_id
            ? ($page_manager->public_url($first_placement_id) ?: (string) ($page_manager->legacy_public_urls([$first_placement_id])[$first_placement_id] ?? ''))
            : '';
        $item_rows = [];
        $placement_events = [];
        $status_events = [];
        $move_notice = get_transient($this->order_move_notice_key(absint($order['id'] ?? 0)));
        if (is_array($move_notice)) {
            delete_transient($this->order_move_notice_key(absint($order['id'] ?? 0)));
        } else {
            $move_notice = [];
        }

        foreach (array_reverse(is_array($metadata['status_events'] ?? null) ? $metadata['status_events'] : []) as $event) {
            $event_status = sanitize_key((string) ($event['status'] ?? ''));
            if (!$event_status) {
                continue;
            }

            $user_id = absint($event['user_id'] ?? 0);
            $user = $user_id ? get_userdata($user_id) : null;
            $status_events[] = [
                'created_at' => sanitize_text_field((string) ($event['created_at'] ?? '')),
                'source' => sanitize_key((string) ($event['source'] ?? '')),
                'status' => $status_labels[$event_status] ?? $event_status,
                /* translators: %d: user ID. */
                'user' => $user_id ? ($user ? $user->display_name : sprintf(__('User #%d', 'million-dollar-script'), $user_id)) : __('System', 'million-dollar-script'),
            ];
        }

        foreach (array_reverse(is_array($metadata['placement_events'] ?? null) ? $metadata['placement_events'] : []) as $event) {
            $from = is_array($event['from'] ?? null) ? $event['from'] : [];
            $to = is_array($event['to'] ?? null) ? $event['to'] : [];
            if (!isset($from['row'], $from['col'], $to['row'], $to['col'])) {
                continue;
            }

            $user_id = absint($event['user_id'] ?? 0);
            $user = $user_id ? get_userdata($user_id) : null;
            $placement_events[] = [
                'block_count' => absint($event['block_count'] ?? 0),
                'created_at' => sanitize_text_field((string) ($event['created_at'] ?? '')),
                'from_col' => absint($from['col']),
                'from_row' => absint($from['row']),
                'grid_id' => absint($event['grid_id'] ?? 0),
                'source' => sanitize_key((string) ($event['source'] ?? '')),
                'to_col' => absint($to['col']),
                'to_row' => absint($to['row']),
                /* translators: %d: user ID. */
                'user' => $user_id ? ($user ? $user->display_name : sprintf(__('User #%d', 'million-dollar-script'), $user_id)) : __('System', 'million-dollar-script'),
            ];
        }

        foreach ($items as $item) {
            $item_meta = json_decode((string) ($item['metadata'] ?? ''), true);
            $item_meta = is_array($item_meta) ? $item_meta : [];
            $rectangle = isset($item_meta['x'], $item_meta['y'], $item_meta['width'], $item_meta['height'])
                ? $item_meta['x'] . ',' . $item_meta['y'] . ' ' . $item_meta['width'] . 'x' . $item_meta['height']
                : '-';
            $item_rows[] = [
                'block_id' => $item['block_id'] ?: '-',
                'grid_id' => $item['grid_id'],
                'item_type' => $item['item_type'],
                'price_source' => $item_meta['price_source'] ?? 'legacy',
                'rectangle' => $rectangle,
                'total' => number_format_i18n((float) $item['total'], 2),
            ];
        }

        Template::display('admin/partials/order-detail-panel.php', [
            'advertiser_page_url' => $advertiser_page_url,
            'inline' => $inline,
            'item_rows' => $item_rows,
            'manage_url' => $manage_url,
            'metadata' => $metadata,
            'move_notice' => $move_notice,
            'order' => $order,
            'placement_events' => $placement_events,
            'placement_maps' => $this->order_placement_maps($items, $order),
            'rect' => $rect,
            'renewal_available' => $renewals->can_renew($order),
            'renewal_nonce' => wp_create_nonce('mds3_start_order_renewal_' . absint($order['id'] ?? 0)),
            'status_events' => $status_events,
        ], $this);
    }

    private function order_placement_maps(array $items, array $order) {
        $grids = [];
        $repo = new GridRepository();
        $block_repo = new BlockRepository();

        foreach ($items as $item) {
            $grid_id = absint($item['grid_id'] ?? 0);
            if (!$grid_id) {
                continue;
            }

            $item_meta = json_decode((string) ($item['metadata'] ?? ''), true);
            $item_meta = is_array($item_meta) ? $item_meta : [];
            if (!isset($item_meta['x'], $item_meta['y'], $item_meta['width'], $item_meta['height'])) {
                continue;
            }

            if (!isset($grids[$grid_id])) {
                $grid = $repo->find($grid_id);
                if (!$grid) {
                    continue;
                }

                $geometry = $grid->geometry();
                $width = max(1, absint($grid->get('width', 1)));
                $height = max(1, absint($grid->get('height', 1)));
                $grids[$grid_id] = [
                    'block_height' => max(1, absint($grid->get('block_height', 1))),
                    'block_width' => max(1, absint($grid->get('block_width', 1))),
                    'blocks' => $this->region_editor_block_payload($grid, $block_repo->for_grid($grid_id, ['sold', 'reserved', 'unavailable'])),
                    'cols' => $geometry->columns(),
                    'grid_id' => $grid_id,
                    'height' => $height,
                    'regions' => $this->region_editor_region_payload($block_repo->unavailable_regions($grid)),
                    'rows' => $geometry->rows(),
                    'selections' => [],
                    'status_text' => '',
                    /* translators: %d: grid ID. */
                    'title' => (string) $grid->get('title', sprintf(__('Grid #%d', 'million-dollar-script'), $grid_id)),
                    'total_blocks' => $geometry->total_blocks(),
                    'width' => $width,
                ];
            }

            $width = max(1, absint($item_meta['width']));
            $height = max(1, absint($item_meta['height']));
            $x = max(0, absint($item_meta['x']));
            $y = max(0, absint($item_meta['y']));
            $grid_width = max(1, absint($grids[$grid_id]['width']));
            $grid_height = max(1, absint($grids[$grid_id]['height']));
            $block_width = max(1, absint($grids[$grid_id]['block_width']));
            $block_height = max(1, absint($grids[$grid_id]['block_height']));
            $col_from = min(max(0, $grids[$grid_id]['cols'] - 1), intdiv(min($x, $grid_width - 1), $block_width));
            $row_from = min(max(0, $grids[$grid_id]['rows'] - 1), intdiv(min($y, $grid_height - 1), $block_height));
            $col_to = min(max(0, $grids[$grid_id]['cols'] - 1), intdiv(min($grid_width - 1, $x + $width - 1), $block_width));
            $row_to = min(max(0, $grids[$grid_id]['rows'] - 1), intdiv(min($grid_height - 1, $y + $height - 1), $block_height));
            $label = sprintf(
                /* translators: 1: x, 2: y, 3: width, 4: height */
                __('%1$d,%2$d %3$dx%4$d', 'million-dollar-script'),
                $x,
                $y,
                $width,
                $height
            );

            $grids[$grid_id]['selections'][] = [
                'block_id' => absint($item['block_id'] ?? 0),
                'col_from' => min($col_from, $col_to),
                'col_to' => max($col_from, $col_to),
                'label' => $label,
                'row_from' => min($row_from, $row_to),
                'row_to' => max($row_from, $row_to),
            ];
        }

        foreach ($grids as &$grid) {
            $count = count($grid['selections']);
            if (1 === $count) {
                $grid['status_text'] = $grid['selections'][0]['label'];
            } elseif ($count > 1) {
                $grid['status_text'] = sprintf(
                    /* translators: %d: placement count. */
                    _n('%d placement highlighted.', '%d placements highlighted.', $count, 'million-dollar-script'),
                    $count
                );
            }

            $row_from = null;
            $row_to = null;
            $col_from = null;
            $col_to = null;
            foreach ($grid['selections'] as $selection) {
                $row_from = null === $row_from ? absint($selection['row_from']) : min($row_from, absint($selection['row_from']));
                $row_to = null === $row_to ? absint($selection['row_to']) : max($row_to, absint($selection['row_to']));
                $col_from = null === $col_from ? absint($selection['col_from']) : min($col_from, absint($selection['col_from']));
                $col_to = null === $col_to ? absint($selection['col_to']) : max($col_to, absint($selection['col_to']));
            }

            $shape = [];
            if (null !== $row_from && null !== $col_from) {
                $shape_keys = [];
                foreach ($grid['selections'] as $selection) {
                    for ($row = absint($selection['row_from']); $row <= absint($selection['row_to']); $row++) {
                        for ($col = absint($selection['col_from']); $col <= absint($selection['col_to']); $col++) {
                            $shape_keys[($row - $row_from) . ':' . ($col - $col_from)] = [
                                'row' => $row - $row_from,
                                'col' => $col - $col_from,
                            ];
                        }
                    }
                }
                $shape = array_values($shape_keys);
            }

            $grid['move_allowed'] = $shape && OrderPlacementMovePlanner::can_move_status($order['status'] ?? '');
            $grid['move_col_span'] = null === $col_from ? 0 : max(1, $col_to - $col_from + 1);
            $grid['move_nonce'] = wp_create_nonce('mds3_preview_order_move_' . absint($order['id'] ?? 0) . '_' . absint($grid['grid_id']));
            $grid['move_post_nonce'] = wp_create_nonce('mds3_move_order_placement_' . absint($order['id'] ?? 0) . '_' . absint($grid['grid_id']));
            $grid['move_row_span'] = null === $row_from ? 0 : max(1, $row_to - $row_from + 1);
            $grid['move_shape'] = $shape;
            $grid['order_id'] = absint($order['id'] ?? 0);
        }
        unset($grid);

        return array_values($grids);
    }

    private function order_manage_url(array $order) {
        if (empty($order['order_key']) || empty($order['id'])) {
            return '';
        }

        $page_id = absint(get_option('mds3_page_upload_id', 0));
        $base = $page_id ? get_permalink($page_id) : '';
        if (!$base) {
            return '';
        }

        return add_query_arg([
            'mds3_order_id' => absint($order['id']),
            'mds3_order_key' => (string) $order['order_key'],
        ], $base);
    }

    private function packages_panel($grid, $wrap = true) {
        $repo = new PackageRepository();
        $packages = $repo->for_grid($grid->id());
        $currency = Currency::code($grid->get('currency', 'USD'));
        $currency_locked = Currency::provider_locks_currency();
        $package_rows = [];

        foreach ($packages as $package) {
            $package_currency = Currency::code((string) ($package['currency'] ?? $currency));
            $package_rows[] = [
                'data_json' => wp_json_encode([
                    'id' => absint($package['id'] ?? 0),
                    'title' => (string) ($package['title'] ?? ''),
                    'description' => (string) ($package['description'] ?? ''),
                    'duration_days' => absint($package['duration_days'] ?? 0),
                    'price' => (string) ($package['price'] ?? '0.00'),
                    'currency' => Currency::code((string) ($package['currency'] ?? $grid->get('currency', 'USD'))),
                    'max_orders' => absint($package['max_orders'] ?? 0),
                    'is_default' => !empty($package['is_default']) ? '1' : '0',
                    'status' => (string) ($package['status'] ?? 'active'),
                ]),
                'duration_days' => absint($package['duration_days'] ?? 0),
                'id' => absint($package['id'] ?? 0),
                'is_default_label' => !empty($package['is_default']) ? __('yes', 'million-dollar-script') : __('no', 'million-dollar-script'),
                'max_orders' => absint($package['max_orders'] ?? 0),
                'price_label' => Currency::format((float) ($package['price'] ?? 0), $package_currency),
                'status' => (string) ($package['status'] ?? ''),
                'title' => (string) ($package['title'] ?? ''),
            ];
        }

        Template::display('admin/partials/packages-panel.php', [
            'currency' => $currency,
            'currency_locked' => $currency_locked,
            'grid' => $grid,
            'package_rows' => $package_rows,
            'wrap' => $wrap,
        ], $this);
    }

    private function price_rules_panel($grid, $wrap = true) {
        $repo = new PriceRuleRepository();
        $rules = $repo->for_grid($grid->id());
        $currency = Currency::code($grid->get('currency', 'USD'));
        $currency_locked = Currency::provider_locks_currency();
        $rule_rows = [];

        foreach ($rules as $rule) {
            $rule_currency = Currency::code((string) ($rule['currency'] ?? $currency));
            $rule_rows[] = [
                'block_id_from' => null === ($rule['block_id_from'] ?? null) ? '' : $rule['block_id_from'],
                'block_id_to' => null === ($rule['block_id_to'] ?? null) ? '' : $rule['block_id_to'],
                'block_label' => $this->range_label($rule['block_id_from'] ?? null, $rule['block_id_to'] ?? null),
                'col_from' => null === ($rule['col_from'] ?? null) ? '' : $rule['col_from'],
                'col_label' => $this->range_label($rule['col_from'] ?? null, $rule['col_to'] ?? null),
                'col_to' => null === ($rule['col_to'] ?? null) ? '' : $rule['col_to'],
                'color' => ($rule['color'] ?? '') ?: '#2563eb',
                'currency' => Currency::code($rule['currency'] ?? $currency),
                'id' => absint($rule['id'] ?? 0),
                'price' => $rule['price'] ?? '',
                'price_label' => Currency::format((float) ($rule['price'] ?? 0), $rule_currency),
                'row_from' => null === ($rule['row_from'] ?? null) ? '' : $rule['row_from'],
                'row_label' => $this->range_label($rule['row_from'] ?? null, $rule['row_to'] ?? null),
                'row_to' => null === ($rule['row_to'] ?? null) ? '' : $rule['row_to'],
                'status' => $rule['status'] ?? '',
            ];
        }

        Template::display('admin/partials/price-rules-panel.php', [
            'currency' => $currency,
            'currency_locked' => $currency_locked,
            'grid' => $grid,
            'rule_rows' => $rule_rows,
            'rules' => $rules,
            'wrap' => $wrap,
        ], $this);
    }

    private function availability_panel($grid, $wrap = true) {
        $repo = new BlockRepository();
        $counts = $repo->counts($grid->id());
        $virtual = $grid->geometry()->total_blocks();
        $blocks = $repo->for_grid($grid->id(), ['sold', 'reserved', 'unavailable']);
        $regions = $repo->unavailable_regions($grid);
        $count_rows = [];
        $region_rows = [];
        foreach (['sold', 'reserved', 'unavailable', 'available'] as $status) {
            $count_rows[] = [
                'count' => number_format_i18n(absint($counts[$status] ?? 0)),
                'status' => $status,
            ];
        }
        foreach ($regions as $region) {
            $region_rows[] = [
                'col_from' => $region['col_from'],
                'col_label' => $this->range_label($region['col_from'], $region['col_to']),
                'col_to' => $region['col_to'],
                'count' => number_format_i18n(absint($region['count'] ?? 0)),
                'note' => $region['note'] ?? '',
                'row_from' => $region['row_from'],
                'row_label' => $this->range_label($region['row_from'], $region['row_to']),
                'row_to' => $region['row_to'],
            ];
        }

        Template::display('admin/partials/availability-panel.php', [
            'blocks' => $blocks,
            'count_rows' => $count_rows,
            'grid' => $grid,
            'region_error' => !empty($_GET['region_error']) ? sanitize_text_field(wp_unslash($_GET['region_error'])) : '',
            'region_rows' => $region_rows,
            'region_updated' => isset($_GET['region_updated']) ? [
                'changed' => number_format_i18n(absint($_GET['region_updated'])),
                'skipped' => number_format_i18n(absint($_GET['region_skipped'] ?? 0)),
            ] : null,
            'regions' => $regions,
            'virtual' => number_format_i18n($virtual),
            'wrap' => $wrap,
        ], $this);
    }

    private function region_editor($grid, $mode, array $rules, array $blocks, array $regions = []) {
        $geometry = $grid->geometry();
        $payload_blocks = $this->region_editor_block_payload($grid, $blocks);

        $payload_rules = [];
        foreach ($rules as $rule) {
            $payload_rules[] = [
                'row_from' => null === $rule['row_from'] ? null : absint($rule['row_from']),
                'row_to' => null === $rule['row_to'] ? null : absint($rule['row_to']),
                'col_from' => null === $rule['col_from'] ? null : absint($rule['col_from']),
                'col_to' => null === $rule['col_to'] ? null : absint($rule['col_to']),
                'price' => (float) ($rule['price'] ?? 0),
                'color' => sanitize_text_field($rule['color'] ?? ''),
                'status' => sanitize_key($rule['status'] ?? 'active'),
            ];
        }

        $payload_regions = $this->region_editor_region_payload($regions);

        Template::display('admin/partials/region-editor.php', [
            'blocks_json' => wp_json_encode($payload_blocks),
            'cols' => $geometry->columns(),
            'mode' => $mode,
            'regions_json' => wp_json_encode($payload_regions),
            'rows' => $geometry->rows(),
            'rules_json' => wp_json_encode($payload_rules),
            'total_blocks' => $geometry->total_blocks(),
        ], $this);
    }

    private function region_editor_block_payload($grid, array $blocks) {
        $geometry = $grid->geometry();
        $payload_blocks = [];

        foreach ($blocks as $block) {
            $coord = $geometry->coordinate_from_pixel(absint($block['x'] ?? 0), absint($block['y'] ?? 0));
            if (!$coord) {
                continue;
            }
            $metadata = json_decode((string) ($block['metadata'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];
            $status = sanitize_key($block['status'] ?? 'available');
            if ('unavailable' === $status && !empty($metadata['availability_region_id'])) {
                continue;
            }

            $payload_blocks[] = [
                'row' => absint($coord['row']),
                'col' => absint($coord['col']),
                'status' => $status,
                'region_id' => sanitize_key((string) ($metadata['availability_region_id'] ?? '')),
            ];
        }

        return $payload_blocks;
    }

    private function region_editor_region_payload(array $regions) {
        $payload_regions = [];

        foreach ($regions as $region) {
            $payload_regions[] = [
                'id' => sanitize_key((string) ($region['id'] ?? '')),
                'row_from' => absint($region['row_from'] ?? 0),
                'row_to' => absint($region['row_to'] ?? 0),
                'col_from' => absint($region['col_from'] ?? 0),
                'col_to' => absint($region['col_to'] ?? 0),
                'count' => absint($region['count'] ?? 0),
                'note' => sanitize_text_field((string) ($region['note'] ?? '')),
                'virtual' => !empty($region['virtual']),
            ];
        }

        return $payload_regions;
    }

    private function range_label($from, $to) {
        if (null === $from && null === $to) {
            return '*';
        }

        return (null === $from ? '*' : (string) $from) . '-' . (null === $to ? '*' : (string) $to);
    }
}
