# Configuration

Publish the configuration before changing defaults:

```bash
php artisan vendor:publish --tag=laranail::authkit-config
```

Set `AUTHKIT_GUARD` to the application's intended guard. The `laranail.authkit.fortify.features` list enables Fortify capabilities individually: `reset-passwords`, `update-profile-information`, `update-passwords`, `email-verification`, and `passkeys`. Removing an item prevents Auth Kit from enabling that capability; remove corresponding application routes and UI too.

Credential throttling is controlled by `AUTHKIT_RATE_LIMIT_MAX_ATTEMPTS` and `AUTHKIT_RATE_LIMIT_DECAY_MINUTES`, defaulting to five attempts per minute. Social credentials, callbacks, and enabled providers belong to [`laranail/authkit-social-login`](https://github.com/laranail/authkit-social-login); see its [social login guide](https://github.com/laranail/authkit-social-login/blob/main/docs/social-login.md).

---

[← Docs index](../README.md#documentation)
