<?php
/**
 * The customer's review step: whether a site asks for it, and what confirming does.
 *
 * MDS2 put a review between uploading artwork and paying for it. The customer pressed Confirm
 * and only then did the order move towards payment; the destination after the upload form was
 * chosen from the same setting (milliondollarscript-two/src/Classes/Forms/Forms.php:526). MDS3
 * carried the setting over and never acted on it. Everything that needs to know about the step -
 * the settings screen, the save path, the customer's order page - reads it here, so they cannot
 * disagree about whether it exists.
 *
 * An order waiting on its customer is still a reservation: the blocks stay held, and
 * "Minutes To Keep Unconfirmed Orders" is what releases them. That is the same thing MDS2's
 * "Minutes To Keep Unconfirmed Orders" did with an order nobody had confirmed.
 *
 * @package MillionDollarScript\V3\Orders
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Blocks\BlockRepository;
use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Media\PlacementRepository;
use MillionDollarScript\V3\Settings\SettingsSchema;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderConfirmation {

    /**
     * Is the site asking customers to confirm their order before it moves forward?
     */
    public function step_requested(array $settings) {
        return 'yes' === SettingsSchema::sanitize('confirm-orders', (string) ($settings['confirm-orders'] ?? 'no'));
    }

    /**
     * Has this customer submitted their ad and not confirmed it yet?
     *
     * The order row cannot tell those two situations apart - a reservation nobody has filled in
     * and a submission waiting for its customer are both "reserved" - so the ad itself is the
     * evidence: a placement only exists once the customer has uploaded and saved their artwork.
     * Without that check the review step would tell people to confirm an order they have not
     * finished making.
     */
    public function await_confirmation(array $order, array $settings, array $placements = null) {
        if ('yes' !== SettingsSchema::sanitize('confirm-orders', (string) ($settings['confirm-orders'] ?? 'no'))) {
            return false;
        }

        if ('reserved' !== sanitize_key((string) ($order['status'] ?? ''))) {
            return false;
        }

        if (null === $placements) {
            $placements = (new PlacementRepository())->for_order(absint($order['id'] ?? 0));
        }

        return !empty($placements);
    }

    /**
     * Is this an order the site handles itself - no payment provider, and no payment page of
     * its own? It is the only case where confirming changes the order's status, and the only
     * case where confirming can put an ad on the grid without a payment step. Both the action
     * and the words on the review screen ask here, so they cannot disagree about what pressing
     * Confirm will do.
     */
    public function handled_by_site(array $order, array $settings) {
        $provider = sanitize_key((string) ($order['commerce_provider'] ?? '')) ?: 'standalone';

        return 'standalone' === $provider && empty($settings['checkout-url']);
    }

    /**
     * Move an unpaid standalone order forward now that its customer has confirmed it.
     *
     * This is the transition the save path used to perform by itself the moment artwork arrived.
     * A site with a payment page of its own, or a payment provider handling the money, is left
     * alone here: in those cases the provider's own flow sets the status, and changing it from
     * the review step would fight it.
     *
     * @return array|\WP_Error the updated order, or the error from the update
     */
    public function advance(OrderRepository $orders, PlacementRepository $placements, array $order, array $settings) {
        $order_id = absint($order['id'] ?? 0);
        $status = sanitize_key((string) ($order['status'] ?? ''));

        if (!$this->handled_by_site($order, $settings) || in_array($status, ['paid', 'cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'], true)) {
            return $order;
        }

        $auto_complete = $this->accepts_manual_payment($order, $settings);
        $next = $auto_complete ? 'paid' : 'pending_payment';
        if ($status === $next) {
            // The order is already where this step would put it. Writing it again would move
            // updated_at, and the window an unpaid order holds its space for is counted from
            // that - so re-saving an ad would silently extend the hold on grid space.
            return $order;
        }
        $updated = $orders->update($order_id, ['status' => $next]);
        if (is_wp_error($updated)) {
            return $updated;
        }

        $order = $updated;
        if ($auto_complete) {
            (new BlockRepository())->mark_by_order($order_id, 'sold');
            PublishGate::publish($order_id, PublishGate::CUSTOMER_CONFIRMED);
        }

        return $order;
    }

    /**
     * Auto-approval for an order: each grid's own choice wins over the global setting
     * (blank = inherit), and a grid that says "do not approve" holds the order back for
     * review so a mixed basket is never activated against the site owner's wishes.
     */
    /**
     * May this order move forward without anything confirming the money?
     *
     * Three separate reasons settle it, and each is a decision somebody already made somewhere -
     * this method only gathers them, so the settings screen, the review step and a payment
     * provider's own flow cannot each invent their own rule:
     *
     * 1. There is nothing to pay. Million Dollar Script 2 finished these orders itself
     *    (src/Classes/Orders/Orders.php:1172 reads a zero price and the per-person flag in the
     *    same condition). Leaving a free order waiting for a payment nobody will make only
     *    teaches a customer that the site does not work.
     * 2. The customer is someone the site lets order without paying - see Orders/PaymentBypass.
     * 3. The site accepts manual payments, either site-wide or on every grid in the basket.
     *
     * A grid that says "review this" still holds the order back, because the site owner's
     * reason for asking is about the ad, not about the money.
     */
    public function accepts_manual_payment(array $order, array $settings) {
        return '' !== $this->manual_payment_reason($order, $settings);
    }

    /**
     * Why this order may be taken without a gateway confirming it, or '' when it may not.
     *
     * The reason travels with the order so the record says which rule let the ad through. A
     * site owner reading an order that shows no payment needs that sentence, and a support
     * conversation about it cannot be settled from an absence.
     */
    public function manual_payment_reason(array $order, array $settings) {
        if (0 >= (float) ($order['total'] ?? 0)) {
            return 'nothing_to_pay';
        }

        if ((new PaymentBypass())->for_order($order)) {
            return 'person_allowed';
        }

        if ($this->auto_approve_for_grids(new OrderRepository(), absint($order['id'] ?? 0), $settings)) {
            return 'site_accepts_manual';
        }

        return '';
    }

    public function auto_approve_for_grids(OrderRepository $orders, $order_id, array $settings) {
        $auto_complete = 'yes' === SettingsSchema::sanitize('auto-approve', $settings['auto-approve'] ?? 'no');
        $explicit = [];

        foreach ((new GridRepository())->all() as $grid) {
            $choice = strtoupper((string) ($grid->settings()['auto_approve'] ?? ''));
            if ('' === $choice) {
                continue;
            }
            foreach ($orders->items($order_id) as $item) {
                if (absint($item['grid_id'] ?? 0) === $grid->id()) {
                    $explicit[] = ('Y' === $choice);
                    break;
                }
            }
        }

        return $explicit ? !in_array(false, $explicit, true) : $auto_complete;
    }
}
