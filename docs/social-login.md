# Social login

Auth Kit integrates Laravel Socialite but does not register routes or render buttons. Your application chooses which providers to expose, registers a redirect URL with each provider, and supplies the redirect and callback routes.

## Supported providers and setup

| Provider | Route value | Provider-console callback                             | Required environment prefix | Verified email trusted for auto-linking |
|----------|-------------|-------------------------------------------------------|-----------------------------|-----------------------------------------|
| Google   | `google`    | `https://your-app.test/auth/social/google/callback`   | `AUTHKIT_GOOGLE_`          | Yes                                     |
| Facebook | `facebook`  | `https://your-app.test/auth/social/facebook/callback` | `AUTHKIT_FACEBOOK_`        | No                                      |
| X        | `twitter`   | `https://your-app.test/auth/social/twitter/callback`  | `AUTHKIT_TWITTER_`         | No                                      |
| LinkedIn | `linkedin`  | `https://your-app.test/auth/social/linkedin/callback` | `AUTHKIT_LINKEDIN_`        | Yes                                     |
| PayPal   | `paypal`    | `https://your-app.test/auth/social/paypal/callback`   | `AUTHKIT_PAYPAL_`          | Yes                                     |

Create an OAuth application in the provider's developer console, add the exact callback URL used by your application, then set its credentials. Google, LinkedIn, and PayPal request OpenID, profile, and email scopes; Facebook requests email and public profile; X requests user and email access. Provider approval, app mode, and email-access requirements remain provider-specific.

```env
AUTHKIT_GOOGLE_CLIENT_ID=
AUTHKIT_GOOGLE_CLIENT_SECRET=
AUTHKIT_GOOGLE_REDIRECT="${APP_URL}/auth/social/google/callback"
```

Replace `GOOGLE` with `FACEBOOK`, `TWITTER`, `LINKEDIN`, or `PAYPAL` for the other providers. PayPal is sandboxed by default; set `AUTHKIT_PAYPAL_SANDBOX_MODE=false` only when both the callback and credentials are production values. Clear Laravel's configuration cache after changing environment values.

## Routes, controllers, and persistence

Publish the social migration before enabling a provider:

```bash
php artisan vendor:publish --tag=laranail::authkit-social-migrations
php artisan migrate
```

The migration creates a polymorphic `socials` table and enforces uniqueness for the provider/provider-user-ID pair. It stores the provider profile fields and access, refresh, and expiry values returned by Socialite. Treat those tokens as sensitive data: restrict database access and do not expose the model directly in an API response.

Add the relation to every authenticatable model that can own a social identity:

```php
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Simtabi\Laranail\AuthKit\Models\Social;

public function socials(): MorphMany
{
    return $this->morphMany(Social::class, 'socialable');
}
```

Register a redirect route and callback route whose `{provider}` value is restricted to the supported provider keys. Extend `AbstractSocialRedirectController` for the redirect and `AbstractSocialCallbackController` for the callback. The redirect controller returns an external URL for JSON requests and redirects browsers otherwise. On success, the callback controller creates a session with the configured guard; override `passed()` and `failed()` to choose application-specific responses and redirect targets.

## Identity resolution and account-linking safety

`ResolveSocialIdentity` uses the following order:

1. An existing provider/provider-ID record is reused and its token metadata is refreshed.
2. If a user is already authenticated, the provider identity is linked to that user.
3. For a guest, a matching local email is linked only when the provider supplied a trusted `email_verified` claim.
4. A guest with a trusted verified email and no local account gets a new local account, marked verified, plus a social record.
5. A missing email, an unverified email, or an existing matching account without a trusted verification claim fails the callback; it never silently links the account.

Only Google, LinkedIn, and PayPal are currently trusted for `email_verified`. Facebook and X identities can be linked from an authenticated account, but they cannot create or auto-link an account by email. This deliberately prevents a provider email claim from becoming an account-takeover path.

## Adding a provider

The accepted route values are the `SocialProvider` enum cases. Adding a provider requires a package change: add its enum case, add its credentials, redirect, and scopes under `laranail.authkit.social`, and ensure Socialite has a driver for that key. First-party Socialite drivers work through the normal `services.<provider>` configuration; a third-party driver must be registered with Socialite's extension mechanism, as PayPal is. Add callback tests for an existing identity, a trusted verified email, an unverified or missing email, and authenticated linking before exposing the new provider.