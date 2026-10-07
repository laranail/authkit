<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\TokenResult;
use Simtabi\Laranail\AuthKit\Contracts\IssueTokenForUserInterface;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerRegistryInterface;

class IssueTokenForUser implements IssueTokenForUserInterface
{
    public function __construct(private TokenIssuerRegistryInterface $issuers) {}

    public function execute(
        Authenticatable $user,
        ?string $name = null,
        ?array $abilities = null,
        ?DateTimeInterface $expiresAt = null,
        bool $twoFactorVerified = false,
    ): TokenResult {
        return $this->issuers->issue($user, $name, $abilities, $expiresAt, $twoFactorVerified);
    }
}
