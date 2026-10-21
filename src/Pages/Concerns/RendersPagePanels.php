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
use MillionDollarScript\V3\Media\UploadValidator;
use MillionDollarScript\V3\Orders\CheckoutRouter;
use MillionDollarScript\V3\Orders\OrderConfirmation;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Orders\OrderRenewal;
use MillionDollarScript\V3\Pages\PageRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\DB;
use MillionDollarScript\V3\Support\PageUrls;
use MillionDollarScript\V3\Support\Template;
use MillionDollarScript\V3\Support\ThemeMode;

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
        return 'mds3-theme-' . ThemeMode::mode() . ' wp-dark-mode-ignore';
    }

    private function panel($type, $grid_id, array $args = []) {
        $labels = PageRepository::labels();
        $title = $labels[$type] ?? ucfirst(str_replace('-', ' ', $type));

        if ('list' === $type) {
            return $this->advertiser_list($grid_id, $args);
        }

        // A visitor who arrives with their own order in the address is not asking "which grid
        // do you want" - they have just placed an ad, and the page they were sent to is about
        // that order. On a site whose Thank You or Payment page is not tied to one grid, the
        // chooser below used to win, so the receipt never appeared. Read the order first; the
        // chooser still answers the page for anyone who has no order to look at.
        $order = in_array($type, ['confirm-order', 'payment', 'thank-you'], true) ? $this->request_order() : null;

        if (null === $order && !$grid_id && $this->page_type_needs_grid_choice($type)) {
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
        $path = wp_parse_url($request_uri, PHP_URL_PATH);
        if (null === $path) {
            $path = '/';
        }
        // A site installed in a subdirectory already carries that path in the
        // request, and home_url() adds it again: keep only the part below the
        // site so a paginated list does not point at /subdirectory/subdirectory/.
        $site = wp_parse_url(home_url('/'), PHP_URL_PATH);
        $site = is_string($site) ? untrailingslashit($site) : '';
        if ('' !== $site && 0 === strpos($path, $site)) {
            $path = substr($path, strlen($site));
        }
        $query = wp_parse_url($request_uri, PHP_URL_QUERY);

        return home_url('/' . ltrim((string) $path, '/') . ($query ? '?' . $query : ''));
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
        $confirm_error = '';
        $confirm_notice = '';
        $order = $this->maybe_confirm_order($order, $confirm_error, $confirm_notice);
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $term_expires_at = !empty($metadata['expires_at']) ? mysql2date(get_option('date_format'), (string) $metadata['expires_at'], true) : '';
        $placements = (new PlacementRepository())->for_order(absint($order['id'] ?? 0));
        // A site that asks its customers to review an order keeps the payment route to itself
        // until they do: a "Continue payment" button on the review screen would let a customer
        // skip the step the site asked for.
        $awaiting_confirmation = (new OrderConfirmation())->await_confirmation($order, $this->site_settings(), $placements);
        $cleanup_notice = $this->order_cleanup_notice($order, $awaiting_confirmation);
        $payment_url = $awaiting_confirmation ? '' : ($renewal_checkout_url ?: $this->order_payment_url($order));
        // A Checkout URL pointed at this screen produced a "Continue payment" link that
        // reloaded the page it was on, which a customer reads as a dead button. Saving that
        // value is refused; this covers one stored earlier or written by another tool.
        if ('' !== $payment_url && PageUrls::is_same_page($payment_url, PageUrls::requested_page())) {
            $payment_url = '';
        }
        $status = sanitize_key((string) ($order['status'] ?? ''));
        // Expired orders land on this panel themselves, so a manage link here would loop back to this page.
        $manage_url = 'expired' === $status ? '' : $this->customer_order_url($order);

        // The same words the order page uses. A customer who reaches this panel through a
        // thank-you link, the "my pixels" list, or an ended listing gets one explanation of
        // where they stand rather than a table of labels.
        $panel_settings = wp_parse_args(is_array(get_option('mds3_settings', [])) ? (array) get_option('mds3_settings', []) : [], SettingsSchema::defaults());
        $panel_settings = PlacementFieldContract::settings_for_grid($panel_settings, $this->order_grid_id($order));
        $preview_attachment = 0;
        foreach ($placements as $placement_row) {
            if (!empty($placement_row['attachment_id'])) {
                $preview_attachment = absint($placement_row['attachment_id']);
                break;
            }
        }

        return Template::render('frontend/pages/order-summary.php', [
            'back_url' => esc_url_raw($this->pixels_back_url()),
            'cleanup_notice' => $cleanup_notice,
            'confirm_available' => $awaiting_confirmation,
            'confirm_attachment' => $awaiting_confirmation ? $preview_attachment : 0,
            'confirm_error' => $confirm_error,
            'confirm_notice' => $confirm_notice,
            'confirm_nonce' => $awaiting_confirmation ? wp_create_nonce('mds3_confirm_order_' . absint($order['id'] ?? 0)) : '',
            'manage_url' => $manage_url,
            'metadata' => $metadata,
            'order' => $order,
            'order_state' => $this->customer_order_state($order, $placements, $panel_settings, $payment_url, $awaiting_confirmation),
            // A reservation the customer has filled in is not the same thing to them as the
            // internal word for it: at this point the order is waiting on them, not on the site.
            'status_label' => $awaiting_confirmation ? __('Awaiting confirmation', 'million-dollar-script') : '',
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

    /**
     * The customer's Confirm action on a review step.
     *
     * MDS2's confirm page had one button, and what it did depended on whether the order owed
     * money: a free order was completed there, anything else moved on to payment
     * (milliondollarscript-two/src/Core/users/confirm_order.php:368). The same rule is here,
     * and the order then goes wherever saving the ad would have taken it - an outside payment
     * page, the customer's own order page, or the thank-you page - so confirming cannot send a
     * customer somewhere other than the path the site is set up for.
     */
    private function maybe_confirm_order(array $order, &$error, &$notice) {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : '';
        if ('POST' !== $method) {
            return $order;
        }

        $post = wp_unslash($_POST);
        if ('confirm_order' !== sanitize_key((string) ($post['mds3_action'] ?? ''))) {
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

        if (empty($post['_wpnonce']) || !wp_verify_nonce(sanitize_text_field((string) $post['_wpnonce']), 'mds3_confirm_order_' . $order_id)) {
            $error = __('That confirmation could not be verified. Refresh the page and try again.', 'million-dollar-script');
            return $order;
        }

        $settings = $this->site_settings();
        $confirmation = new OrderConfirmation();
        if (!$confirmation->await_confirmation($order, $settings)) {
            // Pressing Confirm twice, or on an order that has already moved on: there is nothing
            // to do, and nothing a customer needs to be told is wrong.
            return $order;
        }

        $updated = $confirmation->advance(new OrderRepository(), new PlacementRepository(), $order, $settings);
        if (is_wp_error($updated)) {
            $error = $updated->get_error_message();
            return $order;
        }

        $order = $updated;
        $checkout = (new CheckoutRouter())->payload($order);
        $redirect = esc_url_raw((string) (new CheckoutRouter())->after_upload_url($order, $checkout, $settings, false));
        if ('' !== $redirect && !headers_sent()) {
            wp_safe_redirect($redirect);
            exit;
        }

        $notice = __('Your order is confirmed.', 'million-dollar-script');

        return $order;
    }

    /**
     * Site settings with the defaults applied, for a decision that belongs to the site rather
     * than to one grid. Per-grid choices are read where they are used.
     */
    private function site_settings() {
        $stored = get_option('mds3_settings', []);

        return wp_parse_args(is_array($stored) ? $stored : [], SettingsSchema::defaults());
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
        $settings = wp_parse_args(is_array(get_option('mds3_settings', [])) ? (array) get_option('mds3_settings', []) : [], SettingsSchema::defaults());
        $settings = PlacementFieldContract::settings_for_grid($settings, $placement['grid_id'] ?? 0);
        $popup_text_mode = PlacementFieldContract::popup_text_mode($settings);
        $url_mode = PlacementFieldContract::url_mode($settings);

        return Template::render('frontend/pages/order-upload.php', [
            'back_url' => esc_url_raw($this->pixels_back_url()),
            'image' => $image,
            'order' => $order,
            'order_id' => $order_id,
            'order_key' => $order_key,
            'order_state' => $this->customer_order_state($order, $placements, $settings, $this->order_payment_url($order), (new OrderConfirmation())->await_confirmation($order, $this->site_settings(), $placements)),
            'placement' => $placement,
            'popup_rich_text' => 'yes' === SettingsSchema::sanitize('popup-rich-text', $settings['popup-rich-text'] ?? 'no'),
            'popup_text_max_chars' => PlacementFieldContract::text_max_chars($settings),
            'popup_text_required' => PlacementFieldContract::is_required($popup_text_mode),
            'popup_text_visible' => PlacementFieldContract::is_visible($popup_text_mode),
            'theme_class' => $this->theme_class(),
            'url_required' => PlacementFieldContract::is_required($url_mode),
            'url_visible' => PlacementFieldContract::is_visible($url_mode),
            'upload_size_hint' => UploadValidator::size_hint_sentence($settings),
        ]);
    }

    /**
     * What the customer's order page says about where their order stands.
     *
     * This is the screen a customer arrives on straight after saving an ad, and the one their
     * order link opens later, so it has to answer three questions in plain words: is my ad on
     * the grid, does anything still need doing, and who is going to do it. Until now the page
     * only had the ad form in it, so on the manual-payment path - the default on a fresh
     * install - a customer could reach it and find nothing about their order at all
     * (llm-task b95cc2f5).
     *
     * Every sentence here is built out of facts this screen can see: the order's status, the
     * state of its creatives, whether the site has a payment route, and whether this grid
     * reviews ads before they appear. That is deliberate, because the mistakes this copy must
     * not make are thanking someone for a payment that never arrived, promising a review the
     * site never asked for, or offering a button that goes nowhere.
     *
     * @return array{headline:string,detail:string,tone:string,payment_url:string,shows_until:string}
     */
    /**
     * The site owner's own instructions for paying outside the plugin, or '' when they have not
     * written any. Kept as written, line breaks included, because a bank account and a payment
     * reference are not a sentence.
     */
    private function payment_instructions(array $settings) {
        $raw = trim((string) ($settings['payment-instructions'] ?? ''));

        return '' === $raw ? '' : wp_kses_post($raw);
    }

    private function customer_order_state(array $order, array $placements, array $settings, $payment_url, $awaiting_confirmation = false) {
        $status = sanitize_key((string) ($order['status'] ?? ''));
        $creative_states = [];
        foreach ($placements as $placement) {
            $creative_states[] = sanitize_key((string) ($placement['status'] ?? ''));
        }

        $on_grid = in_array('active', $creative_states, true);
        $awaiting_review = in_array('pending', $creative_states, true);
        $reviews_ads = !empty($settings) && array_key_exists('auto-publish', $settings) && !$settings['auto-publish'];
        $payment_url = esc_url_raw((string) $payment_url);

        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);
        // Only a term that is still running gets a date sentence: on imported records the
        // stored end date can already be past while the artwork is still up, and "It shows
        // until November" is not something to tell a customer. Absent or past, say nothing.
        $expires_at = (is_array($metadata) && !empty($metadata['expires_at'])) ? strtotime((string) $metadata['expires_at']) : false;
        $shows_until = ($expires_at && $expires_at > time()) ? (string) wp_date(get_option('date_format'), $expires_at) : '';

        // The same two facts - is anything showing, and is anything waiting - give the same
        // answer whatever route got the order there.
        $on_the_grid = static function ($awaiting_review, $shows_until) {
            $state = [
                'headline' => __('Your ad is on the grid.', 'million-dollar-script'),
                'tone' => 'live',
            ];
            $state['detail'] = $awaiting_review
                ? __('Some of your pixels are still waiting to be checked by the site.', 'million-dollar-script')
                : __('What you see here is what visitors see. Anything you change on this page replaces it.', 'million-dollar-script');
            $state['shows_until'] = $shows_until;

            return $state;
        };
        $not_on_the_grid = static function ($headline, $detail = '') {
            return ['headline' => $headline, 'detail' => $detail, 'tone' => 'closed'];
        };

        switch ($status) {
            case 'pending_payment':
                $state = [
                    'headline' => __('Your order is waiting for payment.', 'million-dollar-script'),
                    'tone' => 'awaiting',
                ];
                if ('' !== $payment_url) {
                    $state['detail'] = __('Choose Continue payment below to finish it. Your ad is saved on this page and does not go on the grid until the payment comes through.', 'million-dollar-script');
                } elseif ($reviews_ads) {
                    $state['detail'] = __('Nothing more is needed from you right now. The site confirms the payment and then checks your ad before it appears on the grid.', 'million-dollar-script');
                } else {
                    $state['detail'] = __('Nothing more is needed from you right now. The site confirms the payment, and your ad goes on the grid as soon as it has.', 'million-dollar-script');
                }

                // A site that takes payment outside the plugin still has to tell the customer how.
                // This is the site owner's own wording, shown only when there is no payment page to
                // send them to; with a payment page the button above is the whole answer.
                $instructions = '' !== $payment_url ? '' : $this->payment_instructions($settings);

                return array_merge($state, [
                    'payment_url' => $payment_url,
                    'payment_instructions' => $instructions,
                    'shows_until' => '',
                ]);
            case 'paid':
            case 'completed':
            case 'renew_paid':
                if ($on_grid) {
                    return array_merge($on_the_grid($awaiting_review, $shows_until), ['payment_url' => '', 'shows_until' => $shows_until]);
                }
                if ($awaiting_review) {
                    return [
                        'headline' => __('Payment received. Your ad is waiting to be checked.', 'million-dollar-script'),
                        'detail' => $reviews_ads
                            ? __('This grid looks at each ad before it goes up. You can still change the image or text here while you wait.', 'million-dollar-script')
                            : __('Your ad is not on the grid yet. You can still change the image or text here.', 'million-dollar-script'),
                        'tone' => 'awaiting',
                        'payment_url' => '',
                        'shows_until' => '',
                    ];
                }

                return array_merge(
                    $not_on_the_grid(
                        __('This listing has ended.', 'million-dollar-script'),
                        __('Your ad is no longer on the grid. The order and your artwork are kept for your records.', 'million-dollar-script')
                    ),
                    ['payment_url' => '', 'shows_until' => '']
                );
            case 'reserved':
            case 'draft':
                if ($awaiting_confirmation) {
                    // What the customer is asked to do, and what the words here may promise, are
                    // the same thing: the auto-approval rule the Confirm action itself uses
                    // decides whether payment follows or the ad goes up. Where the order then
                    // stands is explained by the screen the customer lands on next, which already
                    // says that in the same words the order page uses.
                    $state = [
                        'headline' => __('Check your order, then confirm it.', 'million-dollar-script'),
                        'detail' => __('Your pixels are held while you look. Nothing moves forward until you confirm.', 'million-dollar-script'),
                        'tone' => 'awaiting',
                        'payment_url' => '',
                        'shows_until' => '',
                    ];
                    $confirmation = new OrderConfirmation();
                    $publishes_here = $confirmation->handled_by_site($order, $settings)
                        && $confirmation->auto_approve_for_grids(new OrderRepository(), absint($order['id'] ?? 0), $settings);
                    $state['confirm_label'] = ((float) ($order['total'] ?? 0) > 0 && !$publishes_here)
                        ? __('Confirm and continue to payment', 'million-dollar-script')
                        : __('Confirm order', 'million-dollar-script');
                    // Panels that are not the review page itself - the order page, a thank-you
                    // link, the pixels list - need the way there, or the customer reads one screen
                    // that says the ad is unfinished and another that says it only needs a click.
                    $state['confirm_url'] = (new CheckoutRouter())->confirm_order_url($order);

                    return $state;
                }

                return [
                    'headline' => __('These pixels are held for you.', 'million-dollar-script'),
                    'detail' => __('Add your image and text below to finish your ad.', 'million-dollar-script'),
                    'tone' => 'awaiting',
                    'payment_url' => $payment_url,
                    'shows_until' => '',
                ];
            case 'expired':
                return array_merge(
                    $not_on_the_grid(
                        __('This listing has ended.', 'million-dollar-script'),
                        __('Your ad is no longer on the grid.', 'million-dollar-script')
                    ),
                    ['payment_url' => '', 'shows_until' => '']
                );
            case 'cancelled':
            case 'canceled':
                return array_merge(
                    $not_on_the_grid(
                        __('This order was cancelled.', 'million-dollar-script'),
                        __('Your ad is not on the grid.', 'million-dollar-script')
                    ),
                    ['payment_url' => '', 'shows_until' => '']
                );
            case 'denied':
                return array_merge(
                    $not_on_the_grid(
                        __('The site did not approve this ad.', 'million-dollar-script'),
                        __('It is not on the grid.', 'million-dollar-script')
                    ),
                    ['payment_url' => '', 'shows_until' => '']
                );
            case 'failed':
                return array_merge(
                    $not_on_the_grid(
                        __('The payment did not go through.', 'million-dollar-script'),
                        __('Your ad is not on the grid.', 'million-dollar-script')
                    ),
                    ['payment_url' => $payment_url, 'shows_until' => '']
                );
            case 'refunded':
                return array_merge(
                    $not_on_the_grid(
                        __('This order was refunded.', 'million-dollar-script'),
                        __('Your ad is not on the grid.', 'million-dollar-script')
                    ),
                    ['payment_url' => '', 'shows_until' => '']
                );
            case 'deleted':
                return array_merge(
                    $not_on_the_grid(
                        __('This order has been removed.', 'million-dollar-script'),
                        __('Your ad is not on the grid.', 'million-dollar-script')
                    ),
                    ['payment_url' => '', 'shows_until' => '']
                );
            default:
                if ($on_grid) {
                    return array_merge($on_the_grid($awaiting_review, $shows_until), ['payment_url' => '', 'shows_until' => $shows_until]);
                }

                return array_merge(
                    $not_on_the_grid(__('Your ad is not on the grid.', 'million-dollar-script')),
                    ['payment_url' => '', 'shows_until' => '']
                );
        }
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

    private function order_cleanup_notice(array $order, $awaiting_confirmation = false) {
        $status = sanitize_key((string) ($order['status'] ?? ''));
        if (!in_array($status, ['reserved', 'pending_payment'], true)) {
            return '';
        }

        $settings = get_option('mds3_settings', []);
        $settings = wp_parse_args(is_array($settings) ? $settings : [], SettingsSchema::defaults());
        if ('yes' !== SettingsSchema::sanitize('expire-orders', $settings['expire-orders'] ?? 'yes')) {
            return '';
        }

        $minutes = \MillionDollarScript\V3\Orders\OrderCleanup::unpaid_hold_minutes($status);
        if (0 === $minutes) {
            return '';
        }

        if (-1 === $minutes) {
            if ('reserved' === $status) {
                return $awaiting_confirmation
                    ? __('This reservation can be released automatically if the order is not confirmed.', 'million-dollar-script')
                    : __('This reservation can be released automatically if payment is not completed.', 'million-dollar-script');
            }

            return __('This order can be released automatically if payment is not completed.', 'million-dollar-script');
        }

        $expires_at = $this->order_cleanup_expires_at($order, $minutes, $status);
        if (!$expires_at) {
            return '';
        }

        $expires_label = get_date_from_gmt($expires_at, get_option('date_format') . ' ' . get_option('time_format'));

        return 'reserved' === $status
            ? sprintf(
                /* translators: %s: reservation expiration date and time */
                $awaiting_confirmation
                    ? __('Your selected blocks are held until %s. Confirm the order before then to keep them.', 'million-dollar-script')
                    : __('Your selected blocks are held until %s. Complete payment before then to keep this reservation.', 'million-dollar-script'),
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
