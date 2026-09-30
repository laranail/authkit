# Architecture

How the core is layered, what it delegates to Fortify, and the seams the sibling packages extend.

## The family

```
laranail/authkit           Simtabi\Laranail\AuthKit\           this package — headless core
laranail/authkit-preset    Simtabi\Laranail\AuthKit\Preset\    Blade scaffolding
laranail/authkit-sso       Simtabi\Laranail\AuthKit\Sso\       SAML and OIDC
laranail/authkit-oauth     Simtabi\Laranail\AuthKit\OAuth\     OAuth server, apps and scopes
laranail/authkit-social-login Simtabi\Laranail\AuthKit\Social\ social login (Socialite)
laranail/authkit-tenancy   Simtabi\Laranail\AuthKit\Tenancy\   multi-tenancy
laranail/authkit-ldap      Simtabi\Laranail\AuthKit\Ldap\      LDAP and Active Directory
```

The family shares one root namespace and each sibling is a segment under it. Two packages mapping
nested PSR-4 prefixes is fine: Composer's loader matches the longest prefix first, so
`Simtabi\Laranail\AuthKit\Preset\` resolves into the preset and everything shallower resolves
here.

## Layering

The intended shape is **form request → controller → action → service**, with DTOs in and result
objects out.

| Layer | Responsibility |
|---|---|
| Form request | Validation and authorization. Overridable by swapping the request, without touching the action. |
| Controller | HTTP shape only — resolve, delegate, respond. |
| Action | One use case, orchestration only. Behind a contract, container-bound. |
| Service | Reusable domain logic and infrastructure adapters. Unit-testable with no HTTP. |

Being honest about the current state: the middle two are partly collapsed. Several actions build a
validator inline instead of taking a form request, and there are more actions than services. That
is a known gap, not the target.

## What is delegated

Password reset, email verification, profile and password updates, and passkey ceremonies are
Fortify's. This package supplies the actions Fortify calls — `CreateNewUser`, `ResetUserPassword`,
`UpdateUserPassword`, `UpdateUserProfileInformation` — and configures which features are on.

That is a deliberate trade: first-party, audited code for the flows where hand-rolling is most
dangerous, and this package's own actions where the shape matters more than the cryptography.

## Extension seams

The sibling packages exist only if these are real. Each is a container-bound contract:

| Seam | Purpose | Consumers |
|---|---|---|
| `SocialIdentityProviderInterface` | Describes a social identity provider to the social-login package and provider registry | social-login |
| `IdentityProviderRegistryInterface` | A registry a sibling pushes a provider into, replacing a closed enum as the universe of providers | social-login, oauth, sso |
| `IssueTokenForUserInterface` | Token issuance, with ability scoping and expiry | all |
| `TenantResolverInterface` | Resolves the active tenant; identity tables carry a nullable tenant key | tenancy |
| `DirectoryResolverInterface` | Resolves and syncs a directory entry to a local user | ldap |
| `GuardProfileInterface` | A named guard profile — domain or prefix, redirects, features, user model | all |

**Sequencing rule.** Every one of these changes a published contract, so they land here before the
first sibling is written. A sub-package that has to edit the core to do its job is not extending
the core, it is forking it.

Social-provider account linking and verified-email checks are implemented by
[`laranail/authkit-social-login`](https://github.com/laranail/authkit-social-login). The core exposes
the provider contract and registry that package uses; it does not implement Socialite flows.

---

[← Docs index](../README.md#documentation)
