<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Simtabi\Laranail\AuthKit\Support\IdentityProvider;

/**
 * The seam through which a sub-package adds an identity provider.
 *
 * laranail/authkit-sso and laranail/authkit-oauth need to contribute providers -- an Okta tenant,
 * a SAML IdP, a customer's OIDC endpoint -- without editing this package. The built-in social
 * providers stay a closed enum, because an exhaustive match is what forces an explicit decision
 * when one is added there; this registry is the equivalent forcing function for providers that
 * arrive from outside, since IdentityProvider requires its verification flag to be stated.
 */
interface IdentityProviderRegistryInterface
{
    /** Registering the same slug twice replaces the earlier provider. */
    public function register(IdentityProvider $provider): void;

    public function has(string $slug): bool;

    public function get(string $slug): ?IdentityProvider;

    /** @return array<string, IdentityProvider> keyed by slug */
    public function all(): array;

    /** @return array<int, string> */
    public function slugs(): array;
}
