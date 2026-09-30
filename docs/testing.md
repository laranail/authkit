# Testing

From the package directory, run:

```bash
composer test
composer lint
```

The feature suite covers credentials, registration, logout, resets, profile and password updates, passkeys, tokens, and Turnstile behavior. Social identity handling and provider callbacks are tested by [`laranail/authkit-social-login`](https://github.com/laranail/authkit-social-login). In the consuming application, test application-owned routes, response overrides, middleware, mail delivery, and the configured guard.

---

[← Docs index](../README.md#documentation)
