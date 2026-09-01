# Security policy

## Reporting a vulnerability

Report security issues privately to **security@simtabi.com**. Do not open a public issue.

Include the affected version, the steps to reproduce, and the impact you believe it has. You will
get an acknowledgement within three business days and an assessment within ten.

This package sits directly on the authentication path, so please err toward reporting. A finding
here may also affect its sibling, `laranail/authkit-preset`; say so if you think it does, and we will
coordinate the fix across the family rather than patching one package in isolation.

> **Prefer GitHub private vulnerability reporting** when you can: open it from this
> repository's Security tab. The report arrives attached to the repo with a draft advisory
> and a CVE request path already in place. Email is the fallback for anyone who would
> rather not use GitHub.

## Supported versions

The `main` line receives security fixes. Pre-1.0 releases are not separately maintained — upgrade
to the current line rather than expecting a backport.

## Scope

In scope: authentication bypass, privilege escalation, account takeover, credential or token
disclosure, session fixation, and any weakening of the rate limiting or bot protection this package
configures.

Out of scope: findings that require an already-compromised application key, and misconfiguration of
an application that this package documents correctly.
