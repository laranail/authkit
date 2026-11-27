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

---

[← Docs index](../README.md#documentation)
