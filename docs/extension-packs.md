# Extension Packs

Extension packs provide one license for a defined set of premium Million Dollar Script extensions. Open **Million Dollar Script > Extensions** to review available packs, included extensions, pricing, and the current status of each included extension.

## Complete Extension Pack

The Complete Extension Pack includes the premium extensions shown in its pack card. Its membership comes from the extension server and may change over time. The card always shows the current included extensions before purchase or installation.

Pack access does not install every extension automatically. Install, activate, update, or remove each included extension separately so you remain in control of the features running on your site.

## Activating A Pack

1. Open **Million Dollar Script > Extensions**.
2. Find the Complete Extension Pack.
3. Select **Manage license** and enter the pack license key, or use **Claim license** after an eligible checkout.
4. Install the included extensions you need, one at a time.
5. Activate each installed extension from its extension card or the WordPress Plugins page.

The pack license can be managed independently from licenses for individual extensions.

## Access Priority

When more than one access method can authorize an extension, Million Dollar Script uses this order:

1. An active individual extension license.
2. An active extension pack that currently includes the extension.
3. Approved tester access.

This keeps an individual purchase authoritative while allowing the pack or tester access to act as a fallback.

## Changes To Pack Membership

Pack access follows the pack's current membership. If an extension is added to the pack, an active pack license can authorize it. If an extension is removed, pack-derived downloads, updates, and private documentation for that extension stop unless another valid access method is available.

Removing pack access does not uninstall an extension or delete its settings and content. The installed version remains under your control, but it will no longer receive protected packages, updates, or private documentation through that pack.

## Connection Problems

If the extension server cannot be reached during a refresh, Million Dollar Script keeps the last confirmed access state and displays a warning. A confirmed expired, refunded, cancelled, revoked, or otherwise invalid license disables only that access source. Use **Refresh access** after connectivity is restored.

## Private Documentation

Private documentation for a paid extension is available while an individual license, an eligible pack, or approved tester access authorizes that extension. Private documentation is retrieved from the extension server and is not included in premium extension ZIP files.

## Extension Developers

Premium extensions should check runtime access through `MillionDollarScript\Extensions\Licensing`. Do not inspect core license options directly: core owns the entitlement storage format and resolves individual licenses, extension packs, and tester access in the documented priority order.
