# Email verification

Email verification is a Fortify feature enabled by including `email-verification` in `laranail.authkit.fortify.features`.

```php
'fortify' => [
    'features' => [
        'email-verification',
    ],
],
```

Auth Kit provides headless controller bases for the verification lifecycle:

- `AbstractEmailVerificationPromptController` displays or returns the verification prompt.
- `AbstractEmailVerificationNotificationController` sends a new verification notification.
- `AbstractVerifyEmailController` handles a signed verification link.

The application owns route registration, presentation, redirects, and mail configuration. The user model must implement Laravel's `MustVerifyEmail` contract for verification notifications and state changes to apply. Use Laravel's signed URL and `verified` middleware on the verification endpoint and routes that require a verified address. A profile email change for such a user clears its verification timestamp and sends a new notification.

Social login has its own verified-email rules. See [Social login](https://github.com/laranail/authkit-social/blob/main/docs/social-login.md) before enabling automatic social-account linking.

---

[← Docs index](../README.md#documentation)
