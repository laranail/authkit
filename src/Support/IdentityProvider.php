<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Support;

use Closure;
use Simtabi\Laranail\AuthKit\Contracts\SocialIdentityProviderInterface;

/**
 * An identity provider contributed from outside this package.
 *
 * `assertsEmailVerified` has no default, deliberately. Whether a provider genuinely verifies the
 * address it hands back is the single decision that separates a safe social login from an account
 * takeover: an address treated as verified is allowed to link to an existing local account, so a
 * provider that does not actually verify lets anyone who can register there claim someone else's
 * account. A registry that defaulted the flag either way would make that decision silently on a
 * sub-package's behalf. Requiring it means a new provider cannot be added without someone
 * answering the question.
 *
 * `verifiedEmailResolver` exists because the claim is not standard. The OpenID-style providers
 * return a boolean `email_verified`; X returns the confirmed address itself as `confirmed_email`
 * and omits it when unconfirmed. A provider that asserts verification through some third shape
 * supplies the reader for it rather than having one assumed.
 */
final readonly class IdentityProvider implements SocialIdentityProviderInterface
{
    /**
     * @param  string                        $slug                   the route value, e.g. 'okta'
     * @param  string                        $label                  human-readable name
     * @param  bool                          $assertsEmailVerified   whether this provider verifies the address it returns
     * @param  (Closure(array<string, mixed>): bool)|null  $verifiedEmailResolver  reads this provider's own claim
     */
    public function __construct(
        public string $slug,
        public string $label,
        public bool $assertsEmailVerified,
        public ?Closure $verifiedEmailResolver = null,
    ) {}

    public function slug(): string
    {
        return $this->slug;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * Whether a given raw payload asserts the address was verified.
     *
     * A provider that asserts nothing answers false whatever the payload claims, so a forged
     * `email_verified` cannot promote it.
     *
     * @param  array<string, mixed>  $rawUser
     */
    public function hasVerifiedEmail(array $rawUser): bool
    {
        if (! $this->assertsEmailVerified) {
            return false;
        }

        if ($this->verifiedEmailResolver !== null) {
            return (bool) ($this->verifiedEmailResolver)($rawUser);
        }

        return filter_var($rawUser['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
