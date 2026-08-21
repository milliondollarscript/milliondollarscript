# Extension API

Million Dollar Script exposes a version-neutral PHP API for extensions. Check the API major before using a capability, and depend only on the public namespaces documented here.

```php
use MillionDollarScript\Core\Runtime;

if (!Runtime::is_ready() || version_compare(Runtime::api_version(), '1', '<')) {
    return;
}
```

## Runtime

`MillionDollarScript\Core\Runtime` provides:

- `version()` for the installed plugin version.
- `api_version()` for the supported extension API major.
- `file()` for the core plugin bootstrap file.
- `path($relative)` and `url($relative)` for paths below the core plugin directory.
- `is_ready()` to confirm that core registration has completed.

Relative paths containing `..` are rejected. Extensions should use their own plugin paths for extension-owned assets.

## Core data

`MillionDollarScript\Core\Grids` provides `all()`, `active()`, `find($id)`, and `first_active()`. These methods return `MillionDollarScript\Core\Grid` value objects with `id()`, `get()`, `settings()`, and `to_array()`.

`MillionDollarScript\Core\Orders` provides `find($id)` and `update_metadata($id, $metadata)` for trusted administrative integrations. The public API intentionally does not allow extensions to set payment or lifecycle fields directly.

Customer-facing extensions should first establish a verified principal and then use the customer-scoped methods:

- `query_for_principal($principal, $args)` returns a bounded page with `items`, `total`, `limit`, and `offset`.
- `find_for_principal($id, $principal)` returns customer-safe detail only when the order belongs to that principal.
- `renewal_eligibility_for_principal()` and `start_renewal_for_principal()` delegate renewal rules to core.
- `start_checkout_for_principal()` creates or resumes the configured provider checkout only after ownership is proven.

A principal contains a verified WordPress `user_id`, a verified `email`, or both. When both are provided, orders linked to either identity are included so registered customers can still find guest purchases made with their account email. Customer payloads omit raw order keys and email addresses. Passing an email address from an unverified request is not authentication; extensions are responsible for proving control through a short-lived account or email-verification flow before calling these methods.

`MillionDollarScript\Core\Settings` provides `all()`, `get()`, `defaults()`, field-aware `sanitize()`, `field_classification()`, and `is_admin_visible()` methods.

`MillionDollarScript\Core\Database` provides table-name and identifier helpers for extensions that need read-oriented reports against core tables. Values still need `$wpdb->prepare()`; `ident()` is only for identifiers.

`MillionDollarScript\Core\ApiAccess` provides REST permission checks through `authorize()` and `can_manage()`.

Public WordPress hooks use the `million-dollar-script/` prefix. New extension code can subscribe with WordPress `add_action()` and `add_filter()` directly against the stable hook name. `MillionDollarScript\Core\Hooks` is available when an extension needs to emit a documented canonical hook.

`MillionDollarScript\Media\Placements::public_url($placement_id)` returns a URL only while the placement is eligible for a public individual advertiser page. `public_view($placement_id)` returns the privacy-safe view model used by the page and returns `null` for private placements. Extensions can filter that model with `million-dollar-script/advertiser/page/view-model`, slug tokens with `million-dollar-script/advertiser/page/slug/tokens`, and the default `WebPage` schema with `million-dollar-script/advertiser/page/schema`. A schema extension may use `CreativeWork` when the public content supports it; do not claim `Product` without a valid offer/review/rating model.

The default template emits safe-region actions at `million-dollar-script/advertiser/page/before`, `before-image`, `after-image`, `before-content`, `after-content`, `before-actions`, `after-actions`, and `after`. Each action receives only the sanitized public view model. Never add customer email, account identifiers, order keys, payment metadata, or other private order data to that model or public output.

Only canonical hooks are dispatched, except for selected Million Dollar Script 2 aliases that support side-by-side migration and existing integrations. New code must register only the canonical hook.

