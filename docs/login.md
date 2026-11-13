# Login

Use `AttemptEmailPasswordLogin` to validate credentials and obtain an `AuthResult`:

```php
$result = app(AttemptEmailPasswordLogin::class)->execute(
    request: $request,
    guard: 'web',
);

if (! $result->isPassed()) {
    return back()->withErrors(['email' => 'Invalid credentials']);
}

app(LoginUser::class)->execute($result->user, 'web');
```

`AuthResult` distinguishes passed, failed, and throttled attempts. The limiter key combines the guard, lowercased submitted email, and client IP; failed attempts increment it and a successful attempt clears it. Handle throttling separately so users receive an appropriate retry message.

`LoginUser` creates the session and regenerates it. `LogoutUser` logs the user out, invalidates the session, and regenerates the CSRF token. For controller-based integration, extend `AbstractAttemptEmailPasswordLoginController` and customize `passed()`, `failed()`, or `throttled()`; extend `AbstractLogoutController` and customize `loggedOut()`.

The default guard is `web`; change it with `AUTHKIT_GUARD` or pass the intended guard explicitly. Use the same guard for credential validation and session login.

## Signing in with a username

Email is the only identifier out of the box, which is the behaviour this package has always had.
Accept usernames by naming the columns an identifier is matched against:

```php
// config/laranail/authkit.php
'login' => [
    'field'       => 'login',
    'identifiers' => ['email', 'username'],
],
```

`field` is the request input the identifier arrives in. It stays `email` by default so existing
forms and API clients are untouched; rename it once the field no longer only holds an address.
`identifiers` are user columns, tried in the order given, and the first account whose password
also matches is the one signed in.

Validation follows the list rather than being configured twice. With email alone the identifier is
validated as an address; with anything wider that rule is dropped, because a username is not an
address and validating one as an address rejects every username before the lookup runs.

Throttling, the generic invalid-credential response, and two-factor behaviour are unchanged — the
limiter already keyed on the submitted identifier rather than on an address specifically.

### The application owns the username column

Auth Kit resolves whatever user model the application configures and does not own the users table,
so it ships no migration for one. Adding a `username` column, its uniqueness constraint, and its
registration validation is the application's job. A package that does not own a table must not
migrate it.

Nothing stops one account's username from equalling another account's address. The password is
checked against each candidate rather than only against the first row found, so the username's
owner can still sign in; enforce uniqueness across both columns if you would rather that
ambiguity could not arise.

`CheckEmailExists` is deliberately not widened to usernames. It answers whether an address is
registered, and extending it would add a username-enumeration surface that nothing here needs.

---

[← Docs index](../README.md#documentation)
