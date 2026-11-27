# Getting started

Authenticate a user with the core alone, without any UI.

## Prepare the host

Follow [Installation](installation.md) first: add the VCS repositories, configure the authenticatable
model and Sanctum token table, then inspect `php artisan route:list --path=api/auth`. The REST API
is enabled by default. Send JSON with `Accept: application/json`; its payloads and MFA responses
are in [API routes](api-routes.md).

## The shape of the API

Every capability is an action behind a contract, bound in the container. Inputs are typed, outputs
are result objects rather than booleans or nullable users — so a caller branches on a status, not
on a truthiness accident.

```php
use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Contracts\AttemptEmailPasswordLoginInterface;
use Simtabi\Laranail\AuthKit\Contracts\LoginUserInterface;
use Simtabi\Laranail\AuthKit\Enums\AuthStatus;

public function store(
    Request $request,
    AttemptEmailPasswordLoginInterface $attempt,
    LoginUserInterface $login,
) {
    $result = $attempt->execute(request: $request, guard: 'web');

    return match ($result->status) {
        AuthStatus::Passed    => tap(redirect()->intended(), fn () => $login->execute($result->user, 'web')),
        AuthStatus::Failed    => back()->withErrors(['email' => 'Invalid credentials.']),
        AuthStatus::Throttled => abort(429),
    };
}
```

Credential verification and session issuance are separate actions on purpose. A flow that needs to
check a password without logging anyone in — a re-authentication prompt, a token exchange — calls
the first and not the second.

Use these low-level actions inside a complete authentication pipeline. Calling `LoginUser` or a
token issuer directly does not run an enrolled user's TOTP challenge; the shipped login controllers
coordinate that check. Reuse those controllers for normal sign-in, or preserve all factor checks
in an application-owned flow.

## Issuing an API token instead

```php
use Simtabi\Laranail\AuthKit\Contracts\IssueTokenForUserInterface;

$token = app(IssueTokenForUserInterface::class)->execute($user, name: 'mobile');
// $token->token — the plain-text token, shown once
```

## Swapping any of it

Every contract is bound in the container, so replacing one is a binding, not a fork:

```php
$this->app->bind(
    AttemptEmailPasswordLoginInterface::class,
    MyCredentialCheck::class,
);
```

## Where to go next

- [Configuration](configuration.md) — the guard, rate limits, providers
- [Architecture](architecture.md) — why it is shaped this way, and the extension seams
- [Login](login.md), [registration](registration.md), [password reset](password-reset.md) — flow by flow

---

[← Docs index](../README.md#documentation)
