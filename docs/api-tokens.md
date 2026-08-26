# API tokens

Use `IssueTokenForUser` to issue a Sanctum personal access token from an application-owned API authentication flow. The action returns a `TokenResult` containing the authenticated user and the newly created token.

```php
$result = app(IssueTokenForUser::class)->execute(
    user: $user,
    name: 'mobile',
);
```

The consuming application's model must use Sanctum's `HasApiTokens` trait and its Sanctum migration must be installed. Auth Kit does not register API routes or decide when a token should be issued; authenticate and authorize the request first, then choose a token name and abilities appropriate to the client. Return the plain-text token only at issuance, never log it, and use Sanctum ability middleware plus token revocation for client lifecycle management.

For a ready-made API route set and Sanctum response handling, use `laranail/authkit-preset` and see its [API routes guide](../../authkit-preset/docs/api-routes.md).

---

[← Docs index](../README.md#documentation)
