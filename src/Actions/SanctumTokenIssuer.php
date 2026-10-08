<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use DateTimeInterface;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\TokenResult;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerInterface;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class SanctumTokenIssuer implements TokenIssuerInterface
{
    public function issue(
        Authenticatable $user,
        ?string $name = null,
        ?array $abilities = null,
        ?DateTimeInterface $expiresAt = null,
        bool $twoFactorVerified = false,
    ): TokenResult {
        $tokenAbilities = $abilities ?? $this->defaultAbilities();

        if ($twoFactorVerified && ! in_array('two-factor:verified', $tokenAbilities, true)) {
            $tokenAbilities[] = 'two-factor:verified';
        }

        if (! method_exists($user, 'createToken')) {
            throw new InvalidArgumentException('The configured AuthKit user model cannot create Sanctum tokens.');
        }

        $token = $user->createToken(
            name: $name ?? 'api-token',
            abilities: $tokenAbilities,
            expiresAt: $expiresAt ?? $this->defaultExpiry(),
        );

        return new TokenResult(user: $user, token: $token->plainTextToken);
    }

    public function revokeCurrent(Authenticatable $user): void
    {
        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if ($token instanceof SanctumPersonalAccessToken) {
            $token->delete();
        }
    }

    /**
     * Revoke every Sanctum token the given user holds, through the user's own `tokens()` relation.
     *
     * Only a model that uses Sanctum's `HasApiTokens` can hold one. Querying the token table by a
     * morph type taken from configuration instead deleted another model's tokens whenever ids
     * collided (an Admin with id 5 revoked User 5's tokens and kept its own), and a model without
     * the trait made every password reset hit a token table the application may not have.
     */
    public function revokeAll(Authenticatable $user): void
    {
        if (! in_array(HasApiTokens::class, class_uses_recursive($user), true)) {
            return;
        }

        $user->tokens()->delete();
    }

    /**
     * Both defaults come from configuration rather than being fixed here, because both were once
     * fixed here in the least safe way available: every token was minted with the wildcard ability
     * `*` and no expiry. A wildcard token can do anything its owner can, so a leaked one is a full
     * account compromise; a token with no expiry recovered from a log or an old backup never stops
     * working, and Sanctum's own `sanctum.expiration` is null by default.
     *
     * @return array<int, string>
     */
    private function defaultAbilities(): array
    {
        $abilities = config('laranail.authkit.tokens.abilities', ['*']);

        if (! is_array($abilities) || $abilities === []) {
            return ['*'];
        }

        return array_values(array_filter($abilities, is_string(...)));
    }

    /**
     * A null lifetime defers to Sanctum's own `sanctum.expiration`, which is the only way to opt out
     * of expiry deliberately rather than silently inherit none.
     */
    private function defaultExpiry(): ?DateTimeInterface
    {
        $minutes = config('laranail.authkit.tokens.expires_after_minutes');

        if ($minutes === null || ! is_numeric($minutes) || (int) $minutes <= 0) {
            return null;
        }

        return now()->addMinutes((int) $minutes);
    }
}
