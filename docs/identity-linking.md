# Identity linking

Adding a sign-in method to an account that is already signed in, without that ever becoming a way
to sign in.

`Simtabi\Laranail\AuthKit\Contracts\IdentityLinkingInterface` is the mechanism, bound to
`Services\IdentityLinking`. A provider package — `laranail/authkit-social-login` and its siblings —
owns where links are stored and which external identity it just saw. This owns whether attaching
one is allowed.

## Why it is separate from signing in

Resolving an account from a verified address is how someone *signs in* with a provider. It is not
good enough to *attach a new permanent credential* to an account, because the two have different
blast radii: a wrong sign-in ends with a session, and a wrong link ends with a standing way back
in that the account's owner never chose.

So the two paths are kept apart, structurally rather than by convention:

- Nothing here can authenticate anybody. The service has no access to a guard's login, and every
  method returns a value object or nothing.
- Nothing here accepts an email address. There is no parameter for one and no field on
  `LinkIntent`, so a matching address cannot become authorisation even by mistake. A test asserts
  that shape rather than the behaviour.

## The flow

```php
use Simtabi\Laranail\AuthKit\Contracts\IdentityLinkingInterface;
use Simtabi\Laranail\AuthKit\Exceptions\IdentityLinkException;

// 1. The user asks to connect a provider. Refused unless a credential was confirmed recently.
$token = app(IdentityLinkingInterface::class)->issueIntent(
    request: $request,
    user: $request->user(),
    guard: 'web',
    provider: 'github',
);

// 2. Carry $token through the provider redirect as your own state, and on the way back:
$linking = app(IdentityLinkingInterface::class);

try {
    $linking->consumeIntent($request, $token, $request->user(), 'web', 'github');
    $linking->assertLinkable($request->user(), $ownerOfThisExternalIdentity);
} catch (IdentityLinkException $exception) {
    return back()->withErrors(['connection' => $exception->getMessage()]);
}

// 3. Only now does your package write the link.
```

`$ownerOfThisExternalIdentity` is whoever already holds the identity, or `null` when nobody does.
The provider package looks that up, because it owns the table.

## What each step guarantees

| Step | Refuses |
|---|---|
| `issueIntent` | No credential confirmed within `auth.password_timeout` |
| `consumeIntent` | A token already spent, unknown, or expired |
| `consumeIntent` | A different user, guard, provider or authentication context than the one that started it |
| `assertLinkable` | An identity owned by a different account |

`assertLinkable` is idempotent for an identity the same user already owns, so a double submission
is harmless. It never switches the current user and never transfers an identity between accounts:
the only outcomes are "allowed" and an exception.

## Recent reauthentication

A browser session answers through Laravel's own `auth.password_confirmed_at`, checked against
`auth.password_timeout` — exactly what the framework's `password.confirm` middleware maintains, so
an application that already confirms passwords needs nothing new. Put `password.confirm` on the
route that starts a link and this is satisfied before the service is reached.

A token-authenticated client has no session to carry that, so it proves the credential on the
request that starts the link:

```php
POST /api/auth/connections/github   { "password": "..." }
```

The password is checked through the guard's own provider, so an application that has replaced its
user provider keeps its own rules.

## Why the attempt is single-use and bound

The token is 256 bits of randomness, and only its SHA-256 is stored — the token itself exists only
in the response that returned it. The record binds the user, their model class, the guard, the
provider and a hash of the session or access token the attempt began from, and all of them are
compared again on the way back.

Spending is a read-and-delete taken under an atomic lock where the cache store offers one. A
replayed callback therefore finds nothing, which is the refusal that matters: without it, one
intercepted callback URL could be replayed to attach the same identity repeatedly, or attach it
after the user believed the attempt had failed.

A client presenting neither a session nor an access token binds on the user alone. That is weaker,
but it still cannot cross accounts, because the user is compared too.

## Failure handling

`IdentityLinkException::$failure` is a closed `IdentityLinkFailure` enum, so a caller branches on
the reason rather than parsing a message — a web flow wants to redirect to password confirmation
where an API wants a 403, and both are the same refusal.

| Case | Meaning |
|---|---|
| `RecentAuthenticationRequired` | Confirm a password, then start again |
| `IntentInvalid` | Unknown, expired, or already spent |
| `IntentMismatch` | Finished by a different user, guard, provider or context |
| `IdentityOwnedByAnotherUser` | Already connected elsewhere; nothing was changed |

## Configuration

```php
// config/laranail/authkit.php
'identity_linking' => [
    'intent_ttl_minutes' => 10,
],
```

The attempt spans one redirect out to a provider and back, so minutes rather than hours. It is
single-use regardless; this only bounds how long an unused one lingers.

---

[← Docs index](../README.md#documentation)
