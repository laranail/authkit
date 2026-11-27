# Lifecycle events and audit hooks

`laranail/authkit` dispatches the public events below through Laravel's normal event dispatcher.
Classes live under `Simtabi\Laranail\AuthKit\Events`. Payloads are immutable metadata snapshots: scalar IDs,
booleans and, for OAuth consent, scope-name lists. They contain no model/request objects,
passwords, bearer/provider tokens, authorization codes, recovery values or client secrets.

## Event catalog

| Event | Public payload | Dispatch point |
| --- | --- | --- |
| `AuthenticationFailed` | `guard: string`, `reason: string` | A credential attempt is rejected or throttled. `reason` is `invalid_credentials` or `throttled`; no submitted identifier is included. |
| `PasswordUpdated` | `userType: string`, `userId: string`, `operation: string` | After password persistence and token revocation complete. `operation` is `reset` or `update`. |
| `EmailChangeRequested` | `userType: string`, `userId: string` | After a pending change is stored and verification mail is sent to the new address. |
| `EmailChanged` | `userType: string`, `userId: string` | After the new address is proved and saved. Invalid or replayed proofs do not emit this. |
| `EmailChangeCancelled` | `userType: string`, `userId: string` | After explicit cancellation removes a pending record. |
| `ProfileUpdated` | `userType: string`, `userId: string` | After the profile action completes, including initiation of a pending email change. Field values are excluded. |
| `RecoveryCodesRegenerated` | `userType: string`, `userId: string` | After replacement recovery codes are stored. No codes are included. |
| `SessionAuthenticated` | `userType: string`, `userId: string`, `guard: string`, `remember: bool` | After `LoginUser` authenticates the guard and regenerates the session; credential checks alone do not emit this. |
| `SessionLoggedOut` | `userType: ?string`, `userId: ?string`, `guard: string` | After guard logout, session invalidation and CSRF rotation. User metadata may be null when no session user existed. |
| `TokenIssued` | `userType: string`, `userId: string`, `driver: string` | After the core issuer registry returns a token; `driver` is the selected issuer. Direct backend calls are outside this hook. |
| `TokensRevoked` | `userType: string`, `userId: string`, `scope: string` | After the owning registry/issuer completes a revocation operation. `scope` is `current` or `all`; this is not a count of changed tokens. |
| `TwoFactorDisabled` | `userType: string`, `userId: string` | After disabling an enabled factor; an already-disabled account does not emit a second event. |
| `TwoFactorEnabled` | `userType: string`, `userId: string` | After valid TOTP enrollment confirmation; beginning enrollment and invalid confirmation do not emit it. |
| `UserRegistered` | `userType: string`, `userId: string` | After the core registration action persists a user; independent of the HTTP-only Laravel `Registered` event. |

`userType`/`actorType` are the authenticatable PHP class names; IDs are strings so UUID and integer
models share one contract. Nullable fields are marked `?`. These are public payloads; listeners
should use the named properties rather than infer a meaning from dispatch order.

## Register a listener

In the host application's service provider `boot()` method:

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\AuthKit\Events\PasswordUpdated;

Event::listen(PasswordUpdated::class, function (PasswordUpdated $event): void {
    Log::info('authkit.PasswordUpdated', get_object_vars($event));
});
```

Use your application's audit channel or listener class when it needs persistence or another sink.
Normal Laravel event discovery and class listeners work too. The package provides extension hooks,
not an audit table, retention policy or external logging service.

## Ownership and existing framework events

These hooks belong to the core actions/services. Browser and API callers share them rather than
re-dispatching the same event in each controller. A custom action that replaces a binding owns the
responsibility for preserving any event contract its application relies on.

Laravel/Fortify events remain available: for example, the HTTP registration controllers dispatch
Laravel's `Registered`, verification dispatches `Verified`, and session guards dispatch their own
login/logout events. A package event and a framework event may describe different stages of one
flow; subscribe to the stage you intend to audit.

The profile hook covers profile-action completion; use `EmailChanged` to audit the actual verified
address replacement. Email values and verification proofs are excluded from all event payloads. Token revocation hooks report completed operations even when no tokens exist;
no affected-row count is promised. Factor lifecycle events exclude enrollment secrets and codes.

## Listener execution and transactions

Dispatch is synchronous after the operation described above completes. Listener exceptions
propagate; a failing listener can interrupt the response after a write has already happened. Keep
authentication-path listeners small and reliable, or queue expensive work.

Events do not start or commit a surrounding application transaction. If an application wraps an
operation in a transaction, its audit consumer must respect commit/rollback (for example, queue
listeners with the host's after-commit configuration). A synchronous event is not a durable
outbox or proof that a caller's later transaction committed. No listener can veto an operation
by returning `false`; replace the documented action/contract for pre-operation policy.

## Verification

The normal test suite covers payload contracts, successful dispatch counts and rejection paths.
For a focused check:

```bash
XDEBUG_MODE=off vendor/bin/pest --colors=never --no-coverage tests/Architecture/EventPayloadTest.php tests/Feature/LifecycleEventsTest.php
```

OAuth's authorization/device tests additionally cover approve, deny and invalid session tokens.
Social's deliberate-connect tests additionally cover idempotent and refused linking.

---

[← Docs index](../README.md#documentation)
