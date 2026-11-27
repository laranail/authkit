<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use DateTimeInterface;
use InvalidArgumentException;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\TokenResult;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerInterface;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerRegistryInterface;

class TokenIssuerRegistry implements TokenIssuerRegistryInterface
{
    /** @var array<string, TokenIssuerInterface> */
    private array $issuers = [];

    public function __construct(
        private \Illuminate\Contracts\Config\Repository $config,
        private \Illuminate\Contracts\Events\Dispatcher $events,
    ) {}

    public function register(string $driver, TokenIssuerInterface $issuer): void
    {
        $this->issuers[$driver] = $issuer;
    }

    public function issue(
        Authenticatable $user,
        ?string $name = null,
        ?array $abilities = null,
        ?DateTimeInterface $expiresAt = null,
        bool $twoFactorVerified = false,
    ): TokenResult {
        $driver = (string) $this->config->get('laranail.authkit.tokens.driver', 'sanctum');
        $result = $this->selectedIssuer()->issue($user, $name, $abilities, $expiresAt, $twoFactorVerified);
        $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\TokenIssued($user::class, (string) $user->getAuthIdentifier(), $driver));

        return $result;
    }

    public function revokeCurrent(Authenticatable $user): void
    {
        foreach ($this->issuers as $issuer) {
            $issuer->revokeCurrent($user);
        }

        $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\TokensRevoked($user::class, (string) $user->getAuthIdentifier(), 'current'));
    }

    public function revokeAll(Authenticatable $user): void
    {
        foreach ($this->issuers as $issuer) {
            $issuer->revokeAll($user);
        }

        $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\TokensRevoked($user::class, (string) $user->getAuthIdentifier(), 'all'));
    }

    private function selectedIssuer(): TokenIssuerInterface
    {
        $driver = (string) $this->config->get('laranail.authkit.tokens.driver', 'sanctum');

        return $this->issuers[$driver]
            ?? throw new InvalidArgumentException("The AuthKit token issuer [{$driver}] is not registered.");
    }
}
