# Million Dollar Script

Million Dollar Script turns a WordPress site into a visual advertising marketplace. Create responsive pixel grids, sell adjacent placement blocks, collect artwork and advertiser details, and manage orders from WordPress.

## Features

- Responsive view-only and interactive ordering grids with fit, pan, and zoom controls.
- Multiple grids, price zones, availability controls, packages, reservations, and placement terms.
- Secure artwork uploads, in-grid previews, advertiser links, alt text, and popup content.
- Standalone manual checkout with an optional WooCommerce Checkout extension.
- Order lifecycle email templates, renewals, expiration handling, and customer management pages.
- Scoped REST API access and a guided Million Dollar Script 2 migration workflow.
- Optional extensions for hosted ImageGrid rendering, custom fields, reporting, and other capabilities.

## Requirements

- WordPress 6.0 or newer.
- PHP 8.1 or newer.
- MySQL 5.7 or MariaDB 10.4 or newer.

## Installation

For a packaged release, upload the `million-dollar-script.zip` file through **Plugins > Add Plugin > Upload Plugin**.

For a source checkout, place this repository at:

```text
wp-content/plugins/million-dollar-script
```

Activate **Million Dollar Script**, then open **Million Dollar Script > Setup** in WordPress. The setup wizard creates the recommended grid, ordering, account, upload, and legal pages after you review the selections.

## Development

The repository root is the WordPress plugin root. These focused checks run directly from that directory.

```bash
composer validate --strict --no-check-publish
composer run test:rewrite
find src tests/rewrite -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php -l million-dollar-script.php
```

The rewrite suite is self-contained. WordPress integration fixtures under `tests/rewrite` require a disposable WordPress test installation and should never be run against a production database.

See [CONTRIBUTING.md](CONTRIBUTING.md) before proposing a change and [SECURITY.md](SECURITY.md) when reporting a vulnerability.

## Documentation

User documentation is available inside WordPress under **Million Dollar Script > Documentation** and at [milliondollarscript.com/docs](https://milliondollarscript.com/docs).

## License

Million Dollar Script is licensed under GPL-3.0-or-later. See [license.txt](license.txt).
