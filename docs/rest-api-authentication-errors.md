# Fix REST API Authentication Errors

Million Dollar Script REST endpoints enforce the security policy shown under **Million Dollar Script > API Access**. Start with the error code and HTTP status in the JSON response, then use the matching authentication method. Do not make an endpoint public simply to work around a failed request.

These errors stop the protected operation before it runs. Before changing a key or policy, record the route, HTTP method, status, error code, and current effective policy. Ensure an administrator can update the integration immediately if a key must be rotated.

This guide applies to the Million Dollar Script 3.0 REST API. The `mds3_` prefix remains part of its technical error codes; Million Dollar Script 2 routes do not use this authentication contract.

## Identify the Error

| Status | Error code | What it means |
|---|---|---|
| `401` | `mds3_api_nonce_required` | A browser write requires a valid WordPress REST nonce. |
| `401` | `mds3_api_auth_required` | The request did not satisfy the endpoint's effective authentication policy. |
| `401` | `mds3_api_key_missing` | An API key was required but not supplied. |
| `401` | `mds3_api_key_invalid` | The key is malformed, unknown, or revoked. |
| `403` | `mds3_api_endpoint_disabled` | An administrator disabled the endpoint through API Access policy. |
| `403` | `mds3_api_policy_requires_admin` | The endpoint requires an authenticated WordPress administrator. |
| `403` | `mds3_api_key_scope_denied` | The key is valid but lacks the required scope. |
| `429` | `mds3_api_key_rate_limited` | The key exceeded its configured hourly request limit. |

The response message explains the immediate failure. The endpoint's discovery record and OpenAPI definition identify its required scope and minimum security level.

## Use the Required Authentication Method

### Scoped API key

Server-side integrations can send either supported header:

```http
Authorization: Bearer milliondollarscript_your_key
```

```http
X-Million-Dollar-Script-API-Key: milliondollarscript_your_key
```

Create or rotate keys under **Million Dollar Script > API Access**. Grant only the scopes the integration needs. Obsolete pre-release API-key headers are not supported.

Keep keys in server-side secret storage. Never place them in browser JavaScript, URLs, analytics, screenshots, public repositories, or unsanitized support logs.

### WordPress REST nonce

Browser writes governed by the `public_write_nonce` policy require a logged-in WordPress session and a current REST nonce in this header:

```http
X-WP-Nonce: your_wordpress_rest_nonce
```

Generate the nonce for the current user through WordPress. A nonce from another user, an expired page, or a different WordPress site will fail. Refresh the page before retrying if the browser has been open for a long time.

### WordPress administrator

Administrator-only routes require an authenticated WordPress administrator session with the necessary capability. An API key cannot replace that session, and the endpoint policy cannot be weakened below its declared minimum.

## Resolve Common Failures

### The key is missing or invalid

1. Confirm the request reaches the intended WordPress site and REST route.
2. Confirm exactly one supported authentication header is sent.
3. Check that a proxy or web server is not stripping the `Authorization` header.
4. Rotate the key if its complete value is unavailable or may have been exposed.
5. Update the integration immediately because rotation revokes the previous key.

### The key lacks a scope

Compare the route's required scope with the key under **Million Dollar Script > API Access**. Add only the required scope, or create a separate least-privilege key for that integration. Avoid broad wildcard scopes for routine clients.

### The endpoint is disabled or requires an administrator

Review its effective policy under **Million Dollar Script > API Access**. Re-enable a disabled endpoint only when the integration is expected and its minimum security boundary remains appropriate. Use an administrator session for administrator-only operations.

### The key is rate limited

Wait for the current limit window to clear, reduce unnecessary requests, and cache safe reads where appropriate. Raise the key's hourly limit only after confirming the request volume is expected.

## Verify the Fix

Retry one request and confirm that it returns the expected success status. If it still fails, record the route, method, HTTP status, error code, required scope, effective endpoint policy, and a timestamp. Remove credentials, cookies, nonces, personal data, and private payload fields before sharing logs.

If a policy or scope change does not resolve the request, restore the previous stronger policy, remove any temporary scope, and revoke any temporary key. A rotated or revoked key cannot be restored; create a new least-privilege key and update the client instead of reusing an exposed credential.

See [API Access](api-access.md) for key management and endpoint security levels, or return to [Troubleshooting](troubleshooting.md) for other issues.
