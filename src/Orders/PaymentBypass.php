<?php
/**
 * Letting one named person order without paying.
 *
 * Million Dollar Script 2 kept this as Carbon Fields user meta called "privileged" and used it
 * to finish an order as though the money had arrived (milliondollarscript-two/src/Classes/Orders/Orders.php:1170
 * reads the price and that flag together; Classes/Admin/AdminPostHandlers.php:179 reads the flag
 * on its own and accepts 1, yes, true or privileged). Nothing on a site explains the word
 * "privileged", so a site owner who had granted it could not tell what it still did - and a
 * person who relied on it could not tell why a new site started asking them to pay.
 *
 * MDS3 keeps the behaviour and names it after what it does. It is a decision about one person,
 * separate from the site-wide choice about payments that a manual checkout makes: the site-wide
 * switch covers everyone whose payment will not be confirmed by a gateway, this one covers a
 * named person whatever the checkout looks like.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Support\DB;

if (!defined('ABSPATH')) {
    exit;
}

final class PaymentBypass {

    public const META_KEY = 'mds3_order_without_payment';

    /** The answers a stored flag may hold. Anything else reads as "not allowed". */
    private const ON_VALUES = ['1', 'yes', 'true', 'on'];

    public function register(): void {
        add_action('show_user_profile', [$this, 'render_profile_field']);
        add_action('edit_user_profile', [$this, 'render_profile_field']);
        add_action('personal_options_update', [$this, 'save_profile_field']);
        add_action('edit_user_profile_update', [$this, 'save_profile_field']);
    }

    /**
     * May this person's orders move forward with no payment taken?
     */
    public function allowed($user_id): bool {
        $user_id = absint($user_id);
        if (!$user_id) {
            return false;
        }

        return in_array(strtolower(trim((string) get_user_meta($user_id, self::META_KEY, true))), self::ON_VALUES, true);
    }

    public function for_order(array $order): bool {
        return $this->allowed(absint($order['user_id'] ?? 0));
    }

    public function allow($user_id, bool $allowed): void {
        $user_id = absint($user_id);
        if (!$user_id) {
            return;
        }

        if ($allowed) {
            update_user_meta($user_id, self::META_KEY, 'yes');
        } else {
            delete_user_meta($user_id, self::META_KEY);
        }
    }

    /**
     * How many people on this site have it. The migration report uses the count to say how
     * many of the old site's permissions carried across; a list of names would be a longer
     * sentence that tells a site owner nothing they can act on.
     */
    public function count_allowed(): int {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(DISTINCT user_id) FROM ' . DB::ident($wpdb->usermeta) . ' WHERE meta_key = %s',
            self::META_KEY
        ));
    }

    /**
     * The field on the user's profile screen.
     *
     * Only someone who can change what other people are allowed to do sees it, and a guest
     * editing their own profile cannot grant it to themselves - that is what makes it a
     * permission rather than a preference.
     */
    public function render_profile_field($user): void {
        $user_id = is_object($user) ? absint($user->ID ?? 0) : 0;
        if (!$user_id || !current_user_can('promote_users')) {
            return;
        }

        $on = $this->allowed($user_id);
        wp_nonce_field('mds3_payment_bypass', 'mds3_payment_bypass_nonce');

        echo '<h2>' . esc_html__('Ordering without paying', 'million-dollar-script') . '</h2>';
        echo '<table class="form-table"><tr><th scope="row">' . esc_html__('Payment', 'million-dollar-script') . '</th><td>';
        echo '<label><input type="checkbox" name="mds3_order_without_payment" value="yes"' . checked($on, true, false) . '> '
            . esc_html__('Let this person complete orders without paying.', 'million-dollar-script') . '</label>';
        echo '<p class="description">' . esc_html__(
            'Their ads go live as soon as the artwork is saved, with no payment step. Use this for people who pay by invoice, and for anyone who places orders on the site’s behalf.',
            'million-dollar-script'
        ) . '</p>';
        echo '</td></tr></table>';
    }

    public function save_profile_field($user_id): void {
        $user_id = absint($user_id);
        if (!$user_id || !current_user_can('promote_users')) {
            return;
        }

        $nonce = isset($_POST['mds3_payment_bypass_nonce']) ? sanitize_text_field((string) $_POST['mds3_payment_bypass_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'mds3_payment_bypass')) {
            return;
        }

        $this->allow($user_id, isset($_POST['mds3_order_without_payment']) && 'yes' === (string) $_POST['mds3_order_without_payment']);
    }
}
