<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\TokenResult;

interface IssueTokenForUserInterface
{
    /** @param array<int, string> $abilities */
    /**
     * @param  array<int, string>|null  $abilities  null takes the configured default scope
     * @param  DateTimeInterface|null  $expiresAt  null takes the configured lifetime
     */
    public function execute(
        Authenticatable $user,
        ?string $name = null,
        ?array $abilities = null,
        ?DateTimeInterface $expiresAt = null,
    ): TokenResult;
}
