# Contributing

Where this file is silent, the [laranail contributing guide](https://github.com/laranail/.github/blob/HEAD/CONTRIBUTING.md) applies.

Thanks for helping improve `laranail/authkit`.

## Getting set up

`laranail/*` packages resolve through git rather than Packagist, so the repositories block in
`composer.json` is what makes `composer install` work. Then:

```bash
composer install
XDEBUG_MODE=off vendor/bin/pest --colors=never --no-coverage
composer pint
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
| View namespace | `laranail/authkit::<view>` |
| Translation namespace | `laranail/authkit::<key>` |
| Blade component prefix | `laranail-authkit::<component>` |
| Artisan command | `laranail::authkit.<command>` |
| Middleware alias | `laranail-authkit` |

The separators differ because each registry parses its key differently, and that is forced rather
than stylistic. Commands use `::` because Symfony resolves an exact name before splitting on `:`.
Middleware aliases must not, because Laravel does `explode(':', $name, 2)` to take parameters.
Blade prefixes must not, because the tag already spends `::` between prefix and component.

Views and translations take a **slash** — the composer package name — because Laravel interpolates
the namespace into the override path itself, so published files land in `lang/vendor/laranail/authkit`
and are read back from exactly there. Blade component tags are the one registry that cannot: their
name pattern is `[\w\-\:\.]`, with no forward slash, so `<x-laranail/authkit::card />` truncates
at `laranail` and renders as literal text with no error. Tags keep the hyphen —
`<x-laranail-authkit::card />`, nesting with a dot as `<x-laranail-authkit::forms.input />` — and
`package-tools` registers that prefix as an alias over the same resolved paths, so both spellings
find the same file.

This package is headless and registers neither, but a sub-package that does inherits the shapes.

**No bare short aliases.** A `authkit:install`
alias hands back exactly the collision the namespaced name exists to prevent.

## Verify against a real application

The package test suite runs in an isolated Testbench app, which is not the same as a real one. A
change to routing, publishing, the installer, or anything that interacts with Fortify should also
be checked in the demo application at [`laranail/demo-apps-authkit`](https://github.com/laranail/demo-apps-authkit), which installs both packages the way
a consumer does.

## Security

Do not open a public issue for a security problem. See [SECURITY.md](SECURITY.md).

## Documentation and pull requests

Branch from `main` and use Conventional Commits. Keep the README to installation, a working quick
start and the documentation index; put detailed guides in `docs/`. Link new pages from the index,
include a return link, and verify commands, route names, config keys and supported features against
the current implementation. Link sibling-package docs by GitHub URL so they work outside a monorepo.

Before opening a PR, run the sequential test command above and `composer pint`. Database jobs in
GitHub Actions cover MySQL, MariaDB and PostgreSQL; SQLite is the default local test database.
No local server database installation is needed. `composer test` currently selects parallel mode;
use the explicit command above to reproduce the sequential CI run.

Keep PR descriptions concise, link the issue, list validation, and update `CHANGELOG.md` under
`[Unreleased]`. For authentication behavior, include rejection and authorization cases. Never put
real tokens, passwords or provider secrets in fixtures, logs or reports.

Participation follows the [Laranail Code of Conduct](https://github.com/laranail/.github/blob/main/CODE_OF_CONDUCT.md).
