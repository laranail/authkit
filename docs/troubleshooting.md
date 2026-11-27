# Troubleshooting

## Composer cannot find the package

Merge the full VCS block from [Installation](installation.md) into the application's
`composer.json`, including AuthKit itself. Dependency-level repositories are ignored. Authenticate
Composer to GitHub when a dependency is private. Do not paste credentials into an issue.

## API login or registration fails

Inspect `php artisan route:list --path=api/auth`, substituting a custom prefix. Check
`AUTHKIT_API_ENABLED`, the guard's provider/model, Sanctum's `HasApiTokens` trait and the token
migration. Send `Accept: application/json`. Validation errors are `422`, throttles are `429`, and
enrolled TOTP accounts return `202` until the challenge is completed. See [API routes](api-routes.md).

## Missing migrations or factor setup

Publish only the migrations your chosen features need, then migrate. Configure the TOTP table
before migrating a non-`users` authenticatable model. Preserve the application key; changing it
makes encrypted factor secrets unreadable. See [Two-factor authentication](two-factor-authentication.md).

## Recovery code is rejected

Send the whole unused alphanumeric code, including its separator. It is 21 characters, not a
numeric TOTP. A consumed code cannot be reused. Check published client-side validators as well as
the installed backend version.

## Mail or signed links fail

Check the application's mail transport, `APP_URL`, HTTPS/proxy configuration and signed-link route.
The core has no verification-notice Blade page. Use the preset or application-owned browser routes
for email links that users open without a bearer-token client.

## Passkey origin mismatch

Align the HTTPS origin, relying-party ID and allowed origins. Core passkeys are session/browser
flows; a Sanctum token does not replace the browser ceremony. See [Passkeys](passkeys.md).

## Changed configuration has no effect

Clear cached configuration and inspect the namespaced `laranail.authkit` settings. When the preset
is installed, it configures the core's feature list and redirect resolver. Check the preset's
configuration as well as the core's. See [Configuration](configuration.md).

---

[← Docs index](../README.md#documentation)
