# Account deletion

AuthKit exposes self-service account deletion through `DELETE /api/auth/user` (default prefix).
The route is registered when the core API is enabled and `delete-account` is present in
`laranail.authkit.fortify.features`. It is included in the shipped defaults; applications with
published feature lists must add it to enable the endpoint. Removing it removes the route.

Send the accepted bearer token and the account's current password:

```http
DELETE /api/auth/user
Authorization: Bearer <access-token>
Content-Type: application/json

{"password": "<current-password>"}
```

Success returns `204 No Content` with `Cache-Control: no-store`. Missing authentication returns
`401`; missing or incorrect password returns `422`; the deletion throttle returns `429` after six
attempts per minute, in addition to the API group throttle. The action checks the password on a
fresh locked account row, including when the authenticated model contains a stale password hash.

There is no target account in the path. The handler always uses the accepted guard's authenticated
user; `id` and `user_id` in the request body are rejected. A URL such as `/api/auth/user/123` is not
a deletion route. Applications may add their own token-ability or authorization middleware.

## Authentication cleanup

Before deleting the account, the action:

- Revokes all tokens through every registered AuthKit issuer. Sanctum rows are removed; enabled
  Passport integration revokes access and refresh tokens through its own issuer.
- Removes passkeys through the account's own relation when it implements `PasskeyUser`. Install
  that model's passkey migration before enabling the capability.
- Deletes reset tokens from configured password brokers whose provider maps to the same account
  table, connection and identifier. Configure the matching provider/broker for each population.
- Removes its database session rows via `BrowserSessionService`, when database sessions are used.
- Cancels its pending verified email change.

The controller invalidates and regenerates the CSRF token of a current stateful session, if one
exists. File/cookie/cache session stores have no per-user index for physical deletion; subsequent
requests cannot authenticate a deleted model through Laravel's normal Eloquent provider. Use
separate session/reset storage for distinct populations when identifiers or addresses overlap;
Laravel's shared tables do not store the user model type.

Hard deletion removes the account row and its inline authentication secrets. If the model uses
`SoftDeletes`, AuthKit respects that retention choice, replaces the password with an unknowable
hash, clears remember/TOTP credentials and removes active authentication records. Restoring the
row does not restore the old password, passkeys, recovery codes or tokens. Application-owned data,
external providers and third-party credential stores need the cleanup hook below.

Passwordless accounts cannot confirm this default flow. Applications supporting them must replace
`DeleteUserInterface` with an action that requires an appropriate fresh credential proof; a bearer
token alone must not become sufficient authorization to delete an account.

## Application cleanup and extension points

`Simtabi\Laranail\AuthKit\Contracts\DeleteUserInterface` resolves to `Actions\DeleteUser`.
The action accepts a persisted Eloquent authenticatable and a password. Custom HTTP handlers must
provide authentication, authorization, CSRF protection where applicable and throttling.

`AccountDeleting` is synchronous, after password verification but before cleanup/deletion, while
the account still exists. Its immutable payload is `userType` and `userId`. Register listeners to
remove application or optional-package data. For example, an application using AuthKit Social
Login should remove its linked identities and encrypted provider credentials:

```php
use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\AuthKit\Events\AccountDeleting;
use Simtabi\Laranail\AuthKit\Social\Models\Social;

Event::listen(AccountDeleting::class, function (AccountDeleting $event): void {
    Social::query()
        ->where('socialable_type', $event->userType)
        ->where('socialable_id', $event->userId)
        ->delete();
});
```

Configure required dependent-data cleanup before exposing deletion in an application. A listener
may throw a validation/domain exception to veto deletion, for example when application records
must be retained. An Eloquent observer returning `false` also prevents success. Do not queue a
cleanup listener that must complete before the account is removed.

`AccountDeleted` is emitted only after the action's database transaction succeeds. It carries the
same metadata and no password, email or model object. See [events](events.md) for listener and
outer-transaction semantics. Failures roll back writes on the account's connection. Other
connections, remote issuer revocations and cache cancellation cannot share that transaction; their
cleanup may remain applied if a later step fails. The account remains when deletion fails; the
endpoint does not report a successful deletion.

The core supplies this API and action; it does not add a Preset deletion page or erase arbitrary
application records. See [API routes](api-routes.md) and [actions](actions.md).

---

[← Docs index](../README.md#documentation)
