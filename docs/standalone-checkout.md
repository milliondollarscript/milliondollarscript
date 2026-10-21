# Standalone Checkout

Standalone checkout is the built-in manual payment mode. It is useful for private launches, invoice-based sales, or sites that are not ready to connect a commerce plugin.

## How It Works

1. The customer selects blocks.
2. Million Dollar Script creates an unpaid order and reserves the selection.
3. The customer receives a manage link.
4. The site owner collects payment outside the plugin.
5. An administrator marks the order paid or updates its status from the Orders page.

## When To Use It

Standalone checkout works well when:

- You want to approve customers before payment.
- You sell by invoice, bank transfer, check, or direct agreement.
- You are testing a new grid before connecting a payment gateway.
- You operate a site where every placement requires manual review.

## The Checkout URL

The Checkout URL under **Settings > Commerce** is where a customer goes to pay for an unpaid order. It must be a page that can take payment: an external payment page, a hosted payment link, or a page you built for that purpose. It cannot be one of the pages Million Dollar Script creates — Payment, Thank You, Upload, or My Pixels — because none of those can take payment. Pointing it at the Payment page leaves the customer on the same screen they were already on, and the setting is now refused with that explanation.

The address carries the order reference and amount with it, so the payment page knows what is being paid for. A return address is added only when a Thank You page exists; without one, the customer comes back through their order email or the My Pixels page.

## Order Expiration

Unpaid reservations can expire automatically according to the order settings. Expired reservations release their blocks unless the order has already been paid or published.

## Customer Communication

Standalone checkout relies on clear email messages. Review the Payment Requested, Order Paid, Order Expired, Order Denied, and Renewal Reminder messages under **Settings > Order Emails**.
