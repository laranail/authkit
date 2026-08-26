# Contributing

Thanks for helping improve `laranail/authkit`.

## Getting set up

`laranail/*` packages resolve through git rather than Packagist, so the repositories block in
`composer.json` is what makes `composer install` work. Then:

```bash
composer install
composer test
```

## What a change needs

- **Tests.** Every behavioural change carries one; a bug fix carries a test that fails before it.
- **Live-registry assertions for public names.** Anything registered into a Laravel registry —
  config key, publish tag, view or translation namespace, command name, middleware alias — is
  asserted against the booted application, never by grepping the provider. See
  `tests/Feature/NamingConventionTest.php`. Grepping proves how the registration was written; it
  does not prove what the framework ended up holding.
- **Style.** `composer format` runs Pint. CI checks it.
- **A CHANGELOG entry** under `## [Unreleased]`.

## Naming rules that are not negotiable

Laravel keeps these in flat, global maps. A second package claiming the same key does not collide
loudly — it silently replaces the first, and the damage surfaces far away as a missing view, a
missing translation, or a security control attached to nothing.

| Surface | Shape |
|---|---|
| Config key | `laranail.authkit` |
| Config file | `config/laranail/authkit.php` |
| Publish tag | `laranail::authkit-<suffix>` |
| View namespace | `laranail-authkit::<view>` |
| Translation namespace | `laranail-authkit::<key>` |
| Artisan command | `laranail::authkit.<command>` |
| Middleware alias | `laranail-authkit` |

The separators differ because each registry parses its key differently, and that is forced rather
than stylistic. Commands use `::` because Symfony resolves an exact name before splitting on `:`.
Middleware aliases must not, because Laravel does `explode(':', $name, 2)` to take parameters.
Blade prefixes must not, because the tag already spends `::` between prefix and component.

**No bare short aliases.** A `authkit:install`
alias hands back exactly the collision the namespaced name exists to prevent.

## Verify against a real application

The package test suite runs in an isolated Testbench app, which is not the same as a real one. A
change to routing, publishing, the installer, or anything that interacts with Fortify should also
be checked in the demo application at `laranail/demos/authkit`, which installs both packages the way
a consumer does.

## Security

Do not open a public issue for a security problem. See [SECURITY.md](SECURITY.md).
