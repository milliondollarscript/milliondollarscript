<?php
/**
 * Immutable service-signature request context.
 *
 * @package MillionDollarScript
 */

namespace MillionDollarScript\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class ServiceSignatureRequest {

    private array $values;

    /**
     * Core constructs this value after validating the common v1 envelope.
     *
     * @param array $values Validated service-signature values.
     */
    public function __construct(array $values) {
        $this->values = $values;
    }

    public function endpoint_id(): string {
        return (string) ($this->values['endpoint_id'] ?? '');
    }

    public function scope(): string {
        return (string) ($this->values['scope'] ?? '');
    }

    public function service_id(): string {
        return (string) ($this->values['service_id'] ?? '');
    }

    public function version(): string {
        return (string) ($this->values['version'] ?? '');
    }

    public function method(): string {
        return (string) ($this->values['method'] ?? '');
    }

    public function route(): string {
        return (string) ($this->values['route'] ?? '');
    }

    public function timestamp(): int {
        return (int) ($this->values['timestamp'] ?? 0);
    }

    public function nonce(): string {
        return (string) ($this->values['nonce'] ?? '');
    }

    public function body_sha256(): string {
        return (string) ($this->values['body_sha256'] ?? '');
    }

    public function signature(): string {
        return (string) ($this->values['signature'] ?? '');
    }

    public function idempotency_key(): string {
        return (string) ($this->values['idempotency_key'] ?? '');
    }

    public function canonical_string(): string {
        return implode("\n", [
            $this->version(),
            $this->service_id(),
            $this->method(),
            $this->route(),
            (string) $this->timestamp(),
            $this->nonce(),
            $this->body_sha256(),
            $this->idempotency_key(),
        ]);
    }
}
