# Troubleshooting

Use this guide when setup, updates, extensions, checkout, rendering, or emails do not behave as expected.

## PHP Memory

Million Dollar Script requires a PHP memory limit of at least 256 MB for supported operation with checkout and extensions. Check the effective value under **Million Dollar Script > System Status** or **Tools > Site Health**. The hosting plan maximum and the value applied to an individual WordPress request can be different.

If PHP reports an allowed-memory-size error:

- Increase the effective PHP `memory_limit` to at least 256 MB through the hosting control panel or hosting support.
- Confirm the new value in System Status rather than assuming a configuration file was applied.
- Update WordPress, Million Dollar Script, the payment provider, and active extensions.
- Temporarily disable unrelated plugins in a staging copy to identify unusually expensive combinations.
- Check whether a bulk operation, report, migration, or image job is attempting to process an unbounded data set.

The filename and line in a PHP memory fatal identify the allocation that finally exceeded the limit. They do not necessarily identify the component that consumed most of the request memory. Raising the limit can restore operation, but repeated growth during one action still needs investigation.

## Extension Catalog

The WordPress.org edition does not load a remote extension catalog or install extension ZIPs from Million Dollar Script. Use **Discover extensions** to browse compatible products, install free extensions through WordPress, and upload premium packages supplied with a purchase.

For the direct-download edition, if the extension catalog does not load:

- Confirm the Extension Server URL under Settings.
- Confirm the server is reachable from WordPress.
- Confirm local development points to the local extension server when testing locally.
- Check whether the catalog is showing only extensions compatible with the installed Million Dollar Script version.
- If a custom client contacts the extension service directly, confirm it sends `product_family=modern`, `core_version=<installed version>`, `core_api_version=1`, and the transitional `mds_generation=3` marker. Preserve those parameters in purchase, package, update, and documentation requests.

## Extension Installation

If an extension install fails:

- In the WordPress.org edition, install free extensions through **Plugins > Add New** and upload premium packages through **Plugins > Add Plugin > Upload Plugin**.
- Confirm the WordPress filesystem can write to `wp-content/plugins`.
- Confirm the extension is compatible with the installed major version.
- Confirm any license is active when the extension requires a license.
- Check the WordPress debug log for ZIP, permissions, or HTTP errors.

If WordPress reports a missing extension capability, a conflicting active extension, or a provider required by another active extension, follow [Fix Extension Dependency and Activation Errors](extension-dependency-activation-errors.md). It explains the safe provider-first activation and dependent-first deactivation order.

## Updates

If an update still appears after installing:

- Refresh the WordPress Updates screen.
- Check the installed version in Plugins.
- Confirm the update server is returning the expected channel.
- Confirm no stale plugin ZIP is cached by a development server.

## Documentation

Million Dollar Script keeps remotely delivered extension documentation in a short-lived local cache. It also clears affected documentation automatically when licenses, extension-pack access, or tester access change.

If a guide appears outdated after documentation has been published, use **Million Dollar Script > Documentation > Refresh documentation**. The action clears only this WordPress site’s remote-documentation cache and then retrieves the latest guides allowed by the site’s current access. It does not change licenses, install or update extensions, edit extension-server content, or bypass entitlement checks.

Manual refreshes have a short site-wide cooldown to prevent repeated remote requests. Routine refreshing is unnecessary; use the button after a documentation release, an entitlement change, or recovery from a temporary extension-server problem.

## Checkout

If checkout fails:

- Confirm the active payment provider.
- Confirm WooCommerce and the checkout extension are active when using WooCommerce.
- Confirm the order is still payable or renewable.
- Review scheduled actions and WooCommerce order status.

## API Access

Use the HTTP status and JSON error code to distinguish a missing nonce, invalid key, insufficient scope, disabled endpoint, administrator-only policy, or rate limit. [Fix REST API Authentication Errors](rest-api-authentication-errors.md) provides the exact error mapping and safe recovery steps.

See [API Access](api-access.md) for key management, route discovery, and minimum security levels. An endpoint policy can strengthen or disable access, but it cannot be weakened below that minimum.

## Rendering

If grids load slowly or missing tiles appear:

- Confirm the renderer mode.
- Confirm generated tile files exist before public pages request them.
- If local PNG tile requests return `404` or an image workflow reports an undefined `imagecreate()` function, follow [Restore local grid tiles when PHP GD is unavailable](fatal-error-call-to-undefined-function-imagecreate.md).
- Use hosted ImageGrid rendering for very large grids or heavy processing.
- Check browser console and network requests for 404 or admin-ajax bottlenecks.

## Emails

If emails are missing, install an SMTP or email logging plugin and send a test message. Million Dollar Script uses WordPress mail directly and does not keep its own mail log.
