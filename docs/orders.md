# Managing Orders

Use **Million Dollar Script > Orders** to find, review, and manage grid orders. The order queues and filters can narrow the list by status, grid, payment provider, payment state, upload state, term, placement state, or date.

## Inspect an Order

Choose **Inspect** to review the customer, payment provider, placement dimensions, uploaded artwork, item pricing, and status history. The placement map highlights the order on its grid and provides zoom controls for detailed review.

## Move a Placement

An administrator can reposition a reserved, awaiting-payment, paid, or renewable expired order while it still owns its blocks:

1. Inspect the order.
2. In the placement map, click the new top-left block. You can also enter the zero-based top row and left column.
3. Choose **Preview move**.
4. Review availability, price-zone impact, and the resulting order state.
5. Choose **Move placement** to confirm.

The move keeps the placement on its existing grid and preserves its exact footprint, order total, payment status, placement status, and uploaded artwork. The preview shows the current item price and the target area's current list price, but it does not reprice an existing order.

The move is blocked when the target extends beyond the grid, contains unavailable blocks, overlaps another reservation or sale, uses an incompatible currency, or the order no longer owns all of its original blocks. Availability is checked again when the move is saved.

Completed moves appear under **Placement history** in the order inspector. The history records the source and target coordinates, grid, block count, time, and administrator.

## The Customer's Order Path

A customer picks their pixels, uploads their artwork, and then moves through whatever your site's payment arrangement needs. Two settings decide how many steps that is: **Ask Customers To Confirm Orders** and **Checkout URL**.

**Ask Customers To Confirm Orders** is off by default. With it on, the customer is sent to your **Confirm Order** page after uploading: the artwork, the pixels and the total are shown there, and the customer presses Confirm. Nothing moves towards payment until they do, the pixels stay held for them, and **Minutes To Keep Unconfirmed Orders** releases them if the customer does not come back. Confirming an order that owes nothing publishes it; confirming an order that owes money takes the customer to payment. With the setting off, the order moves on as soon as the artwork arrives.

After that, where the customer lands:

| Site setting | Where the customer lands |
| --- | --- |
| Confirm step on, and the site has a Confirm Order page | Their own order on your Confirm Order page. |
| **Checkout URL** set | Your payment page, with the order in the address so it opens on their order. |
| No Checkout URL, and the order still owes payment | Their own order page, where they can see the order and continue it. |
| **Auto-complete Manual Payments** set to Yes, and nothing is owed | Your Thank You page, with the order reference. The ad is on the grid, so their part is finished. |
| Editing an order from their order page | Stays on that page. |

The review step comes first whatever the payment arrangement behind it. No site is sent to a page it does not have, and no customer is given a step the site did not ask for: when a step has no destination, the customer stays where they are instead of being dropped on a grid.

### The pages the flow uses

The **Upload** page is where a customer manages an order they have started, the **Payment** page shows an order and its payment, the **Confirm Order** page is the review, and the **Thank You** page is where a completed order is confirmed to the customer. Each is an ordinary page holding this plugin's shortcode with its own type, for example `[mds3_page type="confirm-order"]`. A customer who arrives at one of them with an order in the address sees that order. A customer who arrives without one is offered the grid they are standing at — and if they are signed in and already have an open order on that grid, the page names it as **Continue the order you already have on this grid** rather than starting them at the first step again. The **Manage Pixels** page lists the orders connected to their account, and the private link in the order confirmation opens one order without an account at all. An order that is published, cancelled or expired is finished and is not offered as something to continue.

### Coming from an older site

Sites built on the earlier version of this plugin had the review step and expected it, so a migrated site keeps the choice it was already running: in the older plugin the step counted as on unless you had turned it off. A new installation starts with it off, because sites ask for a shorter route to payment, not a longer one.

**Thank You Page URL** should be the address of a Thank You page of your own, or left empty to use the Thank You page this plugin created for you. It cannot be one of the plugin's other pages — saving the Payment, Upload, or My Pixels page there is refused, because that sends a customer back to a page that asks for payment after their order is already complete.

**Auto-complete Manual Payments** makes unpaid manual orders active as soon as the artwork arrives, without waiting for you to confirm payment. Leave it off unless you intend to trust manual reservations that way; with it off, you mark each order paid from **Orders → Inspect**, and the customer sees their order page in the meantime. When the review step is on, it is the customer's own Confirm click that starts this.

Payment provider flows (WooCommerce, Stripe) send the customer through the provider's own checkout and return them to your Thank You page. See [Standalone Checkout](standalone-checkout.md) for the standalone payment page and its return address.

## Status Changes

Use individual or bulk status actions carefully. Marking an order paid can complete its connected commerce order and publish its eligible placement. Cancelling, deleting, or expiring an order can release inventory according to the order lifecycle rules.

Before changing a paid order, confirm the matching payment-provider record and customer communication requirements.
