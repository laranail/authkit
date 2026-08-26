# laranail/authkit

Headless authentication for Laravel 13+. No views, routes, or controllers.

> [!WARNING]
> This package is still in development. Breaking changes are imminent; use it in production at your own risk.

- **Fortify-backed** — password reset, profile updates, password updates, email verification, passkeys, login throttling
- **Sanctum-ready** — API token issuance via `IssueTokenForUser`
- **Social login** — Google, Facebook, X, LinkedIn, PayPal via Socialite
- **Composable** — separate actions for credential check vs session login

## Requirements

PHP 8.4+ / Laravel 13.x

## Installation

```bash
composer require laranail/authkit
php artisan vendor:publish --tag=laranail::authkit-config
```

For a ready-made Blade UI, install `laranail/authkit-preset` instead.

## Documentation

- [Registration](docs/registration.md)
- [Login](docs/login.md)
- [Password reset](docs/password-reset.md)
- [Profile management](docs/profile-management.md)
- [Password updates](docs/password-updates.md)
- [Email verification](docs/email-verification.md)
- [Social login](docs/social-login.md)
- [Passkeys](docs/passkeys.md)
- [API tokens](docs/api-tokens.md)
- [Configuration](docs/configuration.md)
- [Security](docs/security.md)
- [Testing](docs/testing.md)

## Configuration

`.env`:

```env
AUTHKIT_GUARD=web
AUTHKIT_RATE_LIMIT_MAX_ATTEMPTS=5
AUTHKIT_RATE_LIMIT_DECAY_MINUTES=1

AUTHKIT_GOOGLE_CLIENT_ID=
AUTHKIT_GOOGLE_CLIENT_SECRET=
AUTHKIT_GOOGLE_REDIRECT=${APP_URL}/auth/google/callback
```

`config/laranail/authkit.php`:

```php
return [
    'guard' => env('AUTHKIT_GUARD', 'web'),

    'rate_limit' => [
        'max_attempts'  => (int) env('AUTHKIT_RATE_LIMIT_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AUTHKIT_RATE_LIMIT_DECAY_MINUTES', 1),
    ],

    'fortify' => [
        'views'    => false,
        'features' => ['reset-passwords', 'update-profile-information', 'update-passwords', 'email-verification', 'passkeys'],
    ],

    'social' => [
        'google' => [
            'client_id'     => env('AUTHKIT_GOOGLE_CLIENT_ID'),
            'client_secret' => env('AUTHKIT_GOOGLE_CLIENT_SECRET'),
            'redirect'      => env('AUTHKIT_GOOGLE_REDIRECT'),
            'scopes'        => ['openid', 'profile', 'email'],
        ],
        // facebook, twitter, linkedin, paypal ...
    ],
];
```

Remove `passkeys` from `laranail.authkit.fortify.features` to disable Fortify's passkey routes. Auth Kit only enables and configures Fortify; passkey ceremonies, responses, and persistence remain provided by Fortify and `laravel/passkeys`.

### Security defaults

- Two-factor authentication is not enabled by default. MFA is still work in progress.
- Social sign-in only provisions or auto-links accounts when Google, LinkedIn, or PayPal supplies a trusted `email_verified` claim. Facebook and X identities can still be linked by an authenticated user, but cannot establish trust through an email-verification claim.
- Before production, configure HTTPS, secure session cookies, a working mail transport, and Turnstile keys when bot protection is enabled.
- PayPal uses sandbox mode by default. Set `AUTHKIT_PAYPAL_SANDBOX_MODE=false` with production PayPal credentials before enabling it in production.

## Passkeys

Passkey support uses Fortify's native integration with `laravel/passkeys`. It is stateful and requires the consuming application's authenticatable model to implement Fortify's `PasskeyUser` contract and use Auth Kit's morph-aware `PasskeyAuthenticatable` trait:

```php
use Laravel\Fortify\Contracts\PasskeyUser;
use Simtabi\Laranail\AuthKit\PasskeyAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use PasskeyAuthenticatable;
}
```

The published migration stores ownership in `passkeyable_type` and `passkeyable_id`, so the same passkey implementation can be used by users, admins, or another authenticatable model. Auth Kit configures its morph-aware `Passkey` model for Fortify and retains the vendor package's WebAuthn actions, controllers, responses, and credential validation.

Publish Auth Kit's passkeys migration in the consuming application and run it:

```bash
php artisan vendor:publish --tag=laranail::authkit-passkey-migrations
php artisan migrate
```

Do not publish Fortify's migration tag for passkeys; Auth Kit owns this table migration while Fortify and `laravel/passkeys` provide the model and WebAuthn behavior.

Configure the relying party and allowed browser origins in `config/fortify.php`. The defaults use the host and URL from `APP_URL`; set them explicitly for production HTTPS domains when necessary:

```php
'passkeys' => [
    'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),
    'allowed_origins'   => [config('app.url')],
    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
    'timeout' => 60000,
],
```

