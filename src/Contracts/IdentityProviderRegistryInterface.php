<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Simtabi\Laranail\AuthKit\Support\IdentityProvider;

/**
 * The seam through which a sub-package adds an identity provider.
 *
 * Sibling packages contribute providers -- an Okta tenant, a SAML IdP, or a customer's OIDC endpoint --
 * without editing this package. The social-login package supplies its own built-in provider enum;
 * this registry handles providers contributed by packages or applications. IdentityProvider
 * requires its email-verification behavior to be stated explicitly.
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
