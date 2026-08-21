# Developer Upgrade Notes

Million Dollar Script 3.0 keeps selected compatibility surfaces for older integrations while moving new work to a cleaner, extension-friendly architecture.

## Naming

Use **Million Dollar Script** in user-facing copy. Use **MDS** only when the context is already clear. Use **MDS 3.0** only when the version distinction matters.

## Constants And Boot Hooks

Million Dollar Script 3.0 does not define the ambiguous `MDS_VERSION`, `MDS_FILE`, `MDS_PATH`, `MDS_URL`, or `MDS_BASENAME` globals used by Million Dollar Script 2. Extensions should read runtime metadata through `MillionDollarScript\Core\Runtime` so both plugins can load in either order.

Use `million-dollar-script/loaded` for load integrations. The older `mds_initialized`, `mds_loaded`, and `mds-loaded` actions remain silent compatibility aliases for side-by-side Million Dollar Script 2 migration.

```php
// Old ambiguous global.
$version = MDS_VERSION;

// Supported extension API.
$version = \MillionDollarScript\Core\Runtime::version();
```

Browser integrations should read `window.MillionDollarScript`. Grid instances are available through `window.MillionDollarScript.gridInstances`, and extensions can publish configuration through `MillionDollarScript\Extensions\Support::add_browser_config()`. Extensions should not create standalone browser globals for configuration.

## Payment Providers

New payment integrations should register through the Million Dollar Script payments layer instead of calling WooCommerce, Easy Digital Downloads, Stripe, or other checkout systems directly from monetization extensions.

## REST API

API clients should use the `/wp-json/million-dollar-script/v1` namespace with `Authorization: Bearer ...` or `X-Million-Dollar-Script-API-Key`. Browser write endpoints that require a public write nonce also require a valid `X-WP-Nonce`. Development-only REST namespaces are not registered.

## Custom Fields

The importer reads active definitions from the legacy `mds_custom_fields` table and stores recognized ad values under the imported order metadata key `mds_fields`. Unmapped non-core ad metadata is retained under `legacy_ad_post_meta`. Field definitions are not automatically created in the paid Fields extension, so retain matching field keys when recreating fields that must display migrated values.

Code that reads old order, pixel, page, or `_milliondollarscript_<field_key>` post metadata directly should be updated to use current order metadata, repositories, REST endpoints, shortcodes, filters, or documented compatibility hooks. Migration integrations can use `million-dollar-script/migration/legacy/mds/field/definitions`, `million-dollar-script/migration/legacy/mds/fields`, `million-dollar-script/migration/legacy/ad/metadata`, and `million-dollar-script/migration/legacy/order/metadata` to extend the mapping without modifying core.

Placement-field extensions can use `million-dollar-script/placement/form/fields` for both the interactive grid and the Manage Upload screen. The first argument is the grid object on an interactive grid and `null` on Manage Upload. The optional second argument is a context array; Manage Upload provides `context`, `order`, and `placement` values. Callbacks registered for one argument continue to work unchanged.

## Settings Compatibility

Some Million Dollar Script 2 settings are still recognized for migration, import/export, and custom-code compatibility, but they are hidden from the main admin Settings tabs until they affect the current runtime.

Use `MillionDollarScript\Core\Settings::field_classification($key)` to check whether a setting is active, compatibility-only, deferred, or extension-owned. Use `MillionDollarScript\Core\Settings::is_admin_visible($key)` before presenting core settings in custom admin UI.

Settings import/export uses the full schema, including hidden compatibility fields. Use the `million-dollar-script/admin/settings/transfer/fields` filter if an extension needs package-owned settings included in transfer payloads.

## Extensions

Extensions should:

- Register setup and onboarding items through current hooks.
- Keep settings and rendering controls extension-owned when the feature belongs to the extension.
- Avoid hardcoded currency labels and use the core currency helpers or active payment provider.
- Use package-owned docs manifests for bundled documentation.
- Keep user-facing labels free of internal version shorthand.

### Admin Navigation

Use `million-dollar-script/extension/onboarding/items` to expose setup or settings actions for an extension. The Million Dollar Script dashboard and admin-bar extension menu use those actions for quick access when the extension is active.

Use `million-dollar-script/dashboard/extension/cards` when an extension needs to customize its dashboard card, add multiple buttons, or link to bundled documentation. Use `million-dollar-script/admin/bar/extension/items` only when the admin-bar link should differ from the primary onboarding action.

Use `million-dollar-script/extension/visual/metadata` when an extension needs a custom dashboard/catalog icon color beyond the defaults. MDS-owned extensions may also declare `MDS Icon` and `MDS Accent Color` plugin headers. The icon should be a Dashicons class or slug, and the accent color should be a six-digit hex color.

Use `million-dollar-script/dashboard/paid/revenue/url` when an active reporting extension should send the dashboard Paid revenue metric to a deeper report instead of the Orders screen.

Do not modify the WordPress admin menu DOM from an extension. Core owns the scoped MDS sidebar grouping so active extension pages nest below Extensions while remaining compatible with WordPress and other plugins. Extension admin pages should be registered only while the extension is active.
