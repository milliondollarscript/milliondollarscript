# Grids and Editor Blocks

Use **Million Dollar Script > Grids** to create and manage advertising grids. A grid defines its pixel dimensions, sellable block size, default block price, currency, public page behavior, renderer, price zones, and unavailable areas.

## Create a Grid

1. Open **Grids** and choose **Add Grid**.
2. Enter a clear title and the grid dimensions in pixels.
3. Set the sellable block width and height. A 1000 by 1000 pixel grid with 10 by 10 pixel blocks contains 10,000 sellable blocks.
4. Confirm the currency and default price.
5. Save the grid, then review **Price Zones** and **Availability** when parts of the board need different pricing or must not be sold.

The grid editor provides zoom and fit controls for detailed maps. Price zones and unavailable areas must align with the grid's block boundaries. Public grids start fitted to their available container, including grids whose source dimensions are much larger than the page.

### Background presentation

Use **Grid Background** while editing a grid to choose a Media Library image, fit, position, repeat behavior, and opacity. The background color remains visible while the image loads, when the attachment is missing, and through transparent parts of the image.

The image is painted beneath grid lines, price and availability overlays, selections, and approved placement artwork. Clearing the grid field does not delete the Media Library item. A missing or deleted attachment falls back to the configured color.

Background images currently use browser-local composition for both the classic canvas and OpenLayers viewers. While a background image is active, Million Dollar Script does not serve previously generated ImageGrid tiles because the hosted `grid_render` operation does not yet accept the same background-image contract. Remove the image before submitting a hosted render, or keep the grid on local rendering.

Grid exports store a background URL instead of a site-specific attachment ID. Importing on the same site reconnects that attachment. An import on another site keeps the fit, position, repeat, opacity, and fallback color but requires an administrator to choose the image again.

## Public And Ordering Pages

A read-only grid lets visitors inspect published placements without starting an order. An interactive grid lets visitors select available blocks and reserve a placement.

The setup wizard normally creates separate pages for these jobs:

- The public grid page uses read-only display.
- The Order Pixels page uses interactive ordering.
- Manage, upload, payment, confirmation, and thank-you pages continue the customer flow.

Keeping public viewing and ordering on separate pages gives visitors a predictable browsing page while preserving a clear purchase path. Sites with several grids can use the Page Flow block's searchable grid list instead of creating a separate navigation system.

## WordPress Editor Blocks

Million Dollar Script blocks appear in the **Million Dollar Script** block category and can also be found by searching for “MDS”, “Million Dollar Script”, “grid”, or “pixels”.

### Grid Embed

Use **Grid Embed** when a page should display one specific grid. Choose the grid from the editor instead of entering its database ID. The block settings control:

- **Interaction**: view-only display or immediate block ordering.
- **Width and height**: responsive CSS dimensions; the automatic height follows the grid aspect ratio.
- **Renderer**: automatic selection or an available renderer override.
- **Statistics**: inherit the grid setting, show the stats widget, or hide it for this embed.

Leave interaction in view-only mode on a browsing page. Enable ordering only when the page is intended to accept selections.

### Page Flow

Use **Page Flow** for a complete Million Dollar Script page panel. Available flows include public grid, Order Pixels, write ad, confirm order, payment, manage pixels, thank you, advertiser list, upload, no orders, and statistics. The Order Pixels flow is always interactive.

List flows can use a searchable list or responsive grid layout. Grid-related flows expose the same renderer, dimensions, and statistics choices as Grid Embed.

### Stats Widget

Use **Stats Widget** to place sold and available inventory separately from a grid. Select a grid, choose pixels or blocks, set a stable width, and optionally override its number, label, background, and border colors. Leaving the unit at **Use settings** follows **Settings > Display & Interaction > Stats Display Mode**.

## Extension Blocks

Active extensions can add their own blocks to the same category. Extension blocks and their choices are registered by the extension and disappear when that extension is inactive. Where an extension references a saved item, its block should provide a list of current records and a custom identifier option for large or externally managed collections.

## Shortcode Compatibility

Dynamic blocks render through the same validated server-side paths as Million Dollar Script shortcodes. The editor can show a visual preview while preserving a shortcode representation for HTML editing and older content. Use the visual block controls for routine changes so grid, page type, and interaction values remain valid.

After adding or changing a grid block, preview the page while logged out. Confirm that the correct grid loads, view-only pages do not start reservations, the ordering page accepts valid selections, and the layout fits both desktop and mobile widths.
