<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

/**
 * What identity resolution needs to know about a provider, whoever supplied it.
 *
 * The built-in social providers are a closed enum, because an exhaustive match is what forces an
 * explicit decision when one is added. Providers contributed by a sub-package arrive as
 * IdentityProvider objects instead. Both answer the same two questions, so the resolution path
 * takes this interface and does not care which it was handed.
 */
interface SocialIdentityProviderInterface
{
    /** The route value and the value stored against a linked social account. */
    public function slug(): string;

    /** Human-readable name, for a button or an account list. */
    public function label(): string;

    /**
     * Whether this provider's raw payload asserts the address was verified.
     *
     * This is the security-critical answer: an address treated as verified may link to an
     * existing local account, so a provider that does not genuinely verify must answer false
     * whatever the payload claims.
     *
     * @param  array<string, mixed>  $rawUser
     */
    public function hasVerifiedEmail(array $rawUser): bool;
}