Fortify's passkey management routes honor the `fortify-options.confirmPassword` setting and the `passkeys` rate limiter in `fortify.limiters`. Passkey login and confirmation require the configured stateful guard and session, so Auth Kit does not expose equivalent Sanctum API endpoints.

The browser ceremony is application-owned. Install the official client and connect it to Fortify's canonical route names; Auth Kit does not bundle JavaScript or a build pipeline:

```bash
npm install @laravel/passkeys
```

The application client should use Fortify's `/passkeys/login/options`, `/passkeys/login`, `/passkeys/confirm/options`, `/passkeys/confirm`, `/user/passkeys/options`, `/user/passkeys`, and `/user/passkeys/{passkey}` endpoints. Keep the browser origin, relying-party ID, and `APP_URL` aligned or WebAuthn validation will fail.

## Actions

| Action                         | Purpose                                                               |
|--------------------------------|-----------------------------------------------------------------------|
| `AttemptEmailPasswordLogin`    | Verify email + password against a guard, returns `AuthResult`         |
| `LoginUser`                    | Log user into session + regenerate session                            |
| `LogoutUser`                   | Log out + invalidate session                                          |
| `CreateNewUser`                | Validate and create user (Fortify `CreatesNewUsers`)                  |
| `ResetUserPassword`            | Validate and reset password (Fortify `ResetsUserPasswords`)           |
| `UpdateUserProfileInformation` | Validate and update profile (Fortify `UpdatesUserProfileInformation`) |
| `UpdateUserPassword`           | Validate and update password (Fortify `UpdatesUserPasswords`)         |
| `IssueTokenForUser`            | Issue Sanctum personal access token, returns `TokenResult`            |
| `CheckEmailExists`             | Check if email is registered                                          |
| `FindUserByEmail`              | Retrieve user by email                                                |
| `ResolveSocialIdentity`        | Safe social identity → user resolution (verified-email check)         |
| `SocialRedirectAction`         | Generate OAuth redirect URL, returns `SocialRedirectResult`           |
| `SocialCallbackAction`         | Handle OAuth callback via `ResolveSocialIdentity`                     |
| `CreateSocialAccountAction`    | Create social account record via polymorphic relation                 |

## Result types

**`AuthResult`** — returned by login actions:

```php
AuthResult::passed($user)   // credentials valid
AuthResult::failed()        // credentials invalid
AuthResult::throttled($seconds)  // rate limited
```

Check with `$result->isPassed()` or match on `$result->status` (`AuthStatus::Passed|Failed|Throttled`).

**`TokenResult`** — returned by `IssueTokenForUser`:

```php
new TokenResult(user: $user, token: $token)
```

**`SocialRedirectResult`** — returned by `SocialRedirectAction`:

```php
new SocialRedirectResult(url: $url)
```

## Abstract controllers

Extend these to wire up your own routes. JSON responses are handled automatically.

| Controller                                    | Overridable methods                   |
|-----------------------------------------------|---------------------------------------|
| `AbstractAttemptEmailPasswordLoginController` | `passed()`, `failed()`, `throttled()` |
| `AbstractCheckEmailExistsController`          | `respond()`                           |
| `AbstractLogoutController`                    | `loggedOut()`                         |
| `AbstractRegisterController`                  | `registered()`                        |
| `AbstractSocialRedirectController`            | `redirect()`                          |
| `AbstractSocialCallbackController`            | `passed()`, `failed()`                |

## Social login

`ResolveSocialIdentity` implements verified-email linking to prevent account takeover:

1. Existing social account → returns user (updates tokens)
2. Authenticated user → links social account
3. Unverified email match → **returns null** (prevents takeover)
4. Verified email match → auto-links
5. No match → creates new user + social record

### Social model

Add to your `User` model:

```php
use Simtabi\Laranail\AuthKit\Models\Social;
use Illuminate\Database\Eloquent\Relations\MorphMany;

public function socials(): MorphMany
{
    return $this->morphMany(Social::class, 'socialable');
}
```

Publish the migration:

```bash
php artisan vendor:publish --tag=laranail::authkit-social-migrations
```

## Usage

### Session login (web)

```php
$result = app(AttemptEmailPasswordLogin::class)->execute(
    request: $request,
    guard: 'web',
);

if (! $result->isPassed()) {
    return back()->withErrors(['email' => 'Invalid credentials']);
}

app(LoginUser::class)->execute($result->user, guard: 'web');
return redirect()->intended('/dashboard');
```

### API token

```php
$tokenResult = app(IssueTokenForUser::class)->execute(
    user: $user,
    name: 'api-token',
);

return response()->json([
    'token' => $tokenResult->token,
    'user'  => $tokenResult->user,
]);
```

### Social redirect + callback

```php
$redirect = app(SocialRedirectAction::class)->execute(
    request: $request,
);

return redirect($redirect->url);

// Callback:
$result = app(SocialCallbackAction::class)->execute(
    request: $request,
    guard: 'web',
);
```

## Related packages

- `laranail/authkit-preset` — Blade views + routes for this package

## License

MIT
