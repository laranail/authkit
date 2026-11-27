# Account lockout

AuthKit supports an opt-in account lockout policy, separate from request throttling. Password
failures accumulate against the resolved account, across IP addresses, email/username aliases,
and web/API login. The configured threshold locks that account for the configured duration.
Correct credentials do not bypass an active lock.

## Install and enable

Publish and run the lockout migration before enabling the policy:

```bash
php artisan vendor:publish --tag=laranail::authkit-lockout-migrations
php artisan migrate
```

```env
AUTHKIT_LOCKOUT_ENABLED=true
AUTHKIT_LOCKOUT_MAX_ATTEMPTS=5
AUTHKIT_LOCKOUT_DURATION_MINUTES=15
```

These map to `laranail.authkit.lockout.enabled`, `max_attempts` and `duration_minutes`. The shipped
policy is disabled, so upgrading alone adds no database queries or lockout requirement to login.
Both numeric values are positive integers; values below one are clamped to one. There is no
user-table migration. The separate table stores an account-key hash, failure count and Unix
lock-expiry timestamp, without raw submitted identifiers or passwords.

The default table is `authkit_account_lockouts` on the default database connection. Configure
`laranail.authkit.lockout.table` and `connection` before migration if using a dedicated store.
All application nodes must use the same durable lockout database. Database failure while enabled
fails authentication closed; do not turn on the policy before its schema is installed. Disable it
before rolling back that schema.

## Threshold and recovery

- Known-account credential failures count once per fully failed lookup. A request that resolves the
  same user through two columns counts once. A successful overlapping username/email candidate
  does not penalize another account.
- Unknown identifiers use the existing request throttle but create no lockout rows.
- A valid password clears partial failures when the account is not locked. Counts otherwise remain
  until successful credential verification, lock expiry, or explicit clearing; request throttle
  windows do not reset them.
- Reaching the threshold returns a lockout immediately. Repeated attempts against an active lock
  do not extend it. Expiry makes the account eligible again and starts a new failure sequence.
- An authorized application/operator can explicitly clear the account through the service below.
  There is no unauthenticated or public unlock endpoint. Password reset alone does not clear an
  active lock; expiry or explicit clearing still applies.

```php
use Simtabi\Laranail\AuthKit\Services\AccountLockout;

$lockout = app(AccountLockout::class);
$seconds = $lockout->retryAfter($user, 'web');
$lockout->clear($user, 'web'); // Call only after the application's authorization/recovery checks.
```

Eloquent accounts are keyed by connection, table, identifier column and ID, so guards/model aliases
for the same physical account share a lock. Different account tables cannot lock one another when
IDs coincide. Non-Eloquent providers are scoped by configured provider name, authenticatable class
and ID; guard aliases over that provider share the state. Custom providers must resolve a known
account independently of password validation, as Laravel's standard providers do.

Clearing request rate limits, allowing a throttle window to expire, or flushing the application
cache leaves the database lock intact. Conversely, clearing an account lock does not clear its
request throttles; both must allow the next attempt. Counter mutations use a database transaction
and row lock on server databases, with retries for concurrency errors.

## Login and token surfaces

The shared credential action returns `AuthStatus::Locked` / `AuthResult::locked($seconds)`.
Web and API JSON login respond with HTTP `429`, `status: locked`, `data.message`,
`data.retry_after`, and a `Retry-After` header. Ordinary browser form submissions return `429` with
that header. Existing request throttles may reject a request before its account is looked up;
that response remains `status: throttled`. Clients enabling this policy must handle the new status.

AuthKit also rejects new core-issued tokens and pending API MFA completion while locked. A login
event listener rejects direct Laravel session-guard sign-ins, including passkeys/social sign-ins
and pending browser MFA, and clears the staged guard session/remember cookie before returning
`429`. Standard session guards support this cleanup. Applications with custom authentication
mechanisms that do not use these surfaces must call `assertUnlocked($user, $guard)` before
establishing authentication.

Existing authenticated sessions and issued tokens are not revoked merely because password
attempts trigger a lock. They can continue using authenticated endpoints. This is a sign-in
lockout rather than an account-disable flag. Account deletion removes stored lockout state when
the policy is enabled. Custom deletion paths can call `forget($user, $guard)` after deleting the
account; use `clear()` for recovery, so live counter rows remain safe for concurrent updates.

## Audit events

`AccountLocked` carries `userType`, `userId` and `guard`. It fires once when the threshold creates
a new lock, rather than for every blocked request. `AccountUnlocked` adds `reason`, either `manual`
or `expired`. Expiry is effective by time immediately; its event is emitted when the next counter
mutation observes and clears expired state. A read of `retryAfter()` does not mutate state.
`AuthenticationFailed` uses reason `locked` for blocked credential/session attempts.

Payloads contain no credentials, submitted identifiers or model instances. See [events](events.md)
for listener and transaction semantics, [login](login.md) for credential lookup, and
[security](security.md) for deployment guidance.

---

[← Docs index](../README.md#documentation)
