# Testing

From the package directory, run:

```bash
XDEBUG_MODE=off vendor/bin/pest --colors=never --no-coverage
composer pint
```

The feature suite covers credentials, registration, logout, resets, profile and password updates, passkeys, tokens, and Turnstile behavior. Social identity handling and provider callbacks are tested by [`laranail/authkit-social-login`](https://github.com/laranail/authkit-social-login). In the consuming application, test application-owned routes, response overrides, middleware, mail delivery, and the configured guard.

## CI and local databases

Local tests use SQLite. GitHub Actions also checks MySQL, MariaDB and PostgreSQL on PHP 8.5,
alongside PHP/dependency combinations. Coverage is disabled and server-database suites run
sequentially. No local database server installation is required. `composer test` currently enables
parallel mode; use the explicit command above to reproduce the CI invocation.

## Package guard coverage

The normal sequential command collects `tests/Architecture` as well as feature tests:

- `AssertsNoFacadeCalls` scans all package classes with a file-count floor. Providers are the
  composition root. Retained static/framework bridges have a reason beside the exact call;
  there is no whole-file allowlist. New hidden service dependencies fail the scan.
- `AssertsDriverContract` rejects bare package config reads in source and routes. Driver tests
  resolve the actual configured guards, issuers, providers or frontend stacks; these checks do
  not replace a manager with a mock.
- Published-config tests use application files and the package's actual load path, not a config
  value planted by the harness. Core, preset and OAuth use Laravel's config bootstrap; social
  exercises the package-tools published override bridge.
- `AssertsNoStateLeak` runs two distinct request identities/mounts in one container, retaining
  singletons and statics while flushing scoped instances. The first marker must be observed and
  cannot leak into the second request.
- The credential guard still scans the full test tree, with runtime-generated secrets.

For a focused run:

```bash
XDEBUG_MODE=off vendor/bin/pest --colors=never --no-coverage tests/Architecture tests/Feature/DriverContractTest.php tests/Feature/RequestStateIsolationTest.php tests/Feature/PublishedConfigOverrideTest.php
```

Resolve actions, controllers and rules through Laravel's container so their constructor contracts
are supplied. Manual construction and subclasses with their own constructors must supply or
forward those dependencies. Public execution methods and static compatibility APIs keep their
existing signatures.

---

[← Docs index](../README.md#documentation)
