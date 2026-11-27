# Installation

Install the headless core to use its REST API and authentication actions. Add the Blade preset only
when you want its browser UI.

## Requirements

- PHP `^8.4.1 || ^8.5`, Laravel `^13.0`.
- Fortify `^1.38` and Sanctum `^4.0`, installed through Composer.
- An Eloquent authenticatable model and its users table, owned by the application.

## Point Composer at the repositories

Add these entries to the application's `composer.json`, merging with existing entries. Composer
ignores a dependency's repositories. AuthKit needs only `laranail/package-tools` from the Laranail
family; console, captcha and database tools are not core requirements.

```json
"repositories": [
    {"type": "vcs", "url": "https://github.com/laranail/authkit.git"},
    {"type": "vcs", "url": "https://github.com/laranail/package-tools.git"},
    {"type": "composer", "url": "https://repo.packagist.org", "exclude": ["laranail/*"]},
    {"packagist.org": false}
]
```

```bash
composer require laranail/authkit
php artisan vendor:publish --tag=laranail::authkit-config
```

Laravel discovers `AuthKitServiceProvider` automatically. The config is published to
`config/laranail/authkit.php` and read under `laranail.authkit`.

## Prepare API tokens

With the default Sanctum issuer, add `Laravel\Sanctum\HasApiTokens` to the authenticatable model.
Publish Sanctum's migration if the application does not already have `personal_access_tokens`:

```bash
php artisan vendor:publish --tag=sanctum-migrations
php artisan migrate
```

Do not add a second migration for an existing token table. The preset installer performs this setup
when run with `--api`; core-only applications own it. See [API tokens](api-tokens.md).

## Optional authentication features

Publish only the migrations for the features you enable:

```bash
php artisan vendor:publish --tag=laranail::authkit-passkey-migrations
php artisan vendor:publish --tag=laranail::authkit-two-factor-migrations
php artisan migrate
```

Configure the model and browser client in [Passkeys](passkeys.md), or enable and configure
[Two-factor authentication](two-factor-authentication.md). Publishing a migration alone does not
configure either feature. Password reset and verification also need working mail delivery.

## Verify

```bash
php artisan config:clear
php artisan route:list --path=api/auth
```

The API is enabled by default. Its prefix and middleware are configurable; use the configured
prefix in `route:list`. Follow [Getting started](getting-started.md) to register, sign in and use a
bearer token. For browser pages install [authkit-preset](https://github.com/laranail/authkit-preset);
for provider login install [authkit-social-login](https://github.com/laranail/authkit-social-login).

---

[← Docs index](../README.md#documentation)
