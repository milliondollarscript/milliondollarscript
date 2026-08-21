<?php
/**
 * Order lifecycle email notifications.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Commerce\Payments;
use MillionDollarScript\V3\Commerce\Currency;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\Component;
use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifiers are quoted by DB::ident(); values use $wpdb->prepare() or validated query builders.

final class OrderNotifications implements Component {

    private const DEFAULT_BATCH_SIZE = 50;

    /**
     * @var OrderRepository
     */
    private $orders;

    public function __construct(?OrderRepository $orders = null) {
        $this->orders = $orders ?: new OrderRepository();
    }

    public function register() {
        add_action('million-dollar-script/order/status/changed', [$this, 'send_status_change_notice'], 20, 6);
        add_action('million-dollar-script/placement/saved', [$this, 'send_placement_saved_notice'], 20, 3);
    }

    public function send_status_change_notice($order_id, $status, $previous_status, array $order, array $previous_order, array $changes = []) {
        $status = sanitize_key((string) $status);
        $type = $this->notification_type_for_status($status, $previous_order);
        if (!$type) {
            return false;
        }

        $context = [
            'previous_status' => sanitize_key((string) $previous_status),
            'status' => $status,
            'changes' => $changes,
        ];

        if ('order_completed_renewal' === $type) {
            $previous_metadata = $this->metadata($previous_order);
            $context['renewal_started_at'] = sanitize_text_field((string) ($previous_metadata['renewal_started_at'] ?? ''));
        }

        return $this->send_once($type, $order, $context);
    }

    public function send_placement_saved_notice(array $placement, array $order, $post = null) {
        if ('active' !== sanitize_key((string) ($placement['status'] ?? ''))) {
            return false;
        }

        if (!$this->enabled($this->settings(), 'email-admin-publish-notify')) {
            return false;
        }

        return $this->send('order_published', $order, [
            'placement' => $placement,
            'post' => $post,
        ]);
    }

    public function send_due_renewal_reminders($limit = self::DEFAULT_BATCH_SIZE) {
        $settings = $this->settings();
        if (!$this->enabled($settings, 'email-user-renewal-reminder') && !$this->enabled($settings, 'email-admin-renewal-reminder')) {
            return 0;
        }

        $days = $this->reminder_days($settings);
        if (!$days) {
            return 0;
        }

        $sent = 0;
        $now = time();
        foreach ($this->paid_orders_with_expiration($limit, max($days)) as $order) {
            $metadata = $this->metadata($order);
            $expires_at = strtotime((string) ($metadata['expires_at'] ?? ''));
            if (!$expires_at || $expires_at <= $now || !empty($metadata['legacy_expiry_notice_sent'])) {
                continue;
            }

            $reminder_day = $this->due_reminder_day($days, $metadata, $expires_at, $now);
            if (!$reminder_day) {
                continue;
            }

            $days_left = max(1, (int) ceil(($expires_at - $now) / DAY_IN_SECONDS));
            if (!$this->send('renewal_reminder', $order, [
                'days_left' => $days_left,
                'expires_at' => gmdate('Y-m-d H:i:s', $expires_at),
                'reminder_day' => $reminder_day,
            ])) {
                continue;
            }

            $metadata['renewal_reminders_sent'] = is_array($metadata['renewal_reminders_sent'] ?? null) ? $metadata['renewal_reminders_sent'] : [];
            $metadata['renewal_reminders_sent'][(string) $reminder_day] = gmdate('Y-m-d H:i:s');
            $this->orders->update(absint($order['id'] ?? 0), ['metadata' => $metadata]);
            $sent++;
        }

        return $sent;
    }

    public function send_expired_notice($order_id, $reason = 'expired') {
        $order = $this->orders->find(absint($order_id));
        if (!$order) {
            return false;
        }

        $reason = sanitize_key((string) $reason) ?: 'expired';
        return $this->send_once('order_expired', $order, ['reason' => $reason]);
    }

    private function send($type, array $order, array $context = []) {
        $type = sanitize_key((string) $type);
        $settings = $this->settings();
        $definition = $this->definition($type);
        if (!$definition) {
            return false;
        }

        $recipients = $this->recipients($type, $order, $settings);
        $recipients = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/order/notification/recipients', $recipients, $type, $order, $context);
        $recipients = is_array($recipients) ? $recipients : [];

        $subject_key = (string) ($definition['subject'] ?? '');
        $message_key = (string) ($definition['message'] ?? '');
        $subject = $this->replace((string) ($settings[$subject_key] ?? SettingsSchema::defaults()[$subject_key] ?? ''), $order, $context);
        $message = $this->replace((string) ($settings[$message_key] ?? SettingsSchema::defaults()[$message_key] ?? ''), $order, $context);

        $subject = wp_strip_all_tags((string) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/order/notification/subject', $subject, $type, $order, $context));
        $message = (string) \MillionDollarScript\Core\Hooks::apply('million-dollar-script/order/notification/message', $message, $type, $order, $context);
        $headers = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/order/notification/headers', ['Content-Type: text/html; charset=UTF-8'], $type, $order, $context);
        $headers = is_array($headers) ? $headers : ['Content-Type: text/html; charset=UTF-8'];

        $sent = 0;
        foreach ($recipients as $recipient) {
            $email = sanitize_email(is_array($recipient) ? (string) ($recipient['email'] ?? '') : (string) $recipient);
            if (!$email) {
                continue;
            }

            $ok = (bool) wp_mail($email, $subject, wp_kses_post($message), $headers);
            \MillionDollarScript\Core\Hooks::do('million-dollar-script/order/notification/sent', $type, $order, $recipient, $context, $ok);
            if ($ok) {
                $sent++;
            }
        }

        return $sent > 0;
    }

    private function send_once($type, array $order, array $context = []) {
        $type = sanitize_key((string) $type);
        $order_id = absint($order['id'] ?? 0);
        if (!$order_id) {
            return false;
        }

        $metadata = $this->metadata($order);
        $key = $type . '_notice_sent_at';
        $token_key = $type . '_notice_token';
        $token = $this->notice_token($type, $order, $context);
        if (!empty($metadata[$key]) && (!$token || (string) ($metadata[$token_key] ?? '') === $token)) {
            return false;
        }

        if (!$this->send($type, $order, $context)) {
            return false;
        }

        $metadata[$key] = gmdate('Y-m-d H:i:s');
        if ($token) {
            $metadata[$token_key] = $token;
        }

        if ('order_expired' === $type) {
            $metadata['expired_notice_sent_at'] = $metadata[$key];
            $metadata['expired_notice_reason'] = sanitize_key((string) ($context['reason'] ?? 'expired')) ?: 'expired';
        }

        $this->orders->update($order_id, ['metadata' => $metadata]);

        return true;
    }

    private function notice_token($type, array $order, array $context) {
        if ('order_completed_renewal' === $type) {
            return sanitize_text_field((string) ($context['renewal_started_at'] ?? '')) ?: '';
        }

        return '';
    }

    private function recipients($type, array $order, array $settings) {
        $recipients = [];
        $definition = $this->definition($type);
        if (!$definition) {
            return $recipients;
        }

        $customer_key = (string) ($definition['customer'] ?? '');
        $admin_key = (string) ($definition['admin'] ?? '');

        if ($customer_key && $this->enabled($settings, $customer_key)) {
            $customer_email = $this->customer_email($order);
            if ($customer_email) {
                $recipients[] = ['role' => 'customer', 'email' => $customer_email];
            }
        }

        if ($admin_key && $this->enabled($settings, $admin_key)) {
            $admin_email = sanitize_email(get_bloginfo('admin_email'));
            if ($admin_email) {
                $recipients[] = ['role' => 'admin', 'email' => $admin_email];
            }
        }

        return $recipients;
    }

    private function notification_type_for_status($status, array $previous_order) {
        if ('pending_payment' === $status) {
            return 'order_confirmed';
        }

        if ('paid' === $status) {
            $metadata = $this->metadata($previous_order);
            if (!empty($metadata['renewal_started_at'])) {
                return 'order_completed_renewal';
            }

            return 'order_completed';
        }

        if ('expired' === $status) {
            return 'order_expired';
        }

        if ('denied' === $status) {
            return 'order_denied';
        }

        return \MillionDollarScript\Core\Hooks::apply('million-dollar-script/order/notification/type/for/status', '', $status, $previous_order);
    }

    private function definition($type) {
        $definitions = [
            'order_confirmed' => [
                'customer' => 'email-user-order-confirmed',
                'admin' => 'email-admin-order-confirmed',
                'subject' => 'order-confirmed-subject',
                'message' => 'order-confirmed-content',
            ],
            'order_completed' => [
                'customer' => 'email-user-order-completed',
                'admin' => 'email-admin-order-completed',
                'subject' => 'order-completed-subject',
                'message' => 'order-completed-content',
            ],
            'order_completed_renewal' => [
                'customer' => 'email-user-order-completed-renewal',
                'admin' => 'email-admin-order-completed-renewal',
                'subject' => 'order-completed-renewal-subject',
                'message' => 'order-completed-renewal-content',
            ],
            'order_expired' => [
                'customer' => 'email-user-order-expired',
                'admin' => 'email-admin-order-expired',
                'subject' => 'order-expired-subject',
                'message' => 'order-expired-content',
            ],
            'order_denied' => [
                'customer' => 'email-user-order-denied',
                'admin' => 'email-admin-order-denied',
                'subject' => 'order-denied-subject',
                'message' => 'order-denied-content',
            ],
            'order_published' => [
                'customer' => '',
                'admin' => 'email-admin-publish-notify',
                'subject' => 'order-published-subject',
                'message' => 'order-published-content',
            ],
            'renewal_reminder' => [
                'customer' => 'email-user-renewal-reminder',
                'admin' => 'email-admin-renewal-reminder',
                'subject' => 'renewal-reminder-subject',
                'message' => 'renewal-reminder-content',
            ],
        ];

        $definitions = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/order/notification/definitions', $definitions);
        $definitions = is_array($definitions) ? $definitions : [];

        return is_array($definitions[$type] ?? null) ? $definitions[$type] : [];
    }

    private function replace($template, array $order, array $context) {
        $values = $this->placeholder_values($order, $context);
        $search = [];
        $replace = [];
        foreach ($values as $key => $value) {
            $search[] = '%' . $key . '%';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, (string) $template);
    }

    private function placeholder_values(array $order, array $context) {
        $metadata = $this->metadata($order);
        $user = !empty($order['user_id']) ? get_userdata(absint($order['user_id'])) : false;
        $first_name = $user ? (string) get_user_meta($user->ID, 'first_name', true) : '';
        $last_name = $user ? (string) get_user_meta($user->ID, 'last_name', true) : '';
        $customer_email = $this->customer_email($order);
        $expires_at = strtotime((string) ($context['expires_at'] ?? $metadata['expires_at'] ?? ''));
        $duration_days = absint($metadata['duration_days'] ?? ($metadata['package']['duration_days'] ?? 0));
        $pixel_count = $this->pixel_count(absint($order['id'] ?? 0));
        $placement = is_array($context['placement'] ?? null) ? $context['placement'] : [];
        $legacy_row = is_array($metadata['legacy_row'] ?? null) ? $metadata['legacy_row'] : [];
        $original_order_id = absint($metadata['original_order_id'] ?? $metadata['legacy_original_order_id'] ?? $legacy_row['original_order_id'] ?? $metadata['legacy_order_id'] ?? $order['id'] ?? 0);
        $manage_url = esc_url(Payments::customer_manage_url_for_mds_order($order));
        $view_url = esc_url(admin_url('admin.php?page=mds3-orders&order_id=' . absint($order['id'] ?? 0)));
        $expires_display = $expires_at ? esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $expires_at)) : '';

        $values = [
            'SITE_NAME' => esc_html(get_bloginfo('name')),
            'SITE_URL' => esc_url(home_url('/')),
            'SITE_CONTACT_EMAIL' => esc_html(get_bloginfo('admin_email')),
            'FIRST_NAME' => esc_html($first_name),
            'LAST_NAME' => esc_html($last_name),
            'FNAME' => esc_html($first_name),
            'LNAME' => esc_html($last_name),
            'USERNAME' => esc_html($user ? $user->user_login : ($customer_email ? strstr($customer_email, '@', true) : '')),
            'USER_LOGIN' => esc_html($user ? $user->user_login : ($customer_email ? strstr($customer_email, '@', true) : '')),
            'CUSTOMER_EMAIL' => esc_html($customer_email),
            'ORDER_ID' => absint($order['id'] ?? 0),
            'ORIGINAL_ORDER_ID' => $original_order_id,
            'GRID_NAME' => esc_html($this->grid_name($order, $placement)),
            'PIXEL_COUNT' => $pixel_count,
            'BLOCK_COUNT' => $pixel_count,
            'PIXEL_DAYS' => $duration_days,
            'PLACEMENT_URL' => esc_url((string) ($placement['link_url'] ?? '')),
            'PLACEMENT_ALT' => esc_html((string) ($placement['alt_text'] ?? '')),
            'URL_LIST' => esc_html((string) ($placement['link_url'] ?? '')),
            'DAYS_LEFT' => absint($context['days_left'] ?? 0),
            'EXPIRES_AT' => $expires_display,
            'DEADLINE' => $expires_display,
            'PRICE' => esc_html($this->format_price($order)),
            'STATUS' => esc_html(ucwords(str_replace('_', ' ', sanitize_key((string) ($order['status'] ?? ''))))),
            'PREVIOUS_STATUS' => esc_html(ucwords(str_replace('_', ' ', sanitize_key((string) ($context['previous_status'] ?? ''))))),
            'REASON' => esc_html(ucwords(str_replace('_', ' ', sanitize_key((string) ($context['reason'] ?? ''))))),
            'MANAGE_URL' => $manage_url,
            'VIEW_URL' => $view_url,
        ];

        foreach ($this->legacy_custom_field_placeholders($metadata) as $key => $value) {
            if (!array_key_exists($key, $values)) {
                $values[$key] = $value;
            }
        }

        $values = \MillionDollarScript\Core\Hooks::apply('million-dollar-script/order/notification/placeholder/values', $values, $order, $context, $metadata);

        return is_array($values) ? $values : [];
    }

    private function legacy_custom_field_placeholders(array $metadata) {
        $fields = is_array($metadata['mds_fields'] ?? null) ? $metadata['mds_fields'] : [];
        $values = [];
        foreach ($fields as $field_key => $field) {
            $placeholder = preg_replace('/[^A-Z0-9_]/', '_', strtoupper((string) $field_key));
            $placeholder = trim((string) $placeholder, '_');
            if ('' === $placeholder) {
                continue;
            }

            $value = is_array($field) ? ($field['formatted_value'] ?? $field['value'] ?? '') : $field;
            if (is_array($value)) {
                $value = implode(', ', array_map('sanitize_text_field', array_map('strval', $value)));
            }

            $values[$placeholder] = esc_html((string) $value);
        }

        return $values;
    }

    private function format_price(array $order) {
        return Currency::format((float) ($order['total'] ?? 0), $order['currency'] ?? '', $this->settings());
    }

    private function pixel_count($order_id) {
        $count = 0;
        foreach ($this->orders->items($order_id) as $item) {
            $count += max(1, absint($item['quantity'] ?? 1));
        }

        return $count;
    }

    private function grid_name(array $order, array $placement = []) {
        $grid_id = absint($placement['grid_id'] ?? 0);
        if (!$grid_id) {
            foreach ($this->orders->items(absint($order['id'] ?? 0)) as $item) {
                $grid_id = absint($item['grid_id'] ?? 0);
                if ($grid_id) {
                    break;
                }
            }
        }

        if ($grid_id && class_exists('\\MillionDollarScript\V3\\Grid\\GridRepository')) {
            $grid = (new \MillionDollarScript\V3\Grid\GridRepository())->find($grid_id);
            if ($grid && method_exists($grid, 'get')) {
                /* translators: %d: grid ID. */
                return (string) $grid->get('title', sprintf(__('Grid #%d', 'million-dollar-script'), $grid_id));
            }
        }

        /* translators: %d: grid ID. */
        return $grid_id ? sprintf(__('Grid #%d', 'million-dollar-script'), $grid_id) : '';
    }

    private function customer_email(array $order) {
        $email = sanitize_email((string) ($order['email'] ?? ''));
        if ($email) {
            return $email;
        }

        if (!empty($order['user_id'])) {
            $user = get_userdata(absint($order['user_id']));
            if ($user) {
                return sanitize_email((string) $user->user_email);
            }
        }

        return '';
    }

    private function reminder_days(array $settings) {
        $days = [];
        foreach (['renewal-reminder-days-1', 'renewal-reminder-days-2', 'renewal-reminder-days-3'] as $key) {
            $day = absint(SettingsSchema::sanitize($key, $settings[$key] ?? SettingsSchema::defaults()[$key] ?? 0));
            if ($day > 0) {
                $days[] = $day;
            }
        }

        $days = array_values(array_unique($days));
        sort($days, SORT_NUMERIC);

        return $days;
    }

    private function due_reminder_day(array $days, array $metadata, $expires_at, $now) {
        $sent = is_array($metadata['renewal_reminders_sent'] ?? null) ? $metadata['renewal_reminders_sent'] : [];
        $seconds_left = max(0, (int) $expires_at - (int) $now);
        foreach ($days as $day) {
            if ($seconds_left <= ($day * DAY_IN_SECONDS)) {
                return empty($sent[(string) $day]) ? $day : 0;
            }
        }

        return 0;
    }

    private function paid_orders_with_expiration($limit, $max_days = 0) {
        global $wpdb;

        $max_days = max(1, absint($max_days));
        $expires_at = OrderLifecycleFields::expiration_sql('metadata', 'expires_at');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::ident(DB::table('orders')) . ' WHERE status = %s AND ' . $expires_at . ' > %s AND ' . $expires_at . ' <= %s ORDER BY ' . $expires_at . ' ASC, id ASC LIMIT %d',
                'paid',
                gmdate('Y-m-d H:i:s'),
                gmdate('Y-m-d H:i:s', time() + ($max_days * DAY_IN_SECONDS)),
                max(1, min(500, absint($limit)))
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    private function metadata(array $order) {
        $metadata = json_decode((string) ($order['metadata'] ?? ''), true);

        return is_array($metadata) ? $metadata : [];
    }

    private function enabled(array $settings, $key) {
        return 'yes' === SettingsSchema::sanitize($key, $settings[$key] ?? SettingsSchema::defaults()[$key] ?? 'yes');
    }

    private function settings() {
        $stored = get_option('mds3_settings', []);

        return wp_parse_args(is_array($stored) ? $stored : [], SettingsSchema::defaults());
    }
}
