<?php
/**
 * Whether the older plugin recorded payment for one of its orders.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare().

trait ReadsLegacyOrderPayments {

    /**
     * Payment evidence by legacy order id, so a grid with a hundred orders asks once.
     *
     * @var array<int, bool>
     */
    private array $legacy_payment_evidence = [];

    /**
     * Column names by legacy table.
     *
     * @var array<string, array<int, string>>
     */
    private array $legacy_payment_table_columns = [];

    /**
     * Did the Million Dollar Script 2 site record payment for this order?
     *
     * The older plugin holds "confirmed" as the state a customer's own confirmation
     * produces, before payment: a confirmed order with no WooCommerce order should still
     * show "Pay Now" (MDS2 src/Classes/Orders/Orders.php:2994), and only a completed
     * order counts there as having payment (same method, line 3001). So a confirmed row
     * is treated as paid only on evidence the older site left behind:
     *
     *  - a row in its transactions table for that order, written by the payment add-ons;
     *  - or the WooCommerce order it links from the ad post (_wc_order_id, written by
     *    MDS2 Core/users/payment.php) sitting in a state MDS2 itself calls paid -
     *    on-hold, processing, or completed.
     *
     * Anything else stays unpaid, which is the honest reading: the money is not claimed,
     * the customer can still finish the order, and an admin can mark it paid when it was
     * settled outside the older plugin's records.
     *
     * @param int $legacy_order_id Order id in the older site's orders table.
     * @param int $legacy_ad_post_id Post id of the ad that order belongs to, when known.
     */
    private function legacy_order_paid($legacy_order_id, $legacy_ad_post_id = 0) {
        $legacy_order_id = absint($legacy_order_id);
        if (!$legacy_order_id) {
            return false;
        }
        if (isset($this->legacy_payment_evidence[$legacy_order_id])) {
            return $this->legacy_payment_evidence[$legacy_order_id];
        }

        $paid = $this->legacy_transaction_exists($legacy_order_id)
            || $this->legacy_woocommerce_order_paid($legacy_ad_post_id);

        $this->legacy_payment_evidence[$legacy_order_id] = $paid;

        return $paid;
    }

    private function legacy_transaction_exists($legacy_order_id) {
        global $wpdb;

        $table = $this->source->table('transactions');
        if (!DB::table_exists($table) || !in_array('amount', $this->legacy_table_columns($table), true)) {
            // Without the amount column there is nothing here that could record a payment.
            return false;
        }

        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT transaction_id FROM ' . DB::ident($table) . ' WHERE order_id = %d AND amount > 0 LIMIT 1',
            absint($legacy_order_id)
        ));
    }

    /**
     * Columns of a legacy table, read once per table per run.
     *
     * The older plugin's payment records are written by its commerce add-ons, and a site
     * that has an old or partial version of that table must not make this migration's
     * queries fail.
     */
    private function legacy_table_columns($table) {
        if (isset($this->legacy_payment_table_columns[$table])) {
            return $this->legacy_payment_table_columns[$table];
        }

        global $wpdb;
        $columns = $wpdb->get_col('SHOW COLUMNS FROM ' . DB::ident($table));

        $this->legacy_payment_table_columns[$table] = array_map('strval', is_array($columns) ? $columns : []);

        return $this->legacy_payment_table_columns[$table];
    }

    private function legacy_woocommerce_order_paid($legacy_ad_post_id) {
        $legacy_ad_post_id = absint($legacy_ad_post_id);
        if (!$legacy_ad_post_id || !function_exists('wc_get_order')) {
            return false;
        }

        $wc_order_id = absint(get_post_meta($legacy_ad_post_id, '_wc_order_id', true));
        if (!$wc_order_id) {
            return false;
        }

        $order = wc_get_order($wc_order_id);

        return $order && in_array((string) $order->get_status(), ['on-hold', 'processing', 'completed'], true);
    }
}
