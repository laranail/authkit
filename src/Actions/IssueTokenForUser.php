<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Contracts\IssueTokenForUserInterface;
use Simtabi\Laranail\AuthKit\Support\TokenResult;

class IssueTokenForUser implements IssueTokenForUserInterface
{
    /**
     * Issue a personal access token for a user.
     *
     * Both defaults come from configuration rather than being fixed here, because both were
     * previously fixed here in the least safe way available: every token was minted with the
     * wildcard ability `*` and no expiry at all.
     *
     * A wildcard token can do anything its owner can, so a leaked one is a full account
     * compromise rather than a bounded one, and there was no way for a caller to narrow it. A
     * token with no expiry stays valid forever, so one recovered from a log or an old backup
     * never stops working. Sanctum's own `sanctum.expiration` is null by default, so nothing
     * downstream supplied the missing lifetime either.
     *
     * @param  array<int, string>|null  $abilities  null takes the configured default scope
     * @param  DateTimeInterface|null  $expiresAt  null takes the configured lifetime
     */
    public function execute(
        Authenticatable $user,
        ?string $name = null,
        ?array $abilities = null,
        ?DateTimeInterface $expiresAt = null,
    ): TokenResult {
        $token = $user->createToken(
            name: $name ?? 'api-token',
            abilities: $abilities ?? $this->defaultAbilities(),
            expiresAt: $expiresAt ?? $this->defaultExpiry(),
        );

        return new TokenResult(
            user: $user,
            token: $token->plainTextToken,
        );
    }

    /** @return array<int, string> */
    private function defaultAbilities(): array
    {
        $abilities = config(key: 'laranail.authkit.tokens.abilities', default: ['*']);

        if (! is_array($abilities) || $abilities === []) {
            return ['*'];
        }

        return array_values(array_filter($abilities, is_string(...)));
    }

    /**
     * A null lifetime defers to Sanctum's own `sanctum.expiration`, which is the only way to
     * genuinely opt out of expiry rather than silently inherit no expiry at all.
     */
    private function defaultExpiry(): ?DateTimeInterface
    {
        $minutes = config(key: 'laranail.authkit.tokens.expires_after_minutes');

        if ($minutes === null || ! is_numeric($minutes) || (int) $minutes <= 0) {
            return null;
        }

        return now()->addMinutes((int) $minutes);
    }
}