Extension-owned hooks should use `Hooks::apply()` or `Hooks::do()`. Use `Hooks::apply_compat()` or `Hooks::do_compat()` only for an explicitly documented, previously released Million Dollar Script 2 hook that still requires a compatibility window. This keeps extension aliases owned by the extension instead of adding its contract to core.

## REST API

The REST namespace is `/wp-json/million-dollar-script/v1`. API clients authenticate with `Authorization: Bearer ...` or `X-Million-Dollar-Script-API-Key`.

Extensions that declare `service_signature` register exact credential verifiers through `ApiAccess::register_service_signature_verifier($endpoint_id, $scope, $service_id, $callback, ['v1'])`. The callback receives a validated `MillionDollarScript\Core\ServiceSignatureRequest` and the WordPress REST request. Core accepts only literal `true`, safely normalizes a returned `WP_Error`, and denies missing verifiers, exceptions, and malformed results.

The extension owns secret storage, status, expiry, revocation, scope and relationship checks, constant-time signature comparison, atomic nonce replay prevention, and rate limiting. After authorization, `ApiAccess::service_identity($request)` returns the verified non-administrator service identity. Use `ApiAccess::service_signature_error()` for privacy-safe denials. See [Service-Signature Authentication](service-signatures.md) for the v1 canonical request contract.

## Browser API

Frontend grid configuration is available at `window.MillionDollarScript.grid`, and initialized grid instances are collected in `window.MillionDollarScript.gridInstances`. Admin and editor configuration under the shared namespace is private implementation state and must not be used by extensions.

Publish extension-owned browser settings through the public support facade:

```php
\MillionDollarScript\Extensions\Support::add_browser_config(
    'mds-example-frontend',
    'example',
    'frontend',
    ['restUrl' => esc_url_raw(rest_url('million-dollar-script/v1/example/items'))]
);
```

```js
const config = window.MillionDollarScript?.extensions?.example?.frontend ?? {};
```

Do not create standalone browser globals for extension configuration.

## Commerce

`MillionDollarScript\Commerce\Payments` provides active-provider lookup, checkout creation, customer management URLs, and payment-source status callbacks. Payment adapters should register through the documented provider hooks rather than calling WooCommerce or another checkout plugin from core features.

Recurring-capable payment adapters register through `million-dollar-script/payment/recurring/adapters`. An adapter declares its provider, readiness callback, capability names, and only the operations it supports: initial checkout preparation, automatic cycle collection, renewal payment-link creation, and payment-method recovery. Callers must check `recurring_ready()` and capabilities; unsupported operations return `WP_Error` instead of silently becoming automatic charges. Existing checkout requests remain one-time unless an extension adds an explicit billing context through the canonical `million-dollar-script/payment/transaction` filter.

`MillionDollarScript\Commerce\Sources` exposes provider-neutral resources that can participate in monetization workflows. Sources register through `million-dollar-script/commerce/sources`, declare one or more modes (`resource_term`, `allowance`, `feature_access`, or `recurring_contribution`), and provide only the idempotent lifecycle callbacks they support. `invoke()` returns `WP_Error` for unknown operations and emits `million-dollar-script/commerce/source/<operation>` after a successful callback. Subscription, workspace, reporting, and scheduling extensions should use this contract instead of editing another extension's tables directly.

`MillionDollarScript\Commerce\Currency` provides current/effective currency resolution, normalization, amount parsing, formatting, and provider currency-lock detection.

## Media and rendering

`MillionDollarScript\Media\OriginalImage::resolve($attachment_id)` returns verified original-image details as an array. `OriginalAttachmentResolver` provides an instance-based equivalent for integrations that use resolver objects.

`MillionDollarScript\Media\Placements` provides `update_for_principal()` and `replace_image_for_principal()` for customer self-service extensions. Both methods recheck order ownership and placement membership. Field updates reuse the core required, optional, or hidden contract for the advertiser URL and popup text fields. Hidden fields preserve existing values and ignore submitted replacements; image replacement reuses the core upload MIME, size, and dimension checks. Extensions must still protect their request handler with a nonce or a verified short-lived session before calling the facade.

