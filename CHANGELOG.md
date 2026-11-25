# Changelog

All notable changes to `laranail/authkit` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Publishable English translations for package-owned messages and application language overrides.
- `AuthKit::SOCIAL_LOGIN` and `AuthKit::socialLoginEnabled()`. Social login's browser surface is a
  feature an application declares on `laranail.authkit.fortify.features`, rather than a flag inside
  `laranail/authkit-social-login`. The identifier lives here because the packages that must agree
  on it cannot see each other — the preset only suggests the social package, and the social package
  cannot reach the preset's enum — which is the problem `hasFeature()` was written for. Fortify
  receives the list verbatim and ignores a feature it does not know.
- Release workflow: a `workflow_dispatch` that re-runs a release against a tag that already exists. A release tag can never be moved or reused, so a run that died at `composer install` or in the SBOM step was previously unrecoverable.

### Changed

- A tag-driven release workflow: it builds the GitHub release body from this file's matching
  section, marks any `-` suffix as a pre-release, and attaches a CycloneDX SBOM. The package had
  none, which is why it has tags and no GitHub releases.
- CI gained a weekly scheduled run on the default branch. Nothing ran on a merge, so drift on
  `main` was invisible until an unrelated pull request went red.
- The architecture test suite now runs. `phpunit.xml` declared `Feature` and `Unit` only, so
  `tests/Architecture` — the credential-literal guard — was collected by nothing and had never
  executed.
- `laranail/package-tools` floor raised to `^0.1.13`, the first release carrying
  `AssertsNoFacadeCalls` and `AssertsNoStateLeak` — verified with `git ls-tree` across
  `v0.1.11`–`v0.1.15`. **The floor is ahead of what the code needs today**: the only guard this
  package runs is `AssertsNoLiteralCredentials`, which shipped long before `0.1.13`, so until those
  two traits are adopted the constraint tightens a consumer's floor for nothing. Kept rather than
  lowered because adopting them is the next step for these packages and the `prefer-lowest` leg has
  been proved against `v0.1.13`; the adoption is tracked, not assumed.
- The test harness generates its `app.key` per run. A placeholder literal is indistinguishable from
  a real key to a scanner, and it blocked any commit touching that file.

- CI now runs the package test suite on PHP 8.5 against MySQL, MariaDB, and PostgreSQL in addition
  to SQLite. The passkeys migration runs with the workbench, and a focused schema test covers the
  two-factor migration on every database driver.
- Release workflow: `Latest` is no longer asserted for every final tag. A `latest` dispatch input set to `false` lets a patch to an older line publish without demoting a newer one, and the tag shape is validated against the release conventions before anything is published.
- The credential-literal guard's `minFiles` floor now equals the number of files it actually inspects, so a glob that stops matching fails instead of reporting a clean tree.
- The web login runs one pipeline whatever the caller accepts, choosing the representation in `passed()`, `failed()` and `throttled()` rather than before the attempt. Observable for JSON callers of the browser login: a JSON login now establishes a session, and a user with two-factor enrolled gets `202 two-factor-required` where it previously answered `200 passed` with no session and no challenge.
- Release workflow: `permissions` is read-only at the top level and widened to `contents: write` on the release job only, so a job added later starts with no write access. The tag trigger is one pattern rather than two -- GitHub's `*` matches `.` and `-`, so `v*.*.*` already starts on `v0.1.0-rc.1`.

### Fixed

- Accept full 21-character recovery codes for two-factor challenges, disabling two-factor
  authentication, and regenerating recovery codes.
- **Fortify's feature set no longer depends on provider boot order.** `fortify.guard`,
  `fortify.views` and `fortify.features` are configured at register time rather than boot time.
  Fortify reads them in its own `boot()` and mounts its routes there, so whichever provider booted
  first decided the application's routes. Measured with the provider order as the only difference:
  Fortify first mounted `register`, `two-factor.login` and `password.request`; AuthKit first
  mounted none of them. Package discovery orders `laranail/authkit` before `laravel/fortify`, so an
  installed application got the second, while the test suite exercised the first.
  **This changes behaviour for anyone who was getting the first order:** Fortify no longer mounts
  registration or two-factor routes, because `laranail.authkit.fortify.features` does not list
  them. Add `'registration'` and `'two-factor-authentication'` to that key to keep those routes.
- The two-factor challenge fails cleanly instead of returning 500 when the stored secret cannot be
  decrypted (a rotated or mismatched `APP_KEY`), and when the configured cache store cannot provide
  a lock. Three of the stores Laravel ships are not lock providers: `apc`, `session` and `storage`.
