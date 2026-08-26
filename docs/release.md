# Release

How a version is cut, and what a release has to carry.

## Versioning

Semantic versioning. While pre-1.0 the minor position carries breaking changes, so consumers pin
with `^0.1`.

`composer.json` declares `branch-alias` `dev-main → 0.1.x-dev`, so a path or dev checkout satisfies
a `^0.1` constraint during development.

## Before tagging

- CI green on `main` — tests and style.
- The naming guard passing. That is what proves no public name regressed to a bare form, which is
  the failure mode that does not announce itself.
- A `CHANGELOG.md` section written for someone deciding whether to upgrade.
- Verified in a real application, not only the package suite — the demo at `laranail/demos/authkit`
  installs this package the way a consumer does.

## Ordering across the family

The siblings depend on this package. When a change here is breaking, tag it first and let the
siblings follow; a sibling tagged against a contract that has not shipped cannot resolve.

## Cutting the release

```bash
git tag vX.Y.Z
git push origin vX.Y.Z
```

The release body is the CHANGELOG section for that version. A release with auto-generated notes or
a "see CHANGELOG" stub is incomplete — the description is part of the release, not a pointer to it.

## Resolution

`laranail/*` packages resolve through git, not Packagist. Consumers add the repositories block from
[installation](installation.md); nothing is pushed to Packagist as part of a release.

---

[← Docs index](../README.md#documentation)
