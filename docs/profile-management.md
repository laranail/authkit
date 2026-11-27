# Profile management

Retain `update-profile-information` in `laranail.authkit.fortify.features` to enable profile and
email-change API routes. `UpdateUserProfileInformation` implements Fortify's
`UpdatesUserProfileInformation` contract and accepts a required name and a unique email address.
Name changes are saved immediately. A different email starts a pending verified change for every
user, including models that do not implement `MustVerifyEmail`; the current address and verification
state remain active until the new address is proved. Custom profile actions must preserve this rule.

## Verified email change

Authenticated API clients use these endpoints (default prefix `/api/auth`):

| Method and path | Input / result |
| --- | --- |
| `POST /api/auth/user/email-change` | Send `email`; returns `data.pending` with normalized `email` and Unix `expires_at`. Sends a link to the new address. |
| `GET /api/auth/user/email-change` | Returns the current pending change or `null`. |
| `POST /api/auth/user/email-change/verify` | Send `token` from the link, using the same account's bearer token. Applies the address and clears pending state. |
| `GET /api/auth/user/email-change/verify?token=…` | Authenticated verification URL used by the default email notification. |
| `DELETE /api/auth/user/email-change` | Cancels the pending change; returns `data.pending: null`. |

All endpoints require an accepted token guard and are throttled to six requests per minute, in
addition to the API group limit. Responses use `status: success`, `data.pending`, and `Cache-Control:
no-store`. Validation errors return `422`; unauthenticated requests return `401`.

The 256-bit random proof expires after `laranail.authkit.email_change.expires_after_minutes`
(default `60`). It is single-use and bound to the account and its original email. A new request
supersedes the previous link. Invalid, expired, cancelled, superseded, foreign-account and replayed
links cannot change the active address. Uniqueness is checked case-insensitively at request and
confirmation; keep a database unique constraint on the application's email column to cover races.
Successful confirmation sets `email_verified_at` when that column exists (or the model implements
`MustVerifyEmail`). Existing-address verification remains a separate flow.

## Mail and client integration

Configure Laravel mail delivery and a persistent, shared cache across application nodes. Pending
changes are encrypted in the default cache with a hashed proof; no user-table migration is needed.
Cache loss or an application encryption-key change invalidates pending requests; request another
link. Account-row locks serialize changes for the same account on server databases.

For a browser or mobile frontend, set `laranail.authkit.email_change.verification_url` to a trusted
application page containing `{token}`, for example `https://app.example.com/email-change?token={token}`.
That page authenticates the requesting account and posts the proof to the verification endpoint.
The default link points to the authenticated API GET endpoint; a browser click alone does not
supply an API bearer token. Avoid logging proof URLs or forwarding them to third-party analytics.
When the core API is disabled, configure this URL and provide your own authenticated routes.

Browser packages may inject `Simtabi\Laranail\AuthKit\Services\EmailChange` and call
`request($user, $email)`, `pending($user)`, `confirm($user, $token)` and `cancel($user)` directly.
The caller owns authentication, authorization, CSRF protection and throttling for custom routes.
The service expects a persisted Eloquent user with an email column. The Preset's form integration
is tracked separately in [preset#62](https://github.com/laranail/authkit-preset/issues/62).
See [lifecycle events](events.md) for audit hooks and [password updates](password-updates.md) for
changing account credentials.

---

[← Docs index](../README.md#documentation)
