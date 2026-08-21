# Fix Extension Dependency and Activation Errors

Million Dollar Script prevents an extension from activating when a required capability is unavailable or a conflicting capability is already active. It also prevents a provider from being deactivated while another active extension depends on it. These checks protect working site features from being left in an incomplete state.

The blocked activation or deactivation normally leaves the current active extension set unchanged. Before changing that set, record the error and package versions, back up the site, and reproduce the change on staging. Review package-specific export or migration guidance when replacing a provider.

These error codes apply to Million Dollar Script 3.0 extensions that declare capability metadata. Million Dollar Script 2 plugins can use different dependency and activation behavior.

## Identify the Error

| Error code | Message | What it means |
|---|---|---|
| `mds3_extension_missing_dependencies` | `Missing required extension capability: ...` | No active core feature or extension provides one or more required capabilities. |
| `mds3_extension_conflict` | `Conflicting active extension: ...` | An active extension provides a capability that the new extension declares as incompatible. |
| `mds3_extension_required_by_active_extension` | `This extension is required by active extension(s): ...` | One or more active extensions still depend on the extension being deactivated. |

A capability name identifies a feature contract, not necessarily a plugin slug. For example, the bundled Classic Pixel Grid provides the `inventory.grid` capability when it is enabled.

## Fix a Missing Capability

1. Record every capability named in the error.
2. Update Million Dollar Script and the related extensions so their compatibility metadata is current.
3. Open **Million Dollar Script > Extensions** and identify the core feature or extension that provides the capability.
4. Install and activate that provider first.
5. Activate the dependent extension again.

If an extension requires `inventory.grid`, enable Classic Pixel Grid or another compatible grid provider before activating it. Do not enable a provider that does not match the installed Million Dollar Script generation and version.

## Resolve a Conflict

Review the conflicting extension and decide which capability the site should use. On staging:

1. Back up the site.
2. Deactivate the conflicting extension.
3. Activate and configure the replacement.
4. Test its public pages, administration screens, payments, scheduled work, and integrations as applicable.

Do not run two providers that explicitly declare the same combination incompatible. If both are required for the intended workflow, contact the extension publishers with the exact capability and package versions.

## Deactivate in Dependency Order

When deactivation is blocked, note the active extensions listed in the message. Deactivate those dependents first, then deactivate or replace their provider. Re-enable the required provider before reactivating the dependents.

Check for data-export or migration instructions before removing an extension. Deactivation and deletion are different operations; deleting a package can remove files or trigger package-specific cleanup.

## Avoid Forced Workarounds

Do not bypass the guard by editing plugin headers, changing extension records directly in the database, renaming plugin directories, or deleting an active provider. Those actions can leave routes, checkout flows, rendering, or saved settings without the capability they expect.

Reproduce dependency changes on staging before applying them to a live site. If the error remains, collect the exact message, capability name, WordPress and PHP versions, Million Dollar Script version, and the name and version of each involved extension.

If the replacement does not work, deactivate its dependents first, deactivate the replacement, restore the original provider, and then reactivate its dependents. Restore a backup only when an extension changed or removed data and its own recovery guidance requires it.

Developers can review the [Extension API](extension-api.md) for supported extension contracts. For other issues, return to [Troubleshooting](troubleshooting.md).
