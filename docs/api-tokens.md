# API tokens

Use `IssueTokenForUser` to issue a Sanctum personal access token from an application-owned API authentication flow. The action returns a `TokenResult` containing the authenticated user and the newly created token.

```php
$result = app(IssueTokenForUser::class)->execute(
    user: $user,
    name: 'mobile',
);
```

The consuming application's model must use Sanctum's `HasApiTokens` trait and its Sanctum migration must be installed. Auth Kit registers the REST API itself (see [API routes](api-routes.md)); a caller issuing a token directly should authenticate and authorize the request first, then choose a token name and abilities appropriate to the client. Return the plain-text token only at issuance, never log it, and use Sanctum ability middleware plus token revocation for client lifecycle management.

Tokens are scoped and time-limited by default rather than wildcard and eternal — see [API routes](api-routes.md) for the endpoints and `laranail.authkit.tokens` for the defaults.

---

[← Docs index](../README.md#documentation)
