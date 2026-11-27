# laranail/authkit

[![Tests](https://github.com/laranail/authkit/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/authkit/actions/workflows/tests.yml)
[![Code style](https://github.com/laranail/authkit/actions/workflows/code-style.yml/badge.svg)](https://github.com/laranail/authkit/actions/workflows/code-style.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Headless Laravel authentication: actions, contracts, result objects and a token-based REST API.

Requires PHP `^8.4.1 || ^8.5` and Laravel `^13.0`. This package is pre-1.0; review
[feature scope](docs/feature-scope.md) before choosing the flows to enable.

## Install

`laranail/*` packages resolve through git, not Packagist. Add the repositories block to your
application's `composer.json` — Composer ignores a dependency's own `repositories`, so it must list
the whole transitive closure:

```json
"repositories": [
    {"type": "vcs", "url": "https://github.com/laranail/authkit.git"},
    {"type": "vcs", "url": "https://github.com/laranail/package-tools.git"},
    {"type": "composer", "url": "https://repo.packagist.org", "exclude": ["laranail/*"]},
    {"packagist.org": false}
]
```

Then:

```bash
composer require laranail/authkit
php artisan vendor:publish --tag=laranail::authkit-config
```

See [installation](docs/installation.md) for the full walkthrough.

## Quick start guide and usage

### Getting started

Use a model with Sanctum's `HasApiTokens` trait, publish its token migration if needed, and migrate:

```bash
php artisan vendor:publish --tag=sanctum-migrations
php artisan migrate
php artisan route:list --path=api/auth
```

See [Installation](docs/installation.md) for model setup and optional passkey/TOTP migrations.
The API defaults to `/api/auth`; the package does not ship a browser frontend.

### Usage

The core exposes low-level actions for custom pipelines. The shipped login controllers also
coordinate enrolled TOTP challenges; preserve those checks when composing your own flow.

```php
use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Contracts\AttemptEmailPasswordLoginInterface;
use Simtabi\Laranail\AuthKit\Contracts\LoginUserInterface;
use Simtabi\Laranail\AuthKit\Enums\AuthStatus;

public function store(Request $request, AttemptEmailPasswordLoginInterface $attempt, LoginUserInterface $login)
{
    $result = $attempt->execute(request: $request, guard: 'web');

    return match ($result->status) {
        AuthStatus::Passed    => tap(redirect()->intended('/dashboard'), fn () => $login->execute($result->user, 'web')),
        AuthStatus::Failed    => back()->withErrors(['email' => 'Invalid credentials.']),
        AuthStatus::Throttled => abort(429),
    };
}
```

Issue an API token instead of a session:

```php
use Simtabi\Laranail\AuthKit\Contracts\IssueTokenForUserInterface;

$token = app(IssueTokenForUserInterface::class)->execute($user, name: 'mobile');
// $token->token — the plain-text token, shown once
```

The full walkthrough is in [Getting started](docs/getting-started.md); everything else is in the [documentation index](#documentation).

## <a name="documentation"></a>Documentation

The documentation lives in this repository under [`docs/`](docs/).

### Guides

- [Installation](docs/installation.md) — requirements, the repositories block, publishing config
- [Getting started](docs/getting-started.md) — authenticate a user with the core alone
- [Configuration](docs/configuration.md) — guard, rate limits, and Fortify features
- [Architecture](docs/architecture.md) — layering, what is delegated to Fortify, the extension seams
- [Security](docs/security.md) — the guarantees this package makes and the ones it does not
- [Release](docs/release.md) — versioning, tagging, and ordering across the family

### Reference

- [Login](docs/login.md) · [Registration](docs/registration.md) · [Logout](docs/logout.md)
- [Password reset](docs/password-reset.md) · [Password updates](docs/password-updates.md)
- [Account lockout](docs/account-lockout.md) · [Account deletion](docs/account-deletion.md)
- [Profile management](docs/profile-management.md) · [Email verification](docs/email-verification.md)
- [Browser sessions](docs/browser-sessions.md) · [API routes](docs/api-routes.md) · [API tokens](docs/api-tokens.md)
- [Two-factor authentication](docs/two-factor-authentication.md)
- [Identity linking](docs/identity-linking.md)
- [Social login](https://github.com/laranail/authkit-social-login/blob/main/docs/social-login.md) · [Passkeys](docs/passkeys.md)

### Project

- [Testing](docs/testing.md) — how the suite is arranged and what it does not cover
- [Changelog](CHANGELOG.md) · [Contributing](CONTRIBUTING.md) · [Security policy](SECURITY.md)

### Operations

- [Lifecycle events](docs/events.md) — public audit payloads and Laravel listener setup

- [Environment variables](docs/environment.md) — supported names and cached configuration
- [Localization](docs/localization.md) — language files, overrides and fallback
- [Troubleshooting](docs/troubleshooting.md) — setup checks and common failures
- [Feature scope](docs/feature-scope.md) — supported flows, limitations and deferred work
- [Actions and extension points](docs/actions.md) — contracts, results and controller hooks

## Contributing & security

See [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately through the
[security policy](SECURITY.md). Participation follows the
[Laranail Code of Conduct](https://github.com/laranail/.github/blob/main/CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
