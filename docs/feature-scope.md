# Feature scope

AuthKit is pre-1.0. The core supplies authentication actions and a REST API; the preset, social
providers and OAuth server integration are separate packages.

| Capability | Current scope |
| --- | --- |
| Password login and registration | Web actions and REST controllers; configurable identifiers and throttling. |
| Password reset and change | Fortify-backed validation; password-tools/history are not integrated. |
| Email verification | Verification notifications and signed-link completion. |
| Profile email change | Keeps the current address active until an expiring, single-use proof sent to the new address succeeds; authenticated API and shared service are available. Preset form integration is separate. |
| TOTP and recovery codes | Opt-in enrollment and challenge flows; 21-character alphanumeric recovery codes are single-use. |
| Passkeys | Browser/session ceremonies; no token API for passkey registration or authentication. |
| Account lockout | Request/credential throttling only; no persistent account lockout. |
| Account deletion | Authenticated, password-confirmed API deletion and core authentication cleanup, with hooks for application/optional-package data; see [account deletion](account-deletion.md). |
| Magic links | Deferred from 1.0. |
| Audit extension | Public lifecycle events, container contracts and Laravel/Fortify events; see [Events](events.md). |

`authkit-ldap`, `authkit-sso` and `authkit-tenancy` remain experimental scaffolds and are not
implemented authentication providers. Installing this core does not add them. Do not infer support
from a sibling package name.

See [Security](security.md), [API routes](api-routes.md), and [Actions](actions.md) before extending
a flow. The [demo app](https://github.com/laranail/demo-apps-authkit) integrates the four packages.

---

[← Docs index](../README.md#documentation)
