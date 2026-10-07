<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use DateTimeInterface;
use Laravel\Sanctum\Sanctum;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
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

    public function revokeAll(Authenticatable $user): void
    {
        if (! $user instanceof Model) {
            return;
        }

        $model = Sanctum::$personalAccessTokenModel;
        $morphType = $user->getMorphClass();
        $userModel = config('laranail.authkit.user_model') ?? config('auth.providers.users.model');

        if (is_string($userModel) && class_exists($userModel) && is_subclass_of($userModel, Model::class)) {
            $morphType = (new $userModel)->getMorphClass();
        }

        $model::query()
            ->where('tokenable_type', $morphType)
            ->where('tokenable_id', $user->getAuthIdentifier())
            ->delete();
    }

    /** @return array<int, string> */
    private function defaultAbilities(): array
    {
        $abilities = config('laranail.authkit.tokens.abilities', ['*']);

        if (! is_array($abilities) || $abilities === []) {
            return ['*'];
        }

        return array_values(array_filter($abilities, is_string(...)));
    }

    private function defaultExpiry(): ?DateTimeInterface
    {
        $minutes = config('laranail.authkit.tokens.expires_after_minutes');

        if ($minutes === null || ! is_numeric($minutes) || (int) $minutes <= 0) {
            return null;
        }

        return now()->addMinutes((int) $minutes);
    }
}
