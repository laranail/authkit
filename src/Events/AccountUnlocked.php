<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Events;

final readonly class AccountUnlocked
{
    public function __construct(public string $userType, public string $userId, public string $guard, public string $reason) {}
}
