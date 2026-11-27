# Actions and extension points

## Actions

| Action                         | Purpose                                                               |
|--------------------------------|-----------------------------------------------------------------------|
| `AttemptEmailPasswordLogin`    | Verify email + password against a guard, returns `AuthResult`         |
| `LoginUser`                    | Log user into session + regenerate session                            |
| `DeleteUser` | Password-confirmed self-service deletion through `DeleteUserInterface`; cleanup hooks and model retention policy apply. |
| `LogoutUser`                   | Log out + invalidate session                                          |
| `CreateNewUser`                | Validate and create user (Fortify `CreatesNewUsers`)                  |
| `ResetUserPassword`            | Validate and reset password (Fortify `ResetsUserPasswords`)           |
| `UpdateUserProfileInformation` | Validate and update profile (Fortify `UpdatesUserProfileInformation`) |
| `UpdateUserPassword`           | Validate and update password (Fortify `UpdatesUserPasswords`)         |
| `IssueTokenForUser`            | Issue a token using the configured backend, returns `TokenResult`      |
| `CheckEmailExists`             | Check if email is registered                                          |
| `FindUserByEmail`              | Retrieve user by email                                                |

## Result types

**`AuthResult`** — returned by login actions:

```php
use Simtabi\Laranail\AuthKit\Support\AuthResult;

AuthResult::passed($user);   // credentials valid
AuthResult::failed();        // credentials invalid
AuthResult::throttled($seconds);  // rate limited
```

Check with `$result->isPassed()` or match on `$result->status` (`AuthStatus::Passed|Failed|Throttled`).

**`TokenResult`** — returned by `IssueTokenForUser`:

```php
use Simtabi\Laranail\AuthKit\Support\TokenResult;

new TokenResult(user: $user, token: $token);
```

## Container contracts

Depend on the matching `Simtabi\Laranail\AuthKit\Contracts` interfaces for credential checks,
session login/logout and token issuance. Fortify actions implement the corresponding
`Laravel\Fortify\Contracts` interfaces. Replace a binding in an application service provider and
retain its validation, guard and factor requirements. See [Getting started](getting-started.md).

Direct credential checks and token issuance do not constitute a complete two-factor login flow;
use the shipped login/challenge controllers or explicitly preserve their factor checks.

## Abstract controllers

Extend these to wire up your own routes. JSON responses are handled automatically.

| Controller                                    | Overridable methods                   |
|-----------------------------------------------|---------------------------------------|
| `AbstractAttemptEmailPasswordLoginController` | `passed()`, `failed()`, `throttled()` |
| `AbstractCheckEmailExistsController`          | `respond()`                           |
| `AbstractLogoutController`                    | `loggedOut()`                         |
| `AbstractRegisterController`                  | `registered()`                        |

---

[← Docs index](../README.md#documentation)
