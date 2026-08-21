# Setup

Million Dollar Script starts with a short setup flow that chooses site capabilities, creates the standard pages, and connects a payment provider when one is available.

## Server Requirements

Million Dollar Script requires PHP 8.1 or newer and a PHP memory limit of at least 256 MB. The memory requirement leaves room for WordPress, a payment provider such as WooCommerce, and active Million Dollar Script extensions in the same request. Your hosting control panel may expose a higher server maximum while WordPress or PHP still uses a lower per-request value.

Open **Million Dollar Script > System Status** or **Tools > Site Health** to see the effective limit. If it is below 256 MB, update the PHP setting through the hosting control panel or ask the hosting provider to change it before enabling a production checkout and extension stack.

## Recommended First Run

1. Open **Million Dollar Script > Dashboard**.
2. Choose **Setup**.
3. Keep **Classic Pixel Grid** enabled for a traditional pixel advertising site.
4. Choose a payment provider.
5. Create the standard pages.
6. Review the generated pages from the front end.
7. Open **Settings** and confirm currency, display, order, email, and extension server settings.

## Standard Pages

The setup wizard can create the standard pages used by the core grid workflow:

- A read-only public grid page for browsing published placements.
- An order page with the interactive grid.
- Manage Pixels, Upload, Stats, and Advertiser List pages.
- Legal pages when the legal-page setup option is selected.

The read-only grid page is intended for visitors who only want to view the board. The order page is intended for customers who want to select blocks and reserve pixels.

## Payment Provider

Standalone checkout is available without another commerce plugin. It creates Million Dollar Script orders and gives the customer a manage link so the site owner can arrange payment manually.

WooCommerce checkout requires both WooCommerce and the WooCommerce Checkout extension. After both are active, choose WooCommerce as the payment provider in Setup or Settings.

The Commerce step reports the enabled WooCommerce payment methods, assigned checkout page, and HTTPS status. Use **Review checkout readiness** to open the extension's detailed status screen; this remains available before the setup flow is finalized. An active WooCommerce Checkout extension appears as **Selected and active** under Site capabilities because payment routing can be changed without deactivating the extension.

## Display Settings

Use **Settings > Display & Interaction** to control the default public grid appearance. The background, panel, text, button, and button text colors apply to the grid shell and related public grid controls. Individual grid settings still control grid-specific behavior such as renderer mode, public statistics, and ordering.

Keep the default **View all** mode unless a site has a specific reason to show extra view controls. Large grids automatically scale their starting height to the available page width so the full board remains visible when zoomed out.

Popup settings are split between the popup container and uploaded artwork. **Max Popup Size** caps the width of the block popup itself, while **Max Popup Image Size** caps the image inside that popup.

## Compatibility Settings

Some older Million Dollar Script 2 settings are preserved for migration, import/export, and custom-code compatibility without being shown as active controls in the main settings tabs. Review **Settings > Upgrade Compatibility** to see which values are being retained but are not currently used by the Million Dollar Script 3.0 runtime.

The legacy public endpoint slug is one of those preserved values. Current Million Dollar Script sites use WordPress pages, blocks, shortcodes, AJAX actions, and REST API routes for live traffic instead of a configurable frontend endpoint base.

If custom code still reads one of those older settings, it can continue to read the stored option value. New integrations should use current repositories, blocks, shortcodes, REST endpoints, and documented hooks instead of depending on hidden compatibility settings.

## Extensions

Use **Extensions** to install optional capabilities such as WooCommerce checkout, ImageGrid rendering, SponsorBoard, or other package-owned features. Extensions should match the major Million Dollar Script version they were built for.

### Private Tester Access

An extension server administrator can issue a temporary tester access key without creating a customer account or purchase. On **Million Dollar Script > Extensions**, open **Extension server tester access**, enter the supplied key, and choose **Connect tester access**.

The key can cover all approved extensions or a restricted selection. Connecting it binds access to this Million Dollar Script installation and site URL. Its expiry, site limit, development-site access, and revocation are controlled by the extension server administrator. Updates, installation packages, and private documentation are denied when the requesting installation is not actively connected. An extension's own customer license takes priority when both credentials are present. Disconnect the tester key when testing ends.

Connected tester access allows entitled premium extension installation, updates, and online documentation. Private documentation is removed from the Documentation page when access expires, is revoked, or is disconnected.

## Launch Check

Before sending traffic to the site, confirm:

- System Status reports a PHP memory limit of at least 256 MB.
- The public grid page opens without requiring login.
- The order page shows an interactive grid.
- Test reservations create orders with the expected price and currency.
- Manage/upload links work for the customer email address.
- WordPress mail is delivering messages, or an SMTP plugin is configured.
- Legal pages have been reviewed and published.
