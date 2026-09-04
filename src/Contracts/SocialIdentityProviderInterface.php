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
     * The Socialite driver key this provider authenticates through.
     *
     * Deliberately separate from slug(). The slug is stored data -- it is the route value and the
     * value written to `socials.provider` -- so it cannot change without a migration. The driver key
     * belongs to Socialite and does change: `linkedin` resolves to a legacy provider whose payload
     * has no `email_verified`, and only `linkedin-openid` returns it. Assuming the two are the same
     * string is what let a provider ship unable to authenticate.
     */
    public function driver(): string;

    /**
     * Whether this provider's raw payload asserts the address was verified.
     *
     * This is the security-critical answer: an address treated as verified may link to an
     * existing local account, so a provider that does not genuinely verify must answer false
     * whatever the payload claims.
     *
     * @param array<string, mixed> $rawUser
     */
    public function hasVerifiedEmail(array $rawUser): bool;
}
