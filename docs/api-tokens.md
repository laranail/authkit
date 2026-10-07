# API tokens

Use `IssueTokenForUser` to issue a personal access token from an application-owned API authentication flow. Sanctum is the default backend. Optional packages can register another backend through `TokenIssuerRegistryInterface`; `laranail/authkit-oauth` adds Passport while keeping Sanctum as the default. The action returns a `TokenResult` containing the authenticated user and the newly created token.

```php
$result = app(IssueTokenForUser::class)->execute(
    user: $user,
    name: 'mobile',
);
```

With the default Sanctum backend, the consuming application's model must use Sanctum's `HasApiTokens` trait and its Sanctum migration must be installed. When using Passport, follow the OAuth package's separate model/provider setup. Auth Kit registers the REST API itself (see [API routes](api-routes.md)); a caller issuing a token directly should authenticate and authorize the request first, then choose a token name and abilities appropriate to the client. Return the plain-text token only at issuance, never log it, and use the selected token system's scope middleware and revocation behavior for client lifecycle management.

Tokens are scoped and time-limited by default rather than wildcard and eternal — see [API routes](api-routes.md) for the endpoints and `laranail.authkit.tokens` for AuthKit's defaults. A non-default issuer may apply its own expiry configuration.

---

[← Docs index](../README.md#documentation)
