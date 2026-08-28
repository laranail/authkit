# Changelog

All notable changes to `laranail/authkit` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **Breaking. Social login moved to `laranail/authkit-social`.** Fifteen classes, the `socials`
  migration, its factory and the `social` config block left this package. See
  [UPGRADING.md](UPGRADING.md); `rector-migrate-social.php` codemods the class renames.

  The config key moved from `laranail.authkit.social.*` to `laranail.authkit-social.*`, in the new
  package's own published file. **Provider env variable names are unchanged** — `AUTHKIT_GOOGLE_CLIENT_ID`
  and the rest keep working, because renaming them would break deployed `.env` files with
  credentials silently resolving to null.

  `laravel/socialite`, `socialiteproviders/manager` and `laranail/enumerator` are no longer required.
  All three were used only by social code, so the core no longer pulls Socialite into applications
  that never touch social login.

  The `laranail::authkit-social-migrations` publish tag is unchanged, but is now published by the
  new package rather than this one. The migration filename is unchanged, so an application that has
  already run it will not run it again.

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


### Added

- A `NamingConventionTest` that asserts the public names against the **live registries** on a booted
  application, rather than the provider source, so the guard survives a refactor.


### Fixed

- The user-model exception named the old package.

### Removed

- `composer.lock` is no longer tracked. A library's lock records a resolution consumers never use.
