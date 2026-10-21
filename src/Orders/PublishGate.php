<?php
/**
 * The boundary between an order being settled and its ad going up.
 *
 * Two separate things happen when an order is paid or confirmed, and they are
 * deliberately not the same decision. The pixel is sold and the money is settled -
 * core owns that, and no integration can hold a paid pixel back from being sold,
 * because an unsold pixel is one the site may sell again. What the ad is allowed to
 * do next is a different question: an integration may have its own reason to keep a
 * finished ad off the grid until it is ready - a message that must not appear before
 * the date it was written for, a membership that has to renew first, a sponsorship
 * that runs on a schedule.
 *
 * That question is asked here, once, at the point where core would put the ad on the
 * grid. It is asked about the placement, never about the sale. Core publishes only
 * when every integration answers with literal true, in the same strict way the
 * service-signature contract works, so an integration that returns a lookalike value
 * ('yes', 1, a truthy string) holds its own ad rather than being skipped.
 *
 * An administrator setting a placement's state by hand is not asked here: an owner's
 * explicit choice is not something an integration overrules.
 */

namespace MillionDollarScript\V3\Orders;

use MillionDollarScript\V3\Media\PlacementRepository;

defined('ABSPATH') || exit;

final class PublishGate {

    /**
     * The canonical filter integrations answer.
     *
     * Signature: (bool $eligible, int $order_id, string $context)
     */
    public const HOOK = 'million-dollar-script/order/publish/eligible';

    /** Why core is asking. An integration publishing later names its own reason. */
    const CUSTOMER_CONFIRMED = 'customer_confirmed';
    const PAYMENT_CAPTURED = 'payment_captured';
    const AD_SAVED = 'ad_saved';
    const EXTENSION_READY = 'extension_ready';

    /**
     * May this order's ad go on the grid?
     *
     * @param int    $order_id Order whose ad would be published.
     * @param string $context  Why the question is being asked, for logging and for the integration's own rules.
     */
    public static function eligible($order_id, $context = self::EXTENSION_READY) {
        $order_id = absint($order_id);
        if (!$order_id) {
            return false;
        }

        $context = sanitize_key((string) $context);

        return true === \MillionDollarScript\Core\Hooks::apply(self::HOOK, true, $order_id, '' === $context ? 'unspecified' : $context);
    }

    /**
     * Put the order's ad on the grid, if everything that has a say now agrees.
     *
     * This is the only way an automatic transition to an active placement happens.
     * An integration that held an ad back calls the same method when its own moment
     * arrives, naming its own reason, and the ad goes up then - no second path that
     * could disagree with the first about what "published" means.
     *
     * @return bool whether the ad was placed on the grid
     */
    public static function publish($order_id, $context = self::EXTENSION_READY) {
        $order_id = absint($order_id);
        if (!$order_id || !self::eligible($order_id, $context)) {
            return false;
        }

        (new PlacementRepository())->update_status_by_order($order_id, 'active');

        return true;
    }
}
