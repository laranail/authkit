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