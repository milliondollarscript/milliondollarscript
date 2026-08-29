<?php
/**
 * Frontend MDS3 page panel renderers.
 *
 * @package MillionDollarScript\V3\Pages
 */

namespace MillionDollarScript\V3\Pages\Concerns;

use MillionDollarScript\V3\Extensions\ExtensionSupport;

use MillionDollarScript\V3\Grid\GridStats;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementFieldContract;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\OrderRenewal;
use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;
use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

trait RendersPagePanels {

    private function stats($grid_id, array $args = []) {
        if (!$grid_id && $this->has_multiple_active_grids()) {
            return $this->grid_picker('stats');
        }

        $grid_repo = new GridRepository();
        $grid = $grid_id ? $grid_repo->find($grid_id) : $grid_repo->first_active();
        if (!$grid) {
            return '';
        }

        $settings = get_option('mds3_settings', []);
        $settings = wp_parse_args(is_array($settings) ? $settings : [], SettingsSchema::defaults());
        $unit = $this->stats_unit($args['unit'] ?? 'settings');
        $stats = (new GridStats())->public_inventory($grid, $settings, $unit);
        $unit_label = 'pixels' === $unit ? __('pixels', 'million-dollar-script') : __('blocks', 'million-dollar-script');
        $width = $this->css_size($args['width'] ?? '240px', '240px', true);
        $styles = [
            '--mds3-page-panel-width:' . esc_attr($width),
        ];
        $color_map = [
            'number_color' => '--mds3-stats-number-color',
            'label_color' => '--mds3-stats-label-color',
            'background_color' => '--mds3-stats-background-color',
            'border_color' => '--mds3-stats-border-color',
        ];

        foreach ($color_map as $arg_key => $css_var) {
            $color = $this->hex_color($args[$arg_key] ?? '');
            if ($color) {
                $styles[] = $css_var . ':' . esc_attr($color);
            }
        }

        return Template::render('frontend/pages/stats.php', [
            'available' => absint($stats['available'] ?? 0),
            'sold' => absint($stats['sold'] ?? 0),
            'styles' => $styles,
            'theme_class' => $this->theme_class(),
            'unit_label' => $unit_label,
        ]);
    }

    private function hex_color($value) {
        $color = sanitize_hex_color((string) $value);

        return $color ?: '';
    }

    private function stats_unit($unit) {
        $unit = strtolower(sanitize_key((string) $unit));
        if (in_array($unit, ['blocks', 'block'], true)) {
            return 'blocks';
        }
        if (in_array($unit, ['pixels', 'pixel'], true)) {
            return 'pixels';
        }

        $settings = get_option('mds3_settings', []);
        $mode = is_array($settings) ? ($settings['stats-display-mode'] ?? SettingsSchema::defaults()['stats-display-mode']) : SettingsSchema::defaults()['stats-display-mode'];
        $mode = SettingsSchema::sanitize('stats-display-mode', $mode);

        return 'BLOCKS' === $mode ? 'blocks' : 'pixels';
    }

    private function css_size($value, $fallback, $allow_auto = false) {
        $value = trim((string) $value);
        if ($allow_auto && 'auto' === strtolower($value)) {
            return 'auto';
        }
        if (preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|vh|vw|%)$/', $value)) {
            return $value;
        }

