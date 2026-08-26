# Installation

Requirements, the repositories block, and publishing the config.

## Requirements

PHP 8.4 or 8.5, Laravel 13.

## The repositories block

`laranail/*` packages resolve through git rather than Packagist, so Composer needs to be told where
to find them. Composer ignores a dependency's own `repositories`, which is why this goes in the
**application's** `composer.json` and lists the whole transitive closure:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/authkit.git" },
    { "type": "vcs", "url": "https://github.com/laranail/console.git" },
    { "type": "vcs", "url": "https://github.com/laranail/enumerator.git" },
    { "type": "vcs", "url": "https://github.com/laranail/package-tools.git" },
    { "type": "vcs", "url": "https://github.com/laranail/captcha.git" },
    { "type": "vcs", "url": "https://github.com/laranail/db-tools.git" }
]
```

## Require it

```bash
composer require laranail/authkit
php artisan vendor:publish --tag=laranail::authkit-config
```

That writes `config/laranail/authkit.php`. The nested directory is deliberate: Laravel turns a
nested config directory into a nested key, so the file is read as `laranail.authkit`, which is the
key the package merges its defaults into.

## Optional migrations

Publish only what the features you enable actually need:

```bash
php artisan vendor:publish --tag=laranail::authkit-social-migrations
php artisan vendor:publish --tag=laranail::authkit-passkey-migrations
php artisan migrate
```

## For a Blade UI

This package is headless by design — no views, no routes, no shipped controllers. For a ready-made
interface use [`laranail/authkit-preset`](https://github.com/laranail/authkit-preset), which
consumes these actions and adds the Blade layer.

---

[← Docs index](../README.md#documentation)
