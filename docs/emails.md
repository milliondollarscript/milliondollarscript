# Order Emails

Million Dollar Script sends order emails through direct WordPress mail. Use an SMTP, delivery, or logging plugin when the site needs authenticated delivery, retries, or mail logs.

## Email Events

The Order Emails settings cover:

- Payment requested.
- Order paid.
- Renewal paid.
- Order expired.
- Order denied.
- Placement published.
- Renewal reminders.

Each event can have customer and administrator toggles when that recipient makes sense.

## Editing Messages

Message fields use the WordPress editor so you can format email content without editing raw HTML. Keep messages short, clear, and specific to the action the customer should take.

Common placeholders include:

- `%ORDER_ID%`
- `%PIXEL_COUNT%`
- `%PRICE%`
- `%STATUS%`
- `%MANAGE_URL%`
- `%SITE_NAME%`
- `%SITE_URL%`
- `%SITE_CONTACT_EMAIL%`
- `%DAYS_LEFT%`
- `%EXPIRES_AT%`

## Renewal Reminders

The renewal reminder day fields define how many days before expiration the reminder should be sent. A value of `7`, `3`, and `1` sends reminders one week, three days, and one day before expiration when the cleanup scheduler runs.

## Delivery Testing

After editing email settings, place a test order and confirm:

- The expected messages are sent.
- Manage links open the correct order.
- The message renders well in a real email client.
- The From address and delivery method pass the site owner's mail requirements.