- Release workflow: the CHANGELOG extractor stopped at the next version heading only, so the **last** section in the file ran to EOF and the release body ended in `[0.1.0]: https://...` link-reference lines. It now also stops at the link block.
- The API login challenges any user with two-factor enrolled and confirmed, instead of also requiring `laranail.authkit.two_factor.enabled`. That flag is written by `laranail/authkit-preset` at register time from Fortify's feature list, so its value depended on which provider registered first -- and moving this package's own Fortify configuration into `packageRegistered()` flipped it to false for an application that had published `config/fortify.php` with two-factor on. A Sanctum token was then issued on the password alone to a user with TOTP enrolled. The browser login never had this: it asks the service and nothing else, and the API path now matches it. Note the flag's default is `false`, so a core-only install never challenged an enrolled user at all. `laranail.authkit.two_factor.enabled` and `AuthKit::twoFactorEnabled()` are kept and now have no reader inside these packages -- the two-factor routes were never gated on them. Neither is removed; what the key should gate instead is a separate decision.
- Three comments and a test docblock said `Cache::lock()` on a store that is not a `LockProvider` throws `BadMethodCallException`. It raises PHP's `Error: Call to undefined method`: none of `ApcStore`, `SessionStore` or `StorageStore` declares `lock()` or `__call`, and `Repository::__call` forwards straight to the store. The behaviour was already correct; the stated reason was not.
- `TwoFactorResilienceTest`'s lock-less case asserted only that a wrong code fails, which held just as well when the lock-less branch denied everything -- proved by making it `return false;`, which left all three tests in the file green. It now asserts a correct code verifies.

## [0.1.1] - 2026-11-13

### Added

- **Username-based password login.** `laranail.authkit.login.identifiers` names the user columns an
  identifier is matched against, in order, and `laranail.authkit.login.field` names the request input
  it arrives in. Both default to email, so existing forms, API clients and validation are unchanged
  until a second identifier is configured. Validation is derived from the list rather than configured
  separately, so it cannot describe a mode the lookup does not implement. The application owns the
  `username` column, its uniqueness constraint and its registration validation — AuthKit does not own
  the users table and ships no migration for it. See `docs/login.md`. Closes
  [#65](https://github.com/laranail/authkit/issues/65).
- **Explicit, secure identity linking.** `IdentityLinkingInterface` lets a provider package attach a
  sign-in method to an already authenticated user without that path ever being able to sign anyone
  in. A link attempt requires a recently confirmed credential, carries a single-use token bound to
  the initiating user, guard, provider and authentication context, and refuses an external identity
  owned by another account — never switching the current user and never transferring the identity.
  The surface accepts no email address at all, so a matching address cannot become authorisation.
  See `docs/identity-linking.md`. Closes [#64](https://github.com/laranail/authkit/issues/64).
- The two-factor middleware is registered under the vendor-scoped alias `laranail-authkit-two-factor`
  (`AuthKitServiceProvider::TWO_FACTOR_MIDDLEWARE`). `NamingConventionTest` asserts it, and that no
  other bare alias is registered, against the live router.
- A configurable token issuer registry with Sanctum as the default, allowing integration packages
  such as AuthKit OAuth to add token drivers without replacing AuthKit's existing issuer.

- **Opt-in TOTP two-factor authentication for API clients.** Login can return a short-lived MFA challenge; clients can verify TOTP or recovery codes, manage enrollment and recovery codes, and protect routes with the `two-factor` middleware. Verified API tokens receive a `two-factor:verified` ability. The `two_factor_method` field defaults to `none` and is ready for future methods.

- A `NamingConventionTest` that asserts the public names against the **live registries** on a booted
  application, rather than the provider source, so the guard survives a refactor.

### Changed

- **Test credentials are generated at run time.** Passwords, secrets and tokens in the tests and the
  workbench factory come from package-tools' `TestCredentials`, so no literal reaches the repository
  for a secret scanner to report. `tests/Architecture/NoLiteralCredentialsTest.php` fails the suite
  on any literal left behind. The `laranail/package-tools` floor is now `^0.1.10`, the first release
  that also ships `assertCredentialPurposesAreWordsIn()`: a second test keeps every
  `TestCredentials` purpose a single plain lowercase word, since a secret scanner reads a compound
  purpose as the password.

- Pull requests run `composer pint` (Pint with the shared laranail config, check-only) in a new
  *Code style* workflow.

- The `vcs` repository entries for `laranail/captcha`, `console`, `db-tools` and `enumerator` are gone. Nothing in this package's
  `require` or `require-dev` closure pulls them in (`composer why` finds none of them installed),
  so they only told Composer to clone repositories it never used. The Packagist exclusion for
  `laranail/*` stays.

- `composer.json` `authors` email is `opensource@simtabi.com`, the community metadata address,
  replacing `hello@simtabi.com`.
- Password updates, password resets, and API logout now revoke tokens through the registered token
  issuers, so integrations can revoke their tokens through the same AuthKit actions.

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

- **Revoking all of a user's tokens could delete another user's.** With the token issuer registry, `SanctumTokenIssuer::revokeAll()` (called on every password reset and update) queried the token table by a morph type taken from configuration rather than the user's own, so a second token-bearing model with a colliding id (an Admin with id 5) revoked User 5's tokens and kept its own. It also queried that table for user models that hold no Sanctum tokens, failing the reset where the table does not exist. It now revokes through the user's own `tokens()` relation, and only for models that use Sanctum's `HasApiTokens`, as before the registry.
- The user-model exception named the old package.

## [0.1.0] - 2026-10-19

### Added

- The headless core: credential login with throttling by identifier, IP and guard; session
  regeneration and invalidation; Sanctum token issuance behind a registry; two-factor enrolment,
  challenge and recovery codes; passkey wiring; browser-session management; Turnstile support; the
  API surface under `laranail.authkit.api`; and Fortify's user-creation, profile, password and
  reset actions bound to AuthKit's own.

[Unreleased]: https://github.com/laranail/authkit/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/laranail/authkit/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/laranail/authkit/releases/tag/v0.1.0
