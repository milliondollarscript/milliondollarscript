# API Access

Use **Million Dollar Script > API Access** to create scoped credentials for trusted apps, automations, and development tools.

These credentials authenticate requests to this WordPress site. They do not activate extension licenses or tester access. A tester access key supplied by the extension server administrator is connected from **Million Dollar Script > Extensions** instead.

## Create a Key

1. Enter a name that identifies the client or integration.
2. Select only the access scopes that client needs.
3. Set an hourly request limit.
4. Choose **Create API key**.
5. Store the displayed key in the client immediately. The complete key is shown only once.

Read-only grid and extension access is selected by default. Write scopes can create or change site data and should be enabled only for clients that require them.

Active extensions can register additional scopes. Open **Extension scopes** to review those choices. Use **Advanced or extension scopes** only when an integration provides a scope that is not listed. Wildcards such as `core.*` grant broad current and future access and are not recommended for routine integrations.

## Authenticate Requests

Send the key in either supported request header:

```http
Authorization: Bearer milliondollarscript_your_key
```

```http
X-Million-Dollar-Script-API-Key: milliondollarscript_your_key
```

## Rotate or Revoke

Rotate a key when replacing credentials for an existing client. Update the client immediately because the old key stops working.

Revoke a key when a client no longer needs access or the credential may have been exposed. Revocation takes effect immediately.

## Endpoint Policies

Endpoint policies can require stronger authentication than their default minimum or disable an endpoint. A policy cannot weaken the security level required by the endpoint.

The recent audit log records allowed and denied API requests without storing complete API keys or raw visitor IP addresses.

## Extension Discovery

Active extensions can add routes to Million Dollar Script discovery and its OpenAPI document. Each discovered route includes a stable endpoint ID, complete `/million-dollar-script/v1` route, supported HTTP methods, scope, minimum security level, and description. Invalid package-shaped or incomplete entries are omitted rather than receiving a permissive default.

Security levels describe the runtime authorization boundary:

- **Public read** exposes only fields intended for anonymous display.
- **Browser write with REST nonce** requires a valid WordPress REST nonce.
- **API key read/write** requires a scoped Million Dollar Script API key or bearer credential.
- **Signed manage token** is verified by the endpoint that owns the private, resource-specific manage token; core otherwise permits only an administrator.
- **WordPress administrator** requires the relevant WordPress capability.
- **Service signature** requires an exact trusted service verifier registered by the owning extension, or an authenticated WordPress administrator. It is not satisfied by an API key, service ID, label, URL, or header alone.

The OpenAPI document is available through `/wp-json/million-dollar-script/v1/extensions/openapi` to clients with the extension-read scope. Authentication requirements shown there are derived from the same normalized manifest used by endpoint policies.

See [Service-Signature Authentication](service-signatures.md) for the v1 headers, canonical string, verifier registration API, replay requirements, signing fixture, errors, and threat model.