        return $fallback;
    }

    private function theme_class() {
        $settings = get_option('mds3_settings', []);
        $mode = is_array($settings) ? ($settings['theme_mode'] ?? 'light') : 'light';
        $mode = SettingsSchema::sanitize('theme_mode', $mode);
        $mode = in_array($mode, ['light', 'dark', 'system'], true) ? $mode : 'light';

        return 'mds3-theme-' . $mode . ' wp-dark-mode-ignore';
    }

    private function panel($type, $grid_id, array $args = []) {
        $labels = PageRepository::labels();
        $title = $labels[$type] ?? ucfirst(str_replace('-', ' ', $type));

        if ('list' === $type) {
            return $this->advertiser_list($grid_id, $args);
        }

        if (!$grid_id && $this->page_type_needs_grid_choice($type)) {
            $picker = $this->grid_picker($type);
            if ($picker) {
                return $picker;
            }
        }

        $grid = $grid_id ? (new GridRepository())->find($grid_id) : (new GridRepository())->first_active();
        $grid_url = $grid ? $this->grid_url($grid->id()) : home_url('/');

        if (in_array($type, ['manage', 'no-orders'], true)) {
            return $this->manage_orders_panel();
        }

        if (in_array($type, ['confirm-order', 'payment', 'thank-you'], true)) {
            $order = $this->request_order();
            if ($order) {
                return $this->order_summary_panel($type, $order);
            }
        }

        $actions = [
            'order' => ['label' => __('Open grid', 'million-dollar-script'), 'url' => $grid_url],
            'upload' => ['label' => __('Open grid', 'million-dollar-script'), 'url' => $grid_url],
            'payment' => ['label' => __('Open checkout', 'million-dollar-script'), 'url' => $this->checkout_landing_url($grid_url)],
            'manage' => ['label' => __('My account', 'million-dollar-script'), 'url' => $this->account_url()],
            'write-ad' => ['label' => __('Open grid', 'million-dollar-script'), 'url' => $grid_url],
            'confirm-order' => ['label' => __('Open grid', 'million-dollar-script'), 'url' => $grid_url],
            'thank-you' => ['label' => __('Open grid', 'million-dollar-script'), 'url' => $grid_url],
            'list' => ['label' => __('Open grid', 'million-dollar-script'), 'url' => $grid_url],
            'no-orders' => ['label' => __('Open grid', 'million-dollar-script'), 'url' => $grid_url],
        ];

        $action = $actions[$type] ?? ['label' => __('Open grid', 'million-dollar-script'), 'url' => $grid_url];

        return Template::render('frontend/pages/panel.php', [
            'action' => $action,
            'copy' => $this->copy($type),
            'theme_class' => $this->theme_class(),
            'title' => $title,
        ]);
    }

    private function page_type_needs_grid_choice($type) {
        return in_array($type, ['order', 'write-ad', 'confirm-order', 'payment', 'thank-you', 'list', 'upload', 'stats'], true);
    }

    private function grid_picker($type) {
        $repo = new GridRepository();
        $total_active = $repo->active_count();
        $search = $this->grid_picker_search();
        $per_page = (int) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/grid/picker/per/page', 20, $type);
        $per_page = max(1, min(100, $per_page));
        $total = $repo->active_count($search);
        $total_pages = max(1, (int) ceil($total / $per_page));
        $page = min($this->grid_picker_page(), $total_pages);
        $grids = $repo->active_page($page, $per_page, $search);

        if (!$total_active) {
            return Template::render('frontend/pages/panel.php', [
                'action' => [],
                'copy' => __('No active grids are available yet.', 'million-dollar-script'),
                'theme_class' => $this->theme_class(),
                'title' => PageRepository::labels()[$type] ?? __('Choose a grid', 'million-dollar-script'),
            ]);
        }

        if (1 === $total_active && '' === $search) {
            return '';
        }

        $rows = [];
        foreach ($grids as $grid) {
            $rows[] = [
                'description' => wp_strip_all_tags((string) $grid->get('description', '')),
                'dimensions' => absint($grid->get('width')) . 'x' . absint($grid->get('height')),
                'slug' => sanitize_title((string) $grid->get('slug', '')),
                'title' => (string) $grid->get('title', ''),
                'url' => $this->page_type_url($type, $grid->id()),
            ];
        }

        return Template::render('frontend/pages/grid-picker.php', [
            'action_label' => $this->grid_picker_action_label($type),
            'clear_url' => $this->grid_picker_url(1, ''),
            'copy' => $this->grid_picker_copy($type),
            'form_action' => $this->grid_picker_form_action(),
            'grids' => $rows,
            'hidden_inputs' => $this->grid_picker_hidden_inputs(),
            'pagination' => $this->grid_picker_pagination($page, $total_pages, $search),
            'page' => $page,
            'per_page' => $per_page,
            'search' => $search,
            'theme_class' => $this->theme_class(),
            'title' => PageRepository::labels()[$type] ?? __('Choose a grid', 'million-dollar-script'),
            'total' => $total,
            'total_active' => $total_active,
            'total_pages' => $total_pages,
        ]);
    }

    private function grid_picker_search() {
        $search = isset($_GET['mds3_grid_search']) ? sanitize_text_field(wp_unslash($_GET['mds3_grid_search'])) : '';

        return trim(substr($search, 0, 100));
    }

    private function grid_picker_page() {
        return max(1, absint($_GET['mds3_picker_p'] ?? 1));
    }

    private function grid_picker_pagination($page, $total_pages, $search) {
        if ($total_pages <= 1) {
            return [];
        }

        $items = [];
        $page = max(1, min(absint($page), absint($total_pages)));
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);

        if ($page > 1) {
            $items[] = [
                'label' => __('Previous', 'million-dollar-script'),
                'url' => $this->grid_picker_url($page - 1, $search),
                'current' => false,
                'type' => 'previous',
            ];
        }

        if ($start > 1) {
            $items[] = [
                'label' => '1',
                'url' => $this->grid_picker_url(1, $search),
                'current' => 1 === $page,
                'type' => 'page',
            ];
            if ($start > 2) {
                $items[] = ['label' => '&hellip;', 'url' => '', 'current' => false, 'type' => 'gap'];
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $items[] = [
                'label' => (string) $i,
                'url' => $this->grid_picker_url($i, $search),
                'current' => $i === $page,
                'type' => 'page',
            ];
        }

        if ($end < $total_pages) {
            if ($end < $total_pages - 1) {
                $items[] = ['label' => '&hellip;', 'url' => '', 'current' => false, 'type' => 'gap'];
            }
            $items[] = [
                'label' => (string) $total_pages,
                'url' => $this->grid_picker_url($total_pages, $search),
                'current' => $total_pages === $page,
                'type' => 'page',
            ];
        }

        if ($page < $total_pages) {
            $items[] = [
                'label' => __('Next', 'million-dollar-script'),
                'url' => $this->grid_picker_url($page + 1, $search),
                'current' => false,
                'type' => 'next',
            ];
        }

        return $items;
    }

    private function grid_picker_url($page, $search) {
        $url = remove_query_arg(['mds3_grid_id', 'mds3_picker_p', 'mds3_grid_page', 'mds3_grid_search'], $this->current_request_url());
        $args = [];

        $search = trim((string) $search);
        if ('' !== $search) {
            $args['mds3_grid_search'] = $search;
        }
        if (absint($page) > 1) {
            $args['mds3_picker_p'] = absint($page);
        }

        return $args ? add_query_arg($args, $url) : $url;
    }

    private function grid_picker_form_action() {
        $url = $this->current_request_url();
        foreach (array_keys($_GET) as $key) {
            $url = remove_query_arg(sanitize_key((string) $key), $url);
        }

        return $url;
    }

    private function grid_picker_hidden_inputs() {
        $inputs = [];
        foreach ($_GET as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $key = sanitize_key((string) $key);
            if (!$key || in_array($key, ['mds3_grid_id', 'mds3_picker_p', 'mds3_grid_page', 'mds3_grid_search'], true)) {
                continue;
            }

            $inputs[$key] = sanitize_text_field(wp_unslash($value));
        }

        return $inputs;
    }

    private function current_request_url() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';

        return home_url($request_uri ?: '/');
    }

    private function grid_picker_action_label($type) {
        $labels = [
            'order' => __('Order pixels', 'million-dollar-script'),
            'list' => __('View advertisers', 'million-dollar-script'),
            'stats' => __('View statistics', 'million-dollar-script'),
            'payment' => __('Continue', 'million-dollar-script'),
            'upload' => __('Continue', 'million-dollar-script'),
        ];

        return $labels[$type] ?? __('Open grid', 'million-dollar-script');
    }

    private function grid_picker_copy($type) {
        $copy = [
            'order' => __('Choose the grid where you want to place your ad.', 'million-dollar-script'),
            'list' => __('Choose a grid to view its published advertisers.', 'million-dollar-script'),
            'stats' => __('Choose a grid to view its current availability.', 'million-dollar-script'),
        ];

        return $copy[$type] ?? __('Choose the grid you want to continue with.', 'million-dollar-script');
    }

    private function page_type_url($type, $grid_id) {
        $page_id = absint(get_option('mds3_page_' . sanitize_key($type) . '_id', 0));
        $url = $page_id ? get_permalink($page_id) : '';
        if (!$url) {
            $url = $this->grid_url($grid_id);
        }
        if (!$url) {
            $url = home_url('/');
        }

        return add_query_arg('mds3_grid_id', absint($grid_id), $url);
    }

    private function manage_orders_panel() {
        $order = $this->request_order();
        if ($order) {
            return $this->order_summary_panel('manage', $order);
        }

        if (!is_user_logged_in()) {
            return Template::render('frontend/pages/manage-orders.php', [
                'login_url' => wp_login_url(get_permalink()),
                'orders' => [],
                'pixels' => [],
                'requires_login' => true,
                'theme_class' => $this->theme_class(),
            ], $this);
        }

        global $wpdb;

        $user_id = get_current_user_id();
        $paged = max(1, absint($_GET['paged'] ?? 0));
        $per_page = 20;
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::ident(DB::table('orders')) . ' WHERE user_id = %d',
                $user_id
            )
        );
        $orders = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::ident(DB::table('orders')) . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d',
                $user_id,
                $per_page,
                ($paged - 1) * $per_page
            ),
            ARRAY_A
        );
        $orders = is_array($orders) ? $orders : [];

        return Template::render('frontend/pages/manage-orders.php', [
            'login_url' => '',
            'orders' => $orders,
            'paged' => $paged,
            'per_page' => $per_page,
            'total' => $total,
            'pixels' => $this->pixels_for_orders($orders),
            'requires_login' => false,
            'theme_class' => $this->theme_class(),
        ], $this);
    }

    private function order_summary_panel($type, array $order) {
        $labels = PageRepository::labels();
        $title = $labels[$type] ?? __('Order Summary', 'million-dollar-script');
        $renewals = new OrderRenewal();
        $renewal_error = '';
        $renewal_notice = '';
        $renewal_checkout_url = '';
        $order = $this->maybe_start_order_renewal($order, $renewals, $renewal_error, $renewal_notice, $renewal_checkout_url);
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $term_expires_at = !empty($metadata['expires_at']) ? mysql2date(get_option('date_format'), (string) $metadata['expires_at'], true) : '';
        $cleanup_notice = $this->order_cleanup_notice($order);
        $payment_url = $renewal_checkout_url ?: $this->order_payment_url($order);
        $status = sanitize_key((string) ($order['status'] ?? ''));
        // Expired orders land on this panel themselves, so a manage link here would loop back to this page.
        $manage_url = 'expired' === $status ? '' : $this->customer_order_url($order);

        return Template::render('frontend/pages/order-summary.php', [
            'back_url' => esc_url_raw($this->pixels_back_url()),
            'cleanup_notice' => $cleanup_notice,
            'manage_url' => $manage_url,
            'metadata' => $metadata,
            'order' => $order,
            'payment_url' => $payment_url,
            'renewal_available' => $renewals->can_renew($order) && !$renewal_checkout_url,
            'renewal_error' => $renewal_error,
            'renewal_notice' => $renewal_notice,
            'renewal_nonce' => wp_create_nonce('mds3_renew_order_' . absint($order['id'] ?? 0)),
            'term_expires_at' => $term_expires_at,
            'theme_class' => $this->theme_class(),
            'title' => $title,
        ], $this);
    }

    private function maybe_start_order_renewal(array $order, OrderRenewal $renewals, &$error, &$notice, &$checkout_url) {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : '';
        if ('POST' !== $method) {
            return $order;
        }

        $post = wp_unslash($_POST);
        if ('renew_order' !== sanitize_key((string) ($post['mds3_action'] ?? ''))) {
            return $order;
        }

        $order_id = absint($order['id'] ?? 0);
        if ($order_id !== absint($post['order_id'] ?? 0)) {
            return $order;
        }

        if (
            empty($post['order_key']) ||
            !hash_equals((string) ($order['order_key'] ?? ''), sanitize_text_field((string) $post['order_key']))
        ) {
            $error = __('Order could not be verified.', 'million-dollar-script');
            return $order;
        }

        if (empty($post['_wpnonce']) || !wp_verify_nonce(sanitize_text_field((string) $post['_wpnonce']), 'mds3_renew_order_' . $order_id)) {
            $error = __('Renewal request could not be verified.', 'million-dollar-script');
            return $order;
        }

        $result = $renewals->start($order, [
            'source' => 'frontend',
            'force_new_checkout' => true,
        ]);
        if (is_wp_error($result)) {
            $error = $result->get_error_message();
            return $order;
        }

        $order = is_array($result['order'] ?? null) ? $result['order'] : $order;
        $checkout = is_array($result['checkout'] ?? null) ? $result['checkout'] : [];
        $redirect_url = esc_url_raw((string) ($checkout['checkout_url'] ?? ($checkout['after_upload_url'] ?? '')));
        $checkout_url = $redirect_url;
        if (!$redirect_url) {
            $error = __('Renewal checkout is not configured. Contact the site owner for help renewing this placement.', 'million-dollar-script');
            return $order;
        }

        if ($redirect_url && !headers_sent()) {
            ExtensionSupport::external_redirect($redirect_url, home_url('/'));
        }

        $notice = __('Renewal checkout is ready.', 'million-dollar-script');

        return $order;
    }

    private function order_upload_panel($order_id, $order_key) {
        $order = (new OrderRepository())->find($order_id);
        if (!$order || !hash_equals((string) ($order['order_key'] ?? ''), (string) $order_key)) {
            return Template::render('frontend/pages/panel.php', [
                'action' => null,
                'copy' => __('Order could not be verified.', 'million-dollar-script'),
                'theme_class' => $this->theme_class(),
                'title' => __('Manage Upload', 'million-dollar-script'),
            ]);
        }

        if ('expired' === sanitize_key((string) ($order['status'] ?? ''))) {
            return $this->order_summary_panel('manage', $order);
        }

        $placements = (new PlacementRepository())->for_order($order_id);
        $placement = $placements ? $placements[0] : [];
        $image = !empty($placement['attachment_id']) ? wp_get_attachment_image(absint($placement['attachment_id']), 'medium') : '';
        $settings = wp_parse_args(is_array(get_option('mds3_settings', [])) ? get_option('mds3_settings', []) : [], SettingsSchema::defaults());
        $popup_text_mode = PlacementFieldContract::popup_text_mode($settings);
        $url_mode = PlacementFieldContract::url_mode($settings);

        return Template::render('frontend/pages/order-upload.php', [
            'back_url' => esc_url_raw($this->pixels_back_url()),
            'image' => $image,
            'order' => $order,
            'order_id' => $order_id,
            'order_key' => $order_key,
            'placement' => $placement,
            'popup_rich_text' => 'yes' === SettingsSchema::sanitize('popup-rich-text', $settings['popup-rich-text'] ?? 'no'),
            'popup_text_required' => PlacementFieldContract::is_required($popup_text_mode),
            'popup_text_visible' => PlacementFieldContract::is_visible($popup_text_mode),
            'theme_class' => $this->theme_class(),
            'url_required' => PlacementFieldContract::is_required($url_mode),
            'url_visible' => PlacementFieldContract::is_visible($url_mode),
        ]);
    }

    private function pixels_back_url() {
        $manage_page_id = absint(get_option('mds3_page_manage_id', 0));

        return $manage_page_id
            ? (string) get_permalink($manage_page_id)
            : remove_query_arg(['mds3_order_id', 'mds3_order_key'], $this->current_request_url());
    }

    private function pixels_for_orders(array $orders) {
        $order_ids = array_map('absint', array_column($orders, 'id'));
        $pixels = [];
        if (!$order_ids) {
            return $pixels;
        }

        $grid_titles = [];
        foreach ((new PlacementRepository())->for_orders($order_ids) as $placement) {
            $order_id = absint($placement['order_id'] ?? 0);
            if (!$order_id) {
                continue;
            }
            if (isset($pixels[$order_id])) {
                $pixels[$order_id]['count'] += 1;
                continue;
            }

            $grid_id = absint($placement['grid_id'] ?? 0);
            if (!array_key_exists($grid_id, $grid_titles)) {
                $grid = (new GridRepository())->find($grid_id);
                $grid_titles[$grid_id] = $grid ? (string) $grid->get('title', '') : '';
            }

            $pixels[$order_id] = [
                'count' => 1,
                'image' => !empty($placement['attachment_id']) ? wp_get_attachment_image(absint($placement['attachment_id']), 'thumbnail') : '',
                'label' => sprintf(
                    '%s · %d×%d @ %d,%d',
                    $grid_titles[$grid_id] ?: __('Grid', 'million-dollar-script'),
                    absint($placement['width'] ?? 0),
                    absint($placement['height'] ?? 0),
                    absint($placement['x'] ?? 0),
                    absint($placement['y'] ?? 0)
                ),
            ];
        }

        foreach ($pixels as $order_id => $pixel) {
            if ($pixel['count'] > 1) {
                /* translators: %d: number of additional pixels in the same order. */
                $pixels[$order_id]['label'] .= sprintf(' · +%d', $pixel['count'] - 1);
            }
        }

        return $pixels;
    }

    private function copy($type) {
        $copy = [
            'order' => __('Select available blocks on the grid to create an order.', 'million-dollar-script'),
            'write-ad' => __('Ad content is now attached to the reserved grid placement.', 'million-dollar-script'),
            'confirm-order' => __('Confirm the selected blocks from the grid checkout flow.', 'million-dollar-script'),
            'payment' => __('Complete payment through the connected commerce checkout.', 'million-dollar-script'),
            'manage' => __('Review orders and account details from your account area.', 'million-dollar-script'),
            'thank-you' => __('Your order has been received.', 'million-dollar-script'),
            'list' => __('Advertisers are shown directly on the grid.', 'million-dollar-script'),
            'upload' => __('Upload artwork after reserving blocks on the grid.', 'million-dollar-script'),
            'no-orders' => __('No active orders were found for this account.', 'million-dollar-script'),
        ];

        return $copy[$type] ?? __('This page was migrated from Million Dollar Script 2.', 'million-dollar-script');
    }

    private function order_cleanup_notice(array $order) {
        $status = sanitize_key((string) ($order['status'] ?? ''));
        if (!in_array($status, ['reserved', 'pending_payment'], true)) {
            return '';
        }

        $settings = get_option('mds3_settings', []);
        $settings = wp_parse_args(is_array($settings) ? $settings : [], SettingsSchema::defaults());
        if ('yes' !== SettingsSchema::sanitize('expire-orders', $settings['expire-orders'] ?? 'yes')) {
            return '';
        }

        $setting_key = 'reserved' === $status ? 'minutes-unconfirmed' : 'minutes-confirmed';
        $minutes = (int) SettingsSchema::sanitize($setting_key, $settings[$setting_key] ?? SettingsSchema::defaults()[$setting_key] ?? 0);
        if (0 === $minutes) {
            return '';
        }

        if (-1 === $minutes) {
            return 'reserved' === $status
                ? __('This reservation can be released automatically if payment is not completed.', 'million-dollar-script')
                : __('This order can be released automatically if payment is not completed.', 'million-dollar-script');
        }

        $expires_at = $this->order_cleanup_expires_at($order, $minutes, $status);
        if (!$expires_at) {
            return '';
        }

        $expires_label = get_date_from_gmt($expires_at, get_option('date_format') . ' ' . get_option('time_format'));

        return 'reserved' === $status
            ? sprintf(
                /* translators: %s: reservation expiration date and time */
                __('Your selected blocks are held until %s. Complete payment before then to keep this reservation.', 'million-dollar-script'),
                $expires_label
            )
            : sprintf(
                /* translators: %s: order cleanup date and time */
                __('This order is awaiting payment. If payment is not completed, the reserved blocks can be released after %s.', 'million-dollar-script'),
                $expires_label
            );
    }

    private function order_cleanup_expires_at(array $order, $minutes, $status) {
        if ('reserved' === $status) {
            $reserved_until = $this->order_reserved_until(absint($order['id'] ?? 0));
            if ($reserved_until) {
                return $reserved_until;
            }
        }

        $updated_at = sanitize_text_field((string) ($order['updated_at'] ?? ''));
        if (!$updated_at) {
            return '';
        }

        $timestamp = strtotime($updated_at);
        if (false === $timestamp) {
            return '';
        }

        return gmdate('Y-m-d H:i:s', $timestamp + (max(1, absint($minutes)) * MINUTE_IN_SECONDS));
    }

    private function order_reserved_until($order_id) {
        global $wpdb;

        $order_id = absint($order_id);
        if (!$order_id) {
            return '';
        }

        return (string) $wpdb->get_var($wpdb->prepare(
            'SELECT MIN(reserved_until) FROM ' . DB::ident(DB::table('blocks')) . ' WHERE order_id = %d AND status = %s AND reserved_until IS NOT NULL',
            $order_id,
            'reserved'
        ));
    }
}
