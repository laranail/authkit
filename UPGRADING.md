# Upgrading

Breaking changes, and what to do about them. Versions not listed here need no action.

## Social login moved to `laranail/authkit-social`

Social login is no longer part of this package. Fifteen classes, the `socials` migration, the factory
and the `social` config block now live in
[`laranail/authkit-social`](https://github.com/laranail/authkit-social).

**Most of the mechanical part is a codemod:**

```bash
composer require laranail/authkit-social
vendor/bin/rector process app/ --config vendor/laranail/authkit/rector-migrate-social.php
```

Add `tests/` and anything else that names these classes.

### 1. Require the new package

`laranail/*` resolves through git, not Packagist, so the `repositories` entry is required as well as
the `require`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/authkit-social.git" }
],
"require": {
    "laranail/authkit-social": "^0.1"
}
```

### 2. The config key moved

`laranail.authkit.social.*` → `laranail.authkit-social.*`, in its own published file.

```bash
php artisan vendor:publish --tag=laranail::authkit-social-config
```

**Your `.env` does not change.** `AUTHKIT_GOOGLE_CLIENT_ID` and every other provider variable keep
their names — only the config key moved. The one new variable is `AUTHKIT_SOCIAL_ENABLED`, which
defaults to `true` so an upgrade does not silently disable social login.

If you published `config/laranail/authkit.php`, delete its `social` block; nothing reads it now.

### 3. The migration publish tag now comes from the new package

`laranail::authkit-social-migrations` is unchanged as a tag, but it is published by
`laranail/authkit-social` rather than by the core. **The migration filename is unchanged**, so an
application that already ran it will not run it again.

### 4. Class names

Every moved class gains a `Social\` segment. The codemod above rewrites all fifteen:

| Before | After |
|---|---|
| `Simtabi\Laranail\AuthKit\Actions\SocialRedirectAction` | `Simtabi\Laranail\AuthKit\Social\Actions\SocialRedirectAction` |
| `Simtabi\Laranail\AuthKit\Enums\SocialProvider` | `Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider` |
| `Simtabi\Laranail\AuthKit\Models\Social` | `Simtabi\Laranail\AuthKit\Social\Models\Social` |
| `Simtabi\Laranail\AuthKit\Http\Controllers\AbstractSocialRedirectController` | `Simtabi\Laranail\AuthKit\Social\Http\Controllers\AbstractSocialRedirectController` |
| …and the rest, under `Actions\`, `Contracts\`, `Services\`, `Support\` | |

### 5. If you extended the abstract social controllers

They now extend `AbstractAuthController` **through an explicit import** rather than same-namespace
resolution. Your subclasses need no change, but if you copied one, add:

```php
use Simtabi\Laranail\AuthKit\Http\Controllers\AbstractAuthController;
```

Without it PHP looks for `…\Social\Http\Controllers\AbstractAuthController`, which does not exist —
and the file parses cleanly, so the failure only appears when the class is loaded.

### 6. Dependencies you may now be missing

`laravel/socialite`, `socialiteproviders/manager` and `laranail/enumerator` are no longer required by
this package. If your application used them directly, require them yourself — installing
`laranail/authkit-social` also brings all three back transitively.
