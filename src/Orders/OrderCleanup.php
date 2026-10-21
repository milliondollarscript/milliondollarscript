<?php
/**
 * Scheduled and opportunistic order cleanup.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Media\PlacementDraftRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\Component;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class OrderCleanup implements Component {

    public const HOOK = 'mds3_order_cleanup';

    private const DEFAULT_BATCH_SIZE = 50;

    public function register() {
        add_action(self::HOOK, [$this, 'run_scheduled']);
        add_action('init', [self::class, 'schedule'], 20);
    }

    public static function schedule() {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + (5 * MINUTE_IN_SECONDS), 'hourly', self::HOOK);
        }
    }

    public static function unschedule() {
        wp_clear_scheduled_hook(self::HOOK);
    }

    public function run_scheduled() {
        $this->run_with_lock(self::DEFAULT_BATCH_SIZE, 0);
    }

    public function run_if_due($interval = 300, $limit = self::DEFAULT_BATCH_SIZE) {
        return $this->run_with_lock($limit, max(0, absint($interval)));
    }

    public function run($limit = self::DEFAULT_BATCH_SIZE) {
        $limit = max(1, min(500, absint($limit)));
        $drafts_deleted = (new PlacementDraftRepository())->cleanup_stale($limit);

        if (!$this->expiration_enabled()) {
            return $this->result(0, 0, 0, 0, 0, $drafts_deleted);
        }

        $reminders = (new OrderNotifications())->send_due_renewal_reminders($limit);
        $paid_expired = $this->transition_orders($this->expired_paid_order_ids($limit), 'expired', [
            'release_inventory' => false,
            'notify_expired_reason' => 'paid_term_expired',
        ]);
        $expired = $paid_expired + $this->transition_orders($this->stale_unpaid_order_ids($limit), 'expired', [
            'notify_expired_reason' => 'stale_unpaid',
        ]);
        $cancelled = $this->transition_orders($this->stale_status_order_ids(['expired'], 'minutes-renew', $limit), 'cancelled');
        $deleted = $this->transition_orders($this->stale_status_order_ids(['cancelled', 'failed', 'refunded', 'denied'], 'minutes-cancel', $limit), 'deleted');

        $result = $this->result($expired, $cancelled, $deleted, $paid_expired, $reminders, $drafts_deleted);
        \MillionDollarScript\Core\Hooks::do('million-dollar-script/order/cleanup/ran', $result);

        return $result;
    }

    private function run_with_lock($limit, $interval) {
        $now = time();
        $last_run = (int) get_option('mds3_order_cleanup_last_run', 0);
        if ($interval && $last_run && $now < ($last_run + $interval)) {
            return $this->result();
        }

        if (get_transient('mds3_order_cleanup_lock')) {
            return $this->result();
        }

        set_transient('mds3_order_cleanup_lock', '1', 5 * MINUTE_IN_SECONDS);
        update_option('mds3_order_cleanup_last_run', $now, false);

        try {
            return $this->run($limit);
        } finally {
            delete_transient('mds3_order_cleanup_lock');
        }
    }

    private function stale_unpaid_order_ids($limit) {
        global $wpdb;

        $clauses = [];
        $args = [];
        $now = gmdate('Y-m-d H:i:s');

        $unconfirmed = self::unpaid_hold_minutes('reserved');
        if (0 !== $unconfirmed) {
            if (-1 === $unconfirmed) {
                $clauses[] = 'o.status = %s';
                $args[] = 'reserved';
            } else {
                $clauses[] = '(o.status = %s AND (o.updated_at <= %s OR EXISTS (SELECT 1 FROM ' . DB::ident(DB::table('blocks')) . ' b WHERE b.order_id = o.id AND b.status = %s AND b.reserved_until IS NOT NULL AND b.reserved_until <= %s)))';
                $args[] = 'reserved';
                $args[] = gmdate('Y-m-d H:i:s', time() - ($unconfirmed * MINUTE_IN_SECONDS));
                $args[] = 'reserved';
                $args[] = $now;
            }
        }

        $confirmed = self::unpaid_hold_minutes('pending_payment');
        if (0 !== $confirmed) {
            if (-1 === $confirmed) {
                $clauses[] = 'o.status = %s';
                $args[] = 'pending_payment';
            } else {
                $clauses[] = '(o.status = %s AND o.updated_at <= %s)';
                $args[] = 'pending_payment';
                $args[] = gmdate('Y-m-d H:i:s', time() - ($confirmed * MINUTE_IN_SECONDS));
            }
        }

        if (!$clauses) {
            return [];
        }

        $args[] = $limit;
        $sql = 'SELECT DISTINCT o.id FROM ' . DB::ident(DB::table('orders')) . ' o WHERE (' . implode(' OR ', $clauses) . ') ORDER BY o.id ASC LIMIT %d';

        return array_map('absint', (array) $wpdb->get_col($wpdb->prepare($sql, $args)));
    }

    private function stale_status_order_ids(array $statuses, $setting_key, $limit) {
        global $wpdb;

        $minutes = self::hold_minutes($setting_key);
        if (0 === $minutes) {
            return [];
        }

        $statuses = array_values(array_filter(array_map('sanitize_key', $statuses)));
        if (!$statuses) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $args = $statuses;
        $sql = 'SELECT id FROM ' . DB::ident(DB::table('orders')) . " WHERE status IN ({$placeholders})";
        if (-1 !== $minutes) {
            $sql .= ' AND updated_at <= %s';
            $args[] = gmdate('Y-m-d H:i:s', time() - ($minutes * MINUTE_IN_SECONDS));
        }

        $sql .= -1 === $minutes ? ' ORDER BY updated_at DESC, id DESC LIMIT %d' : ' ORDER BY id ASC LIMIT %d';
        $args[] = max(1, absint($limit));

        return array_map('absint', (array) $wpdb->get_col($wpdb->prepare($sql, $args)));
    }

    private function expired_paid_order_ids($limit) {
        global $wpdb;

        $expires_at = OrderLifecycleFields::expiration_sql('metadata', 'expires_at');
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT id FROM ' . DB::ident(DB::table('orders')) . ' WHERE status = %s AND ' . $expires_at . ' IS NOT NULL AND ' . $expires_at . ' <= %s ORDER BY ' . $expires_at . ' ASC, id ASC LIMIT %d',
                'paid',
                gmdate('Y-m-d H:i:s'),
                max(1, absint($limit))
            )
        );

        return array_map('absint', is_array($ids) ? $ids : []);
    }

    private function transition_orders(array $order_ids, $status, array $context = []) {
        $changed = 0;
        foreach (array_unique(array_filter(array_map('absint', $order_ids))) as $order_id) {
            if (Payments::mark_source_status('mds-grid', $order_id, $status, array_merge(['source' => 'cleanup'], $context))) {
                if ('expired' === $status && !empty($context['notify_expired_reason'])) {
                    (new OrderNotifications())->send_expired_notice($order_id, $context['notify_expired_reason']);
                }
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * How long an order in this status keeps the space before the sweep releases it.
     *
     * The answer belongs to the settings, and every surface that talks about holding or
     * returning a space has to read it from here - the sweep, an order row, and a block
     * row cannot each keep their own idea of when the space comes back.
     *
     * @return int minutes of hold; 0 when the site never releases the space itself.
     */
    public static function unpaid_hold_minutes($status) {
        $status = sanitize_key((string) $status);
        if (!in_array($status, array('reserved', 'pending_payment'), true)) {
            return 0;
        }

        return self::hold_minutes('pending_payment' === $status ? 'minutes-confirmed' : 'minutes-unconfirmed');
    }

    /**
     * Read one of this component's own window settings (minutes-unconfirmed, minutes-confirmed,
     * minutes-renew, minutes-cancel) as it is actually configured, including a site that has
     * switched automatic expiry off.
     *
     * @return int minutes; 0 when nothing releases on this setting.
     */
    public static function hold_minutes($setting_key) {
        $key = sanitize_key((string) $setting_key);
        $defaults = SettingsSchema::defaults();
        if (!array_key_exists($key, $defaults)) {
            return 0;
        }

        $stored = get_option('mds3_settings', []);
        $settings = wp_parse_args(is_array($stored) ? $stored : [], $defaults);

        if ('yes' !== SettingsSchema::sanitize('expire-orders', $settings['expire-orders'] ?? 'yes')) {
            return 0;
        }

        return (int) SettingsSchema::sanitize($key, $settings[$key] ?? $defaults[$key] ?? 0);
    }

    /**
     * The moment an unpaid order gives up the space it holds, counted from $from_utc
     * (when it entered the status it is waiting in). '' when nothing releases it.
     */
    public static function unpaid_hold_deadline($status, $from_utc) {
        $minutes = self::unpaid_hold_minutes($status);
        if (0 === $minutes) {
            return '';
        }

        $from = $from_utc ? (int) strtotime((string) $from_utc) : 0;

        return gmdate('Y-m-d H:i:s', ($from > 0 ? $from : time()) + ($minutes * MINUTE_IN_SECONDS));
    }

    private function expiration_enabled() {
        $settings = $this->settings();

        return 'yes' === SettingsSchema::sanitize('expire-orders', $settings['expire-orders'] ?? 'yes');
    }

    private function settings() {
        $stored = get_option('mds3_settings', []);

        return wp_parse_args(is_array($stored) ? $stored : [], SettingsSchema::defaults());
    }

    private function result($expired = 0, $cancelled = 0, $deleted = 0, $paid_expired = 0, $reminders = 0, $drafts_deleted = 0) {
        return [
            'expired' => absint($expired),
            'paid_expired' => absint($paid_expired),
            'cancelled' => absint($cancelled),
            'deleted' => absint($deleted),
            'reminders' => absint($reminders),
            'drafts_deleted' => absint($drafts_deleted),
        ];
    }
}
