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

## Status Changes

Use individual or bulk status actions carefully. Marking an order paid can complete its connected commerce order and publish its eligible placement. Cancelling, deleting, or expiring an order can release inventory according to the order lifecycle rules.

Before changing a paid order, confirm the matching payment-provider record and customer communication requirements.