Customer detail can be enriched through the `million-dollar-script/customer/order/payload` filter. The filter receives the customer-safe payload, the internal source order, and a boolean indicating whether detail was requested. Extensions should add only values the verified order owner is allowed to read. Placement editors can participate in validation and persistence through `million-dollar-script/validate/placement/submission` and `million-dollar-script/placement/saved`; when no extension-owned values were submitted, those hooks should leave existing extension data unchanged.

`MillionDollarScript\Rendering\Estimate` provides `grid()` and `quota()` calculations without requiring ImageGrid or another hosted renderer.

## Extension integration

`MillionDollarScript\Extensions\Registry::register()` registers extension metadata and an optional initialization callback. `registered()` returns registered metadata.

`MillionDollarScript\Extensions\Support` provides shared admin navigation, safe redirects, REST permissions, client IP normalization, rate limiting, and `register_rest_route()` for canonical extension routes.

`MillionDollarScript\Extensions\Admin` provides consistent field descriptions, help popovers, documentation links, and copyable shortcode controls. Use `docs_url($package_slug, $document_id)` when composing an existing action, or `docs_button($package_slug, $label, $document_id)` in an extension admin header. Both documentation helpers return an empty string when the package is inactive or the current site is not entitled to its private documentation, so render the result conditionally. Package links open the requested guide while leaving the complete Documentation navigation available. Use `shortcode_copy($shortcode, $accessible_label)` for shortcodes shown in admin interfaces; it reuses the core clipboard fallback and announces success or failure without changing layout.

`MillionDollarScript\Extensions\Licensing` is the stable entitlement facade for premium extension runtime checks. Use `has_access($extension_slug)` for a boolean gate, `access_record($extension_slug)` for the selected normalized entitlement, and `access_source()`, `access_product()`, or `access_key()` only when the extension genuinely needs those details. `access_candidates()` returns valid credentials in individual-license, extension-pack, then tester-access order for service calls that need fallback credentials.

Do not read or write Million Dollar Script license options directly. Their storage schema is private and may contain multiple individual, pack, and tester entitlements. Core resolves precedence, pack membership, revocation, and cached validation state through the licensing facade.

### Package compatibility

Extensions built for Million Dollar Script 3.0 should declare their compatibility in the main plugin file:

```text
Requires Plugins: million-dollar-script
Requires MDS: 3.0.0
Requires MDS API: 1
MDS Generation: 3
MDS Compatible: MDS 3.0
```

Use the `million-dollar-script` dependency slug and the version-neutral `MillionDollarScript` PHP namespaces. Do not use the legacy Million Dollar Script 2 plugin slug or its PHP namespaces for new extensions.

Direct-download clients must identify the installed core contract when contacting the Million Dollar Script extension service. Send `platform=wordpress`, `product_family=modern`, `core=million-dollar-script`, `core_version=<installed version>`, and `core_api_version=1` with catalog, product, checkout, license, update, download, and documentation requests. Current clients also send `mds_generation=3` as a transitional package-generation marker. Preserve the compatibility parameters when following a purchase, package, or update URL returned by the service.

`product_family` is intentionally stable across future Million Dollar Script major releases that continue to implement the same core API contract. Extension metadata can declare `minimum_core_version` and `core_api_version`; the service omits incompatible catalog entries and packages rather than offering an update that the installed core cannot run.

Requests without a generation marker are treated as Million Dollar Script 2 requests for backward compatibility. A Million Dollar Script 3.0 package, license, tester access key, or private documentation package is therefore unavailable to an unmarked or Million Dollar Script 2 client.

## Compatibility

Legacy Million Dollar Script 2 adapters remain available where required for migration. New code must use the `MillionDollarScript` public namespaces; implementation namespaces can change without backward-compatibility guarantees.

The workspace release audit and extension packager reject runtime dependencies on private implementation namespaces, legacy extension namespaces, ambiguous `MDS_*` core constants, and noncanonical browser globals. Compatibility adapters may remain in core, but extensions must not use them as new contracts.
