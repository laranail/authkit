# Changelog

All notable changes to `laranail/authkit` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- The two-factor middleware is registered under the vendor-scoped alias `laranail-authkit-two-factor`
  (`AuthKitServiceProvider::TWO_FACTOR_MIDDLEWARE`). `NamingConventionTest` asserts it, and that no
  other bare alias is registered, against the live router.

- **Opt-in TOTP two-factor authentication for API clients.** Login can return a short-lived MFA challenge; clients can verify TOTP or recovery codes, manage enrollment and recovery codes, and protect routes with the `two-factor` middleware. Verified API tokens receive a `two-factor:verified` ability. The `two_factor_method` field defaults to `none` and is ready for future methods.

- A `NamingConventionTest` that asserts the public names against the **live registries** on a booted
  application, rather than the provider source, so the guard survives a refactor.

### Changed

- Pull requests run `composer pint` (Pint with the shared laranail config, check-only) in a new
  *Code style* workflow.

- The `vcs` repository entries for `laranail/captcha`, `console`, `db-tools` and `enumerator` are gone. Nothing in this package's
  `require` or `require-dev` closure pulls them in (`composer why` finds none of them installed),
  so they only told Composer to clone repositories it never used. The Packagist exclusion for
  `laranail/*` stays.

- `composer.json` `authors` email is `opensource@simtabi.com`, the community metadata address,
  replacing `hello@simtabi.com`.

- **Breaking. Social login moved to `laranail/authkit-social-login`.** Fifteen classes, the `socials`
  migration, its factory and the `social` config block left this package. See
  [UPGRADING.md](UPGRADING.md); `rector-migrate-social.php` codemods the class renames.

  The config key moved from `laranail.authkit.social.*` to `authkit-social-login.*`, in the new
  package's own `config/authkit-social-login.php` file. **Provider env variable names are unchanged** — `AUTHKIT_GOOGLE_CLIENT_ID`
  and the rest keep working, because renaming them would break deployed `.env` files with
  credentials silently resolving to null.

  `laravel/socialite`, `socialiteproviders/manager` and `laranail/enumerator` are no longer required.
  All three were used only by social code, so the core no longer pulls Socialite into applications
  that never touch social login.

  The `laranail::authkit-social-login-migrations` publish tag is now published by the new package
  rather than this one. The migration filename is unchanged, so an application that has
  already run it will not run it again.

- Core documentation now describes social login as a separate package and points to its current
  guide, config key, and publish tags. The core provider contracts are documented as extension
  seams, not as shipped Socialite behavior.

- `testbench.yaml` no longer declares `Workbench\Database\Seeders\DatabaseSeeder` or
  `workbench/database/migrations`. Neither existed. `composer.json` dropped the matching
  autoload-dev root for the same reason.

- The PHP floor is `^8.4.1`, up from `^8.4`. `laranail/package-tools` and `laranail/console`
  are `^8.4.1`, so a resolver that took the manifest at its word and pinned the platform to
  8.4.0 could not install them. Dependabot does exactly that, and had been failing on it.

- **Breaking.** Renamed from `laranail/auth-kit` to
  `laranail/authkit`, and the namespace moved to `Simtabi\Laranail\AuthKit\`. The family now shares one root
  namespace with each sibling as a segment under it.
- **Breaking.** Every public name is vendor-scoped. Laravel keeps these in flat global maps, where
  a second package claiming the same key silently replaces the first:

| Surface | Before | After |
|---|---|---|
| Config key | `auth-kit` | `laranail.authkit` |
| Config file | `config/auth-kit.php` | `config/laranail/authkit.php` |
| Publish tags | `auth-kit-config`, … | `laranail::authkit-*` |
| Env prefix | `AUTH_KIT_*` | `AUTHKIT_*` |


- `laranail/package-tools` and `laranail/enumerator` are constrained as `^0.1` rather than
  `dev-main`. A `dev-` constraint in `require` propagates dev stability to every consumer,
  and the org convention states no laranail package carries one.

### Deprecated

- **The bare `two-factor` middleware alias.** Middleware aliases share one flat map, so a bare name
  collides with any application or package alias of the same name. It still enforces the same
  checks, through `DeprecatedTwoFactorAlias`, and logs one warning per process naming
  `laranail-authkit-two-factor`. The earliest release that could remove it is the next minor after 0.1.

### Removed

- `composer.lock` is no longer tracked. A library's lock records a resolution consumers never use.

### Fixed

- The user-model exception named the old package.

[Unreleased]: https://github.com/laranail/authkit/compare/v0.1.0...HEAD
