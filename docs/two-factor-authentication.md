# Two-factor authentication

Auth Kit supports TOTP through standard `otpauth` enrollment URIs. Authenticator apps such as 1Password and Google Authenticator can scan the setup QR code or accept the displayed secret manually.

## Enable

Publish the migration and run it:

```sh
php artisan vendor:publish --tag=laranail::authkit-two-factor-migrations
php artisan migrate
```

For an API-only Auth Kit installation, set `laranail.authkit.two_factor.enabled` to `true` in the published Auth Kit config. The preset enables this core setting when `Features::twoFactorAuthentication()` is present in the preset feature list. The installer accepts `--two-factor-authentication`.

The migration adds `two_factor_method` (`none` or `totp`, default `none`), the encrypted TOTP secret and recovery-code columns, and a confirmation timestamp. Configure `laranail.authkit.two_factor.table` if the authentication model uses a table other than `users` before running the migration.

## Browser flow

The preset provides account setup at `/auth/user/two-factor` and gates password sign-in with an authenticator or recovery-code challenge for accounts whose method is `totp`. Setup secrets remain inactive until a valid code confirms enrollment. Recovery codes are shown once and each can be used once.

Apply the `two-factor` middleware to routes that must require an enabled factor and successful verification in the current browser session:

```php
Route::middleware(['auth', 'two-factor'])->group(function () {
    // Sensitive routes
});
```

## API flow

When the core setting is enabled and the account method is `totp`, `POST /api/auth/login` returns HTTP 202 with `status: mfa_required`, a short-lived `challenge_token`, and `expires_in`. Submit that token with the user's TOTP or recovery code to `POST /api/auth/two-factor/challenge`; only a successful challenge returns a Sanctum bearer token. Failed attempts are throttled, and challenges expire after five minutes by default.

Authenticated clients can inspect `GET /api/auth/user/two-factor`, start enrollment with `POST /api/auth/user/two-factor` (send the current `password`), confirm with `POST /api/auth/user/two-factor/confirm`, disable with `POST /api/auth/user/two-factor/disable`, and replace recovery codes with `POST /api/auth/user/two-factor/recovery-codes`. Enrollment confirmation returns the recovery codes once; disable and recovery-code replacement require a current TOTP or unused recovery code.

API tokens issued after the challenge carry the `two-factor:verified` ability. The `two-factor` middleware checks this exact ability (not wildcard ability matching) and confirms that TOTP remains enabled. Accounts with method `none` retain the existing password-only login response.

TOTP verification delegates to Fortify's provider, which applies its configured verification window and replay cache. Keep the application encryption key secure and stable because it protects stored TOTP and recovery secrets.
