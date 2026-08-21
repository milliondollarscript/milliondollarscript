<?php
/**
 * Reservation workflow shared by frontend AJAX and REST.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Grid\Grid;
use MillionDollarScript\V3\Grid\PackageRepository;
use MillionDollarScript\V3\Grid\PriceRuleRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class ReservationService {

    public function reserve(Grid $grid, array $coords, array $data = []) {
        $coords = $this->normalized_coords($grid, $coords);
        if (is_wp_error($coords)) {
            return $coords;
        }

        if (!$coords) {
            return new \WP_Error('mds3_no_blocks_selected', __('No blocks selected.', 'million-dollar-script'));
        }

        (new OrderCleanup())->run_if_due(5 * MINUTE_IN_SECONDS, 50);

        $settings = $grid->settings();
        $global_settings = $this->settings();
        $min_blocks = max(1, absint($settings['min_blocks'] ?? 1));
        $max_blocks = absint($settings['max_blocks'] ?? 0);
        if (count($coords) < $min_blocks) {
            return new \WP_Error('mds3_too_few_blocks', sprintf(
                /* translators: %d: minimum blocks */
                __('Select at least %d blocks.', 'million-dollar-script'),
                $min_blocks
            ));
        }
        if ($max_blocks && count($coords) > $max_blocks) {
            return new \WP_Error('mds3_too_many_blocks', sprintf(
                /* translators: %d: maximum blocks */
                __('Select no more than %d blocks.', 'million-dollar-script'),
                $max_blocks
            ));
        }

        $shape_validation = $this->validate_selection_shape($coords);
        if (is_wp_error($shape_validation)) {
            return $shape_validation;
        }

        $repo = new BlockRepository();
        $materialized = [];
        $reserved = [];
        $items = [];
        $user_id = array_key_exists('user_id', $data) ? absint($data['user_id']) : get_current_user_id();
        $email = $this->customer_email($user_id, $data['email'] ?? '');
        $customer_validation = $this->validate_customer($global_settings, $user_id, $email, $data);
        if (is_wp_error($customer_validation)) {
            return $customer_validation;
        }
        $price_rules = new PriceRuleRepository();
        $package = $this->selected_package($grid, $data);
        if (is_wp_error($package)) {
            return $package;
        }
        $commerce_validation = \MillionDollarScript\Core\Hooks::apply(
            'million-dollar-script/grid/order/validate-commerce-selection',
            true,
            [
                'grid_id' => $grid->id(),
                'block_count' => count($coords),
                'package_id' => absint($package['id'] ?? 0),
                'subscription_plan_id' => absint($data['metadata']['subscription_plan_id'] ?? 0),
            ]
        );
        if (is_wp_error($commerce_validation)) {
            return $commerce_validation;
        }
        $limit_validation = $this->enforce_order_limits($grid, $package);
        if (is_wp_error($limit_validation)) {
            return $limit_validation;
        }
        $unavailable_regions = $repo->stored_unavailable_regions_for_grid($grid);

        foreach ($coords as $coord) {
            $row = absint($coord['row'] ?? 0);
            $col = absint($coord['col'] ?? 0);
            if ($this->coordinate_in_unavailable_regions($unavailable_regions, $row, $col)) {
                return new \WP_Error('mds3_blocks_unavailable', __('One or more selected blocks are no longer available.', 'million-dollar-script'));
            }

            $block = $repo->materialize($grid, $row, $col);
            if (is_wp_error($block)) {
                return $block;
            }

            if ('available' !== ($block['status'] ?? '')) {
                return new \WP_Error('mds3_blocks_unavailable', __('One or more selected blocks are no longer available.', 'million-dollar-script'));
            }

            $materialized[] = $block;
        }

        $reservation_minutes = $this->reservation_minutes();
        foreach ($materialized as $block) {
            $block = $repo->reserve($block, $user_id, $reservation_minutes);
            if (is_wp_error($block)) {
                return $block;
            }

            $reserved[] = $this->block_payload($block);
            $pricing = $price_rules->effective_price($grid, $block);
            $unit_price = (float) ($pricing['price'] ?? $grid->get('price_per_block', 0));
            $items[] = [
                'grid_id' => $grid->id(),
                'block_id' => absint($block['id']),
                'item_type' => 'block',
                'quantity' => 1,
                'unit_price' => $unit_price,
                'total' => $unit_price,
                'metadata' => [
                    'x' => absint($block['x'] ?? 0),
                    'y' => absint($block['y'] ?? 0),
                    'width' => absint($block['width'] ?? 0),
                    'height' => absint($block['height'] ?? 0),
                    'price_source' => sanitize_key($pricing['source'] ?? 'grid'),
                    'price_rule_id' => !empty($pricing['rule']['id']) ? absint($pricing['rule']['id']) : 0,
                    'price_currency' => \MillionDollarScript\V3\Commerce\Currency::code($pricing['currency'] ?? $grid->get('currency', 'USD')),
                    'package_id' => $package ? absint($package['id'] ?? 0) : 0,
                ],
            ];
        }

        if ($package && (float) ($package['price'] ?? 0) > 0) {
            $items = $this->apply_package_price($items, (float) $package['price']);
        }

        $order_repo = new OrderRepository();
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $duration_days = $package ? absint($package['duration_days'] ?? 0) : absint($settings['days_expire'] ?? 0);
        if ($duration_days) {
            $metadata['duration_days'] = $duration_days;
            $metadata['expires_at'] = gmdate('Y-m-d H:i:s', time() + ($duration_days * DAY_IN_SECONDS));
        }
        if ($package) {
            $metadata['package_id'] = absint($package['id'] ?? 0);
            $metadata['package'] = [
                'id' => absint($package['id'] ?? 0),
                'title' => sanitize_text_field($package['title'] ?? ''),
                'duration_days' => absint($package['duration_days'] ?? 0),
                'max_orders' => absint($package['max_orders'] ?? 0),
                'price' => (float) ($package['price'] ?? 0),
            ];
        }

        $order_id = $order_repo->create($items, [
            'user_id' => $user_id,
            'email' => $email,
            'status' => 'reserved',
            'block_status' => 'reserved',
            'currency' => \MillionDollarScript\V3\Commerce\Currency::code($package['currency'] ?? $grid->get('currency', 'USD')),
            'commerce_provider' => Payments::active_provider_id(),
            'metadata' => $metadata,
        ]);

        if (is_wp_error($order_id)) {
            $repo->release_ids(wp_list_pluck($reserved, 'id'));
            return $order_id;
        }

        $order = $order_repo->find($order_id);

        return [
            'blocks' => $reserved,
            'order' => $order,
            'order_key' => $order['order_key'] ?? '',
            'placement_rect' => $order_repo->item_rect($order_id),
            'checkout' => [],
            'message' => __('Selection reserved and order created.', 'million-dollar-script'),
        ];
    }

    private function customer_email($user_id, $email) {
        $email = sanitize_email((string) $email);
        if ($email) {
            return $email;
        }

        if ($user_id && function_exists('get_userdata')) {
            $user = get_userdata($user_id);
            if ($user && !empty($user->user_email)) {
                return sanitize_email((string) $user->user_email);
            }
        }

        return '';
    }

    private function validate_customer(array $settings, $user_id, $email, array $data) {
        $accounts_optional = SettingsSchema::sanitize('accounts-optional', $settings['accounts-optional'] ?? 'yes');
        $email_provided = array_key_exists('email', $data) && '' !== trim((string) $data['email']);

        if (!$user_id && 'yes' !== $accounts_optional) {
            return new \WP_Error('mds3_login_required', __('Sign in before reserving blocks.', 'million-dollar-script'));
        }

        if (($email || $email_provided) && !is_email($email)) {
            return new \WP_Error('mds3_invalid_customer_email', __('Enter a valid email address before reserving blocks.', 'million-dollar-script'));
        }

        if (!$user_id && !$email) {
            return new \WP_Error('mds3_customer_email_required', __('Enter your email address before reserving blocks.', 'million-dollar-script'));
        }

        return true;
    }

    private function block_payload(array $block) {
        return [
            'id' => absint($block['id'] ?? 0),
            'grid_id' => absint($block['grid_id'] ?? 0),
            'x' => absint($block['x'] ?? 0),
            'y' => absint($block['y'] ?? 0),
            'width' => absint($block['width'] ?? 0),
            'height' => absint($block['height'] ?? 0),
            'status' => sanitize_key($block['status'] ?? 'available'),
        ];
    }

    private function selected_package(Grid $grid, array $data) {
        $package_id = absint($data['package_id'] ?? 0);
        if (!$package_id) {
            return null;
        }

        $package = (new PackageRepository())->find($package_id);
        if (!$package || absint($package['grid_id'] ?? 0) !== $grid->id() || 'active' !== ($package['status'] ?? '')) {
            return new \WP_Error('mds3_invalid_package', __('Selected package is not available for this grid.', 'million-dollar-script'));
        }

        return $package;
    }

    private function coordinate_in_unavailable_regions(array $regions, $row, $col) {
        $row = absint($row);
        $col = absint($col);

        foreach ($regions as $region) {
            if (
                $row >= absint($region['row_from'] ?? 0) &&
                $row <= absint($region['row_to'] ?? 0) &&
                $col >= absint($region['col_from'] ?? 0) &&
                $col <= absint($region['col_to'] ?? 0)
            ) {
                return true;
            }
        }

        return false;
    }

    private function enforce_order_limits(Grid $grid, $package) {
        $orders = new OrderRepository();
        $settings = $grid->settings();
        $grid_limit = absint($settings['max_orders'] ?? 0);

        if ($grid_limit && $orders->active_count_for_grid($grid->id()) >= $grid_limit) {
            return new \WP_Error('mds3_grid_order_limit_reached', __('This grid has reached its active order limit.', 'million-dollar-script'));
        }

        if ($package) {
            $package_limit = absint($package['max_orders'] ?? 0);
            if ($package_limit && $orders->active_count_for_package($package['id'] ?? 0) >= $package_limit) {
                return new \WP_Error('mds3_package_order_limit_reached', __('Selected package has reached its active order limit.', 'million-dollar-script'));
            }
        }

        return true;
    }

    private function apply_package_price(array $items, $package_price) {
        $count = max(1, count($items));
        $remaining = round((float) $package_price, 2);

        foreach ($items as $index => $item) {
            $price = $index === $count - 1 ? $remaining : round((float) $package_price / $count, 2);
            $remaining = round($remaining - $price, 2);
            $items[$index]['unit_price'] = $price;
            $items[$index]['total'] = $price;
            $items[$index]['metadata']['price_source'] = 'package';
            $items[$index]['metadata']['price_rule_id'] = 0;
        }

        return $items;
    }

    private function settings() {
        $stored_settings = get_option('mds3_settings', []);

        return wp_parse_args(is_array($stored_settings) ? $stored_settings : [], SettingsSchema::defaults());
    }

    private function reservation_minutes() {
        $settings = $this->settings();
        $minutes = (int) SettingsSchema::sanitize('minutes-unconfirmed', $settings['minutes-unconfirmed'] ?? 60);

        return max(1, $minutes);
    }

    private function validate_selection_shape(array $coords) {
        $settings = $this->settings();
        $block_selection_mode = SettingsSchema::sanitize('block-selection-mode', $settings['block-selection-mode'] ?? 'YES');
        if ('NO' === $block_selection_mode && count($coords) > 1) {
            return new \WP_Error('mds3_selection_single_block_only', __('Select only one block for this grid.', 'million-dollar-script'));
        }

        $mode = SettingsSchema::sanitize('selection-adjacency-mode', $settings['selection-adjacency-mode'] ?? 'ADJACENT');
        if ('NONE' === $mode || count($coords) <= 1) {
            return true;
        }

        if ('RECTANGLE' === $mode && !$this->forms_rectangle($coords)) {
            return new \WP_Error('mds3_selection_not_rectangle', __('Selection must form a complete rectangle or square.', 'million-dollar-script'));
        }

        if ('ADJACENT' === $mode && !$this->is_contiguous($coords)) {
            return new \WP_Error('mds3_selection_not_adjacent', __('You must select blocks that touch each other.', 'million-dollar-script'));
        }

        return true;
    }

    private function normalized_coords(Grid $grid, array $coords) {
        $rows = $grid->geometry()->rows();
        $columns = $grid->geometry()->columns();
        $normalized = [];

        foreach ($coords as $coord) {
            if (!is_array($coord)) {
                return new \WP_Error('mds3_selection_invalid', __('Selected blocks must include row and column coordinates.', 'million-dollar-script'));
            }

            if (
                !array_key_exists('row', $coord) ||
                !array_key_exists('col', $coord) ||
                !preg_match('/^-?\d+$/', trim((string) $coord['row'])) ||
                !preg_match('/^-?\d+$/', trim((string) $coord['col']))
            ) {
                return new \WP_Error('mds3_selection_invalid', __('Selected blocks must include row and column coordinates.', 'million-dollar-script'));
            }

            $row = (int) $coord['row'];
            $col = (int) $coord['col'];
            if ($row < 0 || $col < 0) {
                return new \WP_Error('mds3_selection_out_of_bounds', __('Selected blocks are outside the grid.', 'million-dollar-script'));
            }

            if ($row >= $rows || $col >= $columns) {
                return new \WP_Error('mds3_selection_out_of_bounds', __('Selected blocks are outside the grid.', 'million-dollar-script'));
            }

            $normalized[$row . ':' . $col] = [
                'row' => $row,
                'col' => $col,
            ];
        }

        return array_values($normalized);
    }

    private function forms_rectangle(array $coords) {
        if (count($coords) <= 1) {
            return true;
        }

        $rows = [];
        $cols = [];
        $keys = [];
        foreach ($coords as $coord) {
            $rows[] = $coord['row'];
            $cols[] = $coord['col'];
            $keys[$coord['row'] . ':' . $coord['col']] = true;
        }

        $min_row = min($rows);
        $max_row = max($rows);
        $min_col = min($cols);
        $max_col = max($cols);

        if (($max_row - $min_row + 1) * ($max_col - $min_col + 1) !== count($coords)) {
            return false;
        }

        for ($row = $min_row; $row <= $max_row; $row++) {
            for ($col = $min_col; $col <= $max_col; $col++) {
                if (empty($keys[$row . ':' . $col])) {
                    return false;
                }
            }
        }

        return true;
    }

    private function is_contiguous(array $coords) {
        if (count($coords) <= 1) {
            return true;
        }

        $remaining = [];
        foreach ($coords as $coord) {
            $remaining[$coord['row'] . ':' . $coord['col']] = $coord;
        }

        $queue = [array_shift($remaining)];
        while ($queue) {
            $coord = array_shift($queue);
            $neighbors = [
                ($coord['row'] - 1) . ':' . $coord['col'],
                ($coord['row'] + 1) . ':' . $coord['col'],
                $coord['row'] . ':' . ($coord['col'] - 1),
                $coord['row'] . ':' . ($coord['col'] + 1),
            ];

            foreach ($neighbors as $key) {
                if (!isset($remaining[$key])) {
                    continue;
                }
                $queue[] = $remaining[$key];
                unset($remaining[$key]);
            }
        }

        return empty($remaining);
    }
}
