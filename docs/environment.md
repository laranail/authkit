# Environment variables

These names are read by this package's shipped configuration. Set deployment values in the host
application's environment; do not edit files under `vendor/`. Defaults and corresponding config
keys are explained in [Configuration](configuration.md).

| Variable |
| --- |
| `AUTHKIT_GUARD` |
| `AUTHKIT_USER_MODEL` |
| `AUTHKIT_LOGIN_FIELD` |
| `AUTHKIT_LINK_INTENT_TTL_MINUTES` |
| `AUTHKIT_RATE_LIMIT_MAX_ATTEMPTS` |
| `AUTHKIT_RATE_LIMIT_DECAY_MINUTES` |
| `AUTHKIT_TURNSTILE_ENABLED` |
| `TURNSTILE_SITE_KEY` |
| `TURNSTILE_SECRET_KEY` |
| `TURNSTILE_URL` |
| `AUTHKIT_TWO_FACTOR_ENABLED` |
| `AUTHKIT_TWO_FACTOR_TABLE` |
| `AUTHKIT_TWO_FACTOR_CHALLENGE_EXPIRATION_MINUTES` |
| `AUTHKIT_TOKEN_DRIVER` |
| `AUTHKIT_TOKEN_EXPIRES_AFTER_MINUTES` |
| `AUTHKIT_API_ENABLED` |
| `AUTHKIT_API_PREFIX` |
| `AUTHKIT_API_ROUTE_NAME_PREFIX` |

## Apply changes

```bash
php artisan config:clear
php artisan route:list
```

Check the configured route prefix and guard, then rebuild the configuration cache as part of your
deployment. Never commit filled provider-secret or signing-key variables. A variable mentioned by
a different package does not configure this package unless explicitly mapped here.

---

[← Docs index](../README.md#documentation)
