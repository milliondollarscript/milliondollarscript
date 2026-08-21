# Contributing

Contributions should remain compatible with WordPress 6.0+, PHP 8.1+, and the plugin's existing public extension contracts.

## Before submitting a change

1. Keep the change focused and preserve Million Dollar Script 2 migration and compatibility behavior.
2. Use WordPress escaping, sanitization, capability checks, nonces, and safe HTTP APIs where applicable.
3. Add or update focused tests for behavior that can regress.
4. Run the repository-local checks:

```bash
composer validate --strict --no-check-publish
composer run test:rewrite
find src tests/rewrite -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php -l million-dollar-script.php
```

WordPress integration fixtures must run against a disposable test site. Do not use production customer data, license keys, API keys, or payment credentials in tests or issue reports.

## Public interfaces

Treat WordPress hooks, REST routes, block attributes, shortcodes, stored settings, migration mappings, payment-provider contracts, and extension registration APIs as compatibility surfaces. Document intentional changes and provide a migration path when a compatible implementation is possible.

Release packaging, signing, uploads, deployment credentials, and production operations are maintained separately and are not part of this repository.
