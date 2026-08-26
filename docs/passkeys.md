# Passkeys

Passkeys use Fortify's native integration with `laravel/passkeys`. Auth Kit configures the feature and supplies a morph-aware model and trait; Fortify and `laravel/passkeys` perform WebAuthn ceremonies, validation, controllers, and responses.

## Model and migration

The authenticatable model must implement Fortify's `PasskeyUser` contract and use `PasskeyAuthenticatable`:

```php
use Laravel\Fortify\Contracts\PasskeyUser;
use Simtabi\Laranail\AuthKit\PasskeyAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use PasskeyAuthenticatable;
}
```

Publish Auth Kit's migration, then migrate:

```bash
php artisan vendor:publish --tag=laranail::authkit-passkey-migrations
php artisan migrate
```

Do not publish Fortify's passkey migration tag. Auth Kit owns the polymorphic `passkeys` table, allowing users, administrators, or other authenticatable models to own credentials.

## Relying party and browser client

Configure the relying-party ID and exact allowed browser origins in `config/fortify.php`. They must agree with `APP_URL`, especially for a production HTTPS domain:

```php
'passkeys' => [
    'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),
    'allowed_origins' => [config('app.url')],
    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
    'timeout' => 60000,
],
```

Auth Kit does not ship JavaScript. Install `@laravel/passkeys` and connect the browser client to Fortify's canonical passkey endpoints. Passkey login and confirmation use the stateful guard and session, so they are not available as Sanctum API equivalents.

Keep `passkeys` in `laranail.authkit.fortify.features` to enable Fortify's passkey routes. Fortify's `confirmPassword` option and `passkeys` limiter continue to govern management operations.

---

[← Docs index](../README.md#documentation)
