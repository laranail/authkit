# Security

Review the published `config/laranail/authkit.php` before production. Set the intended guard and user model, keep credential rate limits enabled, and configure HTTPS, secure session cookies, CSRF protection, trusted hosts/proxies, and a working mail transport in the consuming Laravel application.

Auth Kit's implemented safeguards are credential throttling by email, IP, and guard; session regeneration on login; session invalidation and CSRF-token regeneration on logout; verified-email-only guest social linking for trusted providers; uniqueness of provider identities; reset/password-change revocation of personal tokens; and password-change invalidation of other compatible guard sessions. They complement—not replace—Laravel's application and deployment security configuration.

Turnstile support is opt-in:

```env
AUTHKIT_TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
```

The configured challenge input is `cf-turnstile-response`. The rule is applied during Auth Kit user creation; attach `ValidateTurnstile` or `TurnstileRule` deliberately to other application-owned endpoints. Protect only routes where a challenge is appropriate, verify the callback server-side, and never treat the browser response as sufficient on its own.

Two-factor authentication is not enabled by default; MFA remains work in progress. Review the [social linking rules](social-login.md#identity-resolution-and-account-linking-safety) and [passkey origin requirements](passkeys.md#relying-party-and-browser-client) before deploying either feature. See [configuration](configuration.md) for feature and guard settings, and [testing](testing.md) for validation guidance.