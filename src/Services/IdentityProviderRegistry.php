<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use Simtabi\Laranail\AuthKit\Contracts\IdentityProviderRegistryInterface;
use Simtabi\Laranail\AuthKit\Support\IdentityProvider;

class IdentityProviderRegistry implements IdentityProviderRegistryInterface
{
    /** @var array<string, IdentityProvider> */
    private array $providers = [];

    public function register(IdentityProvider $provider): void
    {
        $this->providers[$provider->slug] = $provider;
    }

    public function has(string $slug): bool
    {
        return isset($this->providers[$slug]);
    }

    public function get(string $slug): ?IdentityProvider
    {
        return $this->providers[$slug] ?? null;
    }

    /** @return array<string, IdentityProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    /** @return array<int, string> */
    public function slugs(): array
    {
        return array_keys($this->providers);
    }
}
