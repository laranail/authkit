<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\TokenResult;

interface TokenIssuerInterface
{
    /** @param array<int, string>|null $abilities */
    public function issue(
        Authenticatable $user,
        ?string $name = null,
        ?array $abilities = null,
        ?DateTimeInterface $expiresAt = null,
        bool $twoFactorVerified = false,
    ): TokenResult;

    public function revokeCurrent(Authenticatable $user): void;

    public function revokeAll(Authenticatable $user): void;
}
