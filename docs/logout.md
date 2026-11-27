# Logout

API logout revokes the current token through the configured token issuer registry. Browser logout
ends the configured guard's session and rotates the CSRF token.

## API

Call `POST /api/auth/logout` with `Accept: application/json` and `Authorization: Bearer <token>`.
A successful response has `status: success`; discard the client-side token. Other tokens are not
revoked by this endpoint. See [API tokens](api-tokens.md) for revoking all tokens through the registry.

## Browser sessions

The preset provides its browser route. A custom session controller can call the core contract:

```php
use Simtabi\Laranail\AuthKit\Contracts\LogoutUserInterface;

app(LogoutUserInterface::class)->execute(guard: 'web');
return redirect('/');
```

Use session middleware and CSRF protection on browser logout. Ending a browser session does not
revoke every bearer token. See [Browser sessions](browser-sessions.md) for ending other sessions.

---

[← Docs index](../README.md#documentation)
