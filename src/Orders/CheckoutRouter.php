<?php
/**
 * Checkout URL routing for standalone and extension-provided payment flows.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\PageUrls;

if (!defined('ABSPATH')) {
    exit;
}

final class CheckoutRouter {

    public function payload(array $order) {
        $provider = sanitize_key((string) ($order['commerce_provider'] ?? 'standalone')) ?: 'standalone';
        $providers = Payments::providers();
        if ('standalone' !== $provider && empty($providers[$provider])) {
            $provider = Payments::active_provider_id();
        }

        $settings = $this->settings();
        $standalone_url = 'standalone' === $provider ? $this->standalone_checkout_url($order, $settings) : '';
        // One rule for where the customer ends up, shared with the save path: the address handed
        // to the browser before the ad form and the address it sends them to afterwards must not
        // disagree, and neither may come back empty for a site that simply has no Thank You page.
        $default = [
            'provider' => $provider,
            'checkout_url' => $standalone_url,
            'after_upload_url' => $this->destination_after_form($order, ['checkout_url' => $standalone_url], $settings, null, false),
        ];

        $payload = Payments::checkout_for_mds_order($order, $default);
        if (!is_array($payload)) {
            $payload = $default;
        }

        $payload['provider'] = sanitize_key((string) ($payload['provider'] ?? $provider)) ?: $provider;
        $payload['checkout_url'] = esc_url_raw($payload['checkout_url'] ?? '');
        $payload['after_upload_url'] = esc_url_raw($payload['after_upload_url'] ?? ($payload['checkout_url'] ?: $default['after_upload_url']));

        return $payload;
    }

    public function standalone_checkout_url(array $order, array $settings = null) {
        $settings = $settings ?: $this->settings();
        $url = esc_url_raw($settings['checkout-url'] ?? '');
        if ('' === $url) {
            return '';
        }

        $values = $this->order_placeholder_values($order);
        $has_legacy_placeholders = false !== strpos($url, '%');

        $url = str_replace(
            ['%AMOUNT%', '%CURRENCY%', '%QUANTITY%', '%ORDERID%', '%ORDERID', '%USERID%', '%GRID%', '%PIXELID%'],
            [
                $values['amount'],
                $values['currency'],
                $values['quantity'],
                $values['order_id'],
                $values['order_id'],
                $values['user_id'],
                $values['grid_id'],
                $values['block_id'],
            ],
            $url
        );

        if ($has_legacy_placeholders) {
            return esc_url_raw($url);
        }

        $args = [
            'mds3_order_id' => $values['order_id'],
            'mds3_order_key' => $values['order_key'],
            'amount' => $values['amount'],
            'currency' => $values['currency'],
            'quantity' => $values['quantity'],
            'grid_id' => $values['grid_id'],
            'block_id' => $values['block_id'],
        ];
        // A return address only helps when the site has a thank-you page. Without one this
        // went out as a bare "&return_url" with no value, which the payment page then
        // received as an empty parameter.
        $return_url = $this->thank_you_url($order, $settings);
        if ('' !== $return_url) {
            // WordPress leaves a value that already carries a query string unencoded, so a
            // return address with its own parameters would arrive at the payment page as part
            // of the checkout address and be truncated. It goes out encoded and comes back whole.
            $args['return_url'] = rawurlencode($return_url);
        }

        return add_query_arg($args, $url);
    }

    public function thank_you_url(array $order, array $settings = null) {
        $settings = $settings ?: $this->settings();
        $base = $this->thank_you_target(esc_url_raw($settings['thank-you-page'] ?? ''));
        if ('' === $base) {
            $page_id = absint(get_option('mds3_page_thank-you_id', 0));
            $base = $page_id ? get_permalink($page_id) : '';
        }

        if (!$base) {
            return '';
        }

        return add_query_arg([
            'mds3_order_id' => absint($order['id'] ?? 0),
            'mds3_order_key' => (string) ($order['order_key'] ?? ''),
        ], $base);
    }

    /**
     * Where a customer goes once their ad is saved, or once they have confirmed it.
     *
     * The order of this is the order of the steps. A site that asks its customers to review the
     * order first stops them there, whatever the payment arrangement behind it - MDS2 chose the
     * destination after the upload form the same way. Then an outside payment page, then the
     * customer's own order page while payment is outstanding, then the Thank You page for an
     * order the site has already completed. A site is never sent to a page it does not have and
     * never gets a step it did not ask for: when a step has no destination the customer stays
     * where they are instead of being dropped on a grid.
     *
     * @param bool $awaiting_confirmation whether this order still needs its customer to confirm it
     */
    public function after_upload_url(array $order, array $checkout, array $settings = null, $awaiting_confirmation = false) {
        $settings = $settings ?: $this->settings();
        $status = sanitize_key((string) ($order['status'] ?? ''));

        if ($awaiting_confirmation) {
            $review = $this->confirm_order_url($order);
            if ('' !== $review) {
                return $review;
            }
            // A site without a Confirm Order page skips the step instead of inventing a page, and
            // the order continues as it would on a site that never asked for one.
        }

        return $this->destination_after_form($order, $checkout, $settings, $status);
    }

    /**
     * The end of the path once the ad is in: the payment step when the site has one, and after
     * that the customer's own order page while the order is still theirs to finish, or the Thank
     * You page for an order the site has already taken. Each destination falls back to one the
     * site does have. An order that is closed - refused, expired, cancelled, failed - gets no
     * destination at all: there is no next step to send anyone to, and '' means the browser keeps
     * the customer where they are rather than inventing a page.
     *
     * Both the reservation payload and the save path come through here, so a site with no Thank
     * You page cannot be given a route in one place and none in the other. An unfinished order is
     * sent to the customer's own order page rather than a Thank You page, because that page says
     * what happens next and how to pay; a Thank You page would tell them it is finished when it
     * is not.
     *
     * The one place the two moments differ is while the ad form is still in front of the customer.
     * A save answers where to move them now, and a customer who is mid-form is not moved: the grid
     * keeps them where they are. A reservation has to describe where the finished flow leads,
     * because the browser navigates on that promise - so it names the customer's own order page
     * rather than leaving the browser with an empty address and a customer standing nowhere. That
     * is the defect this method exists to keep from coming back.
     *
     * @param string|null $status the order status, read from the order when not given
     * @param bool $while_editing true while the customer still has the form open (a save), false
     *                           when describing where a finished flow leads (a reservation)
     */
    public function destination_after_form(array $order, array $checkout, array $settings = null, $status = null, $while_editing = true) {
        $settings = $settings ?: $this->settings();
        $checkout_url = esc_url_raw((string) ($checkout['checkout_url'] ?? ''));
        if ('' !== $checkout_url) {
            return $checkout_url;
        }

        $status = null === $status ? sanitize_key((string) ($order['status'] ?? '')) : sanitize_key((string) $status);

        // An order still waiting for its customer to pay is sent to their own order page, which
        // says what is outstanding and how to settle it. A Thank You page would tell them the
        // order is finished when it is not.
        if ('pending_payment' === $status) {
            return $this->customer_order_page_url($order);
        }

        if (in_array($status, ['paid', 'completed', 'renew_paid'], true)) {
            $thank_you = $this->thank_you_url($order, $settings);

            return '' !== $thank_you ? $thank_you : $this->customer_order_page_url($order);
        }

        // A reservation on a site with no payment page still ends somewhere: the customer's own
        // order page, then the Thank You page, then nowhere at all. Naming it is what lets the
        // browser finish the flow instead of stopping after the form.
        if (!$while_editing) {
            $order_page = $this->customer_order_page_url($order);

            return '' !== $order_page ? $order_page : $this->thank_you_url($order, $settings);
        }

        // Anything else while the form is open - a reservation the customer may still be
        // finishing, or an order that is closed - gets no destination: there is no next page to
        // move them to, and the browser keeps them where they are.
    }

    /**
     * The site's Confirm Order page with this order in the address, or '' when it has none.
     */
    public function confirm_order_url(array $order) {
        $page_id = absint(get_option('mds3_page_confirm-order_id', 0));
        if (!$page_id) {
            return '';
        }

        $url = get_permalink($page_id);
        if (!$url || is_wp_error($url)) {
            return '';
        }

        return add_query_arg([
            'mds3_order_id' => absint($order['id'] ?? 0),
            'mds3_order_key' => (string) ($order['order_key'] ?? ''),
        ], $url);
    }

    /**
     * The customer's order page with this order in the query, or '' when the site has none.
     *
     * Deliberately not Payments::customer_manage_url_for_mds_order(), which falls back to a grid
     * page or the home page so an email always carries a link: being dropped on a grid after
     * saving an ad is the dead end this exists to close.
     */
    public function customer_order_page_url(array $order) {
        $page_id = absint(get_option('mds3_page_upload_id', 0));
        if (!$page_id) {
            return '';
        }

        $url = get_permalink($page_id);
        if (!$url || is_wp_error($url)) {
            return '';
        }

        return add_query_arg([
            'mds3_order_id' => absint($order['id'] ?? 0),
            'mds3_order_key' => (string) ($order['order_key'] ?? ''),
        ], $url);
    }

    /**
     * The Thank You Page URL when it names something a thank-you page can be.
     *
     * A site can point this setting at any address, and a custom page outside this plugin is
     * often what its owner means. What it cannot be is one of this plugin's own pages with a
     * different job: the development site had it set to the Payment Processing page, so an
     * order that needed no payment at all sent the customer back to a page that asks for
     * payment. Empty means "use the site's own Thank You page", which is the same answer the
     * setting-less case already gives.
     */
    private function thank_you_target($configured) {
        if ('' === $configured) {
            return '';
        }

        $configured_key = PageUrls::page_key($configured);
        if ('' === $configured_key) {
            return $configured;
        }

        $thank_you_id = absint(get_option('mds3_page_thank-you_id', 0));
        $permalink = $thank_you_id ? (string) get_permalink($thank_you_id) : '';
        if ('' !== $permalink && PageUrls::page_key($permalink) === $configured_key) {
            return $configured;
        }

        return '';
    }

    private function order_placeholder_values(array $order) {
        $items = (new OrderRepository())->items($order['id'] ?? 0);
        $quantity = 0;
        $grid_id = 0;
        $block_id = 0;

        foreach ($items as $item) {
            $quantity += max(1, absint($item['quantity'] ?? 1));
            if (!$grid_id) {
                $grid_id = absint($item['grid_id'] ?? 0);
            }
            if (!$block_id && !empty($item['block_id'])) {
                $block_id = absint($item['block_id']);
            }
        }

        return [
            'amount' => number_format((float) ($order['total'] ?? 0), 2, '.', ''),
            'currency' => Currency::code($order['currency'] ?? 'USD'),
            'quantity' => $quantity,
            'order_id' => absint($order['id'] ?? 0),
            'order_key' => (string) ($order['order_key'] ?? ''),
            'user_id' => absint($order['user_id'] ?? 0),
            'grid_id' => $grid_id,
            'block_id' => $block_id,
        ];
    }

    private function settings() {
        $stored_settings = get_option('mds3_settings', []);

        return wp_parse_args(is_array($stored_settings) ? $stored_settings : [], SettingsSchema::defaults());
    }
}
