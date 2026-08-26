# Getting started

Authenticate a user with the core alone, without any UI.

## The shape of the API

Every capability is an action behind a contract, bound in the container. Inputs are typed, outputs
are result objects rather than booleans or nullable users — so a caller branches on a status, not
on a truthiness accident.

```php
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
