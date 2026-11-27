# API routes

These routes ship with `laranail/authkit`, so an API-only or Filament consumer installs the core
alone and has them — no Blade scaffolding required.

With the default Sanctum token driver, the consuming model must use Sanctum's `HasApiTokens` trait
and the `personal_access_tokens` migration must be installed; `laranail/authkit-preset`'s installer
does both if you are using it. An OAuth package can register another guard and issuer; AuthKit OAuth
adds Passport as an accepted bearer-token guard without changing the Sanctum default.
The routes are registered when `laranail.authkit.api.enabled` is true; their default prefix is
`/api/auth` and middleware is `api` plus `throttle:60,1`. The API routes do not render preset Blade views, create a browser session, run the preset CAPTCHA middleware, or replace a client application's authorization policy.

## Endpoints

| Method and path                                  | Feature            | Protection                             | Result                                                                                 |
|--------------------------------------------------|--------------------|----------------------------------------|----------------------------------------------------------------------------------------|
| `POST /api/auth/register`                        | Registration       | Guest; `throttle:10,1`                 | `201` with `status`, `data.token`, and `data.user`.                                    |
| `POST /api/auth/login`                           | Login              | Guest; `throttle:10,1`                 | `200` with token and user; enrolled TOTP accounts receive `202` with a challenge, invalid credentials return `422`, throttling and enabled account lockout return `429`. |
| `POST /api/auth/logout`                          | Logout             | configured token guards               | Revokes the current token.                                                             |
| `POST /api/auth/email/verification-notification` | Email verification | configured token guards, `throttle:6,1` | Sends a verification notification.                                                   |
| `GET /api/auth/email/verify/{id}/{hash}`         | Email verification | configured token guards, signed, `throttle:6,1` | Completes verification.                                                       |
| `POST /api/auth/forgot-password`                 | Password reset     | Guest; `throttle:10,1`                 | Sends a reset link through Laravel's password broker.                                  |
| `POST /api/auth/reset-password`                  | Password reset     | Guest; `throttle:10,1`                 | Validates the token and resets the password.                                           |
| `PUT /api/auth/user/password`                    | Password updates   | configured token guards               | Uses AuthKit's password-update action.                                                |
| `DELETE /api/auth/user` | Account deletion | configured token guards, current password, `throttle:6,1` | `204`; deletes the authenticated account and cleans up authentication data. See [account deletion](account-deletion.md). |
| `PUT /api/auth/user/profile-information`         | Profile management | configured token guards               | Uses AuthKit's profile-update action.                                                 |

### Account lockout

When enabled after migration, account lockout is enforced across login and new token issuance.
JSON login returns `429`, `status: locked`, `data.retry_after` and `Retry-After`; this differs from
request throttling. See [account lockout](account-lockout.md) for expiry and authorized clearing.

### Verified email change

The profile feature also exposes pending email request, status, verification and cancellation
endpoints. The active address stays unchanged until the new address is verified. See
[profile management](profile-management.md) for paths, payloads and frontend mail-link integration.

### Two-factor endpoints

When `laranail.authkit.two_factor.enabled` is true, these endpoints are also registered:

| Method and path | Purpose |
| --- | --- |
| `POST /api/auth/two-factor/challenge` | Exchange a login challenge token plus TOTP/recovery code for a full token. |
| `GET /api/auth/user/two-factor` | Inspect enrollment status using a bearer token. |
| `POST /api/auth/user/two-factor` | Begin enrollment after checking the current password. |
| `POST /api/auth/user/two-factor/confirm` | Confirm enrollment and return recovery codes once. |
| `POST /api/auth/user/two-factor/disable` | Disable enrollment with factor proof. |
| `POST /api/auth/user/two-factor/recovery-codes` | Replace recovery codes with factor proof. |

The challenge endpoint is throttled to five requests per minute; authenticated management endpoints
to ten. Enrollment controls route availability; a confirmed enrolled user's login still requires a
factor even if the route flag was subsequently disabled. See [Two-factor authentication](two-factor-authentication.md).

Register, login and logout are always present when the API is enabled; the rest follow the Fortify-style feature list in `laranail.authkit.fortify.features`, so removing `reset-passwords` removes the two password endpoints. `POST /register` and `POST /login` have both the API group's `throttle:60,1` and their endpoint `throttle:10,1` middleware. Authentication failures from the login action return `422`; rate-limit responses return `429`.

Send an issued token as `Authorization: Bearer <token>`. A plaintext token is returned only at registration or login; store it using the client platform's secure mechanism, never log it, and remove it when logout succeeds. Issued tokens are scoped and time-limited by default rather than wildcard and eternal: `laranail.authkit.tokens.abilities` sets the scope and `expires_after_minutes` the lifetime, and a caller may narrow either per token. A wildcard token can do anything its owner can, so a leaked one is a full account compromise rather than a bounded one.

Change the prefix with `AUTHKIT_API_PREFIX` and adjust `laranail.authkit.api.middleware` for application-wide API policy. Route names carry the `laranail-auth-api.` prefix, since route names are a flat global registry; `AUTHKIT_API_ROUTE_NAME_PREFIX` changes it. Passkey ceremonies remain browser/session flows, not API token authentication. Web URLs and rendered views are documented by `laranail/authkit-preset`.

---

[← Docs index](../README.md#documentation)
