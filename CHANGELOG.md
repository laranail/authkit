# Changelog

All notable changes to `laranail/authkit` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

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
