# Service-Signature Authentication

`service_signature` is for server-to-server requests owned by an extension. It is not an API-key alias and it does not grant WordPress capabilities. Core validates the common request envelope, dispatches only an exact verifier registered by the owning extension, and accepts only a literal `true` result. An authenticated WordPress administrator may still call the route locally for administration and recovery.

If no exact verifier is registered, or a verifier throws, returns malformed data, or rejects the request, access is denied. Sites with no extension credential therefore remain fail-closed.

## Threat Model

The v1 protocol prevents a captured request from being changed or replayed within the accepted time window when the owning extension implements the required nonce store. The signature binds the credential identity, method, canonical route, timestamp, nonce, exact body bytes, and optional idempotency key.

It does not protect a secret copied from either service, a compromised WordPress administrator, a compromised signing host, or data after the request has been accepted. Keep credentials in server-side secret storage, use HTTPS, rotate exposed credentials, and retain local domain validation and moderation.

## Version 1 Headers

Send:

```text
X-MDS-Service-Id: <opaque credential ID>
X-MDS-Signature-Version: v1
X-MDS-Timestamp: <10-digit Unix timestamp in seconds>
X-MDS-Nonce: <unpadded base64url of 32 random bytes>
X-MDS-Content-SHA256: <lowercase hex SHA-256 of exact raw body>
X-MDS-Signature: <lowercase hex HMAC-SHA256>
X-Idempotency-Key: <endpoint-specific key, when used>
```

The nonce must be exactly 43 unpadded base64url characters. The service ID and idempotency key may contain ASCII letters, digits, `.`, `_`, `:`, or `-`; each must be between 8 and 128 characters when present.

Core accepts timestamps no more than 300 seconds in the past or future. Production requests require HTTPS. Plain HTTP is accepted only when both the request host and the remote peer are loopback addresses for local development.

## Canonical String

The v1 canonical string is the following eight values joined by one LF byte (`\n`), with no trailing LF:

```text
v1
<SERVICE_ID>
<UPPERCASE_METHOD>
<CANONICAL_ROUTE>
<TIMESTAMP>
<NONCE>
<BODY_SHA256>
<IDEMPOTENCY_KEY_OR_EMPTY_STRING>
```

The canonical route begins with `/million-dollar-script/v1/`. It excludes the scheme, host, `/wp-json` prefix, query string, and fragment. Clients must sign the route exactly as registered, without decoding, re-encoding, or normalizing path characters. The body hash covers the exact bytes sent on the wire; hashing a parsed or re-serialized JSON value is not equivalent.

Compare the received and expected signature with a constant-time primitive such as PHP `hash_equals()`.

## Extension Verifier Contract

Register each credential against its exact endpoint ID, scope, service ID, and version during WordPress initialization:

```php
use MillionDollarScript\Core\ApiAccess;
use MillionDollarScript\Core\ServiceSignatureRequest;

ApiAccess::register_service_signature_verifier(
    'example-placement-create',
    'example.placement.write',
    $opaque_service_id,
    static function (ServiceSignatureRequest $signature, WP_REST_Request $request) use ($secret) {
        if (!$credential_is_active || !$scope_is_allowed || $nonce_was_already_used) {
            return ApiAccess::service_signature_error('invalid');
        }

        $expected = hash_hmac('sha256', $signature->canonical_string(), $secret);
        if (!hash_equals($expected, $signature->signature())) {
            return ApiAccess::service_signature_error('invalid');
        }

        // Atomically claim the nonce for at least the accepted timestamp window.
        return $nonce_was_claimed ? true : ApiAccess::service_signature_error('invalid');
    },
    ['v1']
);
```

Only the owning extension should register its verifier. The verifier must check credential status, expiry, revocation, scope, domain-object ownership, rate limits, and replay state. It must claim an accepted nonce atomically and retain it past the timestamp window. Return `ApiAccess::service_signature_error('rate_limited', $retry_after)` for a deliberate `429`; return the generic invalid error for other authentication failures.

After authorization, an endpoint can call `ApiAccess::service_identity($request)` to read the verified service ID, endpoint ID, scope, and signature version. This identity does not change the WordPress user and does not make `current_user_can('manage_options')` true.

## Signing Pseudocode

```text
body_bytes = exact bytes to send
timestamp = current Unix time in seconds
nonce = base64url(random_bytes(32), no padding)
body_hash = lowercase_hex(sha256(body_bytes))
canonical = join_lf(
  "v1", service_id, uppercase(method), canonical_route,
  timestamp, nonce, body_hash, idempotency_key_or_empty
)
signature = lowercase_hex(hmac_sha256(secret, canonical))
send body_bytes and all v1 headers
```

Generate a new nonce for every attempt, including an idempotent retry. Reuse the idempotency key, not the nonce, when retrying the same logical operation.

## Errors and Audit Records

Invalid, unknown, expired, revoked, replayed, malformed, or tampered requests return the same generic `401` authentication error. HTTPS failures return `403`, rate limits return `429`, and a verifier may return a privacy-safe temporary `503`. Responses never include the secret, expected signature, raw body, or verifier exception.

The API audit log stores the decision, endpoint and scope, safe reason code, and keyed fingerprints for the actor, IP address, and user agent. It does not store service IDs, signatures, secrets, nonces, headers, or raw request bodies.
