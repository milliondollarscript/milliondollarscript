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

The setup wizard creates the pages a store works with:

- A read-only public grid page for browsing published placements.
- An order page with the interactive grid.
- Manage Pixels and Upload pages, where a customer finds their own ads again.
- Legal pages when the legal-page setup option is selected.

The remaining pages each hold a step a site may not use, so they are created only
when asked for on the same screen. Every box there starts unchecked:

- Write Content, for typing the ad text away from the grid.
- Confirm Order, which asks a customer to check an order before its ad goes on the
  grid. It belongs with **Ask Customers To Confirm Orders** under Settings > Orders:
  with that switched off the page has nothing to do, and without the page that step
  is skipped and the customer goes on to payment or to their own order.
- Payment, which shows what an order costs and what it still owes on its own page.
- Thank You, the page an order lands on when it is finished.
- Advertiser List, a public list of the advertisers on a grid.
- No Orders, shown instead of a grid that has no ads up.

A page that already exists is used whichever boxes are chosen — a page in this
site, or one carried over from an earlier version of Million Dollar Script.
Leaving a box unchecked only means no page is made for it.

The read-only grid page is intended for visitors who only want to view the board. The order page is intended for customers who want to select blocks and reserve pixels.

## Page Addresses

The pages live at addresses of their own, such as `/order/` or `/upload/`. Those
addresses only work when WordPress is allowed to build them, and a brand-new
WordPress site does not start that way: **Settings > Permalinks** ships set to
**Plain**, which addresses pages as `?page_id=12` instead.

Set it before you send anyone to the site:

1. Open **Settings > Permalinks**.
2. Choose **Post name** and save.
3. Open the grid page and the order page from the front end to confirm they open.

With Plain still in place, opening `/order/` shows your blog or a Not Found page,
and the links in order emails and customer notices do not reach the page they name.
Advertiser pages and the pages a migrated site brings over need the same setting.

## Navigation

A WordPress theme with no menu assigned lists **every published page** in the
header. On an advertising site that is a long list: the grid, the order pages, plus
a page per advertiser once the site has been running or migrated for a while.

Choose the pages customers should reach, in the order you want them:

- The public grid page.
- The order page.
- Manage Pixels and Upload, so a customer can find their own ad again.

Where you do that depends on the theme. A block theme (the themes that ship with
recent WordPress versions) uses **Appearance > Editor**, then the navigation area;
the classic **Appearance > Menus** screen is not present in a block theme. A classic
theme uses **Appearance > Menus**.

### Adding pages to your menu

A block theme:

1. Open **Appearance > Editor**, then choose **Navigation**.
2. Choose the navigation your theme shows in the header.
3. Remove the links you do not want, and add the pages listed above in the order you
   want customers to see them. Save.

A classic theme:

1. Open **Appearance > Menus**.
2. Create a menu, or select the one your theme already uses.
3. Add the pages listed above, then under **Menu settings** tick the location the theme
   calls primary or main and save.

Both routes change only the links in that menu. The pages themselves are not touched.

### The starter-site step and your existing menu

**Create starter site** can write the navigation for you, but it does not touch your
navigation unless you tell it to. **Replace my existing navigation with the starter pages**
is off; while it is off this step writes no menu, adds no links to a menu you have, and
rewrites nothing it wrote before — it leaves navigation exactly as it is and tells you what
is still missing, with a link back to this page.

Turn it on when you would rather start from the starter pages than edit your own menu. It
then removes the links your current navigation holds and writes the starter pages in their
place, or writes them as a new menu if the site has none. Your pages are not deleted — only
the links are replaced.

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

Setup reports what it did with the extensions you selected: the ones it activated, and — by name — any that could not be set up because something they need is missing or switched off, and any that are not installed yet. An extension that is not installed yet is left untouched: install it from **Extensions** whenever you want it on the site.

### Private Tester Access

An extension server administrator can issue a temporary tester access key without creating a customer account or purchase. On **Million Dollar Script > Extensions**, open **Extension server tester access**, enter the supplied key, and choose **Connect tester access**.

The key can cover all approved extensions or a restricted selection. Connecting it binds access to this Million Dollar Script installation and site URL. Its expiry, site limit, development-site access, and revocation are controlled by the extension server administrator. Updates, installation packages, and private documentation are denied when the requesting installation is not actively connected. An extension's own customer license takes priority when both credentials are present. Disconnect the tester key when testing ends.

Connected tester access allows entitled premium extension installation, updates, and online documentation. Private documentation is removed from the Documentation page when access expires, is revoked, or is disconnected.

## Launch Check

Before sending traffic to the site, confirm:

- System Status reports a PHP memory limit of at least 256 MB.
- The public grid page opens without requiring login.
- The order page shows an interactive grid.
- Every page opens at its own address rather than showing the blog or a Not Found page.
- The header lists the pages you chose, not every published page on the site.
- Test reservations create orders with the expected price and currency.
- Manage/upload links work for the customer email address.
- WordPress mail is delivering messages, or an SMTP plugin is configured.
- Legal pages have been reviewed and published.
