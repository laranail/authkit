# Configuration

Publish defaults to `config/laranail/authkit.php`:

```bash
php artisan vendor:publish --tag=laranail::authkit-config
```

All settings below are relative to `laranail.authkit`. Keep the full nested block when changing it:
Laravel's `mergeConfigFrom` merge is shallow.

## Identity and authentication

| Key | Default | Purpose |
| --- | --- | --- |
| `guard` | `web` | Session guard used by credential and Fortify flows. |
| `user_model` | `null` | Optional explicit authenticatable model; otherwise resolved from the configured auth provider. |
| `login.field` | `email` | Input field containing the login identifier. |
| `login.identifiers` | `['email']` | User columns checked in order; add application-owned `username` when needed. |
| `email_change.expires_after_minutes` | `60` | Pending email-change proof lifetime in minutes. |
| `email_change.verification_url` | `null` | Optional frontend URL containing `{token}`; defaults to the authenticated API verification URL. See [profile management](profile-management.md). |
| `identity_linking.intent_ttl_minutes` | `10` | Lifetime of a single-use link attempt. |
| `rate_limit.max_attempts` | `5` | Credential failures allowed per throttle key. |
| `rate_limit.decay_minutes` | `1` | Credential-throttle window. This is not account lockout. |

See [Login](login.md) and [Identity linking](identity-linking.md) for validation and session rules.

## Fortify and factors

| Key | Default | Purpose |
| --- | --- | --- |
| `fortify.views` | `false` | Fortify view registration. The core supplies no Blade pages. |
| `fortify.features` | reset, profile, password, email verification, passkeys, account deletion | Exact feature names below. |
| `two_factor.enabled` | `false` | Exposes the TOTP enrollment/challenge API routes. Enrolled accounts still require a factor at login. |
| `two_factor.table` | `users` | Table targeted by the published TOTP migration. |
| `two_factor.challenge_expiration_minutes` | `5` | Login-challenge lifetime. |

The default feature identifiers are `reset-passwords`, `update-profile-information`,
`update-passwords`, `email-verification`, `passkeys` and `delete-account`. Remove an identifier to disable its gated
routes, and remove application UI that calls them. `social-login` is the shared browser capability
identifier used by compatible social-login releases; check that package's installed version and
[configuration](https://github.com/laranail/authkit-social-login/blob/main/docs/configuration.md).

## Tokens and API routes

| Key | Default | Purpose |
| --- | --- | --- |
| `tokens.driver` | `sanctum` | Registered issuer used for new tokens. |
| `tokens.abilities` | `user:read`, `user:update-profile`, `user:update-password` | Default token abilities. |
| `tokens.expires_after_minutes` | `43200` | Token lifetime; `null` in PHP config delegates to the backend setting. |
| `api.enabled` | `true` | Register the core REST API. |
| `api.prefix` | `api/auth` | API URI prefix. |
| `api.name_prefix` | `laranail-auth-api.` | API route-name prefix. |
| `api.middleware` | `['api', 'throttle:60,1']` | Group middleware. Individual routes add further throttles. |
| `api.token_guards` | `['sanctum']` | Accepted token guards; enabled OAuth integration adds its Passport guard. |

Abilities do not replace application authorization. See [API routes](api-routes.md) and
[API tokens](api-tokens.md) for payloads, two-factor responses and issuance/revocation.

## Turnstile

`turnstile.enabled` defaults to `false`. `turnstile.site_key` and `secret_key` read
`TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY`. `turnstile.url` defaults to Cloudflare's siteverify
endpoint and `turnstile.input` to `cf-turnstile-response`. Core Turnstile rules apply to web
middleware routes. Preset CAPTCHA configuration belongs to the preset and `laranail/captcha`.

## Environment and cached configuration

The full mapping is in [Environment variables](environment.md). After editing configuration:

```bash
php artisan config:clear
php artisan route:list
```

Rebuild the application's configuration cache during deployment after verification.

---

[← Docs index](../README.md#documentation)
